/**
 * The version of the JSON format this package reads. It changes only when the format changes
 * incompatibly, while the package version follows the plugin's.
 */
export declare const COMMENTS_API_VERSION = 1;
/** Sent on every request, and echoed on every response, so either side can refuse the other. */
export declare const COMMENTS_API_VERSION_HEADER = "X-Comments-Api-Version";
/**
 * Throws `unsupported_api_version` unless the response is in the API version this package reads.
 * `headerVersion` is the response's `X-Comments-Api-Version` header, checked when present, and
 * `payload.apiVersion` is checked unless `requireBody` is false (Editor's endpoints answer in
 * Editor's own format, so only the header applies to them).
 */
export declare function assertApiVersion(payload: unknown, headerVersion?: string | null, requireBody?: boolean): void;
