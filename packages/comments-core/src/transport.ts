import { CommentsRequestError, GENERIC_MESSAGE } from './errors.js';
import { apiUrl, type QueryValue } from './url.js';

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
export function restTransport(options: RestTransportOptions): CommentsTransport {
    return {
        name: 'rest',
        protectionChannel: 'browser',
        async send(request) {
            const read = request.kind === 'read';
            const headers = { ...request.headers };
            let body: string | FormData | undefined;

            if (!read) {
                if (request.body instanceof FormData) {
                    body = request.body;
                } else {
                    body = toJson(request.body ?? {});
                    headers['Content-Type'] = 'application/json';
                }
            }

            const response = await resolveFetch(options.fetch)(apiUrl(options.endpoint, request.action, read ? request.query : {}), {
                method: read ? 'GET' : 'POST',
                headers,
                body,
                credentials: request.credentials,
                redirect: request.redirect,
                cache: request.cache,
                signal: request.signal,
            });

            return { status: response.status, headers: response.headers, payload: await readJson(response) };
        },
    };
}

export function resolveFetch(custom?: typeof fetch): typeof fetch {
    if (custom) {
        return custom;
    }

    if (typeof globalThis.fetch !== 'function') {
        throw new Error('Comments: no fetch() is available here, so pass one to createCommentsClient().');
    }

    // Called unbound, some browsers throw "Illegal invocation".
    return (input, init) => globalThis.fetch(input, init);
}

/**
 * JSON with any unpaired surrogate replaced by U+FFFD. `JSON.stringify()` writes one as an escape
 * that PHP's JSON parser and GraphQL both refuse, so a stray half of an emoji would fail the request.
 */
export function toJson(value: unknown): string {
    return JSON.stringify(value, (_key, item: unknown) => (typeof item === 'string' ? wellFormed(item) : item));
}

export function wellFormed(text: string): string {
    return text.replace(/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/g, '\uFFFD');
}

/** The response's JSON, or a `CommentsRequestError` for anything else, such as a server error page. */
export async function readJson(response: Response): Promise<unknown> {
    // A redirect stopped by `redirect: 'manual'`: a browser hides it, Node shows the 3xx.
    if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
        throw CommentsRequestError.from('invalid_response', GENERIC_MESSAGE, {
            status: response.status,
            // Usually the endpoint's trailing slash or scheme.
            detail: 'redirect not followed',
        });
    }

    const text = await response.text();

    try {
        return JSON.parse(text) as unknown;
    } catch (error) {
        const code = response.status >= 500 ? 'server_error' : 'invalid_response';

        throw CommentsRequestError.from(code, GENERIC_MESSAGE, {
            status: response.status,
            recoverable: response.status >= 500,
            cause: error,
            detail: `HTTP ${response.status}, not JSON`,
        });
    }
}
