/** The custom field values, as GraphQL arguments named by handle. */
export declare function fieldArguments(fields: unknown, taken?: string[]): string;
/**
 * A JSON value written as a GraphQL literal: object keys unquoted, strings JSON-escaped (control
 * characters included) with unpaired surrogates replaced, which GraphQL refuses.
 */
export declare function graphqlLiteral(value: unknown): string;
