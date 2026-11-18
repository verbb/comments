import { type CommentsEventName } from '@verbb/comments-core';
import type { CommentsEventMap } from '@verbb/comments-browser';
type Pascal<S extends string> = S extends `${infer Head}-${infer Tail}` ? `${Capitalize<Head>}${Pascal<Tail>}` : Capitalize<S>;
/** `comments:comment-created` as a prop name, `onCommentCreated`. */
export type CommentsEventPropName<K extends CommentsEventName> = K extends `comments:${infer Rest}` ? `on${Pascal<Rest>}` : never;
/**
 * A prop for every browser event, such as `onCommentCreated` for `comments:comment-created`. Each
 * gets the event itself, so a cancelable one such as `onBeforeSubmit` can call `preventDefault()`.
 */
export type CommentsEventProps = {
    [K in CommentsEventName as CommentsEventPropName<K>]?: (event: CommentsEventMap[K]) => void;
};
/** Every event name with its prop name. */
export declare const EVENT_PROPS: ReadonlyArray<readonly [CommentsEventName, keyof CommentsEventProps]>;
export {};
