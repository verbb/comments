import assert from 'node:assert/strict';
import { mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';
import { build } from 'vite';

/**
 * Gate 2's package budgets. Each package's built entry is bundled and minified the way a site's
 * own build would, with every `@verbb/comments-*` package it depends on left out (each has its own
 * budget) and its other dependencies bundled in, then gzipped. Fails when a package is over its
 * budget. Run `npm run build:packages` first.
 */
const root = fileURLToPath(new URL('../', import.meta.url));
const budgets = {
    // Every site ships the main entry, which is what gate 2 limits. Optional entries, such as
    // core's GraphQL transport, are measured together with it and reported.
    // Raised from 10 KB to 10.25 KB when Protect became a chunk of its own, imported when a preparation
    // arrives, whose loading code costs a few bytes (SD-55).
    '@verbb/comments-core': { budget: 10.25 * 1024, optional: ['./graphql'] },
    // Gate 2's 3 KB on top of the browser package. The element is about 1.5 KB; the rest is headroom
    // for what stage J's starters and gate 7's naming pass may add.
    '@verbb/comments-web-components': { budget: 3 * 1024, optional: [] },
    // Gate 2 started each framework adapter at 3 KB on top of the browser package. What a page with
    // a thread ships (`<CommentsThread>` with its settings, the mount point, the events, and the
    // portals or teleports for component overrides) was 2.6 KB at first, and is about 3.4 KB (React)
    // and 3.6 KB (Vue) with the gate 9 review's fixes: an error boundary per override, a sign-in
    // provider or fetch handed over without starting the thread again, `darkSelector`, and Vue's
    // props from mixins, `extends` and async components. So it gets 4 KB. The whole entry also has
    // the hooks or composables and the form helper (about 5.5 KB), which an app's bundler leaves
    // out unless it uses them, so it gets 6 KB. The form helper is reported alone, and with Protect
    // for an app that uses it without the thread. The server helpers are reported with the entry,
    // though only a server imports them.
    '@verbb/comments-react': { budget: 6 * 1024, optional: ['./server'], subsets: { 'CommentsThread alone': { exports: ['CommentsThread', 'CommentsProvider'], budget: 4 * 1024 }, 'useCommentForm alone': { exports: ['useCommentForm'] }, 'useCommentForm with Protect, for an app without the thread': { exports: ['useCommentForm'], bundle: ['@verbb/protect'] } } },
    '@verbb/comments-vue': { budget: 6 * 1024, optional: ['./server'], subsets: { 'CommentsThread alone': { exports: ['CommentsThread', 'CommentsPlugin'], budget: 4 * 1024 }, 'useCommentForm alone': { exports: ['useCommentForm'] }, 'useCommentForm with Protect, for an app without the thread': { exports: ['useCommentForm'], bundle: ['@verbb/protect'] } } },
};
const failures = [];

async function measure(directory, manifest, entries, shared) {
    const output = await build({
        configFile: false,
        logLevel: 'silent',
        root: directory,
        build: {
            write: false,
            minify: true,
            emptyOutDir: false,
            // An export's subpath, such as `./graphql`, or a file of its own, such as a subset's entry below.
            lib: { entry: Object.fromEntries(entries.map((entry) => (entry.startsWith('.') ? [entry === '.' ? 'index' : entry.slice(2), join(directory, manifest.exports[entry].import)] : ['subset', entry]))), formats: ['es'] },
            // Library mode leaves whitespace in ES output; a site's own build takes it out. Shared
            // packages are left out with their subpaths, such as the browser package's `/renderer`.
            rolldownOptions: { external: (id) => shared.some((name) => id === name || id.startsWith(`${name}/`)), output: { minify: true } },
        },
    });
    const code = [output].flat().flatMap((result) => result.output).filter((chunk) => chunk.type === 'chunk').map((chunk) => chunk.code).join('\n');

    return { gzipped: gzipSync(code, { level: 9 }).length, minified: code.length };
}

const kb = (bytes) => `${(bytes / 1024).toFixed(2)} KB`;

for (const [name, { budget, optional, subsets = {} }] of Object.entries(budgets)) {
    const directory = join(root, 'packages', name.replace('@verbb/', ''));
    const manifest = JSON.parse(readFileSync(join(directory, 'package.json'), 'utf8'));
    // Editor is loaded on demand, in a chunk of its own, and is never part of a package's budget.
    // Peers, such as React and Vue, are the app's own, and Protect comes with the browser package,
    // whose budget already counts it.
    const peers = Object.keys(manifest.peerDependencies ?? {});
    const withBrowser = '@verbb/comments-browser' in (manifest.dependencies ?? {});
    const shared = Object.keys({ ...manifest.dependencies, ...manifest.peerDependencies }).filter((dependency) => dependency.startsWith('@verbb/comments-') || dependency === '@verbb/editor' || peers.includes(dependency) || (withBrowser && dependency === '@verbb/protect'));
    const main = await measure(directory, manifest, ['.'], shared);
    const line = `${name}: ${kb(main.gzipped)} gzipped (${kb(main.minified)} minified), budget ${kb(budget)}`;

    if (main.gzipped > budget) {
        failures.push(line);
    }

    console.log(`${main.gzipped > budget ? 'FAIL' : 'PASS'}: ${line}`);

    for (const entry of optional) {
        const both = await measure(directory, manifest, ['.', entry], shared);
        console.log(`INFO: ${name} with ${entry}: ${kb(both.gzipped)} gzipped, ${kb(both.gzipped - main.gzipped)} more`);
    }

    // What an app that imports only some exports ships, with the rest left out by its bundler.
    // A subset without a budget is reported. `bundle` counts a shared package too, such as Protect for
    // an app that uses only the form hook, and so doesn't ship the browser package's copy.
    for (const [label, { exports, budget: limit, bundle = [] }] of Object.entries(subsets)) {
        const work = mkdtempSync(join(tmpdir(), 'comments-size-'));
        const entry = join(work, 'subset.js');

        try {
            writeFileSync(entry, `export { ${exports.join(', ')} } from ${JSON.stringify(join(directory, manifest.exports['.'].import))};`);
            const subset = await measure(directory, manifest, [entry], shared.filter((dependency) => !bundle.includes(dependency)));
            const subsetLine = `${name}, ${label}: ${kb(subset.gzipped)} gzipped${limit ? `, budget ${kb(limit)}` : ''}`;

            if (limit && subset.gzipped > limit) failures.push(subsetLine);

            console.log(`${!limit ? 'INFO' : subset.gzipped > limit ? 'FAIL' : 'PASS'}: ${subsetLine}`);
        } finally {
            rmSync(work, { recursive: true, force: true });
        }
    }
}

// The plugin's own asset, in its pieces (SD-46): the startup loader every page with a thread
// downloads first, the renderer it imports when a thread nears view, and the comment forms,
// imported when someone heads for one. Run `npm run build:assets` first. Editor must never be in
// any of them; the forms load Editor's own module when someone starts writing.
const asset = join(root, 'src/web/assets/frontend/dist');
const manifest = JSON.parse(readFileSync(join(asset, 'manifest.json'), 'utf8'));
const entry = Object.keys(manifest).find((key) => manifest[key].isEntry);
const dynamic = (name) => Object.keys(manifest).find((key) => manifest[key].isDynamicEntry && manifest[key].name === name);

/** A chunk and every chunk it imports statically, leaving out those already counted. */
function piece(key, counted) {
    const files = [];
    const visit = (current) => {
        const file = manifest[current].file;

        if (counted.has(file)) return;

        counted.add(file);
        files.push(file);
        (manifest[current].imports ?? []).forEach(visit);
    };

    visit(key);
    return files;
}

const gzipped = (files) => files.reduce((sum, file) => sum + gzipSync(readFileSync(join(asset, file)), { level: 9 }).length, 0);
const counted = new Set();
const first = piece(entry, counted);
const rendererFiles = piece(dynamic('threads'), counted);
const formFiles = piece(dynamic('form-controller'), counted);
const trayFiles = piece(dynamic('uploads'), counted);
const gifFiles = piece(dynamic('gif-picker'), counted);
const reportFiles = piece(dynamic('report'), counted);
// An Assets field's file tray is a piece of its own, loaded only by a form that has one.
// Gate 2's 26 KB is for the first download; the loader's own budget keeps it as small as it was built.
const pieces = [
    ['The first download, the startup loader (gate 2: at most 26 KB)', first, 4 * 1024],
    // SD-47 raised the renderer from 34 KB to 36 KB after the stage F and G reviews, and SD-55 took it
    // back to 34 KB when Protect and the report dialog moved out of it.
    ['The renderer, when a thread nears view', rendererFiles, 34 * 1024],
    // Raised from 10 KB to 12 KB for `commentForm()`, a public entry built on the same form code, and the
    // GIF control GIPHY's return added to the form (SD-54). The picker itself is a chunk of its own.
    // Raised to 14 KB when Protect, which only someone posting needs, moved here from the renderer (SD-55),
    // then to 15 KB when the spam rules, providers and GIF control took it to 14.45 KB (SD-85).
    ['The comment forms, when someone heads for one', formFiles, 15 * 1024],
    ['The file tray, for a form with an Assets field', trayFiles, 4 * 1024],
    // GIPHY's search, loaded only when a site has GIFs on and someone opens the picker (SD-52).
    ['The GIF picker, when someone opens it', gifFiles, 3 * 1024],
    // Loaded when someone who can report opens a comment's menu, or reports (SD-55).
    ['The report dialog, when someone heads for it', reportFiles, 1.5 * 1024],
];
const unbudgeted = Object.values(manifest).map((chunk) => chunk.file).filter((file) => file.endsWith('.js') && !counted.has(file));

if (unbudgeted.length) failures.push(`Script without a budget: ${unbudgeted.join(', ')}`);

// Craft serves the entry as `comments.js?v=…`, so a chunk importing it back would run the loader a
// second time under another URL. No chunk may import it, statically or not.
const entryFile = manifest[entry].file;
const importsEntry = Object.entries(manifest).filter(([key, chunk]) => key !== entry && ([...(chunk.imports ?? []), ...(chunk.dynamicImports ?? [])].includes(entry)
    || new RegExp(`["'\`](\\.\\./)+${entryFile.split('/').pop().replace('.', '\\.')}["'\`]`).test(readFileSync(join(asset, chunk.file), 'utf8'))));

if (importsEntry.length) failures.push(`Chunks import the entry: ${importsEntry.map(([, chunk]) => chunk.file).join(', ')}`);

console.log(`${importsEntry.length ? 'FAIL' : 'PASS'}: No chunk imports the entry (${entryFile}), which Craft serves with a cache-busting query`);

// The build must be the one the sources make, or these figures are about something else.
const newest = (dir) => readdirSync(dir, { withFileTypes: true }).reduce((latest, item) => Math.max(latest, item.isDirectory() ? newest(join(dir, item.name)) : statSync(join(dir, item.name)).mtimeMs), 0);
const stale = newest(join(root, 'packages/comments-browser/src')) > statSync(join(asset, entryFile)).mtimeMs;

if (stale) failures.push('The asset is older than the renderer\'s sources. Run npm run build:assets.');

console.log(`${stale ? 'FAIL' : 'PASS'}: The asset is built from the current sources`);

for (const [label, files, budget] of pieces) {
    const size = gzipped(files);
    const line = `${label} (${files.join(', ')}): ${kb(size)} gzipped, budget ${kb(budget)}`;

    if (size > budget) failures.push(line);

    console.log(`${size > budget ? 'FAIL' : 'PASS'}: ${line}`);
}

// The plan hoped a page with a thread in view would stay under 26 KB too; reported, not enforced.
const inView = gzipped([...first, ...rendererFiles]);
console.log(`INFO: A page with a thread, after it's in view (loader and renderer, from the built files): ${kb(inView)} gzipped${inView > 26 * 1024 ? ', over the 26 KB the plan hoped for' : ''}; with the forms, ${kb(inView + gzipped(formFiles))}`);

const stylesheet = readFileSync(join(asset, 'css/comments.css'));
const css = gzipSync(stylesheet, { level: 9 }).length;

if (css > 9 * 1024) failures.push(`The stylesheet: ${kb(css)}`);

console.log(`${css > 9 * 1024 ? 'FAIL' : 'PASS'}: The stylesheet (css/comments.css): ${kb(css)} gzipped, budget ${kb(9 * 1024)}`);

const everything = [...counted].map((file) => readFileSync(join(asset, file), 'utf8')).join('\n');

if (/prosemirror|tiptap/i.test(everything)) {
    failures.push('Editor is in the comments script.');
}

console.log(`${/prosemirror|tiptap/i.test(everything) ? 'FAIL' : 'PASS'}: Editor isn't in any of the comments script's chunks`);

assert.deepEqual(failures, [], 'Something is over its gate 2 budget.');
