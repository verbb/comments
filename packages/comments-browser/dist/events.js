import { COMMENTS_API_VERSION } from '@verbb/comments-core';
// Sends Comments 3's event names. The renderer sets it, so the mapping isn't in every chunk that
// dispatches events, such as the forms chunk.
let legacyDispatcher = null;
export function setLegacyEventDispatcher(dispatcher) {
    legacyDispatcher = dispatcher;
}
/**
 * Dispatches a `comments:*` event from a thread's root, with the detail every event shares, and
 * returns false if a listener cancelled it.
 */
export function dispatchCommentsEvent(root, name, detail, options = {}) {
    const create = () => new CustomEvent(name, {
        bubbles: true,
        composed: true,
        cancelable: options.cancelable ?? false,
        detail: {
            apiVersion: COMMENTS_API_VERSION,
            root,
            instanceId: options.controller?.instanceId ?? root.id,
            conversationUid: options.controller?.conversationUid ?? '',
            controller: options.controller,
            source: options.source ?? 'api',
            ...detail,
        },
    });
    const event = create();
    const allowed = root.dispatchEvent(event);
    // Comments 3's names too, for a thread Craft renders in compatibility mode, once the renderer has
    // handed over the mapping.
    if (allowed) {
        legacyDispatcher?.(root, name, event.detail);
    }
    // A root that's already out of the page, such as one an SPA swapped away, can't bubble to the
    // document, where the docs say to listen. Listeners there get their own copy instead.
    if (!root.isConnected && !options.cancelable) {
        document.dispatchEvent(create());
    }
    return allowed;
}
