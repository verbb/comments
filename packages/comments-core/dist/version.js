import { CommentsRequestError, GENERIC_MESSAGE } from './errors.js';
/**
 * The version of the JSON format this package reads. It changes only when the format changes
 * incompatibly, while the package version follows the plugin's.
 */
export const COMMENTS_API_VERSION = 1;
/** Sent on every request, and echoed on every response, so either side can refuse the other. */
export const COMMENTS_API_VERSION_HEADER = 'X-Comments-Api-Version';
/**
 * Throws `unsupported_api_version` unless the response is in the API version this package reads.
 * `headerVersion` is the response's `X-Comments-Api-Version` header, checked when present, and
 * `payload.apiVersion` is checked unless `requireBody` is false (Editor's endpoints answer in
 * Editor's own format, so only the header applies to them).
 */
export function assertApiVersion(payload, headerVersion, requireBody = true) {
    if (headerVersion !== undefined && headerVersion !== null && headerVersion.trim() !== String(COMMENTS_API_VERSION)) {
        throw mismatch(headerVersion.trim());
    }
    if (!requireBody) {
        return;
    }
    if (!payload || typeof payload !== 'object' || !('apiVersion' in payload)) {
        throw CommentsRequestError.from('invalid_response', GENERIC_MESSAGE, { detail: 'no apiVersion' });
    }
    const version = payload.apiVersion;
    if (version !== COMMENTS_API_VERSION) {
        throw mismatch(String(version));
    }
}
function mismatch(version) {
    return CommentsRequestError.from('unsupported_api_version', 'Comments couldn’t load. Please refresh the page.', {
        detail: `API version ${version}, expected ${COMMENTS_API_VERSION}`,
    });
}
