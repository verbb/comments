/**
 * The rules every step of a package release shares: which packages are released together, how their
 * versions move, what a packed manifest must say, and that Editor and Protect are depended on as
 * released packages whose npm major matches the plugin's Composer constraint.
 *
 * Copied from Formie's release tooling (`formie/craft5/plugin/scripts/package-release.mjs`) and driven
 * by `scripts/release.config.json` instead of hard-coded lists, so it can become a shared tool later.
 * Unlike Formie's, it takes prerelease versions such as `4.0.0-beta.2`, which go to npm's `next`
 * channel as they are.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { isAbsolute, join } from 'node:path';
import { isDeepStrictEqual } from 'node:util';

export function loadConfig(root) {
    const config = JSON.parse(readFileSync(join(root, 'scripts/release.config.json'), 'utf8'));

    return { ...config, names: config.packages.map((dir) => `@verbb/${dir.split('/').at(-1)}`) };
}

// Semantic versions, with an optional prerelease of dot-separated identifiers and no build metadata.
const semver = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*))*))?$/;

export function parseVersion(version) {
    const match = semver.exec(version ?? '');

    return match ? { core: match.slice(1, 4).map(BigInt), pre: match[4] ? match[4].split('.') : [] } : null;
}

export const isStable = (version) => parseVersion(version)?.pre.length === 0;

/** Compares two versions by semantic versioning's precedence, so `4.0.0-beta.2` comes before `4.0.0`. */
export function compareVersions(left, right) {
    const [a, b] = [parseVersion(left), parseVersion(right)];

    if (!a || !b) {
        throw new Error(`Can't compare ${left} with ${right}.`);
    }

    for (let i = 0; i < 3; i++) {
        if (a.core[i] !== b.core[i]) return a.core[i] < b.core[i] ? -1 : 1;
    }

    // A version without a prerelease is newer than the same version with one.
    if (!a.pre.length || !b.pre.length) return a.pre.length === b.pre.length ? 0 : a.pre.length ? -1 : 1;

    for (let i = 0; i < Math.max(a.pre.length, b.pre.length); i++) {
        const [x, y] = [a.pre[i], b.pre[i]];

        if (x === undefined) return -1;
        if (y === undefined) return 1;
        if (x === y) continue;

        const [xNumber, yNumber] = [/^\d+$/.test(x), /^\d+$/.test(y)];

        if (xNumber && yNumber) return BigInt(x) < BigInt(y) ? -1 : 1;
        if (xNumber !== yNumber) return xNumber ? -1 : 1;

        return x < y ? -1 : 1;
    }

    return 0;
}

/** The packages share the plugin's version, and are released just ahead of it. */
export function assertPreparedVersion(target, current, pluginVersion) {
    if (!parseVersion(target)) {
        throw new Error('Expected the exact version to prepare, such as 4.0.0 or 4.0.0-beta.2.');
    }

    if (compareVersions(target, current) <= 0) {
        throw new Error(`Version ${target} must be newer than the current packages (${current}).`);
    }

    if (compareVersions(target, pluginVersion) < 0) {
        throw new Error(`Version ${target} is behind the Comments plugin (${pluginVersion}).`);
    }
}

/** The major a Composer constraint allows, such as 1 for `^1.0`, `~1.2` or `1.0.0`, or null for a development or open one. */
export function composerMajor(constraint) {
    const match = /^[\^~]?(\d+)(?:\.\d+){0,2}$/.exec((constraint ?? '').trim());

    return match ? Number(match[1]) : null;
}

/**
 * What stops a packed manifest's dependency on Editor or Protect from being released: a local link
 * instead of a released version, a range that isn't a caret on one major, or an npm major that
 * doesn't match the plugin's Composer constraint, since a library's PHP and browser halves must agree.
 */
export function libraryProblems(manifest, libraries, composer) {
    const problems = [];

    for (const library of libraries) {
        for (const kind of ['dependencies', 'peerDependencies', 'optionalDependencies']) {
            const range = manifest[kind]?.[library.npm];

            if (!range) continue;

            const major = /^\^(\d+)\.\d+\.\d+$/.exec(range)?.[1];
            const composerRange = composer.require?.[library.composer];
            const composerMajorVersion = composerMajor(composerRange);

            if (!major) {
                problems.push(`${manifest.name} depends on ${library.npm} as ${range}, not a caret range on a released version such as ^1.0.0.`);
            } else if (composerMajorVersion === null) {
                problems.push(`composer.json requires ${library.composer} as ${composerRange ?? 'nothing'}, which isn't a released major for ${manifest.name}'s ${library.npm} ${range} to match.`);
            } else if (Number(major) !== composerMajorVersion) {
                problems.push(`${manifest.name} depends on ${library.npm} ${range}, but composer.json requires ${library.composer} ${composerRange}.`);
            }
        }
    }

    return problems;
}

/**
 * Works out a release from the checkout and the workflow's inputs: the version, the channel, and the
 * manifest each package is packed with. Outside a rehearsal it only runs in the release branch's
 * GitHub Actions workflow, and it refuses anything that would publish the wrong thing.
 *
 * A rehearsal (`{ rehearse: true }`) skips the workflow checks and collects what would stop a real
 * release in `blockers` instead of throwing, so the packages can still be packed and tried.
 */
export function releasePlan(root, env, { rehearse = false } = {}) {
    const config = loadConfig(root);
    const blockers = [];
    const block = (message) => {
        if (!rehearse) throw new Error(message);
        blockers.push(message);
    };

    if (!rehearse && (env.GITHUB_ACTIONS !== 'true' || env.GITHUB_REF !== `refs/heads/${config.branch}`)) {
        throw new Error(`Prepared publication is restricted to the ${config.branch} GitHub Actions workflow.`);
    }

    const channel = env.RELEASE_CHANNEL || 'latest';
    const sha = env.RELEASE_SHA;
    const tarballDir = env.PACKAGE_TARBALL_DIR;

    if (!['latest', 'next'].includes(channel)) {
        throw new Error('RELEASE_CHANNEL must be latest or next.');
    }

    if (!/^[0-9a-f]{40}$/.test(sha ?? '')) {
        throw new Error('RELEASE_SHA must be a full Git commit SHA.');
    }

    if (!tarballDir || !isAbsolute(tarballDir)) {
        throw new Error('PACKAGE_TARBALL_DIR must be an absolute path.');
    }

    const composer = JSON.parse(readFileSync(join(root, config.plugin), 'utf8'));
    const packages = config.packages.map((dir, index) => {
        const manifest = JSON.parse(readFileSync(join(root, dir, 'package.json'), 'utf8'));

        if (manifest.name !== config.names[index] || manifest.repository?.url !== config.repository) {
            throw new Error(`${dir}'s name or repository doesn't match ${config.repository}.`);
        }

        return { dir, manifest };
    });
    const baseVersion = packages[0].manifest.version;

    if (!parseVersion(baseVersion) || packages.some(({ manifest }) => manifest.version !== baseVersion)) {
        throw new Error('The packages must have one valid version between them.');
    }

    // Every package carries the plugin's exact version (SD-36), as gate 10's pack check also insists.
    if (composer.version !== baseVersion) {
        block(`The packages are at ${baseVersion}, but composer.json is at ${composer.version}. Release them with the plugin version they carry.`);
    }

    let version = env.RELEASE_VERSION;
    // A prerelease, such as a beta, goes to `next` as itself. Between stable releases, `next` gets a
    // generated version after the current one instead.
    const exact = channel === 'latest' || !isStable(baseVersion);

    if (channel === 'latest' && !isStable(baseVersion)) {
        throw new Error(`${baseVersion} is a prerelease, which goes to the next channel, not latest.`);
    }

    if (exact) {
        if (version !== baseVersion) {
            throw new Error(`RELEASE_VERSION must be the packages' version, ${baseVersion}.`);
        }
    } else {
        if (version) {
            throw new Error('Leave RELEASE_VERSION empty for next between stable releases. The workflow generates it.');
        }

        if (!/^[1-9]\d*$/.test(env.GITHUB_RUN_NUMBER ?? '')) {
            throw new Error('A positive GITHUB_RUN_NUMBER is required for next.');
        }

        // A rerun keeps its version, and a new dispatch gets a new one. The `g` before the SHA keeps an
        // all-numeric hash from making the version invalid.
        const [major, minor, patch] = parseVersion(baseVersion).core;
        version = `${major}.${minor}.${patch + 1n}-next.${env.GITHUB_RUN_NUMBER}.g${sha.slice(0, 12)}`;
    }

    for (const pkg of packages) {
        if (exact && !readFileSync(join(root, pkg.dir, 'CHANGELOG.md'), 'utf8').includes(`## ${version} - `)) {
            block(`${pkg.dir} has no finished ${version} changelog entry. Run prepare-release first.`);
        }

        pkg.packed = structuredClone(pkg.manifest);
        pkg.packed.version = version;

        for (const kind of ['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies']) {
            for (const name of config.names) {
                if (!pkg.packed[kind]?.[name]) continue;

                if (exact && pkg.packed[kind][name] !== version) {
                    block(`${pkg.dir} must pin ${name} to ${version}.`);
                }

                pkg.packed[kind][name] = version;
            }
        }

        libraryProblems(pkg.packed, config.libraries, composer).forEach(block);

        // The commit each published version came from, so resuming a release can't mix two commits.
        pkg.packed.gitHead = sha;

        if (channel === 'next') {
            pkg.packed.publishConfig = { ...pkg.packed.publishConfig, tag: 'next' };
        } else if (pkg.packed.publishConfig?.tag && pkg.packed.publishConfig.tag !== channel) {
            block(`${pkg.dir}'s publishConfig.tag conflicts with ${channel}.`);
        }

        pkg.tarball = join(tarballDir, `verbb-${pkg.dir.split('/').at(-1)}-${version}.tgz`);
    }

    return { root, config, channel, sha, version, tarballDir, packages, blockers };
}

export function assertReviewedCheckout(plan, run = execFileSync) {
    const head = run('git', ['rev-parse', 'HEAD'], { cwd: plan.root, encoding: 'utf8' }).trim();
    const status = run('git', ['status', '--porcelain'], { cwd: plan.root, encoding: 'utf8' }).trim();

    if (head !== plan.sha || status) {
        throw new Error('The checkout must be clean and match the reviewed RELEASE_SHA.');
    }
}

export function assertPackedManifest(pkg, run = execFileSync) {
    const packed = JSON.parse(run('tar', ['-xOf', pkg.tarball, 'package/package.json'], { encoding: 'utf8' }));

    if (!isDeepStrictEqual(packed, pkg.packed)) {
        throw new Error(`${pkg.tarball}'s manifest doesn't match the reviewed package manifest.`);
    }
}

// Changelogs and the lockfile
// -------------------------------------------------------------------------

/** The Unreleased section's body, up to the next version's heading or the end of the file. */
export const extractUnreleasedBody = (content) => content.match(/^## Unreleased\n([\s\S]*?)(?=^## |(?![\s\S]))/m)?.[1] ?? null;

export const hasChangelogEntries = (body) => body?.split('\n').some((line) => /^(-|\*|\d+\.)\s+\S/.test(line.trim()) || /^###\s+\S/.test(line.trim())) ?? false;

export const finalizeChangelog = (content, version, date, packageName) => {
    const normalized = content.replace(/\r\n/g, '\n');

    if (!normalized.startsWith('# Changelog\n')) {
        throw new Error(`${packageName}'s CHANGELOG.md must start with "# Changelog".`);
    }

    const unreleased = extractUnreleasedBody(normalized);
    // Every release publishes every package, as Formie's do, so one with nothing to say says why.
    const body = hasChangelogEntries(unreleased) ? unreleased.trim() : `### Changed\n- Released alongside Comments ${version} with no changes to this package.`;
    const previous = normalized
        .replace(/^# Changelog\n+/, '')
        .replace(/^## Unreleased\n[\s\S]*?(?=^## |(?![\s\S]))/m, '')
        .trim();

    return `# Changelog\n\n## Unreleased\n\n## ${version} - ${date}\n\n${body}\n${previous ? `\n${previous}\n` : ''}`;
};

// The workspace's own packages resolve through workspace links. Any other `@verbb/*` package, such as
// Editor, Protect or Plugin Kit, must be a published npm artifact, never a link into a sibling checkout.
export const lockfileProblems = (lockfile, config) => {
    const problems = [];

    for (const [path, entry] of Object.entries(lockfile.packages ?? {})) {
        if (path.startsWith('../') || entry.resolved?.startsWith('../')) {
            problems.push(`${path || '<root>'}: ${entry.resolved ?? 'outside the repository'}`);
            continue;
        }

        const name = path.match(/(?:^|\/)node_modules\/(@verbb\/[^/]+)$/)?.[1];

        if (!name) continue;

        if (config.names.includes(name)) {
            // Only the root workspace link, never a registry copy, at the root or nested in another package.
            if (path !== `node_modules/${name}` || !entry.link) {
                problems.push(`${path}: ${name} must resolve through the root workspace link`);
            }

            continue;
        }

        const workspaceLink = entry.link && !entry.resolved?.startsWith('../');

        if (!workspaceLink && (entry.link || !entry.version || !entry.integrity || !entry.resolved?.startsWith('https://registry.npmjs.org/'))) {
            problems.push(`${path}: ${name} isn't a published npm artifact`);
        }
    }

    return problems;
};

/**
 * Pins every dependency on a Comments package in a manifest's text to the version, keeping the file's
 * own indentation so the diff shows only the versions. Returns null when nothing changes.
 */
export const pinInternalDependencies = (content, names, version) => {
    const manifest = JSON.parse(content);
    let changed = false;

    for (const kind of ['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies']) {
        for (const name of names) {
            if (manifest[kind]?.[name] && manifest[kind][name] !== version) {
                manifest[kind][name] = version;
                changed = true;
            }
        }
    }

    const indent = content.match(/^\{\r?\n([ \t]+)"/)?.[1] ?? '    ';

    return changed ? `${JSON.stringify(manifest, null, indent)}\n` : null;
};
