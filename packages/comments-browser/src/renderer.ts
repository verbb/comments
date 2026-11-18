import { adoptRenderer } from './start.js';
import * as threadsModule from './threads.js';
import { getThread, mountThread as mountRendererThread, type InternalMountOptions } from './threads.js';
import type { CommentsApp, CommentsController } from './types.js';

// The loader in the main entry hands `configure()`'s settings to the renderer once it has it. A page
// that imports this entry directly has it from the start.
adoptRenderer(threadsModule);

/**
 * The renderer itself, for code that mounts threads synchronously, such as the web components,
 * React and Vue packages. Most sites start Comments with `startComments()` from the package's main
 * entry, which loads this when a thread is about to be needed.
 */
export { destroyThreads, getThread } from './threads.js';
export type { ThreadController } from './controller.js';

let warned = false;

/**
 * Starts one thread on an element and returns its controller. If the comments script running on
 * the page (another copy) has already started it, or is waiting to, that thread is stopped first,
 * so the one drawn here has the options given here, such as an adapter's overrides and sign-in.
 * The thread this draws carries `data-comments-js`, which every copy's loader leaves alone, and an
 * adapter's server markup can carry `data-comments-adapter` so the script never starts it at all.
 * When mounting threads yourself, turn off **Load Bundled JavaScript** so the page runs one copy.
 */
export function mountThread(element: HTMLElement, options: InternalMountOptions = {}): CommentsController {
    const running = (globalThis as { [key: symbol]: CommentsApp | undefined })[Symbol.for('verbb.comments')];

    if (running && !warned) {
        warned = true;
        console.warn('Comments: a thread is being mounted through @verbb/comments-browser/renderer while the comments script is also running on this page. Turn off Load Bundled JavaScript (outputDefaultJs) in the Comments settings, so the page runs one copy.');
    }

    // The running copy's own thread, or one it's waiting to draw, gives way to this one.
    if (running && !getThread(element) && (running.get(element) || element.dataset.commentsPhase)) running.destroy(element);

    return mountRendererThread(element, options);
}
