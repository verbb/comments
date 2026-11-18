import { type RequestProfile, type SignInProvider } from './sign-in.js';
import { type CommentsTransport } from './transport.js';
import type { QueryValue } from './url.js';
import type { CommentResponse, CreateRequest, CreateResponse, EditResponse, FollowRequest, FollowResponse, ModerateRequest, ModerateResponse, ProtectionEvidenceData, ProtectionResponse, DeleteRequest, DeleteResponse, RepliesResponse, ReportRequest, ReportResponse, SessionResponse, SortOrder, StructuredDataResponse, ThreadResponse, UpdateRequest, UpdateResponse, ViewerResponse, VoteRequest, VoteResponse } from './types.js';
export type CommentsClientOptions = {
    /** The data API's base URL, such as `https://example.com/actions/comments/api`, or a relative path in a browser. */
    endpoint: string;
    /** The site to read and write, when the endpoint's own site isn't the one wanted. */
    siteId?: number | null;
    /** The request profile, used to choose the default sign-in provider. */
    profile?: RequestProfile;
    /** How the person is identified. Defaults to `cookieSession()` for the profile. */
    auth?: SignInProvider;
    /**
     * REST by default. Pass `graphqlTransport()` from `@verbb/comments-core/graphql`, or a transport
     * of your own; whatever it doesn't `handles()` still goes through REST to `endpoint`.
     */
    transport?: CommentsTransport;
    fetch?: typeof fetch;
    /**
     * Extra headers: `public` ones on the cacheable public reads, `private` ones on everything that
     * identifies the person. Kept apart so a secret meant for private requests can't end up in a
     * shared cache's request or on a CDN.
     */
    headers?: {
        public?: Record<string, string>;
        private?: Record<string, string>;
    };
    /** Milliseconds before a request is abandoned. Defaults to 30 seconds. */
    timeoutMs?: number;
};
export type RequestOptions = {
    signal?: AbortSignal;
    /** The fetch cache mode, such as `no-cache` to revalidate what the browser holds. */
    cache?: RequestCache;
};
export type WriteOptions = RequestOptions & {
    /** Reuse an `operationId` to retry a write the server may already have made. */
    operationId?: string;
};
export type OwnerParams = {
    ownerId?: number;
    /** The owner's URI, for headless sites that route by URI. */
    ownerUri?: string;
    siteId?: number | null;
};
export type ThreadParams = OwnerParams & {
    sort?: SortOrder;
    /** Must stay the same while paging: a cursor is only valid at the limit it was made with. */
    limit?: number;
    cursor?: string | null;
    /**
     * The `threadVersion` a `viewer` read gave, sent as `v` to ask past a CDN holding an older
     * thread. The server ignores it; it only makes the URL new.
     */
    threadVersion?: string;
};
export type RepliesParams = {
    /** The comment whose replies to load. */
    parentUid: string;
    cursor?: string | null;
    limit?: number;
};
export type ViewerParams = ThreadParams & {
    /** Comments outside the window, such as loaded replies, to include permissions for. */
    uids?: string[];
    /** Comments by the number in their permalink (`#comment-1446`), such as from a link. */
    ids?: number[];
};
/** A comment by its UID, or by the number in its permalink (`#comment-1446`). */
export type CommentTarget = string | {
    uid: string;
} | {
    id: number;
};
export type ProtectionParams = {
    ownerId: number;
    siteId?: number | null;
    /** The comment being edited. */
    commentUid?: string | null;
};
export type StructuredDataParams = OwnerParams & {
    limit?: number;
};
type WithoutOperation<T> = Omit<T, 'operationId'>;
export type CreateInput = Omit<WithoutOperation<CreateRequest>, 'siteId' | 'parentUid'> & {
    siteId?: number | null;
    parentUid?: string | null;
};
export type UpdateInput = WithoutOperation<UpdateRequest>;
export type DeleteInput = WithoutOperation<DeleteRequest>;
export type VoteInput = WithoutOperation<VoteRequest>;
export type ReportInput = WithoutOperation<ReportRequest>;
export type FollowInput = Omit<WithoutOperation<FollowRequest>, 'siteId'> & {
    siteId?: number | null;
};
export type ModerateInput = WithoutOperation<ModerateRequest>;
export type UploadInput = {
    file: Blob;
    filename?: string;
    /** An Assets field's handle, for a custom field upload. */
    field?: string;
    /** For a field upload on a new comment: its thread, and the comment it replies to. */
    ownerId?: number;
    ownerUri?: string;
    siteId?: number | null;
    parentUid?: string | null;
    /** For a field upload on the asker's own comment. */
    commentUid?: string;
    /** A guest's evidence from a fresh preparation, which the upload uses up. */
    protection?: ProtectionEvidenceData;
    /** Editor's own upload parameters. */
    params?: Record<string, string>;
};
export interface CommentsClient {
    readonly endpoint: string;
    readonly siteId: number | null;
    readonly auth: SignInProvider;
    readonly transport: CommentsTransport;
    /** Public: the conversation and a page of approved root comments. */
    thread(params: ThreadParams, options?: RequestOptions): Promise<ThreadResponse>;
    /** Public: a page of approved replies. */
    replies(params: RepliesParams, options?: RequestOptions): Promise<RepliesResponse>;
    /** Public: one approved comment and its ancestors, for deep links. */
    comment(target: CommentTarget, options?: RequestOptions): Promise<CommentResponse>;
    /** Private: everything personal for a window of the thread. */
    viewer(params: ViewerParams, options?: RequestOptions): Promise<ViewerResponse>;
    /** Private: who's signed in, and the CSRF token. */
    session(options?: RequestOptions): Promise<SessionResponse>;
    /** A fresh Protect preparation. */
    protection(params: ProtectionParams, options?: RequestOptions): Promise<ProtectionResponse>;
    /** Public: the JSON-LD object, for server-side rendering. */
    structuredData(params: StructuredDataParams, options?: RequestOptions): Promise<StructuredDataResponse>;
    /** Private: a comment's editable content and field values, for its author. */
    edit(uid: string, options?: RequestOptions): Promise<EditResponse>;
    create(input: CreateInput, options?: WriteOptions): Promise<CreateResponse>;
    update(input: UpdateInput, options?: WriteOptions): Promise<UpdateResponse>;
    /** The asker's own comment. A moderator's removal is `moderate()` with `remove`. */
    delete(input: DeleteInput, options?: WriteOptions): Promise<DeleteResponse>;
    vote(input: VoteInput, options?: WriteOptions): Promise<VoteResponse>;
    report(input: ReportInput, options?: WriteOptions): Promise<ReportResponse>;
    follow(input: FollowInput, options?: WriteOptions): Promise<FollowResponse>;
    moderate(input: ModerateInput, options?: WriteOptions): Promise<ModerateResponse>;
    /** Editor's mention search (`context`, `query` and `scope`), answered in Editor's format. */
    mentions<T = unknown>(params: Record<string, QueryValue | Record<string, unknown>>, options?: RequestOptions): Promise<T>;
    /** An upload for Editor's attachments or an Assets field, answered in Editor's format. */
    upload<T = unknown>(input: UploadInput, options?: RequestOptions): Promise<T>;
}
/**
 * The data API client. Public reads go without credentials or sign-in headers, so any cache can
 * hold them; everything else goes through the sign-in provider. Every write gets an
 * `operationId`, and one that loses its connection is sent once more with the same ID, which the
 * server answers from its replay store rather than acting twice.
 */
export declare function createCommentsClient(options: CommentsClientOptions): CommentsClient;
export {};
