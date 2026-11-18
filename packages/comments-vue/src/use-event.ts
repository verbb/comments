import type { CommentsEventMap } from '@verbb/comments-browser';
import type { CommentsEventName, ThreadStore, ThreadStoreEventDetail, ThreadStoreEventName } from '@verbb/comments-core';
import { getCurrentInstance, isRef, onMounted, onScopeDispose, toValue, watch, type MaybeRefOrGetter, type ShallowRef } from 'vue';

export type UseCommentsEventOptions = {
    /** Only events from the thread with this element ID, such as `comments-109-1`. */
    instanceId?: string;
    /** Where to listen. Defaults to the document, which hears every thread on the page. */
    target?: MaybeRefOrGetter<EventTarget | null | undefined>;
};

export type UseCommentsStoreEventOptions = {
    /** A thread from `useCommentsThread()`, or core's store, whose own events to hear. */
    thread: MaybeRefOrGetter<{ store: Readonly<ShallowRef<ThreadStore | null>> | ThreadStore | null } | ThreadStore | null>;
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
    let stop: (() => void) | null = null;

    const start = () => {
        if ('thread' in options) {
            stop = watch(() => {
                const thread = toValue(options.thread);
                if (!thread) return null;
                if ('getState' in thread) return thread;
                return isRef(thread.store) ? thread.store.value : thread.store;
            }, (store, _previous, onCleanup) => {
                const off = store?.on(name as ThreadStoreEventName, (detail) => (handler as (detail: ThreadStoreEventDetail) => void)(detail));
                onCleanup(() => off?.());
            }, { immediate: true });
            return;
        }

        stop = watch(() => toValue(options.target) ?? document, (target, _previous, onCleanup) => {
            const listener = (event: Event) => {
                if (options.instanceId && (event as CustomEvent<{ instanceId?: string }>).detail?.instanceId !== options.instanceId) return;

                (handler as (event: Event) => void)(event);
            };

            target.addEventListener(name, listener);
            onCleanup(() => target.removeEventListener(name, listener));
        }, { immediate: true });
    };

    if (getCurrentInstance()) onMounted(start);
    else if (typeof document !== 'undefined') start();

    onScopeDispose(() => stop?.());
}
