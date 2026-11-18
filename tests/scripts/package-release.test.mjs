import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join } from 'node:path';
import { test } from 'node:test';
import { packPrepared } from '../../scripts/pack-prepared.mjs';
import {
    assertPreparedVersion,
    compareVersions,
    composerMajor,
    extractUnreleasedBody,
    finalizeChangelog,
    isStable,
    libraryProblems,
    lockfileProblems,
    pinInternalDependencies,
    releasePlan,
} from '../../scripts/package-release.mjs';
import { publishPrepared } from '../../scripts/publish-prepared.mjs';

const sha = '0123456789abcdef0123456789abcdef01234567';
const dirs = ['packages/comments-core', 'packages/comments-react'];
const config = {
    repository: 'git+https://github.com/verbb/comments.git',
    branch: 'craft-5',
    plugin: 'composer.json',
    packages: dirs,
    pinnedBy: [],
    workspaceManifests: [],
    libraries: [{ npm: '@verbb/editor', composer: 'verbb/editor' }],
};

/** A checkout with two lockstep packages, the second depending on the first and on Editor. */
function fixture(t, { version = '4.0.0-beta.2', editor = '^1.0.0', composerEditor = '^1.0', changelog = true } = {}) {
    const root = mkdtempSync(join(tmpdir(), 'comments-release-test-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));

    mkdirSync(join(root, 'scripts'));
    writeFileSync(join(root, 'scripts/release.config.json'), JSON.stringify(config));
    writeFileSync(join(root, 'composer.json'), JSON.stringify({ version, require: { 'verbb/editor': composerEditor } }));

    for (const [index, dir] of dirs.entries()) {
        mkdirSync(join(root, dir, 'dist'), { recursive: true });
        writeFileSync(join(root, dir, 'dist/index.js'), 'export const answer = 42;\n');
        writeFileSync(join(root, dir, 'private.txt'), 'Must not ship.');
        writeFileSync(join(root, dir, 'CHANGELOG.md'), `# Changelog\n\n## Unreleased\n${changelog ? `\n## ${version} - 2026-11-14\n\n### Added\n- Something.\n` : ''}`);
        writeFileSync(join(root, dir, 'package.json'), JSON.stringify({
            name: `@verbb/${basename(dir)}`,
            version,
            type: 'module',
            main: './dist/index.js',
            files: ['dist', 'CHANGELOG.md'],
            repository: { type: 'git', url: config.repository, directory: dir },
            publishConfig: { access: 'public' },
            dependencies: index ? { '@verbb/comments-core': version, '@verbb/editor': editor, external: '^2.0.0' } : {},
            optionalDependencies: index ? { '@verbb/comments-core': version } : {},
            scripts: { prepack: 'node -e "process.exit(99)"' },
        }));
    }

    const env = {
        GITHUB_ACTIONS: 'true',
        GITHUB_REF: 'refs/heads/craft-5',
        RELEASE_SHA: sha,
        RELEASE_CHANNEL: isStable(version) ? 'latest' : 'next',
        RELEASE_VERSION: version,
        GITHUB_RUN_NUMBER: '42',
        PACKAGE_TARBALL_DIR: join(root, 'tarballs'),
    };
    const changeManifest = (index, change) => {
        const file = join(root, dirs[index], 'package.json');
        const manifest = JSON.parse(readFileSync(file, 'utf8'));
        change(manifest);
        writeFileSync(file, JSON.stringify(manifest));
    };

    return { root, env, changeManifest };
}

// Versions
// -------------------------------------------------------------------------

test('versions compare by semantic versioning precedence', () => {
    const ordered = ['1.0.0-alpha', '1.0.0-alpha.1', '1.0.0-alpha.beta', '1.0.0-beta', '1.0.0-beta.2', '1.0.0-beta.11', '1.0.0-rc.1', '1.0.0', '1.0.1', '1.1.0', '4.0.0-beta.1', '4.0.0'];

    for (let i = 0; i < ordered.length - 1; i++) {
        assert.equal(compareVersions(ordered[i], ordered[i + 1]), -1, `${ordered[i]} < ${ordered[i + 1]}`);
        assert.equal(compareVersions(ordered[i + 1], ordered[i]), 1, `${ordered[i + 1]} > ${ordered[i]}`);
    }

    assert.equal(compareVersions('4.0.0-beta.2', '4.0.0-beta.2'), 0);
    assert.throws(() => compareVersions('4.0', '4.0.0'), /Can't compare/);
    assert.equal(isStable('4.0.0'), true);
    assert.equal(isStable('4.0.0-beta.1'), false);
    assert.equal(isStable('v4.0.0'), false);
});

test('a prepared version must be valid, newer than the packages and not behind the plugin', () => {
    assert.doesNotThrow(() => assertPreparedVersion('4.0.0-beta.2', '4.0.0-beta.1', '4.0.0-beta.2'));
    assert.doesNotThrow(() => assertPreparedVersion('4.0.0', '4.0.0-beta.9', '4.0.0'));
    assert.throws(() => assertPreparedVersion(undefined, '4.0.0-beta.1', '4.0.0-beta.1'), /exact version/);
    assert.throws(() => assertPreparedVersion('4.0', '4.0.0-beta.1', '4.0.0-beta.1'), /exact version/);
    assert.throws(() => assertPreparedVersion('4.0.0-beta.1', '4.0.0-beta.1', '4.0.0-beta.1'), /newer than the current/);
    assert.throws(() => assertPreparedVersion('4.0.0-alpha.9', '4.0.0-alpha.1', '4.0.0-beta.1'), /behind the Comments plugin/);
});

// Editor and Protect
// -------------------------------------------------------------------------

test('Composer constraints give their major, or none for development and open ones', () => {
    assert.equal(composerMajor('^1.0'), 1);
    assert.equal(composerMajor('~2.3'), 2);
    assert.equal(composerMajor('3.0.1'), 3);
    assert.equal(composerMajor('*@dev'), null);
    assert.equal(composerMajor('^1.0 || ^2.0'), null);
    assert.equal(composerMajor(undefined), null);
});

test('Editor must be a released caret range whose major matches composer.json', () => {
    const problems = (range, constraint) => libraryProblems(
        { name: '@verbb/comments-react', dependencies: { '@verbb/editor': range } },
        config.libraries,
        { require: constraint ? { 'verbb/editor': constraint } : {} },
    );

    assert.deepEqual(problems('^1.2.0', '^1.0'), []);
    assert.match(problems('file:../editor', '^1.0')[0], /not a caret range on a released version/);
    assert.match(problems('1.2.0', '^1.0')[0], /not a caret range/);
    assert.match(problems('^1.2.0', '*@dev')[0], /isn't a released major/);
    assert.match(problems('^1.2.0', undefined)[0], /as nothing/);
    assert.match(problems('^2.0.0', '^1.0')[0], /but composer.json requires verbb\/editor \^1.0/);
    assert.deepEqual(libraryProblems({ name: '@verbb/comments-core' }, config.libraries, {}), []);
});

// Release plans
// -------------------------------------------------------------------------

test('a stable version publishes to latest as itself', (t) => {
    const { root, env } = fixture(t, { version: '4.0.0' });
    const plan = releasePlan(root, env);

    assert.equal(plan.channel, 'latest');
    assert.equal(plan.version, '4.0.0');
    assert.deepEqual(plan.blockers, []);
    assert.equal(plan.packages[1].packed.dependencies['@verbb/comments-core'], '4.0.0');
    assert.equal(plan.packages[1].packed.publishConfig.tag, undefined);
    assert.equal(plan.packages[1].packed.gitHead, sha);
    assert.equal(plan.packages[1].tarball, join(root, 'tarballs/verbb-comments-react-4.0.0.tgz'));
});

test('a prerelease publishes to next as itself, tagged next', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);

    assert.equal(plan.channel, 'next');
    assert.equal(plan.version, '4.0.0-beta.2');
    assert.equal(plan.packages[0].packed.publishConfig.tag, 'next');
    assert.equal(plan.packages[0].packed.gitHead, sha);
    assert.equal(plan.packages[0].packed.publishConfig.access, 'public');
});

test('a prerelease never goes to latest, and needs its own version', (t) => {
    const { root, env } = fixture(t);

    assert.throws(() => releasePlan(root, { ...env, RELEASE_CHANNEL: 'latest' }), /prerelease, which goes to the next channel/);
    assert.throws(() => releasePlan(root, { ...env, RELEASE_VERSION: '' }), /must be the packages' version, 4.0.0-beta.2/);
    assert.throws(() => releasePlan(root, { ...env, RELEASE_VERSION: '4.0.0-beta.3' }), /must be the packages' version/);
});

test('next between stable releases gets a generated version after the current one', (t) => {
    const { root, env } = fixture(t, { version: '4.0.0', changelog: false });
    const plan = releasePlan(root, { ...env, RELEASE_CHANNEL: 'next', RELEASE_VERSION: '' });

    assert.equal(plan.version, `4.0.1-next.42.g${sha.slice(0, 12)}`);
    assert.equal(plan.packages[1].packed.dependencies['@verbb/comments-core'], plan.version);
    assert.equal(plan.packages[1].packed.optionalDependencies['@verbb/comments-core'], plan.version);
    assert.equal(plan.packages[1].packed.dependencies.external, '^2.0.0');
    assert.equal(plan.packages[1].packed.version, plan.version);
    // The source manifests are left alone.
    assert.equal(plan.packages[1].manifest.version, '4.0.0');
    assert.throws(() => releasePlan(root, { ...env, RELEASE_CHANNEL: 'next', RELEASE_VERSION: '4.0.1' }), /Leave RELEASE_VERSION empty/);
    assert.throws(() => releasePlan(root, { ...env, RELEASE_CHANNEL: 'next', RELEASE_VERSION: '', GITHUB_RUN_NUMBER: '0' }), /GITHUB_RUN_NUMBER/);
});

test('a release only runs in the release branch workflow, with valid inputs', (t) => {
    const { root, env } = fixture(t);

    assert.throws(() => releasePlan(root, { ...env, GITHUB_ACTIONS: '' }), /restricted to the craft-5 GitHub Actions workflow/);
    assert.throws(() => releasePlan(root, { ...env, GITHUB_REF: 'refs/heads/react' }), /restricted/);
    assert.throws(() => releasePlan(root, { ...env, RELEASE_CHANNEL: 'beta' }), /latest or next/);
    assert.throws(() => releasePlan(root, { ...env, RELEASE_SHA: 'abc123' }), /full Git commit SHA/);
    assert.throws(() => releasePlan(root, { ...env, PACKAGE_TARBALL_DIR: 'tarballs' }), /absolute path/);
});

test('a release refuses packages that are out of lockstep, unpinned or not ours', (t) => {
    const { root, env, changeManifest } = fixture(t);

    changeManifest(1, (manifest) => { manifest.dependencies['@verbb/comments-core'] = '^4.0.0-beta.1'; });
    assert.throws(() => releasePlan(root, env), /must pin @verbb\/comments-core to 4.0.0-beta.2/);

    changeManifest(1, (manifest) => {
        manifest.dependencies['@verbb/comments-core'] = '4.0.0-beta.2';
        manifest.optionalDependencies['@verbb/comments-core'] = '^4.0.0-beta.1';
    });
    assert.throws(() => releasePlan(root, env), /must pin @verbb\/comments-core to 4.0.0-beta.2/);

    changeManifest(1, (manifest) => { manifest.version = '4.0.0-beta.1'; });
    assert.throws(() => releasePlan(root, env), /one valid version/);

    changeManifest(0, (manifest) => { manifest.repository.url = 'git+https://github.com/someone/comments.git'; });
    assert.throws(() => releasePlan(root, env), /name or repository/);
});

test('a release refuses a missing changelog entry and unreleased libraries', (t) => {
    const unfinished = fixture(t, { changelog: false });
    assert.throws(() => releasePlan(unfinished.root, unfinished.env), /no finished 4.0.0-beta.2 changelog entry/);

    const linked = fixture(t, { editor: 'file:../../editor' });
    assert.throws(() => releasePlan(linked.root, linked.env), /not a caret range/);

    const development = fixture(t, { composerEditor: '*@dev' });
    assert.throws(() => releasePlan(development.root, development.env), /isn't a released major/);
});

test('a release needs composer.json at the packages\' version', (t) => {
    const { root, env } = fixture(t);

    writeFileSync(join(root, 'composer.json'), JSON.stringify({ version: '4.0.0-beta.1', require: { 'verbb/editor': '^1.0' } }));
    assert.throws(() => releasePlan(root, env), /composer.json is at 4.0.0-beta.1/);
});

test('a rehearsal skips the workflow checks and lists what would stop a release', (t) => {
    const { root, env } = fixture(t, { editor: 'file:../../editor', composerEditor: '*@dev', changelog: false });
    const plan = releasePlan(root, { ...env, GITHUB_ACTIONS: '', GITHUB_REF: '' }, { rehearse: true });

    assert.equal(plan.version, '4.0.0-beta.2');
    assert.equal(plan.blockers.length, 3);
    assert.match(plan.blockers[0], /comments-core has no finished/);
    assert.match(plan.blockers[1], /comments-react has no finished/);
    assert.match(plan.blockers[2], /@verbb\/editor as file:/);
    // Invalid inputs still stop a rehearsal, since nothing could be packed from them.
    assert.throws(() => releasePlan(root, { ...env, RELEASE_SHA: '' }, { rehearse: true }), /full Git commit SHA/);
});

// Packing and publishing
// -------------------------------------------------------------------------

test('packing ships the reviewed manifest and npm\'s own file set, without lifecycle scripts', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);
    const linted = [];
    const run = (command, args, options) => {
        if (command.endsWith('/publint') || command.endsWith('/attw')) {
            linted.push(`${basename(command)} ${basename(args.find((arg) => arg.endsWith('.tgz')))}`);

            return '';
        }

        return execFileSync(command, args, options);
    };

    packPrepared(plan, { run, checkout: false });

    for (const pkg of plan.packages) {
        assert.ok(existsSync(pkg.tarball), `${pkg.tarball} exists`);

        const files = execFileSync('tar', ['-tzf', pkg.tarball], { encoding: 'utf8' }).split('\n').filter(Boolean).sort();
        const manifest = JSON.parse(execFileSync('tar', ['-xOf', pkg.tarball, 'package/package.json'], { encoding: 'utf8' }));

        assert.deepEqual(files, ['package/CHANGELOG.md', 'package/dist/index.js', 'package/package.json']);
        assert.deepEqual(manifest, pkg.packed);
    }

    assert.deepEqual(linted, [
        'publint verbb-comments-core-4.0.0-beta.2.tgz',
        'attw verbb-comments-core-4.0.0-beta.2.tgz',
        'publint verbb-comments-react-4.0.0-beta.2.tgz',
        'attw verbb-comments-react-4.0.0-beta.2.tgz',
    ]);
});

test('packing refuses a checkout that isn\'t the reviewed commit, or has changes', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);
    const git = (head, status) => (command, args) => {
        if (command !== 'git') throw new Error(`Packed with ${command} before checking the checkout.`);

        return args[0] === 'rev-parse' ? head : status;
    };

    assert.throws(() => packPrepared(plan, { run: git(sha, ' M packages/comments-core/package.json') }), /must be clean/);
    assert.throws(() => packPrepared(plan, { run: git('f'.repeat(40), '') }), /must be clean and match/);
    assert.equal(existsSync(plan.tarballDir), false);
});

/** A stand-in for git, tar and npm, with npm already holding the given packages from the given commit. */
function registry(plan, onNpm = {}, calls = []) {
    return (command, args) => {
        calls.push([command, ...args].join(' '));

        if (command === 'git') return args[0] === 'rev-parse' ? `${sha}\n` : '';
        if (command === 'tar') return JSON.stringify(plan.packages.find((pkg) => pkg.tarball === args[1]).packed);
        if (args[0] === 'view' && args[2] === 'dist-tags.latest') return plan.version;

        if (args[0] === 'view') {
            const name = args[1].slice(0, args[1].lastIndexOf('@'));

            if (onNpm[name]) return JSON.stringify({ version: plan.version, gitHead: onNpm[name] });

            throw Object.assign(new Error('Not found'), { stderr: 'npm error code E404' });
        }

        return '';
    };
}

test('publishing a stable release sends every package to latest, in order', (t) => {
    const { root, env } = fixture(t, { version: '4.0.0' });
    const plan = releasePlan(root, env);
    const calls = [];

    publishPrepared(plan, registry(plan, {}, calls));

    assert.deepEqual(calls.filter((call) => call.startsWith('npm publish')).map((call) => call.split(' ')[2]), plan.packages.map((pkg) => pkg.tarball));
    assert.ok(calls.filter((call) => call.startsWith('npm publish')).every((call) => call.includes('--tag latest --access public --ignore-scripts')));
});

test('publishing stops before npm when a tarball\'s manifest isn\'t the reviewed one', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);
    const calls = [];
    const run = registry(plan, {}, calls);

    assert.throws(() => publishPrepared(plan, (command, args, options) => {
        if (command === 'tar' && args[1] === plan.packages[1].tarball) {
            return JSON.stringify({ ...plan.packages[1].packed, dependencies: { '@verbb/comments-core': '^4.0.0' } });
        }

        return run(command, args, options);
    }), /doesn't match the reviewed package manifest/);
    assert.equal(calls.filter((call) => call.startsWith('npm')).length, 0);
});

test('a rerun only finishes a release from the same commit', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);
    const calls = [];

    assert.throws(
        () => publishPrepared(plan, registry(plan, { '@verbb/comments-core': 'a'.repeat(40) }, calls)),
        /already has @verbb\/comments-core@4.0.0-beta.2 from aaaa/,
    );
    assert.equal(calls.filter((call) => call.startsWith('npm publish')).length, 0);
});

test('publishing checks every tarball first, skips what npm has and publishes the rest to the channel', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);
    const calls = [];
    const run = registry(plan, { '@verbb/comments-core': sha }, calls);

    publishPrepared(plan, run);

    const published = calls.filter((call) => call.startsWith('npm publish'));

    assert.equal(published.length, 1);
    assert.match(published[0], /verbb-comments-react-4.0.0-beta.2.tgz --tag next --access public --ignore-scripts/);
    // Both tarballs were checked before anything was published.
    assert.ok(calls.findLastIndex((call) => call.startsWith('tar')) < calls.findIndex((call) => call.startsWith('npm view')));
});

test('publishing stops on an unreviewed checkout or a registry it can\'t read', (t) => {
    const { root, env } = fixture(t);
    const plan = releasePlan(root, env);
    const packed = (args) => JSON.stringify(plan.packages.find((pkg) => pkg.tarball === args[1]).packed);

    assert.throws(() => publishPrepared(plan, (command, args) => (command === 'git' ? (args[0] === 'rev-parse' ? sha : ' M package.json') : '')), /must be clean/);
    assert.throws(() => publishPrepared(plan, (command, args) => {
        if (command === 'git') return args[0] === 'rev-parse' ? sha : '';
        if (command === 'tar') return packed(args);

        throw Object.assign(new Error('timeout'), { stderr: 'ETIMEDOUT' });
    }), /Couldn't check @verbb\/comments-core/);
});

// Changelogs and the lockfile
// -------------------------------------------------------------------------

test('a changelog with only an Unreleased section is finished with its entries, or a lockstep note', () => {
    assert.equal(extractUnreleasedBody('# Changelog\n\n## Unreleased\n\n### Fixed\n- A fix.\n'), '\n### Fixed\n- A fix.\n');
    assert.equal(
        finalizeChangelog('# Changelog\n\n## Unreleased\n\n### Fixed\n- A fix.\n', '4.0.0-beta.2', '2026-11-14', '@verbb/comments-core'),
        '# Changelog\n\n## Unreleased\n\n## 4.0.0-beta.2 - 2026-11-14\n\n### Fixed\n- A fix.\n',
    );
    assert.equal(
        finalizeChangelog('# Changelog\r\n\r\n## Unreleased\r\n', '4.0.0-beta.2', '2026-11-14', '@verbb/comments-vue'),
        '# Changelog\n\n## Unreleased\n\n## 4.0.0-beta.2 - 2026-11-14\n\n### Changed\n- Released alongside Comments 4.0.0-beta.2 with no changes to this package.\n',
    );
    assert.equal(
        finalizeChangelog('# Changelog\n\n## Unreleased\n\n- New.\n\n## 4.0.0-beta.1 - 2026-11-01\n\n- Old.\n', '4.0.0-beta.2', '2026-11-14', '@verbb/comments-core'),
        '# Changelog\n\n## Unreleased\n\n## 4.0.0-beta.2 - 2026-11-14\n\n- New.\n\n## 4.0.0-beta.1 - 2026-11-01\n\n- Old.\n',
    );
    assert.throws(() => finalizeChangelog('## Unreleased\n', '4.0.0-beta.2', '2026-11-14', '@verbb/comments-core'), /must start with "# Changelog"/);
});

test('the lockfile resolves Comments through the workspace and every other Verbb package from npm', () => {
    const names = ['@verbb/comments-core', '@verbb/comments-react'];
    const registry = (name) => ({ version: '1.0.0', integrity: 'sha512-x', resolved: `https://registry.npmjs.org/${name}/-/x-1.0.0.tgz` });

    assert.deepEqual(lockfileProblems({
        packages: {
            '': {},
            'node_modules/@verbb/comments-core': { resolved: 'packages/comments-core', link: true },
            'node_modules/@verbb/editor': registry('@verbb/editor'),
            'node_modules/@verbb/comments-docs': { resolved: 'docs', link: true },
            'packages/comments-core': { version: '4.0.0-beta.2' },
        },
    }, { names }), []);

    assert.deepEqual(lockfileProblems({
        packages: {
            '../../editor/packages/editor': { version: '1.0.0' },
            'node_modules/@verbb/protect': { resolved: '../../protect/packages/protect', link: true },
            'node_modules/@verbb/plugin-kit-web': { version: '1.0.0', resolved: 'https://example.com/x.tgz', integrity: 'sha512-x' },
            'node_modules/@verbb/editor': { version: '1.0.0', resolved: 'https://registry.npmjs.org/@verbb/editor/-/editor-1.0.0.tgz' },
            'packages/comments-react/node_modules/@verbb/comments-core': registry('@verbb/comments-core'),
            'node_modules/vitest/node_modules/@verbb/comments-react': registry('@verbb/comments-react'),
            'node_modules/@verbb/comments-react': registry('@verbb/comments-react'),
        },
    }, { names }), [
        '../../editor/packages/editor: outside the repository',
        'node_modules/@verbb/protect: ../../protect/packages/protect',
        'node_modules/@verbb/plugin-kit-web: @verbb/plugin-kit-web isn\'t a published npm artifact',
        'node_modules/@verbb/editor: @verbb/editor isn\'t a published npm artifact',
        'packages/comments-react/node_modules/@verbb/comments-core: @verbb/comments-core must resolve through the root workspace link',
        'node_modules/vitest/node_modules/@verbb/comments-react: @verbb/comments-react must resolve through the root workspace link',
        'node_modules/@verbb/comments-react: @verbb/comments-react must resolve through the root workspace link',
    ]);
});

test('preparing pins every Comments dependency and keeps each manifest\'s indentation', () => {
    const names = ['@verbb/comments-core', '@verbb/comments-browser'];
    // The plugin's own asset, indented with two spaces.
    const asset = '{\n  "name": "comments-frontend",\n  "dependencies": {\n    "@verbb/comments-browser": "4.0.0-beta.1",\n    "lit": "^3.0.0"\n  }\n}\n';

    assert.equal(
        pinInternalDependencies(asset, names, '4.0.0-beta.2'),
        '{\n  "name": "comments-frontend",\n  "dependencies": {\n    "@verbb/comments-browser": "4.0.0-beta.2",\n    "lit": "^3.0.0"\n  }\n}\n',
    );

    const tabbed = '{\n\t"name": "@verbb/comments-browser",\n\t"optionalDependencies": {\n\t\t"@verbb/comments-core": "4.0.0-beta.1"\n\t},\n\t"devDependencies": {\n\t\t"@verbb/comments-core": "4.0.0-beta.1"\n\t}\n}\n';

    assert.equal(
        pinInternalDependencies(tabbed, names, '4.0.0-beta.2'),
        '{\n\t"name": "@verbb/comments-browser",\n\t"optionalDependencies": {\n\t\t"@verbb/comments-core": "4.0.0-beta.2"\n\t},\n\t"devDependencies": {\n\t\t"@verbb/comments-core": "4.0.0-beta.2"\n\t}\n}\n',
    );
    assert.equal(pinInternalDependencies(asset.replace('4.0.0-beta.1', '4.0.0-beta.2'), names, '4.0.0-beta.2'), null);
});
