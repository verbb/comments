import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Gate 10. Packs each `@verbb/comments-*` package as npm would publish it and checks the tarball
 * on its own: the files, every export with `types` first, the Verbb licence and `license` field,
 * the version against the plugin's, an import in Node with no DOM, and a TypeScript consumer under
 * bundler, Node16 and NodeNext resolution. Run `npm run build:packages` first. The release also
 * checks each tarball with publint and attw (`scripts/pack-prepared.mjs`).
 */
const root = fileURLToPath(new URL('../', import.meta.url));
const pluginVersion = JSON.parse(readFileSync(join(root, 'composer.json'), 'utf8')).version;
const licence = readFileSync(join(root, 'LICENSE.md'), 'utf8');
const tsc = join(root, 'node_modules/typescript/bin/tsc');
// Packages link Editor and Protect with `file:` until they're published (D8). Local checks pass this
// flag. The publish workflow doesn't, so a link can't be published.
const allowLocalLinks = process.argv.includes('--allow-local-links');

const packages = [
    {
        name: '@verbb/comments-core',
        files: ['LICENSE.md', 'README.md', 'CHANGELOG.md', 'package.json', 'dist/index.js', 'dist/index.d.ts', 'schema/data.json'],
        published: /^(dist\/[\w-]+\.(js|d\.ts)|schema\/data\.json|LICENSE\.md|README\.md|CHANGELOG\.md|package\.json)$/,
        exports: [
            'COMMENTS_API_VERSION',
            'COMMENTS_API_VERSION_HEADER',
            'COMMENTS_ERROR_CODES',
            'COMMENTS_EVENT_NAMES',
            'COMMENTS_PROFILE_HEADER',
            'COMMENTS_SESSION_HEADER',
            'CommentsRequestError',
            'assertApiVersion',
            'cookieSession',
            'createCommentsClient',
            'createOperationId',
            'createProtectionFlow',
            'createThreadStore',
            'guestSession',
            'initialThreadState',
            'isCommentsRequestError',
            'jsonLdScript',
            'memberToken',
            'restTransport',
            'selectReplies',
            'selectRoots',
        ],
        subpaths: { './graphql': ['graphqlTransport'] },
        // Shared with the plugin's other packages, linked from the workspace for the consumer.
        dependencies: ['@verbb/protect'],
        consumer: 'packages/comments-core/tests/package/consumer.ts',
        domFree: true,
        sideEffects: false,
    },
    {
        name: '@verbb/comments-browser',
        files: ['LICENSE.md', 'README.md', 'CHANGELOG.md', 'package.json', 'dist/index.js', 'dist/index.d.ts', 'dist/renderer.js', 'dist/renderer.d.ts', 'dist/css/comments.css', 'dist/css/comments-base.css', 'dist/css/comments-theme.css', 'markup/classes.json'],
        published: /^(dist\/[\w/-]+\.(js|d\.ts|css)|markup\/classes\.json|LICENSE\.md|README\.md|CHANGELOG\.md|package\.json)$/,
        exports: [
            'CONTROL_NAMES',
            'CONTROL_STYLE_CLASSES',
            'Comments',
            'FALLBACK_LABELS',
            'PART_NAMES',
            'TRUSTED_TYPES_POLICY',
            'configure',
            'mergeThemeConfig',
            'mountThread',
            'setTheme',
            'startComments',
        ],
        subpaths: {
            // The renderer itself, which startComments() loads when a thread nears view (SD-46).
            './renderer': ['destroyThreads', 'getThread', 'mountThread'],
            // Posting from a site's own form with spam protection, for an interface without a framework.
            './form': ['commentForm'],
        },
        // Linked from the workspace for the consumer, as they would be installed beside it.
        dependencies: ['@verbb/comments-core', '@verbb/editor', '@verbb/protect', 'lit-html'],
        consumer: 'packages/comments-browser/tests/package/consumer.ts',
        domFree: false,
        sideEffects: ['**/*.css'],
    },
    {
        name: '@verbb/comments-web-components',
        files: ['LICENSE.md', 'README.md', 'CHANGELOG.md', 'package.json', 'dist/index.js', 'dist/index.d.ts', 'dist/element.js', 'dist/element.d.ts'],
        published: /^(dist\/[\w-]+\.(js|d\.ts)|LICENSE\.md|README\.md|CHANGELOG\.md|package\.json)$/,
        exports: ['COMMENTS_THREAD_TAG', 'CommentsThreadElement', 'registerCommentsWebComponents'],
        subpaths: {},
        // Editor is imported only when someone starts writing, so the probe never loads it.
        dependencies: ['@verbb/comments-browser', '@verbb/editor'],
        consumer: 'packages/comments-web-components/tests/package/consumer.ts',
        domFree: false,
        sideEffects: false,
    },
    {
        name: '@verbb/comments-react',
        files: ['LICENSE.md', 'README.md', 'CHANGELOG.md', 'package.json', 'dist/index.js', 'dist/index.d.ts', 'dist/server.js', 'dist/server.d.ts'],
        published: /^(dist\/[\w-]+\.(js|d\.ts)|LICENSE\.md|README\.md|CHANGELOG\.md|package\.json)$/,
        exports: ['CommentsProvider', 'CommentsThread', 'templatePart', 'useCommentForm', 'useCommentsEvent', 'useCommentsSettings', 'useCommentsThread'],
        // Imported in Node with nothing that touches the page, as a server component would.
        subpaths: { './server': ['fetchCommentsCount', 'fetchStructuredData', 'jsonLdScript'] },
        // Editor is imported only when someone starts writing, so the probe never loads it.
        dependencies: ['@verbb/comments-browser', '@verbb/comments-core', '@verbb/editor', '@verbb/protect'],
        links: { react: 'node_modules/react', 'react-dom': 'node_modules/react-dom', '@types/react': 'node_modules/@types/react' },
        consumer: 'packages/comments-react/tests/package/consumer.ts',
        domFree: false,
        sideEffects: false,
    },
    {
        name: '@verbb/comments-vue',
        files: ['LICENSE.md', 'README.md', 'CHANGELOG.md', 'package.json', 'dist/index.js', 'dist/index.d.ts', 'dist/server.js', 'dist/server.d.ts'],
        published: /^(dist\/[\w-]+\.(js|d\.ts)|LICENSE\.md|README\.md|CHANGELOG\.md|package\.json)$/,
        exports: ['COMMENTS_SETTINGS', 'CommentsPlugin', 'CommentsThread', 'provideCommentsSettings', 'templatePart', 'useCommentForm', 'useCommentsEvent', 'useCommentsSettings', 'useCommentsThread'],
        subpaths: { './server': ['fetchCommentsCount', 'fetchStructuredData', 'jsonLdScript'] },
        dependencies: ['@verbb/comments-browser', '@verbb/comments-core', '@verbb/editor', '@verbb/protect'],
        links: { vue: 'node_modules/vue' },
        consumer: 'packages/comments-vue/tests/package/consumer.ts',
        domFree: false,
        sideEffects: false,
    },
];

for (const pkg of packages) {
    const directory = join(root, 'packages', pkg.name.replace('@verbb/', ''));
    const work = mkdtempSync(join(tmpdir(), 'comments-pack-'));

    try {
        const [packed] = JSON.parse(execFileSync('npm', ['pack', '--json', '--ignore-scripts', '--pack-destination', work], { cwd: directory, encoding: 'utf8' }));
        const files = packed.files.map((file) => file.path).sort();
        const manifest = JSON.parse(readFileSync(join(directory, 'package.json'), 'utf8'));

        assert.equal(packed.name, pkg.name);
        assert.equal(packed.version, pluginVersion, `${pkg.name} must carry the plugin's version, ${pluginVersion}.`);
        assert.equal(manifest.license, 'SEE LICENSE IN LICENSE.md', `${pkg.name} must point at its LICENSE.md.`);
        assert.equal(manifest.type, 'module', `${pkg.name} must be ESM only.`);
        assert.deepEqual(manifest.sideEffects, pkg.sideEffects, `${pkg.name} must declare its side effects (none in its modules), so bundlers can drop what isn't used.`);
        assert.deepEqual(Object.keys(manifest.dependencies ?? {}), pkg.dependencies, `${pkg.name}'s dependencies changed.`);

        const links = Object.entries({ ...manifest.dependencies, ...manifest.peerDependencies }).filter(([, spec]) => /^(file|link|workspace):/.test(spec));
        // A sibling Comments package is pinned to the plugin's own version, as lockstep requires.
        for (const [dependency, spec] of Object.entries(manifest.dependencies ?? {}).filter(([dependency]) => dependency.startsWith('@verbb/comments-'))) {
            assert.equal(spec, pluginVersion, `${pkg.name} must depend on ${dependency} at exactly ${pluginVersion}.`);
        }

        if (links.length && !allowLocalLinks) {
            assert.fail(`${pkg.name} depends on ${links.map(([name, spec]) => `${name} (${spec})`).join(', ')}. They must be released version ranges before publishing (D8). Pass --allow-local-links to check a local build.`);
        }

        if (links.length) {
            console.warn(`WARNING: ${pkg.name} still links ${links.map(([name]) => name).join(', ')} with file:, which can't be published.`);
        }

        // Core's declarations must stand alone, without Protect's types, which need the DOM.
        for (const file of pkg.domFree ? files.filter((path) => path.endsWith('.d.ts')) : []) {
            assert.doesNotMatch(readFileSync(join(directory, file), 'utf8'), /(from|import\()\s*['"]@verbb\/protect/, `${file} imports @verbb/protect, whose types need the DOM.`);
        }

        for (const required of pkg.files) {
            assert.ok(files.includes(required), `The ${pkg.name} tarball is missing ${required}.`);
        }

        for (const file of files) {
            assert.match(file, pkg.published, `${file} shouldn't be published in ${pkg.name}.`);
        }

        const targets = [manifest.types, manifest.main];

        for (const [subpath, entry] of Object.entries(manifest.exports)) {
            if (typeof entry === 'string') {
                targets.push(entry);
                continue;
            }

            assert.equal(Object.keys(entry)[0], 'types', `The "${subpath}" export must list types first.`);
            targets.push(...Object.values(entry));
        }

        for (const target of targets) {
            assert.ok(files.includes(target.replace(/^\.\//, '')), `${target} is exported but not in the ${pkg.name} tarball.`);
        }

        // Unpack beside a consumer, with the package's own dependencies linked from the workspace.
        const consumer = join(work, 'consumer');
        const installed = join(consumer, 'node_modules', pkg.name);

        mkdirSync(installed, { recursive: true });
        execFileSync('tar', ['-xzf', join(work, packed.filename), '-C', installed, '--strip-components=1']);
        assert.equal(readFileSync(join(installed, 'LICENSE.md'), 'utf8'), licence, `${pkg.name}'s LICENSE.md must match the plugin's licence.`);

        // Peers such as React and Vue come from `links`, as an app would install them beside the package.
        for (const dependency of [...pkg.dependencies, ...Object.keys(pkg.links ?? {})]) {
            mkdirSync(join(consumer, 'node_modules', dependency, '..'), { recursive: true });
            symlinkSync(realpathSync(join(root, pkg.links?.[dependency] ?? join('node_modules', dependency))), join(consumer, 'node_modules', dependency));
        }

        writeFileSync(join(consumer, 'package.json'), JSON.stringify({ name: 'comments-pack-consumer', private: true, type: 'module' }));

        // A server render imports the package in Node, with no DOM, and reads the schema.
        const probe = `
            const module = await import('${pkg.name}');
            const subpaths = {};
            for (const subpath of ${JSON.stringify(Object.keys(manifest.exports).filter((key) => key !== '.' && !/\.(json|css)$/.test(key)))}) subpaths[subpath] = Object.keys(await import('${pkg.name}' + subpath.slice(1))).sort();
            const schema = await import('${pkg.name}/schema/data.json', { with: { type: 'json' } }).catch(() => null);
            console.log(JSON.stringify({ exports: Object.keys(module).sort(), subpaths, schema: schema ? Object.keys(schema.default.$defs ?? {}).length : null, document: typeof document }));
        `;
        const result = JSON.parse(execFileSync(process.execPath, ['--input-type=module', '-e', probe], { cwd: consumer, encoding: 'utf8' }));

        assert.deepEqual(result.exports, [...pkg.exports].sort(), `${pkg.name}'s exports in Node changed.`);
        assert.deepEqual(result.subpaths, pkg.subpaths, `${pkg.name}'s subpath exports changed.`);
        assert.equal(result.document, 'undefined');

        if (pkg.files.includes('schema/data.json')) {
            assert.ok(result.schema > 0, `${pkg.name}/schema/data.json didn't import.`);
        }

        const source = readFileSync(join(root, pkg.consumer), 'utf8');

        // A browser app, Node resolution both ways, and a worker or server with no DOM, each with
        // every declaration checked (no skipLibCheck).
        for (const [resolution, module, file, lib] of [
            ['Bundler', 'ESNext', 'consumer.ts', ['ES2022', 'DOM', 'DOM.Iterable']],
            ['Node16', 'Node16', 'consumer.mts', ['ES2022', 'DOM', 'DOM.Iterable']],
            ['NodeNext', 'NodeNext', 'consumer.mts', ['ES2022', 'DOM', 'DOM.Iterable']],
            ['NodeNext', 'NodeNext', 'consumer.mts', ['ES2022', 'WebWorker']],
        ].filter(([, , , lib]) => pkg.domFree || lib.includes('DOM'))) {
            writeFileSync(join(consumer, file), source);
            writeFileSync(join(consumer, 'tsconfig.json'), JSON.stringify({
                compilerOptions: { target: 'ES2022', module, moduleResolution: resolution, strict: true, noEmit: true, skipLibCheck: false, types: [], lib },
                files: [file],
            }));

            try {
                execFileSync(process.execPath, [tsc, '--project', join(consumer, 'tsconfig.json')], { cwd: consumer, encoding: 'utf8' });
            } catch (error) {
                assert.fail(`${pkg.name}'s packed types don't resolve under ${resolution} with ${lib.join(', ')}:\n${error.stdout}`);
            }

            rmSync(join(consumer, file));
        }

        console.log(`PASS: ${packed.filename} holds ${files.length} files, every export, the Verbb licence, version ${packed.version}, and types for bundler, Node16 and NodeNext resolution, with the DOM or a worker's library.`);
    } finally {
        rmSync(work, { recursive: true, force: true });
    }
}
