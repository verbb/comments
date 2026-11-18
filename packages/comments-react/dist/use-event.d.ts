import type { CommentsEventMap } from '@verbb/comments-browser';
import type { CommentsEventName, ThreadStore, ThreadStoreEventDetail, ThreadStoreEventName } from '@verbb/comments-core';
import { type RefObject } from 'react';
export type UseCommentsEventOptions = {
    /** Only events from the thread with this element ID, such as `comments-109-1`. */
    instanceId?: string;
    /** Where to listen. Defaults to the document, which hears every thread on the page. */
    target?: EventTarget | RefObject<EventTarget | null> | null;
};
export type UseCommentsStoreEventOptions = {
    /** A thread from `useCommentsThread()`, or core's store, whose own events to hear. */
    thread: {
        store: ThreadStore | null;
    } | ThreadStore | null;
};
/**
 * Listens for a Comments event while the component is mounted. By default it hears the browser
 * events every thread on the page dispatches, such as `comments:comment-created`, as the
 * `CustomEvent` itself. With `thread`, it hears that headless thread's store instead, which emits
 * the events about its data under the same names and `detail` keys, typed by `ThreadStoreEventMap`.
 */
export declare function useCommentsEvent<K extends CommentsEventName>(name: K, handler: (event: CommentsEventMap[K]) => void, options?: UseCommentsEventOptions): void;
export declare function useCommentsEvent<K extends ThreadStoreEventName>(name: K, handler: (detail: ThreadStoreEventDetail<K>) => void, options: UseCommentsStoreEventOptions): void;
