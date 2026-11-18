#!/usr/bin/env node
/**
 * Prepares the `@verbb/comments-*` packages for a release, for review before it's committed: their
 * versions, their dependencies on each other and in the plugin's own asset, their changelogs and the
 * lockfile. It never publishes, commits or pushes.
 *
 *   node scripts/prepare-release.mjs 4.0.0-beta.2             Prepare a version.
 *   node scripts/prepare-release.mjs 4.0.0-beta.2 --dry-run   Build and pack without changing anything.
 *   node scripts/prepare-release.mjs --check-lockfile         Check the lockfile resolves nothing locally.
 *   node scripts/prepare-release.mjs --refresh-lockfile-only  Rebuild the lockfile from the manifests.
 *
 * Copied from Formie's `prepare-release.mjs` and driven by `scripts/release.config.json`.
 */
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { assertPreparedVersion, extractUnreleasedBody, finalizeChangelog, hasChangelogEntries, libraryProblems, loadConfig, lockfileProblems, pinInternalDependencies } from './package-release.mjs';

const root = fileURLToPath(new URL('../', import.meta.url));
process.chdir(root);

const config = loadConfig(root);
const args = process.argv.slice(2);

if (args.includes('--help') || args.includes('-h')) {
    console.log('Prepare the Comments packages: node scripts/prepare-release.mjs <version> [--dry-run]');
    console.log('Use the Comments plugin version the packages will be released with, such as 4.0.0-beta.2.');
    console.log('Maintenance: --check-lockfile or --refresh-lockfile-only');
    console.log('This never publishes to npm, commits or pushes.');
    process.exit(0);
}

const options = ['--dry-run', '--check-lockfile', '--refresh-lockfile-only'];
const unknown = args.filter((arg) => arg.startsWith('-') && !options.includes(arg));

// A mistyped option such as --dryrun must never run a real preparation.
if (unknown.length || args.filter((arg) => !arg.startsWith('-')).length > 1) {
    throw new Error(`Unexpected arguments: ${[...unknown, ...args.filter((arg) => !arg.startsWith('-')).slice(1)].join(' ')}. Run with --help.`);
}

const dryRun = args.includes('--dry-run');
const checkLockfileOnly = args.includes('--check-lockfile');
const refreshLockfileOnly = args.includes('--refresh-lockfile-only');
const targetVersion = args.find((arg) => !arg.startsWith('--'));

if ([dryRun, checkLockfileOnly, refreshLockfileOnly].filter(Boolean).length > 1) {
    throw new Error('Use only one of --dry-run, --check-lockfile or --refresh-lockfile-only.');
}

const run = (command, commandArgs, options = {}) => execFileSync(command, commandArgs, { stdio: 'inherit', ...options });
const output = (command, commandArgs) => execFileSync(command, commandArgs, { encoding: 'utf8' }).trim();
const readJson = (path) => JSON.parse(readFileSync(join(root, path), 'utf8'));
const packageVersion = () => readJson(`${config.packages[0]}/package.json`).version;

// Every manifest that names a Comments package: the packages themselves, the plugin's own asset, and
// the docs workspaces.
const dependentManifests = [...new Set([...config.packages.map((dir) => `${dir}/package.json`), ...config.pinnedBy, ...config.workspaceManifests])]
    .filter((path) => existsSync(join(root, path)));

const ensureCleanWorkingTree = () => {
    const status = output('git', ['status', '--porcelain']);

    if (status) {
        throw new Error(`Preparation stopped. Review the repository's existing changes first.\n${status}`);
    }
};

const assertLockstepVersions = () => {
    const expected = packageVersion();
    const mismatches = config.packages
        .map((dir) => readJson(`${dir}/package.json`))
        .filter((pkg) => pkg.version !== expected)
        .map((pkg) => `${pkg.name}@${pkg.version} (expected ${expected})`);

    if (mismatches.length) {
        throw new Error(`The packages' versions aren't in lockstep:\n${mismatches.join('\n')}`);
    }
};

const assertStandaloneLockfile = ({ report = false } = {}) => {
    const problems = lockfileProblems(readJson('package-lock.json'), config);

    if (problems.length && !report) {
        throw new Error(`package-lock.json resolves packages outside the repository or from local links. Run npm run release:refresh-lockfile, after Editor, Protect and Plugin Kit are published:\n${problems.join('\n')}`);
    }

    return problems;
};

/** Drops links into sibling checkouts and stale nested copies of the workspace's own packages. */
const sanitizeLockfile = (lockfile) => {
    for (const [path, entry] of Object.entries(lockfile.packages ?? {})) {
        const name = path.match(/(?:^|\/)node_modules\/(@verbb\/[^/]+)$/)?.[1];
        const nested = name && config.names.includes(name) && path !== `node_modules/${name}`;

        if (path.startsWith('../') || entry.resolved?.startsWith('../') || nested) {
            delete lockfile.packages[path];
        }
    }
};

/**
 * Resolves the lockfile from the manifests alone, in a temporary folder, so npm can't pick up the
 * sibling checkouts or symlinks in the developer's own node_modules.
 */
const refreshLockfile = () => {
    const temporaryRoot = mkdtempSync(join(tmpdir(), 'comments-release-lock-'));
    const manifests = ['package.json', 'package-lock.json'];

    for (const pattern of readJson('package.json').workspaces ?? []) {
        if (pattern.endsWith('/*')) {
            const folder = pattern.slice(0, -2);

            for (const entry of readdirSync(join(root, folder), { withFileTypes: true })) {
                if (entry.isDirectory() && existsSync(join(root, folder, entry.name, 'package.json'))) {
                    manifests.push(`${folder}/${entry.name}/package.json`);
                }
            }
        } else if (existsSync(join(root, pattern, 'package.json'))) {
            manifests.push(`${pattern}/package.json`);
        }
    }

    try {
        for (const relative of manifests) {
            mkdirSync(dirname(join(temporaryRoot, relative)), { recursive: true });
            copyFileSync(join(root, relative), join(temporaryRoot, relative));
        }

        const lockfilePath = join(temporaryRoot, 'package-lock.json');
        const rewrite = () => {
            const lockfile = JSON.parse(readFileSync(lockfilePath, 'utf8'));
            sanitizeLockfile(lockfile);
            writeFileSync(lockfilePath, `${JSON.stringify(lockfile, null, 2)}\n`);
        };

        rewrite();
        run('npm', ['install', '--package-lock-only', '--ignore-scripts', '--legacy-peer-deps', '--registry=https://registry.npmjs.org/', '--@verbb:registry=https://registry.npmjs.org/'], { cwd: temporaryRoot });
        rewrite();
        copyFileSync(lockfilePath, join(root, 'package-lock.json'));
    } finally {
        rmSync(temporaryRoot, { recursive: true, force: true });
    }
};

// Changelogs
// -------------------------------------------------------------------------

const changelogPath = (dir) => join(root, dir, 'CHANGELOG.md');

const syncInternalDependencies = (version) => {
    for (const relative of dependentManifests) {
        const pinned = pinInternalDependencies(readFileSync(join(root, relative), 'utf8'), config.names, version);

        if (pinned) writeFileSync(join(root, relative), pinned);
    }
};

// Editor and Protect must be depended on as released packages that match the plugin's Composer
// constraints. Until they're published (D8) they're local links, which only a dry run tolerates.
const libraryBlockers = () => {
    const composer = readJson(config.plugin);

    return config.packages.flatMap((dir) => libraryProblems(readJson(`${dir}/package.json`), config.libraries, composer));
};

// Run
// -------------------------------------------------------------------------

if (checkLockfileOnly) {
    assertStandaloneLockfile();
    console.log('package-lock.json resolves nothing locally, and the Comments packages through the root workspace links.');
    process.exit(0);
}

if (refreshLockfileOnly) {
    if (output('git', ['status', '--porcelain', '--', 'package-lock.json'])) {
        throw new Error('Review the existing package-lock.json change before refreshing it.');
    }

    refreshLockfile();
    assertStandaloneLockfile();
    console.log('Refreshed package-lock.json without local links. Review the diff.');
    process.exit(0);
}

const pluginVersion = readJson(config.plugin).version;

assertPreparedVersion(targetVersion, packageVersion(), pluginVersion);
assertLockstepVersions();

if (pluginVersion !== targetVersion) {
    console.log(`composer.json is at ${pluginVersion}. Bump it to ${targetVersion} in the same commit, since the release checks the packages carry the plugin's version.`);
}

const blockers = [...assertStandaloneLockfile({ report: true }).map((problem) => `Lockfile: ${problem}`), ...libraryBlockers()];

if (dryRun) {
    console.log('Dry run: no versions, changelogs or lockfile will change.');

    if (blockers.length) {
        console.log(`\nA real preparation would stop here:\n${blockers.map((blocker) => `- ${blocker}`).join('\n')}\n`);
    }
} else {
    ensureCleanWorkingTree();

    if (blockers.length) {
        throw new Error(`Preparation stopped:\n${blockers.map((blocker) => `- ${blocker}`).join('\n')}`);
    }
}

const changelogs = Object.fromEntries(config.packages.map((dir) => [dir, readFileSync(changelogPath(dir), 'utf8')]));
const withEntries = config.packages.filter((dir) => hasChangelogEntries(extractUnreleasedBody(changelogs[dir].replace(/\r\n/g, '\n'))));

console.log(`Unreleased changelog entries in: ${withEntries.join(', ') || 'none'}`);

run('npm', ['run', 'build:packages']);

for (const name of config.names) {
    run('npm', ['pack', '--dry-run', '--ignore-scripts', '-w', name]);
}

if (dryRun) {
    console.log(`Dry run complete for ${targetVersion}. Nothing changed, and nothing was published.`);
    process.exit(0);
}

const regenerated = output('git', ['status', '--porcelain']);

if (regenerated) {
    throw new Error(`Building or packing changed repository files. Review them before preparing:\n${regenerated}`);
}

const currentVersion = packageVersion();

// All of them at once, so npm never installs a registry copy of one package's siblings.
for (const name of config.names) {
    run('npm', ['version', targetVersion, '-w', name, '--no-git-tag-version', '--no-workspaces-update']);
}

if (packageVersion() !== targetVersion) {
    throw new Error(`Expected the packages at ${targetVersion} after versioning, found ${packageVersion()}.`);
}

assertLockstepVersions();
syncInternalDependencies(targetVersion);
refreshLockfile();
assertStandaloneLockfile();

const date = new Date().toISOString().slice(0, 10);

for (const [index, dir] of config.packages.entries()) {
    writeFileSync(changelogPath(dir), finalizeChangelog(changelogs[dir], targetVersion, date, config.names[index]));
}

console.log(`Prepared the Comments packages ${currentVersion} → ${targetVersion}. Nothing was published or committed.`);
console.log('Review the versions, dependencies, lockfile and changelogs, then commit and push.');
