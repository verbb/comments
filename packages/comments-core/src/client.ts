import { CommentsRequestError, GENERIC_MESSAGE, normalizeErrorData } from './errors.js';
import { createOperationId } from './operation.js';
import { abortable, requestSignal } from './signal.js';
import { COMMENTS_PROFILE_HEADER, cookieSession, type RequestProfile, type SignInContext, type SignInProvider } from './sign-in.js';
import { restTransport, type CommentsTransport, type ReadAction, type TransportRequest, type TransportResponse, type WriteAction } from './transport.js';
import type { QueryValue } from './url.js';
import { COMMENTS_API_VERSION, COMMENTS_API_VERSION_HEADER, assertApiVersion } from './version.js';
import type {
    CommentResponse,
    CreateRequest,
    CreateResponse,
    EditResponse,
    FollowRequest,
    FollowResponse,
    ModerateRequest,
    ModerateResponse,
    ProtectionEvidenceData,
    ProtectionResponse,
    DeleteRequest,
    DeleteResponse,
    RepliesResponse,
    ReportRequest,
    ReportResponse,
    SessionResponse,
    SortOrder,
    StructuredDataResponse,
    ThreadResponse,
    UpdateRequest,
    UpdateResponse,
    ViewerResponse,
    VoteRequest,
    VoteResponse,
} from './types.js';

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
    headers?: { public?: Record<string, string>; private?: Record<string, string> };
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
export type CommentTarget = string | { uid: string } | { id: number };

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
export type FollowInput = Omit<WithoutOperation<FollowRequest>, 'siteId'> & { siteId?: number | null };
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

type Kind = 'public' | 'private' | 'write';

type Call = {
    kind: Kind;
    action: ReadAction | WriteAction;
    query?: Record<string, QueryValue>;
    body?: Record<string, unknown> | FormData;
    operationId?: string;
    /** Editor's endpoints answer in Editor's format, so only the version header is checked. */
    editorFormat?: boolean;
};

/**
 * The data API client. Public reads go without credentials or sign-in headers, so any cache can
 * hold them; everything else goes through the sign-in provider. Every write gets an
 * `operationId`, and one that loses its connection is sent once more with the same ID, which the
 * server answers from its replay store rather than acting twice.
 */
export function createCommentsClient(options: CommentsClientOptions): CommentsClient {
    const auth = options.auth ?? defaultSignIn(options.profile);
    const rest = restTransport({ endpoint: options.endpoint, fetch: options.fetch });
    const transport = options.transport ?? rest;

    if (typeof transport.send !== 'function') {
        throw new Error('Comments: `transport` takes a transport, such as graphqlTransport() from @verbb/comments-core/graphql.');
    }
    const siteId = options.siteId ?? null;
    const timeoutMs = options.timeoutMs ?? 30000;

    const client: CommentsClient = {
        endpoint: options.endpoint,
        siteId,
        auth,
        transport,
        thread: (params, request) => send({ kind: 'public', action: 'thread', query: { ...threadQuery(params), v: params.threadVersion } }, request),
        replies: (params, request) => send({ kind: 'public', action: 'replies', query: { parentUid: params.parentUid, cursor: params.cursor, limit: params.limit } }, request),
        comment: (target, request) => send({ kind: 'public', action: 'comment', query: typeof target === 'string' ? { uid: target } : target }, request),
        viewer: (params, request) => send({ kind: 'private', action: 'viewer', query: { ...threadQuery(params), uids: params.uids?.length ? params.uids : undefined, ids: params.ids?.length ? params.ids : undefined } }, request),
        session: (request) => send({ kind: 'private', action: 'session' }, request),
        protection: (params, request) => send({
            kind: 'private',
            action: 'protection',
            query: { ownerId: params.ownerId, siteId: params.siteId ?? siteId, commentUid: params.commentUid, channel: transport.protectionChannel },
        }, request),
        structuredData: (params, request) => send({
            kind: 'public',
            action: 'structured-data',
            query: { ownerId: params.ownerId, ownerUri: params.ownerUri, siteId: params.siteId ?? siteId, limit: params.limit },
        }, request),
        edit: (uid, request) => send({ kind: 'private', action: 'edit', query: { uid } }, request),
        create: (input, request) => write('create', { ...input, siteId: input.siteId ?? siteId, parentUid: input.parentUid ?? null }, request),
        update: (input, request) => write('update', input, request),
        delete: (input, request) => write('delete', input, request),
        vote: (input, request) => write('vote', input, request),
        report: (input, request) => write('report', input, request),
        follow: (input, request) => write('follow', { ...input, siteId: input.siteId ?? siteId }, request),
        moderate: (input, request) => write('moderate', input, request),
        // Editor's `scope` is an object, which the endpoint takes as a JSON string.
        mentions: (params, request) => send({
            kind: 'private',
            action: 'mentions',
            query: Object.fromEntries(Object.entries(params).map(([name, value]) => [name, isPlainObject(value) ? JSON.stringify(value) : value as QueryValue])),
            editorFormat: true,
        }, request),
        upload: (input, request) => {
            const body = new FormData();
            const { file, filename, params, protection, ...fields } = input;

            for (const [name, value] of Object.entries({ ...params, ...fields, protection: protection && JSON.stringify(protection) })) {
                if (value !== undefined && value !== null) body.append(name, String(value));
            }

            body.append('file', file, filename ?? ((file as { name?: string }).name || 'upload'));

            return send({ kind: 'write', action: 'upload', body, editorFormat: true }, request);
        },
    };

    function threadQuery(params: ThreadParams): Record<string, QueryValue> {
        return {
            ownerId: params.ownerId,
            ownerUri: params.ownerUri,
            siteId: params.siteId ?? siteId,
            sort: params.sort,
            limit: params.limit,
            cursor: params.cursor,
        };
    }

    function write<T>(action: WriteAction, input: Record<string, unknown>, request: WriteOptions = {}): Promise<T> {
        const operationId = request.operationId ?? createOperationId();

        return send({ kind: 'write', action, body: { operationId, ...input }, operationId }, request);
    }

    async function send<T>(call: Call, request: RequestOptions = {}): Promise<T> {
        let resent = false;
        let recovered = false;

        for (;;) {
            try {
                return await attempt<T>(call, request);
            } catch (error) {
                if (!(error instanceof CommentsRequestError)) {
                    throw error;
                }

                // Only a write with an `operationId` is safe to send again: the server answers a
                // repeat from its replay store. A request the caller cancelled never gets here.
                if (call.operationId && error.code === 'network_error' && !resent) {
                    resent = true;
                    continue;
                }

                if (call.kind !== 'public' && error.category === 'auth' && !recovered && auth.recover && await auth.recover(error, signInContext(call, request.signal))) {
                    recovered = true;
                    continue;
                }

                throw error;
            }
        }
    }

    async function attempt<T>(call: Call, request: RequestOptions): Promise<T> {
        const identified = call.kind !== 'public';
        const headers: Record<string, string> = {
            Accept: 'application/json',
            ...(identified ? options.headers?.private : options.headers?.public),
            [COMMENTS_API_VERSION_HEADER]: String(COMMENTS_API_VERSION),
        };
        const timer = requestSignal(request.signal, timeoutMs);
        let response: TransportResponse;

        try {
            // Inside the timeout, since a provider may make a request of its own first.
            if (identified) {
                Object.assign(headers, await abortable(auth.headers(signInContext(call, timer.signal)), timer.signal), { [COMMENTS_PROFILE_HEADER]: auth.profile });
            }

            const outgoing: TransportRequest = {
                kind: call.kind === 'write' ? 'write' : 'read',
                action: call.action,
                query: call.query,
                body: call.body,
                headers,
                credentials: identified ? auth.credentials : 'omit',
                redirect: identified ? 'manual' : 'follow',
                cache: request.cache,
                signal: timer.signal,
            };

            response = await (transport.handles?.(outgoing) === false ? rest : transport).send(outgoing);
        } catch (error) {
            // The caller's own cancellation isn't a failure to report or retry.
            if (request.signal?.aborted) {
                throw error;
            }

            if (error instanceof CommentsRequestError) {
                throw error.withOperation(call.operationId);
            }

            if (timer.timedOut() || isConnectionFailure(error)) {
                const message = timer.timedOut() ? 'The request took too long. Please try again.' : 'Couldn’t reach the server. Check your connection and try again.';
                throw CommentsRequestError.from('network_error', message, { operationId: call.operationId ?? null, recoverable: true, cause: error });
            }

            // A mistake in code, such as a custom transport's, isn't worth sending again.
            throw CommentsRequestError.from('invalid_request', GENERIC_MESSAGE, {
                operationId: call.operationId ?? null,
                cause: error,
                detail: `not sent: ${String(error)}`,
            });
        } finally {
            timer.cleanup();
        }

        const payload = response.payload as { status?: unknown; errors?: unknown } | null;
        const version = response.headers.get(COMMENTS_API_VERSION_HEADER);

        // An error answer is read before the version check, so the server's own
        // `unsupported_api_version` reaches the caller as itself.
        if (payload && payload.status === 'error' && Array.isArray(payload.errors)) {
            assertApiVersion(payload, version, false);
            const errors = payload.errors.map(normalizeErrorData);
            const retryAfter = retryAfterHeader(response.headers.get('Retry-After'));

            if (errors[0] && errors[0].retryAfter === null && retryAfter !== null) {
                errors[0] = { ...errors[0], retryAfter };
            }

            throw new CommentsRequestError(errors, { status: response.status, operationId: call.operationId ?? null, detail: response.detail ?? null });
        }

        try {
            assertApiVersion(payload, version, !call.editorFormat);
        } catch (error) {
            throw (error as CommentsRequestError).withOperation(call.operationId);
        }

        if (response.status >= 400) {
            throw CommentsRequestError.from(response.status >= 500 ? 'server_error' : 'invalid_response', GENERIC_MESSAGE, {
                status: response.status,
                operationId: call.operationId ?? null,
                recoverable: response.status >= 500,
                detail: `HTTP ${response.status}`,
            });
        }

        if (identified) {
            auth.observe?.(payload, response.headers);
        }

        return payload as T;
    }

    function signInContext(call: Call, signal: AbortSignal | undefined): SignInContext {
        // The `session` read gets its own timeout rather than this request's signal, so several
        // writes can share it.
        return { write: call.kind === 'write', endpoint: options.endpoint, session: () => client.session(), signal };
    }

    return client;
}

function defaultSignIn(profile: RequestProfile | undefined): SignInProvider {
    if (profile === 'cross-origin-member' || profile === 'cross-origin-public') {
        throw new Error(`Comments: the ${profile} profile needs a sign-in provider passed as \`auth\`.`);
    }

    return cookieSession({ profile });
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Whether fetch failed to reach the server, rather than code going wrong around it. */
function isConnectionFailure(error: unknown): boolean {
    const name = (error as { name?: unknown } | null)?.name;

    return error instanceof TypeError || name === 'AbortError' || name === 'TimeoutError' || name === 'NetworkError';
}

/** `Retry-After` in seconds, from a number of seconds or an HTTP date. */
function retryAfterHeader(value: string | null): number | null {
    if (!value) return null;

    const seconds = Number(value);

    if (Number.isFinite(seconds)) return Math.max(0, Math.ceil(seconds));

    const date = Date.parse(value);

    return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - Date.now()) / 1000));
}
