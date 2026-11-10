import '../css/comments-frontend.css';

import { dispatchCommentsEvent } from './events';
import { bindInstance } from './instance-controller';
import { CONTRACT_VERSION } from './types/envelope';
import type { CommentsController, CommentsRuntime } from './types/public';

export type * from './types/public';

const ROOT_SELECTOR = '[data-comments-root][data-comments-contract]';
// Shared by every copy of this file on the page, whichever build it came from, so a second copy can
// find the first. A symbol can't clash with a site's own `window.Comments`.
const RUNTIME_KEY = Symbol.for('verbb.comments.runtime');

type RuntimeHost = Window & { [RUNTIME_KEY]?: CommentsRuntime };

function apiBaseFromRoot(root: HTMLElement): string {
  const fromDataset = root.dataset.commentsApiBase;
  if (fromDataset) {
    return fromDataset;
  }

  // The template always gives the action URL, which knows the site's action trigger. This only
  // covers an overridden template that leaves it out.
  return new URL('/actions/comments/frontend-api', window.location.href).toString();
}

function findRoots(scope: ParentNode = document): HTMLElement[] {
  const roots = Array.from(scope.querySelectorAll<HTMLElement>(ROOT_SELECTOR));
  if (scope instanceof HTMLElement && scope.matches(ROOT_SELECTOR)) roots.unshift(scope);
  return roots;
}

/**
 * Whether the runtime starts this thread by itself. `data-comments-auto-mount="false"` on the root or
 * any element around it, `<html>` included, leaves it for the site to start with `Comments.mount()`.
 */
function autoMounts(root: HTMLElement): boolean {
  return root.closest('[data-comments-auto-mount]')?.getAttribute('data-comments-auto-mount') !== 'false';
}

function createRuntime(): CommentsRuntime {
  const controllers = new WeakMap<HTMLElement, CommentsController>();
  let observer: MutationObserver | null = null;

  function mountRoot(root: Element): CommentsController | null {
    if (!(root instanceof HTMLElement)) {
      return null;
    }

    if (root.dataset.commentsContract !== String(CONTRACT_VERSION)) {
      return null;
    }

    const existing = controllers.get(root);
    if (existing) {
      return existing;
    }

    const controller = bindInstance(root, { apiBase: apiBaseFromRoot(root) });
    controllers.set(root, controller);
    dispatchCommentsEvent(root, 'comments:mounted', {}, { controller });
    return controller;
  }

  const runtime: CommentsRuntime = {
    mount(scope) {
      const mounted: CommentsController[] = [];

      for (const root of findRoots(scope ?? document)) {
        const controller = mountRoot(root);
        if (controller) {
          mounted.push(controller);
        }
      }

      return mounted;
    },

    destroy(scope) {
      for (const root of findRoots(scope ?? document)) {
        controllers.get(root)?.destroy();
        controllers.delete(root);
      }
    },

    get(root) {
      return root instanceof HTMLElement ? controllers.get(root) : undefined;
    },
  };

  /** What starts without being asked: the threads in `scope` that haven't opted out. */
  function autoMount(scope: ParentNode = document): void {
    for (const root of findRoots(scope)) {
      if (autoMounts(root)) {
        mountRoot(root);
      }
    }
  }

  function ensureObserver(): void {
    if (observer || typeof MutationObserver === 'undefined') {
      return;
    }

    observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (node instanceof HTMLElement) {
            autoMount(node);
          }
        }

        // A thread a site started itself is still stopped here, as nothing else would clean it up.
        for (const node of mutation.removedNodes) {
          if (node instanceof HTMLElement) {
            runtime.destroy(node);
          }
        }
      }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  function onReady(): void {
    autoMount();
    ensureObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }

  // Turbo Drive swaps page bodies without a full reload, so threads are stopped before Turbo caches
  // the page and started again on the new one.
  document.addEventListener('turbo:before-cache', () => {
    runtime.destroy();
  });

  document.addEventListener('turbo:load', () => {
    autoMount();
  });

  return Object.freeze(runtime);
}

const host = window as RuntimeHost;
const running = host[RUNTIME_KEY];

if (running) {
  // Two copies would both start every thread and handle every click, so a vote would count twice
  // and cancel itself out. The one already running keeps the page, and this copy hands it over.
  console.warn(
    'Comments: the comments script has loaded twice on this page, so this copy won\'t start any threads. '
    + 'If your site bundles comments-frontend.js itself, turn off Load Bundled JavaScript (outputDefaultJs) in the Comments settings.',
  );
}

export const Comments: CommentsRuntime = running ?? createRuntime();

if (!running) {
  host[RUNTIME_KEY] = Comments;
  window.Comments = Comments;
}

export default Comments;
