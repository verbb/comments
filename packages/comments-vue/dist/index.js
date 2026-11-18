/**
 * Vue components and composables for Comments. `<CommentsThread>` draws a thread with
 * `@verbb/comments-browser`, the composables build a custom interface on `@verbb/comments-core`,
 * and `@verbb/comments-vue/server` has the helpers for server rendering.
 */
export { CommentsThread } from './thread.js';
export { COMMENTS_SETTINGS, CommentsPlugin, provideCommentsSettings, useCommentsSettings } from './plugin.js';
export { useCommentsThread } from './use-thread.js';
export { useCommentForm } from './use-form.js';
export { useCommentsEvent } from './use-event.js';
export { templatePart } from './overrides.js';
