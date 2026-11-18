import { GifSearch, QUERY_MAX, thumbnail } from '@verbb/editor/gifs';
import { html, nothing } from 'lit-html';
import { attributes } from './directives.js';
/**
 * GIF search through GIPHY for the plain form. It's its own chunk, loaded when someone first opens
 * the picker, so a thread that never opens it never downloads this or talks to GIPHY.
 *
 * The search itself is Editor's (`@verbb/editor/gifs`), the same one Editor's GIF control uses when
 * rich authoring is on: it talks to GIPHY from the browser as GIPHY's terms require, and sends
 * GIPHY's pings. This draws its results in Comments' own markup, with Comments' classes.
 */
export { chosen, Giphy, thumbnail } from '@verbb/editor/gifs';
/**
 * One form's picker: Editor's search, and the panel drawn from it. The form draws the panel inside
 * itself and calls `render` again whenever the picker changes.
 */
export class GifPicker extends GifSearch {
    /** The panel, drawn for a form. */
    panel(view) {
        return gifPickerTemplate(view, this);
    }
}
/**
 * Sets the picker up in a form: its search, its keys and its results, so the form's own chunk only
 * opens and closes it.
 */
export function attachGifPicker(config, form) {
    const { element, signal } = form;
    const picker = new GifPicker(config, () => {
        form.render();
        // GIPHY counts each result once it's on screen.
        if (form.isOpen() && picker.status === 'ready')
            picker.shown();
    });
    const search = (target) => target instanceof HTMLInputElement && target.dataset.commentsGifSearch !== undefined;
    element.addEventListener('input', (event) => {
        if (search(event.target))
            picker.type(event.target.value);
    }, { signal });
    element.addEventListener('click', (event) => {
        const result = event.target instanceof Element ? event.target.closest('[data-comments-gif-action="choose"]') : null;
        const choice = result ? picker.choose(Number(result.dataset.commentsGifIndex)) : null;
        if (choice)
            form.chosen(choice);
    }, { signal });
    // Before the form's own keys, so Escape closes the picker rather than the form, and Enter in its
    // search searches straight away rather than posting the comment.
    element.addEventListener('keydown', (event) => {
        if (!form.isOpen() || !(event.target instanceof Element) || !event.target.closest('.comments__gif-picker, .comments__gif-button'))
            return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            form.close();
        }
        else if (event.key === 'Enter' && search(event.target)) {
            event.preventDefault();
            event.stopPropagation();
            void picker.now(event.target.value);
        }
    }, { signal, capture: true });
    return picker;
}
/**
 * The picker's panel: a search box, GIPHY's results as buttons, what's happening, and the "Powered by
 * GIPHY" mark GIPHY's terms ask for wherever its search is used.
 */
export function gifPickerTemplate(view, picker) {
    const { labels } = view;
    const id = `${view.idPrefix}-gif`;
    const loading = picker.status === 'loading';
    const message = picker.status === 'error' ? labels.gifUnavailable : picker.status === 'ready' && !picker.results.length ? labels.gifEmpty : '';
    return html `<div class="comments__gif-picker" id=${`${id}-picker`} role="group" aria-label=${labels.gifSearch}><label class="comments__visually-hidden" for=${`${id}-search`}>${labels.gifSearch}</label><input ${attributes(view.control('input', {
        identify: 'comments__gif-search',
        attributes: { id: `${id}-search`, type: 'search', placeholder: labels.gifSearch, maxlength: QUERY_MAX, autocomplete: 'off', value: picker.query, 'data-comments-gif-search': true, 'aria-controls': `${id}-results` },
    }))}><div class="comments__gif-results" id=${`${id}-results`} role="list" aria-label=${labels.gifResults} aria-busy=${loading ? 'true' : 'false'}>${picker.results.map((result, index) => {
        const image = thumbnail(result);
        const name = result.alt_text || result.title || labels.gif;
        return html `<div class="comments__gif-cell" role="listitem"><button type="button" class="comments__gif-result" data-comments-gif-action="choose" data-comments-gif-index=${index}>${image.url ? html `<img class="comments__gif-result-image" src=${image.url} alt=${name} loading="lazy" width=${image.width ?? nothing} height=${image.height ?? nothing}>` : html `<span class="comments__visually-hidden">${name}</span>`}</button></div>`;
    })}</div><p class="comments__gif-status" role="status">${loading ? nothing : message}</p><p class="comments__gif-attribution">${labels.gifPoweredBy}</p></div>`;
}
