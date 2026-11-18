import type { ErrorData } from './types.js';
/**
 * Every error code, with its category and the HTTP status the server answers with. Codes with
 * status 0 are raised by core itself, when a request never reached the server or the answer
 * wasn't the data API's JSON, and core raises `server_error` too for a server error page. A
 * client should handle a code it doesn't know by its `category`, since new codes may be added in
 * any release.
 */
export declare const COMMENTS_ERROR_CODES: {
    readonly validation_failed: {
        readonly category: "validation";
        readonly status: 422;
    };
    readonly file_type_not_allowed: {
        readonly category: "validation";
        readonly status: 422;
    };
    readonly file_too_large: {
        readonly category: "validation";
        readonly status: 422;
    };
    readonly too_many_files: {
        readonly category: "validation";
        readonly status: 422;
    };
    readonly protection_required: {
        readonly category: "protection";
        readonly status: 422;
    };
    readonly protection_expired: {
        readonly category: "protection";
        readonly status: 422;
    };
    readonly protection_unavailable: {
        readonly category: "protection";
        readonly status: 503;
    };
    readonly challenge_failed: {
        readonly category: "protection";
        readonly status: 422;
    };
    readonly possible_spam: {
        readonly category: "protection";
        readonly status: 422;
    };
    readonly rate_limited: {
        readonly category: "rate";
        readonly status: 429;
    };
    readonly sign_in_required: {
        readonly category: "auth";
        readonly status: 401;
    };
    readonly token_expired: {
        readonly category: "auth";
        readonly status: 401;
    };
    readonly token_revoked: {
        readonly category: "auth";
        readonly status: 401;
    };
    readonly session_expired: {
        readonly category: "auth";
        readonly status: 401;
    };
    readonly origin_not_allowed: {
        readonly category: "auth";
        readonly status: 403;
    };
    readonly csrf_failed: {
        readonly category: "auth";
        readonly status: 403;
    };
    readonly signature_invalid: {
        readonly category: "auth";
        readonly status: 401;
    };
    readonly user_not_eligible: {
        readonly category: "auth";
        readonly status: 403;
    };
    readonly forbidden: {
        readonly category: "policy";
        readonly status: 403;
    };
    readonly blocked: {
        readonly category: "policy";
        readonly status: 403;
    };
    readonly closed: {
        readonly category: "policy";
        readonly status: 403;
    };
    readonly edit_window_passed: {
        readonly category: "policy";
        readonly status: 403;
    };
    readonly not_found: {
        readonly category: "state";
        readonly status: 404;
    };
    readonly conflict: {
        readonly category: "state";
        readonly status: 409;
    };
    readonly limit_exceeded: {
        readonly category: "request";
        readonly status: 400;
    };
    readonly invalid_cursor: {
        readonly category: "request";
        readonly status: 400;
    };
    readonly method_not_allowed: {
        readonly category: "request";
        readonly status: 405;
    };
    readonly unsupported_api_version: {
        readonly category: "request";
        readonly status: 400;
    };
    readonly server_error: {
        readonly category: "server";
        readonly status: 500;
    };
    readonly network_error: {
        readonly category: "network";
        readonly status: 0;
    };
    readonly invalid_response: {
        readonly category: "server";
        readonly status: 0;
    };
    readonly invalid_request: {
        readonly category: "request";
        readonly status: 400;
    };
};
/** What a visitor sees when nothing more specific can be said. */
export declare const GENERIC_MESSAGE = "Something went wrong. Please try again.";
export type CommentsErrorCode = keyof typeof COMMENTS_ERROR_CODES;
export type CommentsErrorCategory = (typeof COMMENTS_ERROR_CODES)[CommentsErrorCode]['category'];
type ErrorOptions = {
    /** The HTTP status, or 0 when there was no response. */
    status?: number;
    operationId?: string | null;
    cause?: unknown;
    /** What went wrong, for developers. */
    detail?: string | null;
};
/**
 * A request the data API refused, or one that couldn't complete. `message` is always safe to show
 * a visitor; what a developer needs is in `detail` and `cause`.
 */
export declare class CommentsRequestError extends Error {
    readonly name = "CommentsRequestError";
    /** The first error's code. */
    readonly code: string;
    readonly category: string;
    readonly status: number;
    readonly errors: ErrorData[];
    /** Every error's field messages, merged. */
    readonly fieldErrors: Record<string, string[]>;
    readonly recoverable: boolean;
    readonly retryAfter: number | null;
    /** The write's `operationId`, so a retry can reuse it. */
    readonly operationId: string | null;
    /** What went wrong, for developers, such as the API versions that didn't match. */
    readonly detail: string | null;
    constructor(errors: ErrorData[], options?: ErrorOptions);
    /** An error core raises itself, such as a dropped connection. */
    static from(code: CommentsErrorCode, message: string, options?: ErrorOptions & {
        recoverable?: boolean;
    }): CommentsRequestError;
    /** The same error for another write, keeping everything else. */
    withOperation(operationId: string | undefined): CommentsRequestError;
    /** Whether any of the errors has this code. */
    has(code: string): boolean;
}
export declare function isCommentsRequestError(error: unknown): error is CommentsRequestError;
export declare function categoryOf(code: string): string;
export declare function errorData(code: CommentsErrorCode, message: string, recoverable?: boolean): ErrorData;
/** Fills in what an error from an older server or a GraphQL extension may leave out. */
export declare function normalizeErrorData(value: unknown): ErrorData;
export {};
