import { html, nothing, type TemplateResult } from 'lit-html';
import { attributes, type AttributeMap } from '../directives.js';
import type { FormView } from './form.js';

/**
 * The plain comment form's GIF button and its chosen GIF. The search itself is a chunk of its own
 * (`gif-picker.ts`), drawn into the area below once someone opens it. With rich authoring, Editor's
 * GIF control does all of this inside Editor, and the form reads the GIF from it.
 */

/** The GIF button's attributes. It sits beside Cancel in the plain form; once Editor runs, Editor's own GIF control takes over. */
export function gifButtonAttributes(view: FormView): AttributeMap {
    return view.control('secondaryButton', {
        identify: 'comments__gif-button verbb-editor__button',
        attributes: {
            type: 'button',
            'aria-label': view.labels.gifAdd,
            'aria-expanded': view.gifOpen ? 'true' : 'false',
            'aria-controls': `${view.idPrefix}-gif-picker`,
            'data-comments-gif-action': 'toggle',
            'data-comments-slot': 'actions',
        },
    });
}

export function gifButtonContent(view: FormView): TemplateResult {
    return html`<span aria-hidden="true">${view.labels.gif}</span>`;
}

/** The chosen GIF with a way to take it off, then the picker while it's open. */
export function gifAreaTemplate(view: FormView): TemplateResult {
    const gif = view.gif;

    return html`${gif
        ? html`<figure class="comments__gif-preview"><img class="comments__gif-preview-image" src=${gif.url} alt=${view.labels.gif} width=${gif.width ?? nothing} height=${gif.height ?? nothing}><button ${attributes(view.control('quietButton', { identify: 'comments__gif-remove', attributes: { type: 'button', 'data-comments-gif-action': 'remove' } }))}>${view.labels.gifRemove}</button></figure>`
        : nothing}${view.gifOpen ? view.gifPanel ?? html`<div class="comments__gif-picker" id=${`${view.idPrefix}-gif-picker`} aria-busy=${view.gifFailed ? 'false' : 'true'}><p class="comments__gif-status" role="status">${view.gifFailed ? view.labels.gifUnavailable : nothing}</p><p class="comments__gif-attribution">${view.labels.gifPoweredBy}</p></div>` : nothing}`;
}
