import { type InternalMountOptions } from './threads.js';
import type { CommentsController } from './types.js';
/**
 * The renderer itself, for code that mounts threads synchronously, such as the web components,
 * React and Vue packages. Most sites start Comments with `startComments()` from the package's main
 * entry, which loads this when a thread is about to be needed.
 */
export { destroyThreads, getThread } from './threads.js';
export type { ThreadController } from './controller.js';
/**
 * Starts one thread on an element and returns its controller. If the comments script running on
 * the page (another copy) has already started it, or is waiting to, that thread is stopped first,
 * so the one drawn here has the options given here, such as an adapter's overrides and sign-in.
 * The thread this draws carries `data-comments-js`, which every copy's loader leaves alone, and an
 * adapter's server markup can carry `data-comments-adapter` so the script never starts it at all.
 * When mounting threads yourself, turn off **Load Bundled JavaScript** so the page runs one copy.
 */
export declare function mountThread(element: HTMLElement, options?: InternalMountOptions): CommentsController;
