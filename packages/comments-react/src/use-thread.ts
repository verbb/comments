import {
    createCommentsClient,
    createThreadStore,
    initialThreadState,
    selectReplies,
    selectRoots,
    type CommentData,
    type CommentsClient,
    type SignInProvider,
    type SortOrder,
    type ThreadState,
    type ThreadStore,
} from '@verbb/comments-core';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import { latestAuth, latestFetch, type LatestAuth } from './latest.js';
import { useCommentsSettings } from './settings.js';
import { stableKey } from './stable.js';

export type UseCommentsThreadOptions = {
    ownerId?: number;
    /** The owner's URI, for headless sites that route by URI. */
    ownerUri?: string;
    siteId?: number | null;
    /** Defaults to the provider's endpoint, then the page's own origin. */
    endpoint?: string;
    /** Defaults to the provider's. */
    auth?: SignInProvider;
    sort?: SortOrder;
    /** Root comments per page, 1 to 50. */
    limit?: number;
    /** Make the private `viewer` read alongside each public read, for votes, permissions and the person's own comments. Defaults to true. */
    viewer?: boolean;
    /** Load the thread as soon as the component mounts. Defaults to true; with false, call `load()` yourself. */
    autoLoad?: boolean;
    fetch?: typeof fetch;
};

type StoreActions = Pick<ThreadStore,
    'load' | 'loadMore' | 'setSort' | 'loadReplies' | 'loadComment' | 'refreshViewer' | 'create' | 'update' | 'delete' | 'vote' | 'report' | 'withdrawReport' | 'follow' | 'moderate'>;

export type UseCommentsThreadResult = StoreActions & {
    /** The thread's state, which changes with every load, write and answer. */
    state: ThreadState;
    /** The root comments to show, in order. */
    roots: CommentData[];
    /** One comment's loaded replies, in order. */
    replies(uid: string): CommentData[];
    /** Core's store, once the component has mounted in the browser; null on the server. */
    store: ThreadStore | null;
    client: CommentsClient | null;
};

const ACTIONS = ['load', 'loadMore', 'setSort', 'loadReplies', 'loadComment', 'refreshViewer', 'create', 'update', 'delete', 'vote', 'report', 'withdrawReport', 'follow', 'moderate'] as const;

const noSubscription = () => () => undefined;
const useBrowserLayoutEffect = typeof document === 'undefined' ? useEffect : useLayoutEffect;

/**
 * A thread's state and actions from `@verbb/comments-core`'s store, for building a fully custom
 * interface. The store is made in the browser when the component mounts, so a server render has the
 * empty state and nothing is fetched there. Pair it with `useCommentForm()` for posting.
 */
export function useCommentsThread(options: UseCommentsThreadOptions): UseCommentsThreadResult {
    const settings = useCommentsSettings();
    const endpoint = options.endpoint ?? settings.endpoint;
    const auth = options.auth ?? settings.auth;
    const fetcher = options.fetch ?? settings.fetch;
    const siteId = options.siteId ?? settings.siteId ?? null;
    const sort = options.sort ?? 'newest';
    // A new sign-in provider or fetch of the same kind is taken over without a new store, so one
    // made afresh on every render doesn't start the thread again; a different profile does.
    const key = stableKey({ endpoint, siteId, ownerId: options.ownerId, ownerUri: options.ownerUri, sort: options.sort, limit: options.limit, viewer: options.viewer, profile: auth?.profile ?? null });
    const initial = useMemo(() => initialThreadState(sort), [key]);
    const [current, setCurrent] = useState<{ store: ThreadStore; client: CommentsClient } | null>(null);
    // From the last committed render, never one React threw away.
    const latest = useRef({ auth, fetch: fetcher, autoLoad: options.autoLoad ?? true, store: null as ThreadStore | null });
    const following = useRef<LatestAuth | null>(null);
    useBrowserLayoutEffect(() => {
        latest.current = { auth, fetch: fetcher, autoLoad: options.autoLoad ?? true, store: current?.store ?? null };
        following.current?.sync();
    });

    useEffect(() => {
        const first = latest.current;
        following.current = first.auth ? latestAuth(() => latest.current.auth ?? first.auth!) : null;
        const client = createCommentsClient({
            endpoint: endpoint ?? defaultEndpoint(),
            siteId,
            auth: following.current ?? undefined,
            fetch: latestFetch(() => latest.current.fetch),
        });
        const store = createThreadStore(client, { ownerId: options.ownerId, ownerUri: options.ownerUri, siteId, sort: options.sort, limit: options.limit, viewer: options.viewer });

        setCurrent({ store, client });

        if (first.autoLoad) store.load().catch(() => undefined);

        return () => {
            store.destroy();
            setCurrent((previous) => (previous?.store === store ? null : previous));
        };
    }, [key]);

    const store = current?.store ?? null;
    const state = useSyncExternalStore(store ? store.subscribe : noSubscription, store ? store.getState : () => initial, () => initial);

    const actions = useMemo(() => Object.fromEntries(ACTIONS.map((name) => [name, (...args: unknown[]) => {
        const target = latest.current.store;

        if (!target) return Promise.reject(new Error(`Comments: ${name}() was called before the thread mounted in the browser.`));

        return (target[name] as (...rest: unknown[]) => Promise<unknown>)(...args);
    }])) as unknown as StoreActions, []);
    const replies = useCallback((uid: string) => selectReplies(state, uid), [state]);
    const roots = useMemo(() => selectRoots(state), [state]);

    return { ...actions, state, roots, replies, store, client: current?.client ?? null };
}

/** The data API on the page's own origin, as the plugin serves it. */
export function defaultEndpoint(): string {
    return new URL('/actions/comments/api', globalThis.location?.href ?? 'http://localhost/').toString();
}
