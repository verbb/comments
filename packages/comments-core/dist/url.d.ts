export type QueryValue = string | number | boolean | null | undefined | ReadonlyArray<string | number>;
/**
 * An action under the API's base URL, such as `https://example.com/actions/comments/api` plus
 * `thread`. Keeps Craft's query-string route (`index.php?p=actions/comments/api`) when pretty URLs
 * are off, keeps a relative base relative, and adds the query parameters in the order given, with
 * lists as `name[]`. Empty values are left out.
 */
export declare function apiUrl(endpoint: string, action: string, query?: Record<string, QueryValue>): string;
