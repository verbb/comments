import { COMMENTS_EVENT_NAMES } from '@verbb/comments-core';
/** Every event name with its Vue event name. */
export const EVENT_EMITS = COMMENTS_EVENT_NAMES.map((name) => [name, name.slice('comments:'.length)]);
/** The component's `emits` option, which also types `@comment-created` and the rest in templates. */
export const THREAD_EMITS = Object.fromEntries(EVENT_EMITS.map(([, emit]) => [emit, null]));
