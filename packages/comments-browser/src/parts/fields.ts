import type { CommentData, FieldValueData, FileValueData } from '@verbb/comments-core';
import { html, nothing, type TemplateResult } from 'lit-html';
import type { ThreadView } from '../view.js';

/** As many characters of an inline value as fit after the date, as the Twig meta line cut them. */
export const INLINE_FIELD_LENGTH = 40;

/** What every link to something a commenter gave says about it. */
const REL = 'ugc nofollow noopener';

/**
 * An address the renderer may link to or load: `http` or `https`, or a path on this site, which
 * must stay on this site once the browser has read it (`/\t/evil.test` is `//evil.test`). The
 * server already nulls anything else, but every address is checked again here, whatever a field
 * type says, so a `javascript:` or `data:` address is never linked.
 */
export function safeUrl(value: unknown): string | null {
    if (typeof value !== 'string' || value.trim() === '') return null;

    const address = value.trim();
    const page = typeof location === 'undefined' ? null : location.href;

    try {
        if (address.startsWith('/')) {
            // Drawing to a string has no page to resolve against, so only a plain path will do.
            if (!page) return /^\/(?![/\\])[^\s\\]*$/.test(address) ? address : null;

            const url = new URL(address, page);
            return url.origin === new URL(page).origin ? url.href : null;
        }

        const url = new URL(address);
        return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : null;
    } catch {
        return null;
    }
}

function files(field: FieldValueData): FileValueData[] {
    return Array.isArray(field.value) ? field.value.filter((file): file is FileValueData => Boolean(file) && typeof file === 'object') : [];
}

/** One file: an image's thumbnail or the file's name, linked when its address is safe. */
function file(item: FileValueData): TemplateResult {
    const href = safeUrl(item.url);
    const name = String(item.filename ?? '');

    if (href && item.kind === 'image') {
        return html`<a class="comments__field-file comments__field-file--image" href=${href} rel=${REL}><img class="comments__field-thumb" src=${href} alt=${name} loading="lazy" width=${item.width ?? nothing} height=${item.height ?? nothing}></a>`;
    }

    return href
        ? html`<a class="comments__field-file" href=${href} rel=${REL}>${name}</a>`
        : html`<span class="comments__field-file">${name}</span>`;
}

/** A shown value as text, a link for a web address, or files. Never HTML. */
export function fieldValueTemplate(field: FieldValueData, length = 0): unknown {
    const text = length && field.text.length > length ? `${field.text.slice(0, length)}…` : field.text;

    if (field.type === 'url') {
        const href = safeUrl(field.value);
        return href ? html`<a class="comments__field-link" href=${href} rel=${REL}>${text}</a>` : text;
    }

    if (field.type === 'file' && !length) {
        const list = files(field);
        return list.length ? html`<span class="comments__field-files">${list.map(file)}</span>` : text;
    }

    return text;
}

function shown(field: FieldValueData): boolean {
    return Boolean(field.text) || (field.type === 'file' && files(field).length > 0);
}

/** A field's value through its display override by handle, if the site gave one. */
function display(view: ThreadView, comment: CommentData, field: FieldValueData, length = 0): unknown {
    return view.field('display', field.handle, { field, comment, value: field.value, text: field.text }, () => fieldValueTemplate(field, length));
}

/** Values shown inline, after the date, cut to fit. */
export function inlineFields(view: ThreadView, comment: CommentData): unknown {
    return comment.fields.filter((field) => field.placement === 'inline' && shown(field))
        .map((field) => html`<span class=${`comments__meta-item comments__field-value--${field.handle}`}>${display(view, comment, field, INLINE_FIELD_LENGTH)}</span>`);
}

/** Values shown under the text, as a labelled list: the `fields` part. */
export function detailFields(view: ThreadView, comment: CommentData, data: Record<string, unknown>): unknown {
    const details = comment.fields.filter((field) => field.placement === 'details' && shown(field));

    if (!details.length) {
        return nothing;
    }

    return view.part('fields', { ...data, fields: details }, () => html`<dl class="comments__fields">${details.map((field) => html`<div class=${`comments__field-value comments__field-value--${field.handle}`}><dt class="comments__field-name">${field.label}</dt><dd class="comments__field-text">${display(view, comment, field)}</dd></div>`)}</dl>`);
}
