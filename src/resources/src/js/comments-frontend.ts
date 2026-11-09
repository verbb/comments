import '../css/comments-frontend.css';

import { bindInstance } from './instance-controller';
import { CONTRACT_VERSION } from './types/envelope';

export type CommentsController = {
  root: HTMLElement;
  conversationUid: string;
  refresh: (options?: { reason?: string }) => void;
  openComposer: (options: { mode: string; commentUid?: string }) => void;
  closeComposer: (options?: { reason?: string }) => void;
  destroy: () => void;
};

const controllers = new WeakMap<HTMLElement, CommentsController>();
let observer: MutationObserver | null = null;

function dispatch(root: HTMLElement, name: string, detail: Record<string, unknown>): void {
  root.dispatchEvent(new CustomEvent(name, {
    bubbles: true,
    composed: true,
    detail: {
      contractVersion: CONTRACT_VERSION,
      root,
      instanceId: root.dataset.commentsInstance ?? '',
      conversationUid: root.dataset.commentsConversationUid ?? '',
      source: 'api',
      ...detail,
    },
  }));
}

function apiBaseFromRoot(root: HTMLElement): string {
  const fromDataset = root.dataset.commentsApiBase;
  if (fromDataset) {
    return fromDataset;
  }

  // Default site-relative API root when not injected by Twig yet.
  return '/comments/api/v1/';
}

function createController(root: HTMLElement): CommentsController {
  const controller = bindInstance(root, { apiBase: apiBaseFromRoot(root) });
  controllers.set(root, controller);
  dispatch(root, 'comments:mounted', { controller });
  return controller;
}

function mountRoot(root: Element): CommentsController | null {
  if (!(root instanceof HTMLElement)) {
    return null;
  }

  if (root.dataset.commentsContract !== String(CONTRACT_VERSION)) {
    return null;
  }

  if (controllers.has(root)) {
    return controllers.get(root) ?? null;
  }

  return createController(root);
}

function findRoots(scope: ParentNode = document): HTMLElement[] {
  const roots = Array.from(scope.querySelectorAll<HTMLElement>('[data-comments-root][data-comments-contract]'));
  if (scope instanceof HTMLElement && scope.matches('[data-comments-root][data-comments-contract]')) roots.unshift(scope);
  return roots;
}

export const Comments = {
  mount(scope?: ParentNode): CommentsController[] {
    const mounted: CommentsController[] = [];

    for (const root of findRoots(scope ?? document)) {
      const controller = mountRoot(root);
      if (controller) {
        mounted.push(controller);
      }
    }

    return mounted;
  },

  destroy(scope?: ParentNode): void {
    const roots = scope ? findRoots(scope) : findRoots(document);

    for (const root of roots) {
      const controller = controllers.get(root);
      controller?.destroy();
      controllers.delete(root);
    }
  },

  get(root: Element): CommentsController | undefined {
    return root instanceof HTMLElement ? controllers.get(root) : undefined;
  },
};

function ensureObserver(): void {
  if (observer || typeof MutationObserver === 'undefined') {
    return;
  }

  observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node instanceof HTMLElement) {
          if (node.matches('[data-comments-root][data-comments-contract]')) {
            mountRoot(node);
          } else {
            Comments.mount(node);
          }
        }
      }

      for (const node of mutation.removedNodes) {
        if (node instanceof HTMLElement) {
          Comments.destroy(node);
        }
      }
    }
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
}

function onReady(): void {
  Comments.mount();
  ensureObserver();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', onReady);
} else {
  onReady();
}

// Turbo Drive swaps page bodies without a full reload — remount comment instances.
document.addEventListener('turbo:before-cache', () => {
  Comments.destroy();
});

document.addEventListener('turbo:load', () => {
  Comments.mount();
});

Object.freeze(Comments);
(window as unknown as { Comments: typeof Comments }).Comments = Comments;

export default Comments;
