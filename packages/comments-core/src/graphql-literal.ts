import { CommentsRequestError } from './errors.js';
import { wellFormed } from './transport.js';

type Body = Record<string, unknown>;

// Query-building helpers for `graphqlTransport()`. They're internal, not part of the
// `@verbb/comments-core/graphql` entry.

/**
 * Arguments Craft's element mutations take besides the ones each write names. A custom field
 * with one of these handles, or with one of the write's own argument names such as `comment` or
 * `email`, can't be told apart from it, and Craft doesn't reserve all of them for fields.
 */
const ELEMENT_ARGUMENTS = ['id', 'uid', 'title', 'slug', 'enabled'];

function refused(detail: string): CommentsRequestError {
    return CommentsRequestError.from('invalid_request', 'This comment couldn’t be sent. Please try again.', { detail: `GraphQL: ${detail}` });
}

/** The custom field values, as GraphQL arguments named by handle. */
export function fieldArguments(fields: unknown, taken: string[] = []): string {
    if (!fields || typeof fields !== 'object') {
        return '';
    }

    return Object.entries(fields as Body).map(([handle, value]) => {
        if (!/^[_A-Za-z][_0-9A-Za-z]*$/.test(handle)) {
            throw refused(`bad handle ${handle}`);
        }

        if (taken.includes(handle) || ELEMENT_ARGUMENTS.includes(handle)) {
            throw refused(`field ${handle} clashes with an argument`);
        }

        return `, ${handle}: ${graphqlLiteral(value)}`;
    }).join('');
}

/**
 * A JSON value written as a GraphQL literal: object keys unquoted, strings JSON-escaped (control
 * characters included) with unpaired surrogates replaced, which GraphQL refuses.
 */
export function graphqlLiteral(value: unknown): string {
    if (value === null || value === undefined) return 'null';
    if (typeof value === 'number') return Number.isFinite(value) ? String(value) : 'null';
    if (typeof value === 'boolean') return String(value);
    if (typeof value === 'string') return JSON.stringify(wellFormed(value));
    if (Array.isArray(value)) return `[${value.map(graphqlLiteral).join(', ')}]`;

    if (typeof value !== 'object') {
        throw refused(`can't send ${typeof value}`);
    }

    return `{${Object.entries(value as Body).map(([key, item]) => {
        if (!/^[_A-Za-z][_0-9A-Za-z]*$/.test(key)) {
            throw refused(`bad key ${key}`);
        }

        return `${key}: ${graphqlLiteral(item)}`;
    }).join(', ')}}`;
}
