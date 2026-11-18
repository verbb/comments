import { type QueryValue } from './url.js';
/** Reads under `comments/api/*`. Only `thread`, `replies`, `comment` and `structured-data` are public. */
export type ReadAction = 'thread' | 'replies' | 'comment' | 'viewer' | 'session' | 'protection' | 'structured-data' | 'edit' | 'mentions';
/** Writes under `comments/api/*`. Every one but `upload` carries an `operationId`. */
export type WriteAction = 'create' | 'update' | 'delete' | 'vote' | 'report' | 'follow' | 'moderate' | 'upload';
export type TransportRequest = {
    kind: 'read' | 'write';
    action: ReadAction | WriteAction;
    /** A read's query parameters. */
    query?: Record<string, QueryValue>;
    /** A write's JSON body, or the multipart body of an upload. */
    body?: Record<string, unknown> | FormData;
    headers: Record<string, string>;
    credentials: RequestCredentials;
    /**
     * `manual` for every request that identifies the person, so a redirect can't carry the CSRF
     * token or `Authorization` to another address, and `follow` for public reads.
     */
    redirect: RequestRedirect;
    /** The fetch cache mode, such as `no-cache` to revalidate a thread the browser holds. */
    cache?: RequestCache;
    signal: AbortSignal;
};
export type TransportResponse = {
    /** The HTTP status. */
    status: number;
    headers: Headers;
    /** The JSON, in the shape the REST endpoints send, whichever transport fetched it. */
    payload: unknown;
    /** For developers, when the payload is an error the transport put together, such as GraphQL's own message. */
    detail?: string;
};
/**
 * Sends one request and returns the data API's JSON. The client around it adds the version and
 * sign-in headers, checks the version, raises errors and retries, so a transport only moves data.
 * Throwing anything but a `CommentsRequestError` counts as a dropped connection.
 */
export interface CommentsTransport {
    readonly name: string;
    /**
     * The `channel` sent with `protection` reads: `browser` when Protect's passive checks run in a
     * form, `headless` when they can't, as with GraphQL.
     */
    readonly protectionChannel: 'browser' | 'headless';
    /**
     * Whether this transport carries the request. The client sends anything it doesn't handle,
     * such as GraphQL's missing `session` or `upload`, through REST instead. Leave it out to handle
     * everything.
     */
    handles?(request: TransportRequest): boolean;
    send(request: TransportRequest): Promise<TransportResponse>;
}
export type RestTransportOptions = {
    /** The API's base URL, such as `https://example.com/actions/comments/api`. */
    endpoint: string;
    fetch?: typeof fetch;
};
/** `comments/api/*` over plain HTTP: GET for reads, JSON POST for writes, multipart for uploads. */
export declare function restTransport(options: RestTransportOptions): CommentsTransport;
export declare function resolveFetch(custom?: typeof fetch): typeof fetch;
/**
 * JSON with any unpaired surrogate replaced by U+FFFD. `JSON.stringify()` writes one as an escape
 * that PHP's JSON parser and GraphQL both refuse, so a stray half of an emoji would fail the request.
 */
export declare function toJson(value: unknown): string;
export declare function wellFormed(text: string): string;
/** The response's JSON, or a `CommentsRequestError` for anything else, such as a server error page. */
export declare function readJson(response: Response): Promise<unknown>;
