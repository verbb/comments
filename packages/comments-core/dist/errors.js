/**
 * Every error code, with its category and the HTTP status the server answers with. Codes with
 * status 0 are raised by core itself, when a request never reached the server or the answer
 * wasn't the data API's JSON, and core raises `server_error` too for a server error page. A
 * client should handle a code it doesn't know by its `category`, since new codes may be added in
 * any release.
 */
export const COMMENTS_ERROR_CODES = {
    validation_failed: { category: 'validation', status: 422 },
    file_type_not_allowed: { category: 'validation', status: 422 },
    file_too_large: { category: 'validation', status: 422 },
    too_many_files: { category: 'validation', status: 422 },
    protection_required: { category: 'protection', status: 422 },
    protection_expired: { category: 'protection', status: 422 },
    protection_unavailable: { category: 'protection', status: 503 },
    challenge_failed: { category: 'protection', status: 422 },
    possible_spam: { category: 'protection', status: 422 },
    rate_limited: { category: 'rate', status: 429 },
    sign_in_required: { category: 'auth', status: 401 },
    token_expired: { category: 'auth', status: 401 },
    token_revoked: { category: 'auth', status: 401 },
    session_expired: { category: 'auth', status: 401 },
    origin_not_allowed: { category: 'auth', status: 403 },
    csrf_failed: { category: 'auth', status: 403 },
    // Only a site's own server sees these two, from `member-token` and `revoke-member-tokens`.
    signature_invalid: { category: 'auth', status: 401 },
    user_not_eligible: { category: 'auth', status: 403 },
    forbidden: { category: 'policy', status: 403 },
    blocked: { category: 'policy', status: 403 },
    closed: { category: 'policy', status: 403 },
    edit_window_passed: { category: 'policy', status: 403 },
    not_found: { category: 'state', status: 404 },
    conflict: { category: 'state', status: 409 },
    limit_exceeded: { category: 'request', status: 400 },
    invalid_cursor: { category: 'request', status: 400 },
    method_not_allowed: { category: 'request', status: 405 },
    unsupported_api_version: { category: 'request', status: 400 },
    server_error: { category: 'server', status: 500 },
    network_error: { category: 'network', status: 0 },
    invalid_response: { category: 'server', status: 0 },
    invalid_request: { category: 'request', status: 400 },
};
/** What a visitor sees when nothing more specific can be said. */
export const GENERIC_MESSAGE = 'Something went wrong. Please try again.';
/**
 * A request the data API refused, or one that couldn't complete. `message` is always safe to show
 * a visitor; what a developer needs is in `detail` and `cause`.
 */
export class CommentsRequestError extends Error {
    constructor(errors, options = {}) {
        const first = errors[0] ?? errorData('server_error', GENERIC_MESSAGE);
        super(first.message, options.cause === undefined ? undefined : { cause: options.cause });
        this.name = 'CommentsRequestError';
        this.errors = errors.length ? errors : [first];
        this.code = first.code;
        this.category = first.category || categoryOf(first.code);
        this.status = options.status ?? statusOf(first.code);
        this.recoverable = first.recoverable;
        this.retryAfter = first.retryAfter;
        this.operationId = options.operationId ?? null;
        this.detail = options.detail ?? null;
        this.fieldErrors = {};
        for (const error of this.errors) {
            for (const [field, messages] of Object.entries(error.fieldErrors ?? {})) {
                (this.fieldErrors[field] ??= []).push(...messages);
            }
        }
    }
    /** An error core raises itself, such as a dropped connection. */
    static from(code, message, options = {}) {
        return new CommentsRequestError([errorData(code, message, options.recoverable)], options);
    }
    /** The same error for another write, keeping everything else. */
    withOperation(operationId) {
        if (!operationId || this.operationId)
            return this;
        return new CommentsRequestError(this.errors, { status: this.status, operationId, cause: this.cause, detail: this.detail });
    }
    /** Whether any of the errors has this code. */
    has(code) {
        return this.errors.some((error) => error.code === code);
    }
}
export function isCommentsRequestError(error) {
    return error instanceof CommentsRequestError;
}
export function categoryOf(code) {
    return COMMENTS_ERROR_CODES[code]?.category ?? 'server';
}
function statusOf(code) {
    return COMMENTS_ERROR_CODES[code]?.status ?? 0;
}
export function errorData(code, message, recoverable = false) {
    return { category: categoryOf(code), code, message, fieldErrors: {}, recoverable, retryAfter: null };
}
/** Fills in what an error from an older server or a GraphQL extension may leave out. */
export function normalizeErrorData(value) {
    const error = (value && typeof value === 'object' ? value : {});
    const code = typeof error.code === 'string' && error.code ? error.code : 'server_error';
    return {
        category: typeof error.category === 'string' && error.category ? error.category : categoryOf(code),
        code,
        message: typeof error.message === 'string' && error.message ? error.message : GENERIC_MESSAGE,
        fieldErrors: error.fieldErrors && typeof error.fieldErrors === 'object' ? error.fieldErrors : {},
        recoverable: Boolean(error.recoverable),
        retryAfter: typeof error.retryAfter === 'number' ? error.retryAfter : null,
    };
}
