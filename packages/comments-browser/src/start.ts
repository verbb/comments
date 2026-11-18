import type { ThemeControlConfig } from '@verbb/comments-core';
import type { CommentsApp, CommentsController, CommentsSettings, CommentsTheme, MountOptions } from './types.js';

/**
 * The startup loader: the only code a page downloads before a thread is needed. It finds the
 * mount points `craft.comments.render()` writes, draws **Show comments** for the `button` load
 * mode, watches for a thread nearing view in the `visible` mode, and imports the renderer
 * (`threads.ts` and everything it draws with) when a thread is about to be needed, at once for
 * `eager` or a link to a comment. It also owns `window.Comments`, the two-copies guard, the opt-out
 * attribute, Turbo Drive's swaps and threads added to the page later.
 */

// A mount point marked `data-comments-adapter` belongs to a framework adapter, which mounts it with
// its own options, so this script never starts it, even through Comments.mount().
const ROOT_SELECTOR = '[data-comments-root][data-comments-api-version="1"]:not([data-comments-adapter])';
// Shared by every copy of the comments script on the page, whichever build it came from, so a
// second copy can find the first. A symbol can't clash with a site's own `window.Comments`.
const APP_KEY = Symbol.for('verbb.comments');
const LOAD_MARGIN = '600px 0px';

type CommentsHost = typeof globalThis & { [APP_KEY]?: CommentsApp; Comments?: CommentsApp };
type Threads = typeof import('./threads.js');

export type StartOptions = CommentsSettings & {
    /** Watch for threads added to and removed from the page later. Defaults to true. */
    observe?: boolean;
};

// The loader's view of the settings, enough to wait for and draw a thread. Every configure() call is
// also kept as given, for the renderer to merge (and check) itself when it arrives.
// The loader's own copies of `page.ts`'s two checks. Nothing the renderer's chunks import may live
// in this entry, because Craft serves it with a cache-busting query (`comments.js?v=…`), and a chunk
// importing `../comments.js` back would make the browser run the loader a second time.
// A link to a comment is its number or its UID, as the renderer reads them.
const COMMENT_REF = /^(\d+|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i;
const deepLink = () => COMMENT_REF.test(location.hash.startsWith('#comment-') ? location.hash.slice(9) : '') || COMMENT_REF.test(new URLSearchParams(location.search).get('comment') ?? '');

function darkState(selector: string | undefined): boolean | null {
    try {
        return selector ? document.documentElement.matches(selector) : null;
    } catch {
        return null;
    }
}

let settings: CommentsSettings = {};
const configured: CommentsSettings[] = [];
let threads: Threads | null = null;
let loadingThreads: Promise<Threads> | null = null;
let started = false;
// Threads waiting to be needed, with what stops their wait, and the server's markup to put back.
const waiting = new Map<HTMLElement, () => void>();
const originals = new WeakMap<HTMLElement, Node[]>();
// Threads whose renderer is on its way, so destroy() or taking the thread off the page calls them off.
const pending = new Map<HTMLElement, symbol>();

// A page reloaded by Try again (see importAgain()) shows a `button` thread straight away, as
// someone already pressed Show comments.
const RETRIED_KEY = 'verbb.comments.retried';
const here = () => location.pathname + location.search;
const retried = (() => {
    try {
        const page = sessionStorage.getItem(RETRIED_KEY);
        sessionStorage.removeItem(RETRIED_KEY);
        return page === here();
    } catch {
        return false;
    }
})();

/**
 * The loader's copy of `importAgain()` in `dom.ts`, for **Try again** after the renderer failed to
 * load. Chromium keeps a failed module fetch for the life of the page, so when importing it again
 * fails and the server now answers for the chunk, the page reloads, which is the only way to load it.
 */
function importAgain<T>(load: () => Promise<T>): Promise<T> {
    return load().catch(async (error: unknown) => {
        const url = /dynamically imported module: (\S+)/.exec(String((error as Error | null)?.message))?.[1];

        if (url && await fetch(url, { method: 'HEAD', cache: 'no-store' }).then(() => true, () => false)) {
            try {
                sessionStorage.setItem(RETRIED_KEY, here());
            } catch {
                // Without storage, a `button` thread waits for Show comments again.
            }
            location.reload();
            return new Promise<T>(() => undefined);
        }

        throw error;
    });
}

/**
 * The renderer, imported once from the script's own origin when the first thread needs it. `again`
 * is someone pressing **Try again** after it failed, which may reload the page (see `importAgain()`).
 */
function renderer(again = false): Promise<Threads> {
    loadingThreads ??= (again ? importAgain(() => import('./threads.js')) : import('./threads.js')).then((module) => {
        threads = module;
        configured.forEach((next) => module.configureThreads(next));
        return module;
    }, (error: unknown) => {
        loadingThreads = null;
        throw error;
    });

    return loadingThreads;
}

/**
 * Called by `@verbb/comments-browser/renderer` as it's imported, for pages that mount threads with it
 * directly, such as the web components and the React and Vue packages, and never call
 * `startComments()`. Settings given to `configure()` before then reach those threads now, and later
 * ones as they're given, as they would after this loader imported the renderer itself.
 */
export function adoptRenderer(module: Threads): void {
    if (threads) return;

    threads = module;
    loadingThreads ??= Promise.resolve(module);
    configured.forEach((next) => module.configureThreads(next));
}

function runningCopy(): CommentsApp | null {
    const running = (globalThis as CommentsHost)[APP_KEY];
    return running && running !== Comments ? running : null;
}

function findRoots(scope: ParentNode): HTMLElement[] {
    const roots = Array.from(scope.querySelectorAll<HTMLElement>(ROOT_SELECTOR));

    if (scope instanceof HTMLElement && scope.matches(ROOT_SELECTOR)) roots.unshift(scope);

    return roots;
}

/**
 * Whether the script starts this thread by itself. `data-comments-auto-mount="false"` on the root
 * or any element around it, `<html>` included, leaves it for the site to start.
 */
function autoMounts(root: HTMLElement): boolean {
    return root.closest('[data-comments-auto-mount]')?.getAttribute('data-comments-auto-mount') !== 'false';
}

function stopWaiting(root: HTMLElement): void {
    waiting.get(root)?.();
    waiting.delete(root);
}

/**
 * Hands a thread to the renderer, importing it first if this is the first. The promise rejects
 * with an `AbortError` when the thread is destroyed or taken off the page while the renderer is
 * on its way, and with the import's own error when the renderer can't load, after drawing what
 * went wrong with **Try again**. `again` is that button being pressed.
 */
async function mountNow(root: HTMLElement, options: MountOptions & { showNow?: boolean } = {}, again = false): Promise<CommentsController> {
    // Another copy of the renderer draws this thread, such as an adapter's or a <verbb-comments>
    // element's, with options of its own, so this copy doesn't take it over.
    if (root.dataset.commentsJs && !threads?.getThread(root)) {
        throw new DOMException('Comments: another copy of the renderer is drawing this thread.', 'NotSupportedError');
    }

    stopWaiting(root);
    const token = Symbol('mount');
    pending.set(root, token);
    let module: Threads;

    try {
        module = await renderer(again);
    } catch (error) {
        if (pending.get(root) === token) {
            pending.delete(root);
            if (root.isConnected) drawWaiting(root, 'error', () => mountNow(root, options, true).catch(() => undefined));
        }

        throw error;
    }

    if (pending.get(root) !== token || !root.isConnected) {
        if (pending.get(root) === token) pending.delete(root);
        throw new DOMException('Comments: the thread was stopped before the renderer arrived.', 'AbortError');
    }

    pending.delete(root);
    return module.mountThread(root, { ...options, original: originals.get(root) } as never);
}

/** Starts a thread the script found itself, where nothing is waiting on the promise. */
function startNow(root: HTMLElement, options: MountOptions & { showNow?: boolean } = {}): void {
    mountNow(root, options).catch(() => undefined);
}

/**
 * Gets a thread ready without the renderer: straight to it for `eager` or a link to a comment,
 * a watch for it nearing view for `visible`, or the **Show comments** button for `button`.
 */
function prepare(root: HTMLElement): void {
    // A waiting thread whose waiting state a page swap took, such as a morph putting the server's
    // mount point back, waits again from the start.
    if (waiting.has(root) && (!root.dataset.commentsPhase || !root.querySelector('[data-comments-placeholder]'))) stopWaiting(root);

    // `data-comments-js` marks a thread any copy of the renderer is drawing, such as one an app
    // mounted through `@verbb/comments-browser/renderer`, which this script leaves alone.
    if (waiting.has(root) || pending.has(root) || threads?.getThread(root) || root.dataset.commentsJs) return;

    // A thread's own load mode, from `render()` or its component, wins over `Comments.configure()`'s,
    // as a thread's own `themeConfig` does.
    const mode = root.dataset.commentsLoadMode ?? settings.loadMode;

    if (mode === 'eager' || !['visible', 'button'].includes(mode ?? '') || deepLink()) {
        startNow(root);
        return;
    }

    if (mode === 'visible' && typeof IntersectionObserver !== 'undefined') {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) startNow(root);
        }, { rootMargin: LOAD_MARGIN });

        drawWaiting(root, 'loading');
        observer.observe(root);
        waiting.set(root, () => observer.disconnect());
        return;
    }

    if (mode === 'visible') {
        startNow(root);
        return;
    }

    if (retried) {
        startNow(root, { showNow: true });
        return;
    }

    drawWaiting(root, 'button', () => startNow(root, { showNow: true }));
}

/**
 * The waiting state, drawn exactly as the renderer draws it, so nothing moves when the renderer
 * takes over: the heading with the count, a placeholder where the sort options go, and the quiet
 * loading line of the `visible` load mode, the `button` load mode's **Show comments (42)**, or,
 * when the renderer couldn't load, what went wrong with **Try again**. `press` is what the button does.
 */
function drawWaiting(root: HTMLElement, phase: 'loading' | 'button' | 'error', press?: () => void): void {
    const parse = (value: string | undefined): Record<string, any> => {
        try {
            return JSON.parse(value ?? '{}') ?? {};
        } catch {
            return {};
        }
    };
    const labels = { comments: 'Comments', loading: 'Loading comments…', showComments: 'Show comments', showCommentsCount: 'Show comments ({count})', error: 'Something went wrong. Please try again.', tryAgain: 'Try again', ...parse(root.dataset.commentsLabels), ...settings.labels };
    const count = Number(root.dataset.commentsCount) || 0;
    const id = root.id || 'comments';
    // Show comments is a secondaryButton for themeConfig, from render() and then configure(); the
    // renderer checks every setting when it arrives, and redraws the button with it.
    const control: ThemeControlConfig = {};
    for (const source of [parse(root.dataset.commentsConfig).themeConfig?.secondaryButton, ...configured.map((next) => next.themeConfig?.secondaryButton)]) {
        if (source && typeof source === 'object') Object.assign(control, source, { attributes: { ...control.attributes, ...source.attributes } });
    }
    const extra = control.attributes ?? {};
    const element = (tag: string, attributes: Record<string, unknown> = {}, ...children: Array<Node | string>) => {
        const node = document.createElement(tag);
        Object.entries(attributes).forEach(([name, value]) => {
            if (value !== null && value !== undefined && value !== false) node.setAttribute(name, value === true ? '' : Array.isArray(value) ? value.join(' ') : String(value));
        });
        node.append(...children);
        return node;
    };
    const safe = Object.fromEntries(Object.entries(extra).filter(([name]) => /^(title|aria-label|data-(?!comments-)[a-z0-9-]+)$/.test(name)));
    const button = element('button', {
        class: [phase === 'error' ? 'comments__retry' : 'comments__show-comments-button', ...(control.resetClass ? [] : ['comments__button', 'comments__button--secondary']), ...[extra.class ?? []].flat()].join(' '),
        ...safe,
        type: 'button',
        'data-comments-action': phase === 'error' ? 'retry' : 'show-comments',
        'data-comments-control': 'secondaryButton',
    }, phase === 'error' ? labels.tryAgain : count ? String(labels.showCommentsCount).replace('{count}', String(count)) : labels.showComments);
    // Keyboard focus on the button that's about to be replaced moves to its replacement.
    const hadFocus = root.contains(document.activeElement) && document.activeElement !== document.body;

    if (!originals.has(root)) originals.set(root, Array.from(root.childNodes, (node) => node.cloneNode(true)));
    applyTheme(root);
    root.dataset.commentsPhase = phase;
    root.replaceChildren(
        element('div', { 'data-comments-region': 'live', class: 'comments__live', role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' }),
        element('header', { class: 'comments__header', 'data-comments-region': 'header' }, element('div', { class: 'comments__header-main' },
            element('h2', { class: 'comments__heading', id: `${id}-heading`, tabindex: '-1' }, labels.comments, ...(count ? [' ', element('span', { class: 'comments__count' }, element('span', { class: 'comments__visually-hidden' }, ': '), count.toLocaleString())] : [])),
            element('span', { class: 'comments__sort', 'aria-hidden': 'true', 'data-comments-placeholder': true }, element('span', { class: 'comments__sort-option comments__button comments__button--quiet' }, ' ')))),
        element('div', { 'data-comments-region': 'placeholder' }, phase === 'button'
            ? element('div', { class: 'comments__show-comments', 'data-comments-placeholder': true }, button)
            : phase === 'error'
                ? element('div', { class: 'comments__load-error', 'data-comments-placeholder': true }, element('p', { class: 'comments__loading' }, labels.error), button)
                : element('p', { class: 'comments__loading', 'data-comments-placeholder': true, 'aria-busy': 'true' }, labels.loading)),
        element('div', { 'data-comments-region': 'toast' }, element('div', { class: 'comments__toast' })),
    );

    if (!press) return;

    const onPress = () => {
        button.setAttribute('aria-busy', 'true');
        press();
    };

    button.addEventListener('click', onPress);
    waiting.set(root, () => button.removeEventListener('click', onPress));

    if (phase === 'error') {
        // Said, as the renderer says a failed load, and the keyboard goes to Try again if it was here.
        const live = root.querySelector<HTMLElement>('[data-comments-region="live"]');
        setTimeout(() => { if (live) live.textContent = labels.error; }, 50);
        if (hadFocus) button.focus();
    }
}

function applyTheme(root: HTMLElement): void {
    if (settings.theme) root.dataset.commentsTheme = settings.theme;

    const dark = darkState(settings.darkSelector);
    if (dark !== null && root.dataset.commentsTheme !== 'none') root.dataset.commentsTheme = dark ? 'dark' : 'light';
}

/** The threads in `scope` that haven't opted out. */
function autoPrepare(scope: ParentNode = document): void {
    for (const root of findRoots(scope)) {
        if (autoMounts(root)) prepare(root);
    }
}

function destroy(scope: ParentNode = document): void {
    for (const root of findRoots(scope)) {
        if (waiting.has(root) || pending.has(root)) {
            pending.delete(root);
            stopWaiting(root);
            const original = originals.get(root);
            if (original) root.replaceChildren(...original.map((node) => node.cloneNode(true)));
            delete root.dataset.commentsPhase;
        }
    }

    threads?.destroyThreads(scope);
}

/** Settings for every thread on the page, merged with any given before. Threads already drawn redraw. */
export function configure(next: CommentsSettings): void {
    const running = runningCopy();

    if (running) {
        running.configure(next);
        return;
    }

    configured.push(next);
    settings = { ...settings, ...next, labels: { ...settings.labels, ...next.labels } };
    threads?.configureThreads(next);
    waiting.forEach((_, root) => applyTheme(root));
}

/** Switches every thread's theme, as a site's own dark-mode toggle would. */
export function setTheme(theme: CommentsTheme): void {
    const running = runningCopy();

    if (running) {
        running.setTheme(theme);
        return;
    }

    waiting.forEach((_, root) => {
        root.dataset.commentsTheme = theme;
    });
    threads?.setThreadsTheme(theme);
}

/**
 * Starts one thread on any element, with options that win over the page's settings, loading the
 * renderer first if it isn't here yet.
 */
export function mountThread(element: HTMLElement, options: MountOptions = {}): Promise<CommentsController> {
    const running = runningCopy();

    return running ? running.mountThread(element, options) : mountNow(element, options);
}

export const Comments: CommentsApp = Object.freeze({
    async mount(scope: ParentNode = document) {
        // A thread another copy of the renderer draws, such as a <verbb-comments> element's, is left to it.
        const roots = findRoots(scope).filter((root) => !root.dataset.commentsJs || threads?.getThread(root));

        return Promise.all(roots.map((root) => mountNow(root)));
    },
    destroy,
    get: (root: Element) => threads?.getThread(root),
    configure,
    setTheme,
    mountThread,
});

/**
 * Starts Comments on the page: every thread that hasn't opted out, threads added later, Turbo
 * Drive's page swaps, links to comments, `darkSelector` and `window.Comments`. Importing the
 * package starts nothing; the plugin's own script calls this once, as a site's bundle does. A
 * second copy of the script hands its settings to the first rather than drawing every thread twice.
 */
export function startComments(options: StartOptions = {}): CommentsApp {
    const host = globalThis as CommentsHost;
    const running = host[APP_KEY];
    const { observe = true, ...rest } = options;

    if (running && running !== Comments) {
        console.warn('Comments: the comments script has loaded twice on this page, so this copy won\'t start any threads. If your site bundles @verbb/comments-browser itself, turn off Load Bundled JavaScript (outputDefaultJs) in the Comments settings.');
        // Its settings, such as a nonce, still reach the threads the first copy runs.
        running.configure(rest);
        return running;
    }

    configure(rest);

    if (started) return Comments;

    started = true;
    host[APP_KEY] = Comments;
    host.Comments = Comments;

    const ready = () => {
        autoPrepare();

        // A thread's dark mode follows a site's toggle even before the renderer is here.
        if (settings.darkSelector && typeof MutationObserver !== 'undefined') {
            new MutationObserver(() => waiting.forEach((_, root) => applyTheme(root))).observe(document.documentElement, { attributes: true });
        }

        if (observe && typeof MutationObserver !== 'undefined') {
            new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    for (const node of mutation.removedNodes) {
                        // A thread a site started itself is still stopped here, as nothing else would clean it up.
                        if (node instanceof HTMLElement && !node.isConnected) destroy(node);
                    }

                    for (const node of mutation.addedNodes) {
                        if (node instanceof HTMLElement) autoPrepare(node);
                    }

                    // A waiting thread's own contents changed, perhaps by a morph.
                    if (waiting.has(mutation.target as HTMLElement)) prepare(mutation.target as HTMLElement);
                }
            }).observe(document.documentElement, { childList: true, subtree: true });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready, { once: true });
    } else {
        ready();
    }

    // Turbo Drive swaps page bodies without a full reload, so threads are stopped before Turbo caches
    // the page and started again on the new one.
    document.addEventListener('turbo:before-cache', () => destroy());
    document.addEventListener('turbo:load', () => {
        autoPrepare();
        threads?.watchDarkSelector();
    });

    // A link to a comment loads a waiting thread straight away. Other in-page links, such as a table
    // of contents, leave it waiting.
    window.addEventListener('hashchange', () => {
        if (deepLink()) [...waiting.keys()].forEach((root) => startNow(root));
    });

    return Comments;
}
