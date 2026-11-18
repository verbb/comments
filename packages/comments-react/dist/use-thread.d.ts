import { type CommentData, type CommentsClient, type SignInProvider, type SortOrder, type ThreadState, type ThreadStore } from '@verbb/comments-core';
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
type StoreActions = Pick<ThreadStore, 'load' | 'loadMore' | 'setSort' | 'loadReplies' | 'loadComment' | 'refreshViewer' | 'create' | 'update' | 'delete' | 'vote' | 'report' | 'withdrawReport' | 'follow' | 'moderate'>;
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
/**
 * A thread's state and actions from `@verbb/comments-core`'s store, for building a fully custom
 * interface. The store is made in the browser when the component mounts, so a server render has the
 * empty state and nothing is fetched there. Pair it with `useCommentForm()` for posting.
 */
export declare function useCommentsThread(options: UseCommentsThreadOptions): UseCommentsThreadResult;
/** The data API on the page's own origin, as the plugin serves it. */
export declare function defaultEndpoint(): string;
export {};
