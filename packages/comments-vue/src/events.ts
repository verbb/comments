import { COMMENTS_EVENT_NAMES, type CommentsEventName } from '@verbb/comments-core';
import type { CommentsEventMap } from '@verbb/comments-browser';

/** `comments:comment-created` as a Vue event, `comment-created`. */
export type CommentsEmitName<K extends CommentsEventName> = K extends `comments:${infer Rest}` ? Rest : never;

/**
 * An event for every browser event, such as `@comment-created` for `comments:comment-created`.
 * Each gets the event itself, so a cancelable one such as `@before-submit` can call `preventDefault()`.
 */
export type CommentsThreadEmits = {
    [K in CommentsEventName as CommentsEmitName<K>]: (event: CommentsEventMap[K]) => true;
};

/** Every event name with its Vue event name. */
export const EVENT_EMITS: ReadonlyArray<readonly [CommentsEventName, string]> = COMMENTS_EVENT_NAMES.map((name) => [name, name.slice('comments:'.length)] as const);

/** The component's `emits` option, which also types `@comment-created` and the rest in templates. */
export const THREAD_EMITS = Object.fromEntries(EVENT_EMITS.map(([, emit]) => [emit, null])) as unknown as CommentsThreadEmits;
