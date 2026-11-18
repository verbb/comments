/**
 * Every `comments:*` event name. The browser package dispatches them on the thread's element, and
 * the thread store emits the ones about its data, `ThreadStoreEventName`, to subscribers without a
 * page, with the same `detail` keys.
 */
export const COMMENTS_EVENT_NAMES = [
    'comments:mounted',
    'comments:destroyed',
    'comments:before-render',
    'comments:before-action',
    'comments:before-submit',
    'comments:request-start',
    'comments:request-end',
    'comments:request-error',
    'comments:viewer-loaded',
    'comments:roots-loaded',
    'comments:replies-loaded',
    'comments:sorted',
    'comments:editor-opened',
    'comments:editor-closed',
    'comments:comment-created',
    'comments:comment-updated',
    'comments:comment-deleted',
    'comments:comment-moderated',
    'comments:vote-changed',
    'comments:report-changed',
    'comments:follow-changed',
] as const;

export type CommentsEventName = (typeof COMMENTS_EVENT_NAMES)[number];
