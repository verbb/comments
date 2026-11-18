import type { CommentsEventMap } from '@verbb/comments-browser';
import type { CommentsEventName, ThreadStore, ThreadStoreEventDetail, ThreadStoreEventName } from '@verbb/comments-core';
import { useEffect, useRef, type RefObject } from 'react';

export type UseCommentsEventOptions = {
    /** Only events from the thread with this element ID, such as `comments-109-1`. */
    instanceId?: string;
    /** Where to listen. Defaults to the document, which hears every thread on the page. */
    target?: EventTarget | RefObject<EventTarget | null> | null;
};

export type UseCommentsStoreEventOptions = {
    /** A thread from `useCommentsThread()`, or core's store, whose own events to hear. */
    thread: { store: ThreadStore | null } | ThreadStore | null;
};

/**
 * Listens for a Comments event while the component is mounted. By default it hears the browser
 * events every thread on the page dispatches, such as `comments:comment-created`, as the
 * `CustomEvent` itself. With `thread`, it hears that headless thread's store instead, which emits
 * the events about its data under the same names and `detail` keys, typed by `ThreadStoreEventMap`.
 */
export function useCommentsEvent<K extends CommentsEventName>(name: K, handler: (event: CommentsEventMap[K]) => void, options?: UseCommentsEventOptions): void;
export function useCommentsEvent<K extends ThreadStoreEventName>(name: K, handler: (detail: ThreadStoreEventDetail<K>) => void, options: UseCommentsStoreEventOptions): void;
export function useCommentsEvent(name: CommentsEventName, handler: (value: never) => void, options: UseCommentsEventOptions | UseCommentsStoreEventOptions = {}): void {
    const latest = useRef(handler);
    const attached = useRef<{ source: unknown; name: string; instanceId: string | undefined; stop: () => void } | null>(null);

    useEffect(() => {
        latest.current = handler;
    });

    // After every commit, so a ref's element that changes, or a store that arrives, is followed;
    // the listener is only replaced when what it listens to changes.
    useEffect(() => {
        const thread = 'thread' in options ? options.thread : undefined;
        const store = !thread ? null : 'getState' in thread ? thread : thread.store;
        const target = 'thread' in options ? null : options.target;
        const element = 'thread' in options ? null : (target && 'current' in target ? target.current : target ?? (typeof document === 'undefined' ? null : document));
        const source = 'thread' in options ? store : element;
        const instanceId = 'thread' in options ? undefined : options.instanceId;

        if (attached.current && attached.current.source === source && attached.current.name === name && attached.current.instanceId === instanceId) return;

        attached.current?.stop();
        attached.current = null;

        if (!source) return;

        let stop: () => void;

        if (store) {
            stop = store.on(name as ThreadStoreEventName, (detail) => (latest.current as (detail: ThreadStoreEventDetail) => void)(detail));
        } else {
            const listener = (event: Event) => {
                if (instanceId && (event as CustomEvent<{ instanceId?: string }>).detail?.instanceId !== instanceId) return;

                (latest.current as (event: Event) => void)(event);
            };

            element!.addEventListener(name, listener);
            stop = () => element!.removeEventListener(name, listener);
        }

        attached.current = { source, name, instanceId, stop };
    });

    useEffect(() => () => {
        attached.current?.stop();
        attached.current = null;
    }, []);
}
