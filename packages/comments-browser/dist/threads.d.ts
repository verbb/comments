import type { CommentsController, CommentsSettings, CommentsTheme, MountOptions } from './types.js';
/**
 * Options the renderer's own callers pass beside a thread's: the loader pressing Show comments on
 * its behalf, and an element saying its endpoint came from markup.
 */
export type InternalMountOptions = MountOptions & {
    showNow?: boolean;
    endpointFromMarkup?: boolean;
    original?: Node[];
};
/** Starts one thread, with options that win over the page's settings, and returns its controller. */
export declare function mountThread(element: HTMLElement, options?: InternalMountOptions): CommentsController;
/** Stops every thread inside `scope` (or that is `scope`). */
export declare function destroyThreads(scope: ParentNode): void;
export declare function getThread(root: Element): CommentsController | undefined;
/** Settings for every thread on the page, merged with any given before. Threads already drawn redraw. */
export declare function configureThreads(next: CommentsSettings): void;
/** Switches every thread's theme. */
export declare function setThreadsTheme(theme: CommentsTheme): void;
/**
 * With `darkSelector`, such as `html.dark`, every thread follows a site's own dark-mode toggle,
 * switching to dark while the selector matches `<html>` and back to light when it stops.
 */
export declare function watchDarkSelector(): void;
