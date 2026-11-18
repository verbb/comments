import type { CommentsApp, CommentsController, CommentsSettings, CommentsTheme, MountOptions } from './types.js';
type Threads = typeof import('./threads.js');
export type StartOptions = CommentsSettings & {
    /** Watch for threads added to and removed from the page later. Defaults to true. */
    observe?: boolean;
};
/**
 * Called by `@verbb/comments-browser/renderer` as it's imported, for pages that mount threads with it
 * directly, such as the web components and the React and Vue packages, and never call
 * `startComments()`. Settings given to `configure()` before then reach those threads now, and later
 * ones as they're given, as they would after this loader imported the renderer itself.
 */
export declare function adoptRenderer(module: Threads): void;
/** Settings for every thread on the page, merged with any given before. Threads already drawn redraw. */
export declare function configure(next: CommentsSettings): void;
/** Switches every thread's theme, as a site's own dark-mode toggle would. */
export declare function setTheme(theme: CommentsTheme): void;
/**
 * Starts one thread on any element, with options that win over the page's settings, loading the
 * renderer first if it isn't here yet.
 */
export declare function mountThread(element: HTMLElement, options?: MountOptions): Promise<CommentsController>;
export declare const Comments: CommentsApp;
/**
 * Starts Comments on the page: every thread that hasn't opted out, threads added later, Turbo
 * Drive's page swaps, links to comments, `darkSelector` and `window.Comments`. Importing the
 * package starts nothing; the plugin's own script calls this once, as a site's bundle does. A
 * second copy of the script hands its settings to the first rather than drawing every thread twice.
 */
export declare function startComments(options?: StartOptions): CommentsApp;
export {};
