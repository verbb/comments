import { html, svg } from 'lit-html';
/**
 * One line-icon style throughout, matching Editor's. Each icon is sized and drawn by its own
 * attributes too, so a site's CSS, or theme None, starts from a sensible icon. The paths are
 * `svg` templates, because lit-html parses an `html` one as HTML even inside an `<svg>`.
 */
const paths = {
    heart: svg `<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>`,
    up: svg `<path d="M12 19V5M5 12l7-7 7 7"/>`,
    down: svg `<path d="M12 5v14M19 12l-7 7-7-7"/>`,
    reply: svg `<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>`,
    more: svg `<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>`,
    bell: svg `<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>`,
    user: svg `<circle cx="12" cy="8" r="4.5"/><path d="M20 21a8 8 0 0 0-16 0"/>`,
    clock: svg `<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>`,
    spam: svg `<path d="M7.9 2h8.2L22 7.9v8.2L16.1 22H7.9L2 16.1V7.9z"/><path d="M12 8v4M12 16h.01"/>`,
    flag: svg `<path d="M4 22V4c3-2 6-1 8 0s5 2 8 0v10c-3 2-6 1-8 0s-5-2-8 0"/>`,
    removed: svg `<path d="M10.7 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a13 13 0 0 1-1.7 2.7M6.6 6.6A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6M2 2l20 20M9.9 9.9a3 3 0 0 0 4.2 4.2"/>`,
    close: svg `<path d="M18 6 6 18M6 6l12 12"/>`,
    check: svg `<path d="M20 6 9 17l-5-5"/>`,
    alert: svg `<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>`,
    info: svg `<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>`,
    file: svg `<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>`,
    attach: svg `<path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l8.6-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4L15.6 7"/>`,
    retry: svg `<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>`,
};
/** An icon in the thread, `comments__icon comments__icon--{name}`. */
export function icon(name) {
    return html `<svg class=${`comments__icon comments__icon--${name}`} width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]}</svg>`;
}
/** An icon in the comment editor's frame, drawn as Editor draws its own. */
export function frameIcon(name, extra = '') {
    return html `<svg class=${`comments__frame-icon${extra ? ` ${extra}` : ''}`} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]}</svg>`;
}
