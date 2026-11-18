#!/usr/bin/env node
/**
 * Fails when the package docs' generated reference has fallen behind the code: a class, control,
 * part, CSS custom property, event, error code or label in the code that the committed output in
 * `packages/docs/_generated/` doesn't have, or a class the committed output marks stable that the
 * code no longer has as stable. It also fails when the plugin docs' own tables of CSS custom
 * properties, translatable labels and the server's error codes miss one the code has. Run
 * `npm run docs:reference` to bring the generated output up to date, and
 * take a stable class out of `markup/classes.json` only in a major release.
 *
 * Inputs can be replaced as for `docs-reference.mjs`, such as `--classes=/tmp/classes.json`, and
 * `--generated=<dir>` checks another copy of the output, and `--pluginDocs=<dir>` another copy of the
 * plugin docs.
 */
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { DOCS, extractReference, GENERATED, inputOverrides, MARKUP_SECTIONS, renderReference } from './docs-reference.mjs';

const generatedArgument = process.argv.slice(2).find((argument) => argument.startsWith('--generated='));
const generated = generatedArgument ? generatedArgument.slice('--generated='.length) : GENERATED;
const failures = [];
const fail = (message) => failures.push(message);

const committedFile = join(generated, 'reference.json');

if (!existsSync(committedFile)) {
    console.error(`No generated reference at ${committedFile}. Run npm run docs:reference.`);
    process.exit(1);
}

const committed = JSON.parse(readFileSync(committedFile, 'utf8'));
const current = extractReference(inputOverrides());
const read = (name) => (existsSync(join(generated, name)) ? readFileSync(join(generated, name), 'utf8') : '');
const missing = (kind, names, have) => {
    for (const name of names) {
        if (!have.has(name)) fail(`${kind} ${name} is in the code but not in the generated docs.`);
    }
};

// Classes and attributes.
const committedClasses = new Set([...Object.keys(committed.classes.stable), ...Object.keys(committed.classes.internal)]);
missing('Class', [...Object.keys(current.classes.stable), ...Object.keys(current.classes.internal)], committedClasses);
missing('Class', [...current.classes.lists.stable, ...current.classes.lists.internal], committedClasses);
missing('Attribute', [...Object.keys(current.classes.attributes.public), ...Object.keys(current.classes.attributes.internal)], new Set([...Object.keys(committed.classes.attributes.public), ...Object.keys(committed.classes.attributes.internal)]));

const stableNow = new Set([...Object.keys(current.classes.stable), ...current.classes.lists.stable]);
for (const name of Object.keys(committed.classes.stable)) {
    if (!stableNow.has(name)) fail(`Stable class ${name} is in the generated docs but no longer stable in the code. Removing a stable class is a major release.`);
}

for (const name of Object.keys(current.classes.stable)) {
    if (current.classes.stable[name] === 'not drawn') fail(`Stable class ${name} is listed in classes.json but the renderer doesn't draw it.`);
}

// The class lists and classes.json are kept in step by the browser package's own test, but a
// difference here means the docs are reading a stale file.
for (const name of current.classes.lists.stable) {
    if (!(name in current.classes.stable)) fail(`Stable class ${name} is in classes.ts but not in markup/classes.json. Run npm run classes -w @verbb/comments-browser.`);
}

// Controls, parts, tokens, events, errors and labels.
missing('Control', current.controls.map((control) => control.name), new Set(committed.controls.map((control) => control.name)));
missing('Part', [...current.parts.names, ...current.parts.core], new Set(committed.parts.names));
missing('CSS custom property', current.tokens.public.map((token) => token.name), new Set(committed.tokens.public.map((token) => token.name)));
missing('Event', current.events.events.map((event) => event.name), new Set(committed.events.events.map((event) => event.name)));
missing('Error code', current.errors.map((error) => error.code), new Set(committed.errors.map((error) => error.code)));
missing('Label', current.labels.map((label) => label.key), new Set(committed.labels.map((label) => label.key)));

for (const event of current.events.events) {
    const before = committed.events.events.find((item) => item.name === event.name);

    if (!before) continue;

    const keys = (list) => (list ?? []).map((key) => key.name).join(',');
    if (keys(event.browser) !== keys(before.browser) || keys(event.store) !== keys(before.store) || event.cancelable !== before.cancelable) {
        fail(`Event ${event.name}'s detail keys or cancelable flag changed in the code.`);
    }
}

const partsInCore = new Set(current.parts.core);
for (const name of current.parts.names) {
    if (!partsInCore.has(name)) fail(`Part ${name} is in the browser package's PART_NAMES but not core's PartName type.`);
}

// Descriptions the generated tables need from reference-notes.json.
for (const control of current.controls) if (!current.notes.controls?.[control.name]) fail(`Control ${control.name} has no description in packages/docs/_data/reference-notes.json.`);
for (const name of current.parts.names) if (!current.notes.parts?.[name]) fail(`Part ${name} has no description in packages/docs/_data/reference-notes.json.`);
for (const event of current.events.events) if (!current.notes.eventGroups?.[event.name]) fail(`Event ${event.name} has no group in packages/docs/_data/reference-notes.json.`);
for (const error of current.errors) if (!current.notes.errors?.[error.code]) fail(`Error code ${error.code} has no description in packages/docs/_data/reference-notes.json.`);

// The Errors page explains every category by hand, so a new category needs a row there.
const errorsPage = readFileSync(join(DOCS, 'core/errors.md'), 'utf8');
for (const category of new Set(current.errors.map((error) => error.category))) {
    if (!errorsPage.includes(`| \`${category}\` |`)) fail(`Error category ${category} isn't explained in packages/docs/core/errors.md.`);
}

// Three plugin docs pages keep their own tables, with explanations the generated ones don't have, so
// they're held to the same lists: every public CSS custom property on Theming, every label Craft can
// translate on Text & Translations, and every error code on the Data API page. Their values are held
// to the code too, and a row for something the code no longer has fails.
const pluginDocsArgument = process.argv.slice(2).find((argument) => argument.startsWith('--pluginDocs='));
const pluginDocs = pluginDocsArgument ? pluginDocsArgument.slice('--pluginDocs='.length) : join(DOCS, '../../docs');
const handKept = [
    ['template-guides/theming.md', 'CSS custom property', current.tokens.public.map((token) => token.name)],
    ['template-guides/text-translations.md', 'Translatable label', current.labels.filter((label) => label.translatable).map((label) => label.key)],
    // Codes with no HTTP status are raised by core in the browser, never by the server, so the Data API page leaves them out.
    ['headless/data-api.md', 'Error code', current.errors.filter((error) => error.status).map((error) => error.code)],
];
const pluginPage = (page) => (existsSync(join(pluginDocs, page)) ? readFileSync(join(pluginDocs, page), 'utf8') : '');

for (const [page, kind, names] of handKept) {
    const source = pluginPage(page);

    for (const name of names) {
        if (!source.includes(`\`${name}\``)) fail(`${kind} ${name} is in the code but not in the plugin docs' ${page}.`);
    }
}

/** The body rows of every Markdown table whose header's first cells are `head`, as plain cells. */
function tableRows(source, head) {
    const rows = [];
    const lines = source.split('\n');
    const cells = (line) => line.trim().replace(/^\||\|$/g, '').split(/(?<!\\)\|/).map((cell) => cell.trim().replace(/\\\|/g, '|'));

    for (let index = 0; index < lines.length; index++) {
        const header = lines[index].trim().startsWith('|') ? cells(lines[index]) : null;

        if (!header || head.some((name, position) => header[position] !== name)) continue;

        for (index += 2; index < lines.length && lines[index].trim().startsWith('|'); index++) rows.push(cells(lines[index]));
    }

    return rows;
}

const plain = (cell) => (cell ?? '').replace(/`/g, '').trim();

// Theming: each property's light and dark values. A theme that doesn't set a property keeps the light value.
const tokens = new Map(current.tokens.public.map((token) => [token.name, token]));
const handKeptRows = {
    theming: tableRows(pluginPage('template-guides/theming.md'), ['Property', 'Light', 'Dark']),
    labels: tableRows(pluginPage('template-guides/text-translations.md'), ['Name', 'English Text']),
    errors: tableRows(pluginPage('headless/data-api.md'), ['Code', 'Category', 'Status']),
};

if (!handKeptRows.theming.length || !handKeptRows.labels.length || !handKeptRows.errors.length) fail('A plugin docs table held to the code (Theming, Text & Translations or Data API) has no rows, or its header changed.');

for (const [name, light, dark] of handKeptRows.theming) {
    const token = tokens.get(plain(name));

    if (!token) {
        fail(`template-guides/theming.md lists ${plain(name)}, which the stylesheets no longer have.`);
        continue;
    }

    const expectedLight = token.light ?? 'Not set';
    const expectedDark = token.dark ?? token.light ?? 'Not set';

    if (plain(light) !== expectedLight) fail(`template-guides/theming.md gives ${token.name} a light value of ${plain(light)}, but the code has ${expectedLight}.`);
    if (plain(dark) !== expectedDark) fail(`template-guides/theming.md gives ${token.name} a dark value of ${plain(dark)}, but the code has ${expectedDark}.`);
}

// Text & Translations: each label's English text, which is also its translation key.
const translatable = new Map(current.labels.filter((label) => label.translatable).map((label) => [label.key, label]));
for (const [name, text] of handKeptRows.labels) {
    const label = translatable.get(plain(name));

    if (!label) {
        fail(`template-guides/text-translations.md lists the label ${plain(name)}, which Api::LABELS no longer has.`);
    } else if (text !== label.text) {
        fail(`template-guides/text-translations.md gives ${label.key} the text "${text}", but Api::LABELS has "${label.text}".`);
    }
}

// Data API: each error code's category and HTTP status.
const codes = new Map(current.errors.map((error) => [error.code, error]));
for (const [code, category, status] of handKeptRows.errors) {
    const error = codes.get(plain(code));

    if (!error) {
        fail(`headless/data-api.md lists the error code ${plain(code)}, which COMMENTS_ERROR_CODES no longer has.`);
        continue;
    }

    if (plain(category) !== error.category) fail(`headless/data-api.md gives ${error.code} the category ${plain(category)}, but the code has ${error.category}.`);
    if (plain(status) !== String(error.status)) fail(`headless/data-api.md gives ${error.code} the status ${plain(status)}, but the code has ${error.status}.`);
}

// The generated Markdown matches what the code would generate now, and each page includes its files.
const expected = renderReference(current);
for (const [name, contents] of Object.entries(expected)) {
    if (name === 'reference.json') continue;

    if (read(name) !== contents) fail(`_generated/${name} is out of date. Run npm run docs:reference.`);
}

const includes = {
    'browser/markup-reference.md': [...MARKUP_SECTIONS.map((section) => `markup-${section}.md`), 'markup-attributes.md', 'tokens.md'],
    'browser/theme-config.md': ['theme-config-controls.md'],
    'browser/part-overrides.md': ['parts.md'],
    'browser/browser-events.md': ['events.md', 'events-shared.md'],
    'browser/configuring-threads.md': ['labels.md'],
    'core/errors.md': ['errors.md'],
    'react/events.md': ['event-props-react.md'],
    'vue/events.md': ['event-props-vue.md'],
};

for (const [page, files] of Object.entries(includes)) {
    const source = existsSync(join(DOCS, page)) ? readFileSync(join(DOCS, page), 'utf8') : '';

    for (const file of files) {
        if (!source.includes(`<!--@include: ../_generated/${file}-->`)) fail(`${page} doesn't include _generated/${file}.`);
    }
}

if (failures.length) {
    console.error(`The package docs' reference is behind the code:\n\n${failures.map((failure) => `- ${failure}`).join('\n')}`);
    process.exit(1);
}

console.log(`Reference up to date: ${Object.keys(current.classes.stable).length} stable and ${Object.keys(current.classes.internal).length} internal classes, ${current.controls.length} controls, ${current.parts.names.length} parts, ${current.tokens.public.length} CSS custom properties, ${current.events.events.length} events, ${current.errors.length} error codes and ${current.labels.length} labels. The plugin docs' tables match it: ${handKeptRows.theming.length} properties on Theming, ${handKeptRows.labels.length} labels on Text & Translations and ${handKeptRows.errors.length} error codes on Data API.`);
