import type { CommentTarget, CommentsClient, CreateInput, WriteOptions } from './client.js';
import { CommentsRequestError } from './errors.js';
import { type EvidenceProvider, type ProtectionFlow } from './protection.js';
import type { UnavailableAction } from './sign-in.js';
import type { CommentData, CommentState, CommentResponse, CommentViewerData, ContentInput, ConversationData, CreateResponse, EditorData, FollowResponse, ModerateResponse, ModerationAction, ModerationSummaryData, DeleteResponse, ReportResponse, SortOrder, UpdateResponse, ViewerData, ViewerEditorData, VoteResponse, VoteValue } from './types.js';
export type ThreadStoreOptions = {
    ownerId?: number;
    ownerUri?: string;
    siteId?: number | null;
    sort?: SortOrder;
    /** Root comments per page, 1 to 50. */
    limit?: number;
    /** Make the private `viewer` read alongside each public read. Defaults to true. */
    viewer?: boolean;
    /** Produces Protect evidence for posting and editing. Without it, writes carry none. */
    evidence?: EvidenceProvider;
};
export type RepliesState = {
    /** Loaded replies, in order. */
    uids: string[];
    /** Every approved reply, loaded or not. */
    count: number;
    next: string | null;
    loading: boolean;
};
export type ThreadState = {
    status: 'idle' | 'loading' | 'ready' | 'error';
    /** Why the thread couldn't load. Errors from actions are thrown to their caller instead. */
    error: CommentsRequestError | null;
    sort: SortOrder;
    /** The drawn thread's `threadVersion`, the stamp of the public data it came from. */
    threadVersion: string | null;
    conversation: ConversationData | null;
    editor: EditorData | null;
    labels: Record<string, string>;
    locale: string | null;
    /** Every loaded comment by UID. Each one's `replies.items` is empty; `replies` holds the order. */
    comments: Record<string, CommentData>;
    /** Root comment UIDs, in order. */
    roots: string[];
    /** The cursor for the next page of roots. */
    next: string | null;
    loadingMore: boolean;
    replies: Record<string, RepliesState>;
    viewer: ViewerData | null;
    viewerEditor: ViewerEditorData | null;
    /** What the person may do with each comment, by UID. */
    permissions: Record<string, CommentViewerData>;
    moderation: ModerationSummaryData | null;
    /** Actions in flight, such as `vote:<uid>`, so a control can show it's busy. */
    busy: Record<string, true>;
    /** Actions the sign-in never allows, such as editing and mentions for a tier 3 guest. */
    unavailable: UnavailableAction[];
};
/**
 * The events a thread store emits, with what each one's `detail` adds to `apiVersion`. They're the
 * browser package's events about the thread's data, with the same names and keys, plus a few the
 * page doesn't need, such as `count` and `cursor`.
 */
export interface ThreadStoreEventMap {
    'comments:request-start': {
        /** What the request is for, such as `thread`, `vote` or `create`. */
        reason: string;
    };
    'comments:request-end': {
        reason: string;
    };
    'comments:request-error': {
        reason: string;
        /** A message that's safe to show a visitor. */
        message: string;
        /** From a `CommentsRequestError`, as the browser event has them. */
        category?: string;
        code?: string;
        fieldErrors?: Record<string, string[]>;
        recoverable?: boolean;
        /** What went wrong, usually a `CommentsRequestError`. */
        error: unknown;
    };
    'comments:roots-loaded': {
        /** The cursor the page was loaded from, or null for the first page. */
        cursor: string | null;
        count: number;
    };
    'comments:viewer-loaded': {
        /** Who's reading and what they may do, as the thread now has it. */
        viewer: ViewerData | null;
    };
    'comments:sorted': {
        sort: SortOrder;
    };
    'comments:replies-loaded': {
        commentUid: string;
        count: number;
    };
    'comments:comment-created': {
        commentUid: string;
        parentUid: string | null;
        moderationState: 'approved' | 'pending';
        replayed: boolean;
    };
    'comments:comment-updated': {
        commentUid: string;
    };
    'comments:comment-deleted': {
        commentUid: string;
    };
    'comments:comment-moderated': {
        commentUid: string;
        /** The number in the comment's permalink, `comment-1446`, or null. */
        commentId: string | null;
        moderationAction: ModerationAction;
        /** The comment's state afterwards. */
        state: CommentState;
    };
    'comments:vote-changed': {
        commentUid: string;
        commentId: string | null;
        /** `upvote`, `downvote`, or `null` when a vote was taken back. */
        vote: VoteValue | null;
    };
    'comments:report-changed': {
        commentUid: string;
        commentId: string | null;
        /** False when the report was withdrawn. */
        reported: boolean;
    };
    'comments:follow-changed': {
        following: boolean;
    };
}
export type ThreadStoreEventName = keyof ThreadStoreEventMap;
export type ThreadStoreEventDetail<K extends ThreadStoreEventName = ThreadStoreEventName> = {
    apiVersion: number;
} & ThreadStoreEventMap[K];
export interface ThreadStore {
    getState(): ThreadState;
    /** Called after every change. Returns a function that unsubscribes. */
    subscribe(listener: (state: ThreadState) => void): () => void;
    /** The thread's own events, named and shaped as the browser package's are, for code without a page. */
    on<K extends ThreadStoreEventName>(name: K, listener: (detail: ThreadStoreEventDetail<K>) => void): () => void;
    /** Loads the first page, and the viewer's window alongside it. */
    load(): Promise<void>;
    /** Loads the next page of roots. */
    loadMore(): Promise<void>;
    /** Changes the sort and loads again from the start. */
    setSort(sort: SortOrder): Promise<void>;
    /** Loads the next page of one comment's replies. */
    loadReplies(uid: string): Promise<void>;
    /**
     * Loads one comment and its ancestors into the thread, for a deep link: by UID or by the number
     * in its permalink, or from a `comment` answer already fetched, such as one looked up once for
     * several threads on a page. `null` means that lookup found it isn't public, so only the viewer
     * read is asked.
     */
    loadComment(target: CommentTarget, prefetched?: CommentResponse | null): Promise<void>;
    /** Reads the viewer again for everything loaded, such as after signing in. */
    refreshViewer(): Promise<void>;
    create(input: Omit<CreateInput, 'ownerId'> & {
        ownerId?: number;
    }, options?: WriteOptions & {
        evidence?: EvidenceProvider;
    }): Promise<CreateResponse>;
    update(uid: string, input: {
        content: ContentInput;
        fields?: Record<string, unknown>;
    }, options?: WriteOptions & {
        evidence?: EvidenceProvider;
    }): Promise<UpdateResponse>;
    /** Deletes the person's own comment. */
    delete(uid: string, options?: WriteOptions): Promise<DeleteResponse>;
    vote(uid: string, vote: VoteValue | null, options?: WriteOptions): Promise<VoteResponse>;
    report(uid: string, input: {
        reason: string;
        details?: string | null;
    }, options?: WriteOptions): Promise<ReportResponse>;
    withdrawReport(uid: string, options?: WriteOptions): Promise<ReportResponse>;
    follow(following: boolean, email?: string, options?: WriteOptions): Promise<FollowResponse>;
    moderate(uid: string, action: ModerationAction, input?: {
        reason?: string | null;
        note?: string | null;
    }, options?: WriteOptions): Promise<ModerateResponse>;
    /** The protection flow for new comments and replies, or for editing one comment. */
    protection(uid?: string): ProtectionFlow;
    destroy(): void;
}
/** The comments to show at the root, in order. */
export declare function selectRoots(state: ThreadState): CommentData[];
/** One comment's loaded replies, in order. */
export declare function selectReplies(state: ThreadState, uid: string): CommentData[];
export declare function initialThreadState(sort?: SortOrder, unavailable?: UnavailableAction[]): ThreadState;
/**
 * A thread's state and actions over a client, with no DOM. Pages are merged by UID, so a comment
 * that moves between pages under the top sort appears once. Responses that arrive after the sort
 * changed, after the person signed out, or after `destroy()`, are dropped.
 *
 * Actions on the same thing run one at a time, in the order they were asked for. Asking again
 * with the same input while one is waiting or running returns that promise, so a double click
 * sends once; a different input, such as an unlike straight after a like, waits its turn and
 * then runs, so the last thing the person did is what the server ends up with. A write still
 * running at `destroy()` finishes and resolves for its caller without changing the state, and
 * one still waiting is rejected.
 */
export declare function createThreadStore(client: CommentsClient, options?: ThreadStoreOptions): ThreadStore;
