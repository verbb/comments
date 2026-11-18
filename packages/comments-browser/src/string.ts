import { noChange, nothing, type TemplateResult } from 'lit-html';
import { directiveClass } from './directives.js';

/**
 * Renders a part's template to an HTML string without a DOM, for tests, the markup reference and
 * server-side rendering. It covers what Comments' own templates use: text and nested templates,
 * whole-value attribute bindings (`name=${value}`, with `?` for booleans), interpolation inside
 * quoted attributes, and Comments' own directives. Property (`.`) and event (`@`) bindings exist
 * only in the browser, so they're left out.
 */
export function renderToString(value: unknown): string {
    let out = '';

    write(value, (chunk) => {
        out += chunk;
    });

    return out;
}

type Sink = (chunk: string) => void;

const ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (char) => ESCAPES[char]!);
}

function isTemplate(value: unknown): value is TemplateResult {
    return typeof value === 'object' && value !== null && '_$litType$' in value && 'strings' in value;
}

function isDirective(value: unknown): value is { _$litDirective$: unknown; values: unknown[] } {
    return typeof value === 'object' && value !== null && '_$litDirective$' in value;
}

function write(value: unknown, sink: Sink): void {
    if (value === nothing || value === noChange || value === null || value === undefined || value === '') {
        return;
    }

    if (isTemplate(value)) {
        writeTemplate(value, sink);
        return;
    }

    if (isDirective(value)) {
        const handler = directiveClass(value._$litDirective$);

        if (handler?.child) {
            write(handler.child(value.values), sink);
        }

        return;
    }

    if (typeof value === 'object' && Symbol.iterator in value) {
        for (const item of value as Iterable<unknown>) {
            write(item, sink);
        }

        return;
    }

    sink(escapeHtml(String(value)));
}

type ScanState = 'text' | 'tag' | 'dq' | 'sq';

/** Walks one string of a template, keeping track of whether it ends inside a tag or an attribute. */
function scan(chunk: string, state: ScanState): ScanState {
    for (let i = 0; i < chunk.length; i++) {
        const char = chunk[i];

        if (state === 'text') {
            if (char === '<' && /[a-zA-Z/!]/.test(chunk[i + 1] ?? '')) state = 'tag';
        } else if (state === 'tag') {
            if (char === '>') state = 'text';
            else if (char === '"') state = 'dq';
            else if (char === "'") state = 'sq';
        } else if ((state === 'dq' && char === '"') || (state === 'sq' && char === "'")) {
            state = 'tag';
        }
    }

    return state;
}

function writeTemplate(template: TemplateResult, sink: Sink): void {
    const { strings, values } = template;
    let state: ScanState = 'text';
    // Inner HTML an element-part directive (such as the sanitised comment body) adds once its tag closes.
    let pendingInner: string | null = null;

    for (let i = 0; i < strings.length; i++) {
        let chunk = strings[i]!;

        if (pendingInner !== null) {
            const close = chunk.indexOf('>');

            if (close !== -1) {
                chunk = chunk.slice(0, close + 1) + pendingInner + chunk.slice(close + 1);
                pendingInner = null;
            }
        }

        state = scan(chunk, state);

        if (i === strings.length - 1) {
            sink(chunk);
            break;
        }

        const value = values[i];

        if (state === 'text') {
            sink(chunk);
            write(value, sink);
            continue;
        }

        if (state === 'dq' || state === 'sq') {
            sink(chunk);

            if (value !== nothing && value !== null && value !== undefined) {
                sink(escapeHtml(String(value)));
            }

            continue;
        }

        // Inside a tag: a whole attribute (`name=${value}`) or an element part (`<button ${...}>`).
        const binding = /\s([.?@]?)([^\s"'>/=]+)=$/.exec(chunk);

        if (binding) {
            const [match, prefix, name] = binding;
            sink(chunk.slice(0, chunk.length - match.length));

            if (prefix === '?') {
                if (value && value !== nothing) sink(` ${name}`);
            } else if (prefix === '' && value !== nothing && value !== null && value !== undefined && value !== noChange) {
                sink(` ${name}="${escapeHtml(String(value))}"`);
            }

            continue;
        }

        const handler = isDirective(value) ? directiveClass(value._$litDirective$) : undefined;
        const args = isDirective(value) ? value.values : [];
        const parts = Object.entries(handler?.attributes?.(args) ?? {}).flatMap(([name, attribute]) => {
            // A name that couldn't be an attribute would break out of the tag, so it's left out.
            if (!/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/.test(name)) return [];
            if (attribute === true) return [name];
            return attribute !== false && attribute !== null && attribute !== undefined ? [`${name}="${escapeHtml(String(attribute))}"`] : [];
        });

        // The template put a space before the binding, which goes when the binding adds nothing.
        sink(parts.length ? chunk.replace(/\s+$/, '') + parts.map((part) => ` ${part}`).join('') : chunk.replace(/\s+$/, ''));

        const inner = handler?.inner?.(args);

        if (inner !== undefined) {
            pendingInner = inner;
        }
    }
}
