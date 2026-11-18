#!/usr/bin/env node
/**
 * Checks the package docs' prose against Verbb's writing rules that a script can check: no em
 * dashes, no semicolons, no colon joining two statements (a colon may only end a line, introducing
 * a list, table or code), no hard-wrapped paragraphs, Australian rather than US spellings, Title
 * Case headings, and no raw HTML tags in prose, which Vue would try to render. Code, links' URLs
 * and HTML comments are left out. The generated tables are checked too, since their descriptions
 * are prose.
 *
 * It checks the package docs (`packages/docs/`) and the plugin docs (`docs/`, user guides included),
 * with the exceptions the documentation structure standard allows: the Configuration page's fixed
 * template line, the `**Type:** … · **Default:** …` metadata lines in `::: reference` entries, and quoted
 * interface text (in bold or in quotation marks), which keeps Craft's US spelling and punctuation.
 * `--packageDocs=<dir>` and `--pluginDocs=<dir>` check other copies.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { DOCS, ROOT } from './docs-reference.mjs';

const US_SPELLINGS = [
    /\bcolou?r(?<!colour)(s|ed|ing)?\b/i,
    /\bbehavior/i, /\bfavorite/i, /\bcenter(s|ed)?\b/i, /\bgray\b/i, /\bcatalog\b/i,
    /\b(customiz|organiz|recogniz|initializ|analyz|normaliz|serializ|prioritiz|optimiz|summariz|utiliz|minimiz|maximiz|categoriz|authoriz|synchroniz|memoriz|emphasiz|apologiz|realiz|finaliz|sanitiz|personaliz|standardiz|localiz|stabiliz)/i,
    /\b(canceled|canceling|labeled|labeling|modeling|modeled|traveled|traveling|cancelable)\b/i,
];

const LOWERCASE = new Set(['a', 'an', 'and', 'at', 'for', 'from', 'into', 'nor', 'of', 'or', 'the', 'to', 'vs', 'with']);
const MINOR = new Set(['a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'into', 'nor', 'of', 'on', 'or', 'per', 'the', 'to', 'via', 'vs', 'with']);

const option = (name) => process.argv.slice(2).find((argument) => argument.startsWith(`--${name}=`))?.slice(name.length + 3);
const SITES = [
    { name: 'package docs', root: option('packageDocs') ?? DOCS, prefix: 'packages/docs/' },
    { name: 'plugin docs', root: option('pluginDocs') ?? join(ROOT, 'docs'), prefix: 'docs/' },
];

// The structure standard's fixed opening for the Configuration page, which joins two statements with a colon.
const TEMPLATE_LINES = [/^You can customise .+ settings using a PHP configuration file\. This is optional: each setting has a default, so you only need to include the values you want to change\.$/];

function pages(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return ['node_modules', 'public', '_data'].includes(entry.name) || (entry.name === '.vitepress') ? [] : pages(path);
        }

        return entry.name.endsWith('.md') && entry.name !== 'README.md' ? [path] : [];
    });
}

/** A line of prose without inline code, link URLs, HTML comments or bold interface labels' colons. */
function prose(line) {
    return line
        .replace(/<!--.*?-->/g, '')
        .replace(/`[^`]*`/g, 'CODE')
        .replace(/\]\([^)]*\)/g, ']')
        .replace(/https?:\/\/\S+/g, 'URL')
        // Quoted interface text keeps Craft's own spelling and punctuation.
        .replace(/\*\*[^*]+\*\*/g, 'LABEL')
        .replace(/“[^”]*”/g, 'QUOTE')
        // Craft's own field type names are interface text too.
        .replace(/\bColor fields?\b/g, 'LABEL')
        .replace(/(^|[\s(])"[^"]*"/g, '$1QUOTE');
}

const failures = [];

for (const site of SITES) for (const file of pages(site.root)) {
    const name = `${site.prefix}${relative(site.root, file)}`;
    const lines = readFileSync(file, 'utf8').split('\n');
    let fence = false;
    let previousProse = false;
    let reference = false;

    lines.forEach((raw, index) => {
        const at = `${name}:${index + 1}`;

        if (/^\s*(```|~~~)/.test(raw)) {
            fence = !fence;
            previousProse = false;
            return;
        }

        if (fence) return;

        if (/^\s*:::\s*reference/.test(raw)) reference = true;
        else if (/^\s*:::\s*$/.test(raw)) reference = false;

        if (TEMPLATE_LINES.some((pattern) => pattern.test(raw.trim()))) {
            previousProse = true;
            return;
        }

        // A reference entry's metadata line, such as `**Type:** \`string\` · **Default:** \`''\``.
        const metadata = reference && /^\*\*[A-Z][\w ]*:\*\*/.test(raw.trim());
        const line = metadata ? '' : prose(raw);
        const trimmed = line.trim();
        const isTable = trimmed.startsWith('|');
        const isHeading = /^#{1,6}\s/.test(trimmed);
        const isList = /^([-*+]|\d+\.)\s/.test(trimmed);
        const isBlock = isTable || isHeading || trimmed === '' || trimmed.startsWith('<!--') || trimmed.startsWith(':::') || trimmed.startsWith('>');

        if (raw.includes('—')) failures.push(`${at} has an em dash.`);
        if (/;/.test(line)) failures.push(`${at} has a semicolon in prose.`);

        // A colon may end a line (introducing a list, a table or code), or sit in a bold label or a time.
        const colons = line.replace(/\d:\d/g, '');
        // A colon may also introduce a list within the sentence, such as "load: CODE, CODE or CODE.", so
        // what follows it up to the end of the sentence has to read as a series to pass.
        const joins = [...colons.replace(/:\s*$/, '').matchAll(/:\s+(\S[^]*?)(?:[.!?](?:\s|$)|$)/g)].filter((match) => !(match[1].includes(',') && /\b(and|or)\b/.test(match[1])));
        if (!trimmed.startsWith(':::') && joins.length) failures.push(`${at} joins with a colon: ${trimmed.slice(0, 90)}`);

        for (const pattern of US_SPELLINGS) {
            const match = pattern.exec(line);
            if (match) failures.push(`${at} uses the US spelling "${match[0]}".`);
        }

        // A custom element or component name outside code, such as <verbb-comments> or <CommentsThread>,
        // would be rendered rather than shown. Ordinary HTML, such as an anchor's <span id>, is fine.
        if (/<\/?([A-Z][\w]*|[a-z][\w]*-[\w-]*)[\s>/]/.test(line.replace(/<!--.*?-->/g, ''))) failures.push(`${at} has a custom element or component tag outside code, which would be rendered: ${trimmed.slice(0, 90)}`);

        if (isHeading) {
            const text = trimmed.replace(/^#+\s+/, '').replace(/\{#[\w-]+\}/, '').replace(/CODE/g, 'Code').trim();
            const words = text.split(/\s+/).filter((word) => /^[A-Za-z]/.test(word));

            words.forEach((word, position) => {
                const bare = word.replace(/[^A-Za-z'’-]/g, '');
                const parts = bare.split('-');

                // Inside a hyphenated compound, such as "Step-by-Step", only its outer words are edges.
                parts.forEach((part, index) => {
                    const edge = (position === 0 && index === 0) || (position === words.length - 1 && index === parts.length - 1);
                    if (part && /^[a-z]/.test(part) && (edge || !MINOR.has(part.toLowerCase()))) {
                        failures.push(`${at} heading "${text}" isn't in Title Case ("${part}").`);
                    }

                    // Articles, conjunctions and prepositions stay lowercase inside a heading. "In" and "On"
                    // are left out, because they're often part of a verb, as in "Signing In".
                    if (!edge && /^[A-Z]/.test(part) && LOWERCASE.has(part.toLowerCase()) && words.length > 1) {
                        failures.push(`${at} heading "${text}" capitalises "${part}", which stays lowercase.`);
                    }
                });
            });
        }

        // A prose line straight after another prose line is a hard-wrapped paragraph.
        const isProse = !isBlock && !isList && !trimmed.startsWith('<');
        if (isProse && previousProse) failures.push(`${at} looks hard-wrapped, continuing the line before it.`);
        previousProse = isProse;
    });
}

if (failures.length) {
    console.error(`The docs' prose breaks the writing rules:\n\n${failures.map((failure) => `- ${failure}`).join('\n')}`);
    process.exit(1);
}

console.log('The package docs and plugin docs pass the writing checks.');
