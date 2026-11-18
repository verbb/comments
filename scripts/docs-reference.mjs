#!/usr/bin/env node
/**
 * Generates the package docs' reference tables from the code, so they can't drift from it: the
 * stable and internal class names and `data-comments-*` attributes, the `themeConfig` controls, the
 * overridable parts, the CSS custom properties, the browser events with their detail keys, the error
 * codes and the default labels. Each table is written to `packages/docs/_generated/`, which the
 * pages include, with `reference.json` holding everything read, for `docs-check-reference.mjs`.
 *
 * Descriptions the code doesn't hold (what a control covers, what a part draws, what an error means,
 * which group an event belongs to) come from `packages/docs/_data/reference-notes.json`.
 *
 *   node scripts/docs-reference.mjs            Writes the generated files.
 *   node scripts/docs-reference.mjs --stdout   Prints reference.json instead.
 *
 * Any input can be replaced with `--<input>=<path>`, such as `--classes=/tmp/classes.json`, which the
 * check uses to prove it notices a change without touching the source. The inputs are listed in
 * `INPUTS` below.
 */
import { mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
export const DOCS = join(ROOT, 'packages/docs');
export const GENERATED = join(DOCS, '_generated');

const browser = 'packages/comments-browser';
const core = 'packages/comments-core';

/** Every file the reference is read from, relative to the plugin root. */
export const INPUTS = {
    classes: `${browser}/markup/classes.json`,
    classLists: `${browser}/src/classes.ts`,
    themeConfig: `${browser}/src/theme-config.ts`,
    view: `${browser}/src/view.ts`,
    corePartNames: `${core}/src/theme.ts`,
    css: `${browser}/src/css`,
    browserEvents: `${browser}/src/events.ts`,
    browserSource: `${browser}/src`,
    eventNames: `${core}/src/events.ts`,
    storeEvents: `${core}/src/store.ts`,
    errors: `${core}/src/errors.ts`,
    fallbackLabels: `${browser}/src/labels.ts`,
    phpLabels: 'src/services/Api.php',
    notes: 'packages/docs/_data/reference-notes.json',
};

/** Reads `--name=path` overrides from the command line. */
export function inputOverrides(argv = process.argv.slice(2)) {
    const overrides = {};

    for (const argument of argv) {
        const match = /^--([a-zA-Z]+)=(.+)$/.exec(argument);

        if (match && match[1] in INPUTS) {
            overrides[match[1]] = match[2];
        }
    }

    return overrides;
}

function resolver(overrides) {
    return (name) => overrides[name] ?? join(ROOT, INPUTS[name]);
}

/** Removes `/* … *\/` and `// …` comments, leaving strings alone. */
function stripComments(source) {
    let result = '';
    let quote = null;

    for (let index = 0; index < source.length; index++) {
        const character = source[index];
        const next = source[index + 1];

        if (quote) {
            result += character;
            if (character === '\\') {
                result += next ?? '';
                index++;
            } else if (character === quote) {
                quote = null;
            }
            continue;
        }

        if (character === '\'' || character === '"' || character === '`') {
            quote = character;
            result += character;
        } else if (character === '/' && next === '*') {
            const end = source.indexOf('*/', index + 2);
            index = end === -1 ? source.length : end + 1;
        } else if (character === '/' && next === '/') {
            const end = source.indexOf('\n', index);
            index = end === -1 ? source.length : end - 1;
        } else {
            result += character;
        }
    }

    return result;
}

/** The text between the brace or bracket that opens at `start` and the one that closes it. */
function block(source, start) {
    const open = source[start];
    const close = { '{': '}', '[': ']', '(': ')' }[open];
    let depth = 0;

    for (let index = start; index < source.length; index++) {
        if (source[index] === open) depth++;
        if (source[index] === close && --depth === 0) return source.slice(start + 1, index);
    }

    throw new Error(`No closing ${close} after position ${start}.`);
}

function after(source, marker, file) {
    const index = source.indexOf(marker);

    if (index === -1) {
        throw new Error(`Couldn't find \`${marker}\` in ${file}. The docs generator needs updating to match the code.`);
    }

    return index + marker.length;
}

/** The strings in a `const NAME = [ … ]` array. */
function stringArray(source, name, file) {
    const start = after(source, `const ${name} = [`, file) - 1;
    return [...block(source, start).matchAll(/'([^']+)'/g)].map((match) => match[1]);
}

/** The top-level keys of a TypeScript object type's body, with whether each is optional. */
function typeKeys(body) {
    const keys = [];
    let depth = 0;

    for (let index = 0; index < body.length; index++) {
        const character = body[index];

        if ('{[(<'.includes(character)) depth++;
        else if ('}])'.includes(character) || (character === '>' && body[index - 1] !== '=')) depth--;
        else if (depth === 0) {
            const match = /^(\w+)(\?)?\s*:/.exec(body.slice(index));

            if (match && /[\s;{]/.test(body[index - 1] ?? ' ')) {
                keys.push({ name: match[1], optional: Boolean(match[2]) });
                index += match[0].length - 1;
            }
        }
    }

    return keys;
}

/** `'comments:name': { … }` entries of an interface, each with its keys. */
function eventMap(source, interfaceName, file) {
    const clean = stripComments(source);
    const body = block(clean, after(clean, `interface ${interfaceName} {`, file) - 1);
    const events = {};
    const entry = /'(comments:[a-z-]+)'\s*:\s*/g;
    let match;

    while ((match = entry.exec(body))) {
        const rest = body.slice(entry.lastIndex);

        if (rest.startsWith('Record<never, never>')) {
            events[match[1]] = [];
        } else if (rest.startsWith('{')) {
            const inner = block(body, entry.lastIndex);
            events[match[1]] = typeKeys(inner);
            entry.lastIndex += inner.length + 2;
        } else {
            throw new Error(`Couldn't read the detail of ${match[1]} in ${file}.`);
        }
    }

    return events;
}

function filesUnder(directory, extension) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);
        if (entry.isDirectory()) return filesUnder(path, extension);
        return entry.name.endsWith(extension) ? [path] : [];
    });
}

/** Walks a stylesheet, calling `visit` with each declaration and the rules and at-rules around it. */
function walkCss(source, visit) {
    const css = source.replace(/\/\*[\s\S]*?\*\//g, '');
    const stack = [];
    let buffer = '';

    for (const character of css) {
        if (character === '{') {
            stack.push(buffer.trim());
            buffer = '';
        } else if (character === '}') {
            declarations(buffer, stack, visit);
            stack.pop();
            buffer = '';
        } else if (character === ';') {
            declarations(buffer, stack, visit);
            buffer = '';
        } else {
            buffer += character;
        }
    }
}

function declarations(text, stack, visit) {
    const match = /^\s*(--[\w-]+)\s*:\s*([\s\S]+?)\s*$/.exec(text);
    if (match) visit(match[1], match[2].replace(/\s+/g, ' '), [...stack]);
}

function readTokens(path) {
    const files = filesUnder(path, '.css');
    const tokens = new Map();
    const internal = new Set();
    const used = new Map();

    const token = (name) => {
        if (!tokens.has(name)) tokens.set(name, { name, light: null, dark: null, other: [], declaredIn: null, usedWithFallback: null });
        return tokens.get(name);
    };

    for (const file of files) {
        const source = readFileSync(file, 'utf8');
        const name = relative(ROOT, file);

        walkCss(source, (property, value, stack) => {
            if (property.startsWith('--_')) {
                internal.add(property);
                return;
            }

            if (!property.startsWith('--comments-')) return;

            const context = stack.join(' ');
            const entry = token(property);
            entry.declaredIn ??= name;

            if (!stack.includes('@layer comments-tokens')) {
                entry.other.push({ context: stack.filter((item) => item.startsWith('@media')).join(' ') || stack.at(-1), value });
            } else if (/data-comments-theme='dark'/.test(context)) {
                entry.dark = value;
            } else if (/data-comments-theme='auto'/.test(context)) {
                // The auto theme's dark values match the dark theme's, which the table shows.
                entry.autoDark = value;
            } else if (stack.some((item) => item.startsWith('@media'))) {
                entry.other.push({ context: stack.filter((item) => item.startsWith('@media')).join(' '), value });
            } else {
                entry.light = value;
            }
        });

        for (const match of source.matchAll(/var\((--_?comments-[\w-]+)(?:\s*,\s*([^)]+))?\)/g)) {
            if (match[1].startsWith('--_')) {
                internal.add(match[1]);
                continue;
            }

            if (!used.has(match[1])) used.set(match[1], { file: name, fallback: match[2]?.trim() ?? null });
        }
    }

    // Properties the stylesheets read but never set, such as `--comments-reserve`, which a site sets.
    for (const [name, use] of used) {
        if (!tokens.has(name)) {
            token(name).usedWithFallback = use.fallback;
        }
    }

    for (const entry of tokens.values()) {
        entry.used = used.has(entry.name);
    }

    return {
        public: [...tokens.values()].sort((a, b) => a.name.localeCompare(b.name, 'en')).map(({ autoDark, ...entry }) => ({ ...entry, autoMatchesDark: autoDark === undefined ? null : autoDark === entry.dark })),
        internal: [...internal].sort(),
    };
}

function readLabels(resolve) {
    const phpFile = resolve('phpLabels');
    const php = readFileSync(phpFile, 'utf8');
    const start = after(php, 'public const LABELS = [', phpFile) - 1;
    const body = block(php, start);
    const translated = [...body.matchAll(/'(\w+)'\s*=>\s*'((?:[^'\\]|\\.)*)'/g)].map((match) => ({ key: match[1], text: match[2].replace(/\\'/g, '\'') }));

    const fallbackFile = resolve('fallbackLabels');
    const fallbackSource = readFileSync(fallbackFile, 'utf8');
    const fallbackBody = block(fallbackSource, after(fallbackSource, 'export const FALLBACK_LABELS = {', fallbackFile) - 1);
    const fallback = [...fallbackBody.matchAll(/^\s*(\w+):\s*'((?:[^'\\]|\\.)*)'/gm)].map((match) => ({ key: match[1], text: match[2] }));

    // Keys the renderer reads with its own English beside them, such as `labels.uploadsFailed ?? '…'`.
    const inline = new Map();
    for (const file of filesUnder(resolve('browserSource'), '.ts')) {
        for (const match of readFileSync(file, 'utf8').matchAll(/labels(?:\(\))?\??\.(\w+)\s*\?\?\s*'((?:[^'\\]|\\.)*)'/g)) {
            if (!inline.has(match[1])) inline.set(match[1], match[2]);
        }
    }

    const keys = new Map();
    for (const { key, text } of translated) keys.set(key, { key, text, translatable: true });
    for (const { key, text } of fallback) if (!keys.has(key)) keys.set(key, { key, text, translatable: false });
    for (const [key, text] of inline) if (!keys.has(key)) keys.set(key, { key, text, translatable: false });

    return [...keys.values()];
}

function readEvents(resolve) {
    const namesFile = resolve('eventNames');
    const names = stringArray(readFileSync(namesFile, 'utf8'), 'COMMENTS_EVENT_NAMES', namesFile);
    const browserFile = resolve('browserEvents');
    const browserSource = readFileSync(browserFile, 'utf8');
    const details = eventMap(browserSource, 'CommentsEventDetailMap', browserFile);
    const sharedBody = block(stripComments(browserSource), after(stripComments(browserSource), 'interface CommentsEventDetail {', browserFile) - 1);
    const shared = typeKeys(sharedBody).map((key) => key.name);
    const storeFile = resolve('storeEvents');
    const store = eventMap(readFileSync(storeFile, 'utf8'), 'ThreadStoreEventMap', storeFile);

    // Cancelable events are dispatched with `{ cancelable: true }` among the call's arguments.
    const cancelable = new Set();
    for (const file of filesUnder(resolve('browserSource'), '.ts')) {
        const source = stripComments(readFileSync(file, 'utf8'));
        for (const match of source.matchAll(/dispatch\('(comments:[a-z-]+)'/g)) {
            const call = block(source, match.index + 'dispatch'.length);
            if (/cancelable:\s*true/.test(call)) cancelable.add(match[1]);
        }
    }

    const all = [...new Set([...names, ...Object.keys(details), ...Object.keys(store)])];

    return {
        shared,
        events: all.map((name) => {
            const browserKeys = details[name] ?? null;
            const storeKeys = store[name] ?? null;
            const known = new Set((browserKeys ?? []).map((key) => key.name));

            return {
                name,
                listed: names.includes(name),
                browser: browserKeys,
                store: storeKeys,
                storeOnly: storeKeys ? storeKeys.filter((key) => !known.has(key.name)).map((key) => key.name) : [],
                cancelable: cancelable.has(name),
                reactProp: `on${name.slice('comments:'.length).split('-').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join('')}`,
                vueEvent: name.slice('comments:'.length),
            };
        }),
    };
}

/** Reads everything from the code. */
export function extractReference(overrides = {}) {
    const resolve = resolver(overrides);
    const read = (name) => readFileSync(resolve(name), 'utf8');

    const classes = JSON.parse(read('classes'));
    const classLists = read('classLists');
    const themeSource = read('themeConfig');
    const controls = stringArray(themeSource, 'CONTROL_NAMES', resolve('themeConfig'));
    const styleBody = block(themeSource, after(themeSource, 'CONTROL_STYLE_CLASSES: Record<ControlName, readonly string[]> = {', resolve('themeConfig')) - 1);
    const styleClasses = Object.fromEntries([...styleBody.matchAll(/(\w+):\s*\[([^\]]*)\]/g)].map((match) => [match[1], [...match[2].matchAll(/'([^']+)'/g)].map((item) => item[1])]));
    const allowed = /const ALLOWED = (\/.+\/);/.exec(themeSource)?.[1] ?? null;
    const parts = stringArray(read('view'), 'PART_NAMES', resolve('view'));
    const coreParts = [...(/export type PartName =([^;]+);/.exec(read('corePartNames'))?.[1] ?? '').matchAll(/'([^']+)'/g)].map((match) => match[1]);
    const errorsFile = resolve('errors');
    const errorsSource = read('errors');
    const errorBody = block(errorsSource, after(errorsSource, 'export const COMMENTS_ERROR_CODES = {', errorsFile) - 1);
    const errors = [...stripComments(errorBody).matchAll(/(\w+):\s*\{\s*category:\s*'(\w+)',\s*status:\s*(\d+)\s*\}/g)].map((match) => ({ code: match[1], category: match[2], status: Number(match[3]) }));

    return {
        classes: {
            stable: classes.stable,
            internal: classes.internal,
            attributes: classes.attributes,
            // The lists the renderer's test classifies with, to catch a class taken out of one but
            // still in the committed `classes.json`.
            lists: {
                stable: stringArray(classLists, 'STABLE_CLASSES', resolve('classLists')),
                internal: stringArray(classLists, 'INTERNAL_CLASSES', resolve('classLists')),
            },
        },
        controls: controls.map((name) => ({ name, styleClasses: styleClasses[name] ?? [] })),
        allowedAttributes: allowed,
        parts: { names: parts, core: coreParts },
        tokens: readTokens(resolve('css')),
        events: readEvents(resolve),
        errors,
        labels: readLabels(resolve),
        notes: JSON.parse(read('notes')),
    };
}

// Rendering
// =========================================================================

const SCENARIOS = {
    mountPoint: 'the mount point',
    member: 'a member’s view',
    memberEdit: 'an edit form',
    guest: 'a guest’s view',
    moderator: 'a moderator’s view',
    closed: 'a closed thread',
    empty: 'an empty thread',
    formStates: 'a form with errors',
    error: 'a thread that failed to load',
    waiting: 'a thread waiting to load',
    button: 'the Show comments state',
    fileTray: 'a file field',
};

/** `<button> in member` as `` `<button>` `` and where it's first drawn. */
function where(text) {
    const match = /^<([\w-]+)> in (\w+)$/.exec(text);

    if (match) {
        return [`\`<${match[1]}>\``, SCENARIOS[match[2]] ?? match[2]];
    }

    // Attributes the renderer doesn't draw itself, such as `on the mount point`.
    return ['', text.replace(/^on /, '')];
}

const SECTIONS = [
    ['report', (name) => name.startsWith('comments__report-')],
    ['controls', (name) => /^comments__(button(--.*)?|input|textarea|select|checkbox|radio|link)$/.test(name)],
    ['editor', (name) => /^comments__(editor.*|contextual-editor|frame.*|posting-as|guest-fields|field|field--.*|field-(label|error|custom|instructions|hint)|form-error|submit.*|cancel|textarea-fallback|ring-.*|upload.*|join.*|label)$/.test(name) || name === 'verbb-editor__button'],
    ['comment', (name) => /^comments__(comment.*|author.*|avatar.*|badge.*|permalink|date|edited|meta-item|content.*|gif|fields|field-(value.*|link|files|file.*|thumb|name|text)|actions|interactions|vote.*|score|action|menu.*|review.*|moderation-actions|placeholder|replies.*|toggle-replies)$/.test(name)],
    ['thread', () => true],
];

export const MARKUP_SECTIONS = SECTIONS.map(([name]) => name);

function sectionOf(name) {
    return SECTIONS.find(([, test]) => test(name))[0];
}

const code = (value) => `\`${value}\``;
const cell = (value) => String(value).replace(/\|/g, '\\|').replace(/\n/g, ' ');

function table(head, rows) {
    return [`| ${head.join(' | ')} |`, `| ${head.map(() => '---').join(' | ')} |`, ...rows.map((row) => `| ${row.map(cell).join(' | ')} |`)].join('\n');
}

function header(sources) {
    return `<!-- Generated by scripts/docs-reference.mjs from ${sources.join(', ')}. Run \`npm run docs:reference\` rather than editing this file. -->\n\n`;
}

function note(notes, group, name) {
    const value = notes[group]?.[name];
    return value === undefined ? null : value;
}

/** Each generated file's contents, keyed by its name in `_generated/`. */
export function renderReference(reference) {
    const files = {};
    const { notes } = reference;

    // The markup reference, one table per area of the thread.
    const classes = [
        ...Object.entries(reference.classes.stable).map(([name, seen]) => ({ name, seen, stable: true })),
        ...Object.entries(reference.classes.internal).map(([name, seen]) => ({ name, seen, stable: false })),
    ];

    for (const section of MARKUP_SECTIONS) {
        const rows = classes
            .filter((item) => sectionOf(item.name) === section)
            .sort((a, b) => Number(b.stable) - Number(a.stable) || a.name.localeCompare(b.name, 'en'))
            .map((item) => {
                const [element, scenario] = where(item.seen);
                return [code(item.name.replace(/\*$/, '<value>')), item.stable ? 'Stable' : 'Internal', element, scenario];
            });

        files[`markup-${section}.md`] = header([INPUTS.classes]) + table(['Class', 'Status', 'Element', 'First drawn in'], rows) + '\n';
    }

    const attributeRows = [
        ...Object.entries(reference.classes.attributes.public).map(([name, seen]) => [name, 'Stable', seen]),
        ...Object.entries(reference.classes.attributes.internal).map(([name, seen]) => [name, 'Internal', seen]),
    ].map(([name, status, seen]) => {
        const [element, scenario] = where(seen);
        return [code(name), status, element, scenario];
    });
    files['markup-attributes.md'] = header([INPUTS.classes]) + table(['Attribute', 'Status', 'Element', 'First drawn in'], attributeRows) + '\n';

    // CSS custom properties.
    const tokenRows = reference.tokens.public.map((token) => {
        const other = token.other.map((item) => `${item.value} under ${code(item.context.replace(/\s+/g, ' '))}`).join(', ');
        const light = token.light ?? (token.usedWithFallback ? 'Not set, so each rule’s own fallback applies' : 'Not set');
        return [code(token.name), token.light ? code(token.light) : light, token.dark ? code(token.dark) : '', other];
    });
    files['tokens.md'] = header([`${INPUTS.css}/*.css`]) + table(['Property', 'Light', 'Dark and auto', 'Elsewhere'], tokenRows) + '\n';

    // themeConfig controls.
    const controlRows = reference.controls.map((control) => [code(control.name), note(notes, 'controls', control.name) ?? '', control.styleClasses.map(code).join(', ')]);
    files['theme-config-controls.md'] = header([INPUTS.themeConfig]) + table(['Control', 'Where it appears', 'Classes `resetClass` drops'], controlRows) + '\n';

    // Overridable parts.
    const partRows = reference.parts.names.map((name) => {
        const part = note(notes, 'parts', name) ?? {};
        return [code(name), part.group ?? '', part.draws ?? '', part.context ?? ''];
    });
    files['parts.md'] = header([INPUTS.view]) + table(['Part', 'Group', 'What it draws', 'Its own context'], partRows) + '\n';

    // Browser events.
    const keys = (list) => (list ?? []).map((key) => code(key.name)).join(', ');
    const eventRows = reference.events.events.map((event) => [
        code(event.name),
        note(notes, 'eventGroups', event.name) ?? '',
        event.cancelable ? 'Yes' : '',
        event.browser ? keys(event.browser) || 'Nothing more' : 'Not dispatched in the browser',
        event.store ? (event.storeOnly.length ? `Yes, adding ${event.storeOnly.map(code).join(', ')}` : 'Yes') : '',
    ]);
    files['events.md'] = header([INPUTS.eventNames, INPUTS.browserEvents, INPUTS.storeEvents]) + table(['Event', 'Group', 'Cancellable', 'Detail beyond the shared keys', 'From the thread store'], eventRows) + '\n';
    files['events-shared.md'] = header([INPUTS.browserEvents]) + reference.events.shared.map(code).join(', ') + '\n';

    // Each adapter's own table, so its readers have every event's detail without leaving their section.
    const adapterRows = (name) => reference.events.events.filter((event) => event.listed).map((event) => [
        name(event),
        code(event.name),
        note(notes, 'eventGroups', event.name) ?? '',
        event.cancelable ? 'Yes' : '',
        keys(event.browser) || 'Nothing more',
    ]);
    files['event-props-react.md'] = header([INPUTS.eventNames, INPUTS.browserEvents]) + table(['Prop', 'Event', 'Group', 'Cancellable', 'Detail beyond the shared keys'], adapterRows((event) => code(event.reactProp))) + '\n';
    files['event-props-vue.md'] = header([INPUTS.eventNames, INPUTS.browserEvents]) + table(['Component event', 'Event', 'Group', 'Cancellable', 'Detail beyond the shared keys'], adapterRows((event) => code(`@${event.vueEvent}`))) + '\n';

    // Error codes.
    const errorRows = reference.errors.map((error) => [code(error.code), code(error.category), error.status ? String(error.status) : 'None, raised by core', note(notes, 'errors', error.code) ?? '']);
    files['errors.md'] = header([INPUTS.errors]) + table(['Code', 'Category', 'HTTP status', 'When it happens'], errorRows) + '\n';

    // Labels.
    const labelRows = reference.labels.map((label) => [code(label.key), label.text, label.translatable ? 'Yes' : 'No, `labels` only']);
    files['labels.md'] = header([INPUTS.phpLabels, INPUTS.fallbackLabels]) + table(['Key', 'Default text', 'Translated through Craft'], labelRows) + '\n';

    // What the check compares against: the names in code, as of this run.
    const { notes: _notes, ...data } = reference;
    files['reference.json'] = `${JSON.stringify(data, null, 4)}\n`;

    return files;
}

/** Writes every generated file, and returns their names. */
export function writeReference(reference, directory = GENERATED) {
    const files = renderReference(reference);
    mkdirSync(directory, { recursive: true });

    for (const [name, contents] of Object.entries(files)) {
        writeFileSync(join(directory, name), contents);
    }

    return Object.keys(files);
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
    const reference = extractReference(inputOverrides());

    if (process.argv.includes('--stdout')) {
        process.stdout.write(renderReference(reference)['reference.json']);
    } else {
        const written = writeReference(reference);
        console.log(`Wrote ${written.length} files to ${relative(ROOT, GENERATED)}.`);
    }
}
