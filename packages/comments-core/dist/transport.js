import { CommentsRequestError, GENERIC_MESSAGE } from './errors.js';
import { apiUrl } from './url.js';
/** `comments/api/*` over plain HTTP: GET for reads, JSON POST for writes, multipart for uploads. */
export function restTransport(options) {
    return {
        name: 'rest',
        protectionChannel: 'browser',
        async send(request) {
            const read = request.kind === 'read';
            const headers = { ...request.headers };
            let body;
            if (!read) {
                if (request.body instanceof FormData) {
                    body = request.body;
                }
                else {
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
export function resolveFetch(custom) {
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
export function toJson(value) {
    return JSON.stringify(value, (_key, item) => (typeof item === 'string' ? wellFormed(item) : item));
}
export function wellFormed(text) {
    return text.replace(/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/g, '\uFFFD');
}
/** The response's JSON, or a `CommentsRequestError` for anything else, such as a server error page. */
export async function readJson(response) {
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
        return JSON.parse(text);
    }
    catch (error) {
        const code = response.status >= 500 ? 'server_error' : 'invalid_response';
        throw CommentsRequestError.from(code, GENERIC_MESSAGE, {
            status: response.status,
            recoverable: response.status >= 500,
            cause: error,
            detail: `HTTP ${response.status}, not JSON`,
        });
    }
}
