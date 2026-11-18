import { ThreadController } from './controller.js';
import { setLegacyEventDispatcher } from './events.js';
import { dispatchLegacyEvent } from './legacy-events.js';
import { darkState } from './page.js';
import { mergeThemeConfig } from './theme-config.js';
/**
 * The renderer's side of the page: every drawn thread's controller, and the settings they share.
 * The startup loader imports this module when a thread is about to be needed, and adapters that
 * mount threads themselves import it through `@verbb/comments-browser/renderer`.
 */
// Comments 3's event names, for threads Craft renders in compatibility mode. Set here, where both the
// startup loader and the `/renderer` entry come in, rather than in the events module every chunk shares.
setLegacyEventDispatcher(dispatchLegacyEvent);
let settings = {};
const controllers = new Map();
let themeWatcher = null;
/** Starts one thread, with options that win over the page's settings, and returns its controller. */
export function mountThread(element, options = {}) {
    const existing = controllers.get(element);
    if (existing)
        return existing;
    // A morphing page swap that takes its contents away is drawn again by the same controller, and
    // one that makes the root another thread's mount point starts that thread instead.
    const controller = new ThreadController(element, () => settings, options, () => {
        if (controllers.get(element) !== controller)
            return;
        // The new owner's markup, which the swap put in, is what the new thread puts back when it
        // stops, and the site's own elements carry over, after it.
        const { site, original } = controller.handOver();
        controllers.delete(element);
        controller.destroy(false);
        mountThread(element, { ...options, showNow: false, original });
        element.append(...site);
    });
    controllers.set(element, controller);
    // Stopped through its own destroy(), the element can be mounted again afresh.
    element.addEventListener('comments:destroyed', (event) => {
        if (event.target === element && controllers.get(element) === controller)
            controllers.delete(element);
    }, { once: true });
    return controller;
}
/** Stops every thread inside `scope` (or that is `scope`). */
export function destroyThreads(scope) {
    for (const [root, controller] of controllers) {
        if (root === scope || scope.contains(root)) {
            controller.destroy();
            controllers.delete(root);
        }
    }
}
export function getThread(root) {
    return root instanceof HTMLElement ? controllers.get(root) : undefined;
}
/** Settings for every thread on the page, merged with any given before. Threads already drawn redraw. */
export function configureThreads(next) {
    settings = {
        ...settings,
        ...next,
        themeConfig: next.themeConfig ? mergeThemeConfig([settings.themeConfig, next.themeConfig]) : settings.themeConfig,
        parts: { ...settings.parts, ...next.parts },
        // Per handle and per kind, so a later display override keeps an earlier input override.
        fields: Object.fromEntries([...new Set([...Object.keys(settings.fields ?? {}), ...Object.keys(next.fields ?? {})])]
            .map((handle) => [handle, { ...settings.fields?.[handle], ...next.fields?.[handle] }])),
        labels: { ...settings.labels, ...next.labels },
    };
    if (next.theme)
        setThreadsTheme(next.theme);
    if (next.darkSelector !== undefined)
        watchDarkSelector();
    controllers.forEach((controller) => controller.reconfigure());
}
/** Switches every thread's theme. */
export function setThreadsTheme(theme) {
    controllers.forEach((controller) => controller.setTheme(theme));
}
/**
 * With `darkSelector`, such as `html.dark`, every thread follows a site's own dark-mode toggle,
 * switching to dark while the selector matches `<html>` and back to light when it stops.
 */
export function watchDarkSelector() {
    themeWatcher?.disconnect();
    themeWatcher = null;
    const selector = settings.darkSelector;
    if (!selector || typeof MutationObserver === 'undefined')
        return;
    const apply = () => {
        const dark = darkState(selector);
        if (dark === null) {
            console.warn(`Comments: darkSelector "${selector}" isn't a valid selector.`);
            return;
        }
        controllers.forEach((controller) => {
            if (controller.root.dataset.commentsTheme !== 'none')
                controller.setTheme(dark ? 'dark' : 'light');
        });
    };
    // Only `<html>`, where sites put their dark-mode class or attribute, as the plan describes.
    themeWatcher = new MutationObserver(apply);
    themeWatcher.observe(document.documentElement, { attributes: true });
    apply();
}
