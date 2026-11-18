import { scriptAllowed, trustedScriptUrl } from './trusted.js';
// How long an opening form waits for an Editor the page loads itself. The plain box stays usable
// meanwhile, and Editor still takes over if it turns up later.
const EDITOR_WAIT_MS = 8000;
// Shared by every thread on the page, so Editor loads once however many there are.
const loads = new Map();
export function editorDefined() {
    return typeof customElements !== 'undefined' && Boolean(customElements.get('verbb-editor'));
}
/** Waits for `<verbb-editor>` to be defined, giving up after a while so a post never hangs. */
function defined() {
    return Promise.race([
        customElements.whenDefined('verbb-editor').then(() => undefined),
        new Promise((_, reject) => setTimeout(() => reject(new Error('Editor did not load.')), EDITOR_WAIT_MS)),
    ]);
}
/**
 * Loads Editor's bundle: a site's own loader if it gave one (an import from npm, say), else
 * Editor's own module from the URL in the thread's data, with the page's nonce, but only from the
 * data API's origin or this script's own. With neither, it waits a while for a bundle the page
 * loads itself. It never runs until someone heads for a rich editor, so Editor is never in the
 * page's first download, and it gives up after a while if Editor never defines its element.
 */
export function loadEditor(source, options = {}) {
    if (editorDefined()) {
        return Promise.resolve();
    }
    if (options.loader) {
        return remember('loader', () => Promise.resolve(options.loader()).then(defined));
    }
    if (!source?.module) {
        return defined();
    }
    if (!scriptAllowed(source.module)) {
        return Promise.reject(new Error('Editor’s module isn’t on the data API’s origin or the comments script’s own.'));
    }
    if (source.css && scriptAllowed(source.css) && !document.querySelector(`link[data-comments-editor-css="${CSS.escape(source.css)}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = source.css;
        link.dataset.commentsEditorCss = source.css;
        if (options.nonce)
            link.nonce = options.nonce;
        document.head.append(link);
    }
    const module = source.module;
    return remember(module, () => new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.type = 'module';
        if (options.nonce)
            script.nonce = options.nonce;
        script.onload = () => resolve();
        script.onerror = () => {
            script.remove();
            reject(new Error('Editor could not be loaded.'));
        };
        try {
            script.src = trustedScriptUrl(module);
        }
        catch (error) {
            reject(error);
            return;
        }
        document.head.append(script);
    }).then(defined));
}
/**
 * Loads the Editor features these options use, such as links and mentions. Editor's own module
 * loads them on demand, so loading them before adding `<verbb-editor>` means it mounts the moment
 * it's added. An Editor without on-demand features has them already.
 */
export function loadEditorFeatures(options) {
    const element = customElements.get('verbb-editor');
    return element?.loadFeatures ? element.loadFeatures(options) : Promise.resolve();
}
/** One load per source, shared by every thread; a failed one can be tried again. */
function remember(key, start) {
    let pending = loads.get(key);
    if (!pending) {
        pending = start();
        pending.catch(() => loads.delete(key));
        loads.set(key, pending);
    }
    return pending;
}
