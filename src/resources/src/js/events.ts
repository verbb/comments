import type { CommentsController, CommentsEventDetailMap, CommentsEventName } from './types/public';
import { CONTRACT_VERSION } from './types/envelope';

type DispatchOptions = {
  controller?: CommentsController;
  source?: 'api' | 'user';
  cancelable?: boolean;
};

/**
 * Dispatch a `comments:*` event from a thread's root, with the detail every event shares. Returns
 * false if a listener cancelled it.
 */
export function dispatchCommentsEvent<K extends CommentsEventName>(
  root: HTMLElement,
  name: K,
  detail: CommentsEventDetailMap[K],
  options: DispatchOptions = {},
): boolean {
  const create = () => new CustomEvent(name, {
    bubbles: true,
    composed: true,
    cancelable: options.cancelable ?? false,
    detail: {
      contractVersion: CONTRACT_VERSION,
      root,
      instanceId: root.dataset.commentsInstance ?? '',
      conversationUid: root.dataset.commentsConversationUid ?? '',
      controller: options.controller,
      source: options.source ?? 'api',
      ...detail,
    },
  });

  const allowed = root.dispatchEvent(create());

  // A root that's already out of the page, such as one an SPA swapped away, can't bubble to the
  // document, where the docs say to listen. Listeners there get their own copy instead.
  if (!root.isConnected && !options.cancelable) {
    document.dispatchEvent(create());
  }

  return allowed;
}
