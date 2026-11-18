#!/usr/bin/env node
/**
 * Checks every link in both docs sites, and the links between them.
 *
 * - Package docs (`packages/docs/`): a link within them, or to `https://docs.verbb.io/comments/<path>`,
 *   must reach a page and, with `#anchor`, one of its headings, slugged as VitePress does.
 * - Plugin docs (`docs/`, user guides included): a `docs:<path>#anchor` link, a `#anchor` on the same
 *   page and a link from either site to `https://verbb.io/craft-plugins/comments/docs/v4/<path>#anchor`
 *   must reach `docs/<path>.md` and one of its headings, slugged as verbb.io does with Craft Anchors
 *   (see `craftAnchor()`). Images must exist.
 *
 * A link to a plugin docs page that doesn't exist fails. `--allow-missing` lists those separately
 * instead, for while pages are still being written. Other external links are counted, not fetched.
 * `--packageDocs=<dir>` and `--pluginDocs=<dir>` check other copies of the two sites.
 */
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join, normalize, relative } from 'node:path';
import { DOCS, ROOT } from './docs-reference.mjs';

const option = (name) => process.argv.slice(2).find((argument) => argument.startsWith(`--${name}=`))?.slice(name.length + 3);
const PACKAGE_DOCS = option('packageDocs') ?? DOCS;
const PLUGIN_DOCS = option('pluginDocs') ?? join(ROOT, 'docs');
const PACKAGE_URL = 'https://docs.verbb.io/comments';
const PLUGIN_URL = 'https://verbb.io/craft-plugins/comments/docs/v4';
const BASE = '/comments';
const allowMissing = process.argv.includes('--allow-missing');

// VitePress's own slugify, so anchors match the headings it renders.
const slugify = (text) => text.normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[\u0000-\u001f]/g, '')
    .replace(/[\s~`!@#$%^&*()\-_+=[\]{}|\\;:"'“”‘’<>,.?/]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/^(\d)/, '_$1')
    .toLowerCase();

/**
 * The anchor verbb.io gives a plugin docs heading, as Craft Anchors' `generateAnchorName()` makes it
 * (`craftcms/anchors`' Parser, called by verbb.io's `anchorsAlt` filter on h1 to h4): HTML and anything
 * in parentheses removed, apostrophes and quotes dropped, then each run of letters and numbers joined
 * with hyphens. A word that's all capitals is lowercased, and any other word only loses its first capital,
 * so "Guest's Address" is `guests-address` and `outputDefaultJs` stays `outputDefaultJs`.
 */
const craftAnchor = (text) => (text
    .replace(/<[^>]*>/g, '')
    .replace(/\(.*?\)/g, '')
    .replace(/['"‘’“”]/g, '')
    .replace(/\u00a0/g, ' ')
    .match(/[\p{L}\p{N}]+/gu) ?? [])
    .map((word) => (word.toUpperCase() === word ? word.toLowerCase() : word.charAt(0).toLowerCase() + word.slice(1)))
    .join('-')
    .normalize('NFKD')
    .replace(/[\u0300-\u036F]/g, '');

function markdownFiles(directory, skip = []) {
    if (!existsSync(directory)) return [];

    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return ['node_modules', '.vitepress', 'public', ...skip].includes(entry.name) ? [] : markdownFiles(path, skip);
        }

        return entry.name.endsWith('.md') ? [path] : [];
    });
}

// Includes that don't resolve. VitePress leaves a missing file's comment in place and, worse, includes a
// whole page for a region it can't find, so both fail the check.
const includeFailures = [];

/**
 * A page's Markdown with its includes expanded, as VitePress builds it, including a region of a page,
 * `<!--@include: ../browser/dark-mode.md#dark-theme-effects-->`, between its `<!-- #region name -->` and
 * `<!-- #endregion name -->` lines.
 */
function expanded(file, region = null, within = []) {
    const key = `${file}#${region ?? ''}`;

    if (within.includes(key)) return '';

    let source = readFileSync(file, 'utf8');

    if (region) {
        const lines = source.split('\n');
        const start = lines.indexOf(`<!-- #region ${region} -->`);
        const end = lines.indexOf(`<!-- #endregion ${region} -->`, start + 1);

        if (start < 0 || end < 0) {
            includeFailures.push(`${relative(ROOT, file)} has no region "${region}".`);
            return '';
        }

        source = lines.slice(start + 1, end).join('\n');
    }

    return source.replace(/<!--\s*@include:\s*(.+?)\s*-->/g, (_, include) => {
        const [path, name = null] = include.trim().split('#');
        const target = join(dirname(file), path);

        if (!existsSync(target)) {
            includeFailures.push(`${relative(ROOT, file)} includes ${include.trim()}, which doesn't exist.`);
            return '';
        }

        return expanded(target, name, [...within, key]);
    });
}

/** The text outside fenced code blocks. */
function withoutFences(markdown) {
    return markdown.replace(/^(```|~~~)[\s\S]*?^\1/gm, '');
}

/** The plain text of a heading's Markdown, as it is once rendered and its tags taken away. */
function headingText(text) {
    return text
        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/`([^`]*)`/g, '$1')
        .replace(/\*\*|__|\*|_(?=\w)|(?<=\w)_/g, '')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .trim();
}

/**
 * Every anchor a page's headings give. `vitepress` slugs every level with VitePress's slugify and
 * honours `{#id}`. `craft` slugs h1 to h4 as verbb.io does, where `{#id}` isn't supported.
 * Repeats are numbered as each does it: `-1` for the second.
 */
function anchorsOf(markdown, style = 'vitepress') {
    const anchors = new Set();
    const counts = new Map();

    for (const match of withoutFences(markdown).matchAll(/^(#{1,6})\s+(.+?)\s*$/gm)) {
        let text = match[2];
        const custom = /\{#([\w-]+)\}\s*$/.exec(text);

        if (style === 'craft' && match[1].length > 4) continue;

        if (custom && style === 'vitepress') {
            anchors.add(custom[1]);
            continue;
        }

        text = headingText(text);
        const slug = style === 'craft' ? craftAnchor(text) : slugify(text);
        const count = counts.get(slug) ?? 0;
        counts.set(slug, count + 1);
        anchors.add(count ? `${slug}-${count}` : slug);
    }

    for (const match of markdown.matchAll(/<a\s+(?:id|name)="([^"]+)"/g)) anchors.add(match[1]);

    return anchors;
}

/** Every link in a page's Markdown, outside code. */
function linksOf(markdown) {
    const text = withoutFences(markdown).replace(/`[^`\n]*`/g, '');
    const links = [];

    for (const match of text.matchAll(/\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/g)) links.push(match[1]);
    for (const match of text.matchAll(/^\s*\[[^\]]+\]:\s*(\S+)/gm)) links.push(match[1]);
    for (const match of text.matchAll(/<a\s[^>]*href="([^"]+)"/g)) links.push(match[1]);

    return links;
}

const packagePages = new Map(markdownFiles(PACKAGE_DOCS, ['_generated', '_data']).filter((file) => !['OWNERSHIP.md', 'README.md'].includes(relative(PACKAGE_DOCS, file))).map((file) => [file, expanded(file)]));
const anchorCache = new Map();
const anchors = (file, source, style = 'vitepress') => {
    const key = `${style}:${file}`;
    if (!anchorCache.has(key)) anchorCache.set(key, anchorsOf(source ?? expanded(file), style));
    return anchorCache.get(key);
};

/** The package docs page a path reaches, such as `/browser/` or `/core/errors`. */
function packagePage(path) {
    const clean = decodeURI(path).replace(/^\/+/, '').replace(/\.(md|html)$/, '');
    const candidates = clean === '' ? ['index.md'] : clean.endsWith('/') ? [`${clean}index.md`] : [`${clean}.md`, `${clean}/index.md`];

    return candidates.map((candidate) => join(PACKAGE_DOCS, candidate)).find((candidate) => packagePages.has(candidate)) ?? null;
}

const failures = [];
const pending = new Map();
let external = 0;
let checked = 0;

function checkPackageLink(from, link, url) {
    const [path, anchor] = url.split('#');
    const page = path === '' ? from : packagePage(path);

    if (!page) {
        failures.push(`${relative(ROOT, from)} links to ${link}, which isn't a package docs page.`);
        return;
    }

    if (anchor && !anchors(page, packagePages.get(page)).has(anchor)) {
        failures.push(`${relative(ROOT, from)} links to ${link}, but ${relative(ROOT, page)} has no heading #${anchor}.`);
    }
}

const caseOnly = [];

/** A link to a plugin docs page, by its path under `docs/`, with or without an anchor. */
function checkPluginPath(from, link, path, anchor) {
    const clean = path.replace(/^\/+|\/+$/g, '').replace(/\.md$/, '');
    const file = join(PLUGIN_DOCS, `${clean}.md`);

    if (!existsSync(file)) {
        const list = pending.get(clean) ?? [];
        list.push(relative(ROOT, from));
        pending.set(clean, list);
        return;
    }

    if (!anchor) return;

    const known = anchors(file, readFileSync(file, 'utf8'), 'craft');

    if (known.has(anchor)) return;

    const differsInCase = [...known].find((name) => name.toLowerCase() === anchor.toLowerCase());
    const message = `${relative(ROOT, from)} links to ${link}, but verbb.io gives docs/${clean}.md no #${anchor}${differsInCase ? ` (it's #${differsInCase}, and anchors are case-sensitive)` : ''}.`;

    if (differsInCase) caseOnly.push(message);
    failures.push(message);
}

function checkPluginLink(from, link) {
    const [path, anchor] = link.slice(PLUGIN_URL.length + 1).split('#');
    checkPluginPath(from, link, path, anchor);
}

for (const [file, source] of packagePages) {
    for (const link of linksOf(source)) {
        checked++;

        if (link.startsWith(`${PLUGIN_URL}/`)) {
            checkPluginLink(file, link);
        } else if (link === PACKAGE_URL || link.startsWith(`${PACKAGE_URL}/`) || link.startsWith(`${PACKAGE_URL}#`)) {
            checkPackageLink(file, link, link.slice(PACKAGE_URL.length) || '/');
        } else if (/^(https?:|mailto:)/.test(link)) {
            external++;
        } else if (link.startsWith('#')) {
            checkPackageLink(file, link, link);
        } else if (link.startsWith('/')) {
            checkPackageLink(file, link, link.startsWith(`${BASE}/`) ? link.slice(BASE.length) : link);
        } else {
            // Relative to the page.
            const resolved = `/${normalize(join(dirname(relative(PACKAGE_DOCS, file)), link)).replace(/\\/g, '/')}`;
            checkPackageLink(file, link, resolved);
        }
    }
}

// The navigation and sidebar in the VitePress config.
const config = join(PACKAGE_DOCS, '.vitepress/config.mts');
for (const match of readFileSync(config, 'utf8').matchAll(/link:\s*[`']([^`']+)[`']/g)) {
    const link = match[1];
    checked++;

    if (link.startsWith('${pluginDocs}/')) {
        checkPluginLink(config, `${PLUGIN_URL}/${link.slice('${pluginDocs}/'.length)}`);
    } else if (link.startsWith('/')) {
        checkPackageLink(config, link, link);
    } else {
        external++;
    }
}

// The plugin docs: their own `docs:` links and anchors, images, links into the package docs, and links
// to the plugin docs' own published addresses.
let fromPlugin = 0;
let inPlugin = 0;
for (const file of markdownFiles(PLUGIN_DOCS)) {
    const source = readFileSync(file, 'utf8');
    const page = relative(PLUGIN_DOCS, file).replace(/\.md$/, '');

    for (const link of linksOf(source)) {
        if (link === PACKAGE_URL || link.startsWith(`${PACKAGE_URL}/`)) {
            fromPlugin++;
            checkPackageLink(file, link, link.slice(PACKAGE_URL.length) || '/');
        } else if (link.startsWith(`${PLUGIN_URL}/`)) {
            inPlugin++;
            checkPluginLink(file, link);
        } else if (link.startsWith('docs:')) {
            inPlugin++;
            const [path, anchor] = link.slice('docs:'.length).split('#');
            checkPluginPath(file, link, path, anchor);
        } else if (link.startsWith('#')) {
            inPlugin++;
            checkPluginPath(file, link, page, link.slice(1));
        } else if (/^(https?:|mailto:)/.test(link)) {
            external++;
        } else {
            inPlugin++;
            const [path] = link.split(/[?#]/);
            const target = join(dirname(file), decodeURI(path));

            if (!existsSync(target)) {
                // A relative link to another page, as verbb.io resolves it, or a file such as a screenshot.
                const asPage = relative(PLUGIN_DOCS, target).replace(/\.md$/, '');
                if (!existsSync(join(PLUGIN_DOCS, `${asPage}.md`))) failures.push(`${relative(ROOT, file)} links to ${link}, which doesn't exist.`);
            }
        }
    }
}

if (pending.size) {
    console.log(`Links to plugin docs pages that don't exist${allowMissing ? ' (allowed by --allow-missing)' : ''}:\n`);
    for (const [path, from] of pending) console.log(`- docs/${path}.md, from ${[...new Set(from)].join(', ')}`);
    console.log('');

    if (!allowMissing) {
        for (const path of pending.keys()) failures.push(`docs/${path}.md doesn't exist.`);
    }
}

failures.push(...new Set(includeFailures));

if (failures.length) {
    if (caseOnly.length) {
        console.error(`${caseOnly.length} of the dead links below differ from verbb.io's anchor only in case. Craft Anchors keeps a heading's inner capitals, such as #outputDefaultJs, where VitePress's preview lowercases everything.\n`);
    }

    console.error(`Dead links:\n\n${failures.map((failure) => `- ${failure}`).join('\n')}`);
    process.exit(1);
}

console.log(`Links checked: ${checked} in the package docs, ${inPlugin} within the plugin docs and ${fromPlugin} from the plugin docs into the package docs, all reachable. ${external} other external links weren't fetched.`);
