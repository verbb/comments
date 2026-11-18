import { createCommentsClient, createThreadStore, initialThreadState, selectReplies, selectRoots, } from '@verbb/comments-core';
import { computed, getCurrentInstance, onMounted, onScopeDispose, shallowRef, toValue, watch } from 'vue';
import { latestAuth, latestFetch } from './latest.js';
import { useCommentsSettings } from './plugin.js';
import { stableKey } from './stable.js';
const ACTIONS = ['load', 'loadMore', 'setSort', 'loadReplies', 'loadComment', 'refreshViewer', 'create', 'update', 'delete', 'vote', 'report', 'withdrawReport', 'follow', 'moderate'];
/**
 * A thread's state and actions from `@verbb/comments-core`'s store, as refs, for building a fully
 * custom interface. The store is made in the browser when the component mounts, so a server render
 * has the empty state and nothing is fetched there. Options can be a ref or a getter, and a new
 * owner makes a new store. Pair it with `useCommentForm()` for posting.
 */
export function useCommentsThread(options) {
    const settings = useCommentsSettings();
    const resolved = computed(() => {
        const value = toValue(options);
        return {
            ...value,
            endpoint: value.endpoint ?? settings.endpoint,
            auth: value.auth ?? settings.auth,
            fetch: value.fetch ?? settings.fetch,
            siteId: value.siteId ?? settings.siteId ?? null,
        };
    });
    const state = shallowRef(initialThreadState(resolved.value.sort ?? 'newest'));
    const store = shallowRef(null);
    const client = shallowRef(null);
    let stop = null;
    const start = () => {
        // A new sign-in provider or fetch of the same kind is taken over without a new store, so one
        // a getter makes afresh doesn't start the thread again; a different profile does.
        stop = watch(() => stableKey({ ...resolved.value, auth: undefined, fetch: undefined, autoLoad: undefined, profile: resolved.value.auth?.profile ?? null }), (_key, _previous, onCleanup) => {
            const current = resolved.value;
            const auth = current.auth;
            const following = auth ? latestAuth(() => resolved.value.auth ?? auth) : null;
            // A new provider is followed at once, so its reset() on sign-out reaches the store.
            const stopFollowing = watch(() => resolved.value.auth, () => following?.sync(), { flush: 'sync' });
            const nextClient = createCommentsClient({
                endpoint: current.endpoint ?? defaultEndpoint(),
                siteId: current.siteId,
                auth: following ?? undefined,
                fetch: latestFetch(() => resolved.value.fetch),
            });
            const next = createThreadStore(nextClient, { ownerId: current.ownerId, ownerUri: current.ownerUri, siteId: current.siteId, sort: current.sort, limit: current.limit, viewer: current.viewer });
            const unsubscribe = next.subscribe((value) => {
                state.value = value;
            });
            store.value = next;
            client.value = nextClient;
            state.value = next.getState();
            if (current.autoLoad ?? true)
                next.load().catch(() => undefined);
            onCleanup(() => {
                stopFollowing();
                unsubscribe();
                next.destroy();
                if (store.value === next)
                    store.value = null;
            });
        }, { immediate: true });
    };
    // Only in the browser: a component's store starts once it's mounted, so a server render fetches nothing.
    if (getCurrentInstance())
        onMounted(start);
    else if (typeof document !== 'undefined')
        start();
    onScopeDispose(() => stop?.());
    const actions = Object.fromEntries(ACTIONS.map((name) => [name, (...args) => {
            const target = store.value;
            if (!target)
                return Promise.reject(new Error(`Comments: ${name}() was called before the thread mounted in the browser.`));
            return target[name](...args);
        }]));
    return {
        ...actions,
        state,
        roots: computed(() => selectRoots(state.value)),
        replies: (uid) => selectReplies(state.value, uid),
        store,
        client,
    };
}
/** The data API on the page's own origin, as the plugin serves it. */
export function defaultEndpoint() {
    return new URL('/actions/comments/api', globalThis.location?.href ?? 'http://localhost/').toString();
}
