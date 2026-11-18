/**
 * A string that's the same for equal values whatever their key order, so options written inline on
 * every render don't count as a change. Functions count as equal to each other; callers that care
 * about a function's identity compare it themselves.
 */
export declare function stableKey(value: unknown): string;
