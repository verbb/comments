/**
 * Helpers for server rendering: the comment count and the thread's schema.org data, read from the
 * data API's public, cacheable reads. They import nothing that touches the DOM (only
 * `@verbb/comments-core`, and nothing from this package), so they run in Nuxt's server routes,
 * `useAsyncData()` on the server and plain Node.
 */
import { createCommentsClient } from '@verbb/comments-core';
/**
 * The structured data as text for a `<script type="application/ld+json">`, with `<`, `>`, `&`,
 * U+2028 and U+2029 escaped, so a comment containing `</script>` can't end the script early.
 * `JSON.stringify()` alone isn't safe there.
 */
export { jsonLdScript } from '@verbb/comments-core';
function client(options) {
    if (!options.endpoint || !/^https?:\/\//i.test(options.endpoint)) {
        throw new TypeError('Comments: the server helpers need the data API\'s absolute URL as `endpoint`.');
    }
    return createCommentsClient({
        endpoint: options.endpoint,
        siteId: options.siteId ?? null,
        fetch: options.fetch,
        transport: options.transport,
        timeoutMs: options.timeoutMs,
        headers: options.headers ? { public: options.headers } : undefined,
    });
}
function owner(options) {
    if (options.ownerId === undefined && !options.ownerUri) {
        throw new TypeError('Comments: give `ownerId` or `ownerUri`.');
    }
    return { ownerId: options.ownerId, ownerUri: options.ownerUri, siteId: options.siteId ?? null };
}
/*
 * Both helpers throw a `CommentsRequestError` when the read fails, such as `not_found` for an owner
 * without comments enabled or `network_error` after the timeout, so catch it where a page should
 * still render without its comments.
 */
/**
 * The number of approved comments on an owner, replies included, for `<CommentsThread :count>` and
 * your own headings. It reads the public thread at its default page size, which Craft and a CDN cache.
 */
export async function fetchCommentsCount(options) {
    const thread = await client(options).thread(owner(options), { signal: options.signal });
    return thread.conversation.commentCount;
}
/**
 * The thread's schema.org JSON-LD, without commenters' names unless the site turns them on. Write
 * it into a `<script type="application/ld+json">` with `jsonLdScript()`, never `JSON.stringify()`
 * alone, since it holds commenters' text.
 */
export async function fetchStructuredData(options) {
    const response = await client(options).structuredData({ ...owner(options), ...(options.limit ? { limit: options.limit } : {}) }, { signal: options.signal });
    return response.structuredData;
}
