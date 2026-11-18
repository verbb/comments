/**
 * Helpers for server rendering: the comment count and the thread's schema.org data, read from the
 * data API's public, cacheable reads. They import nothing that touches the DOM (only
 * `@verbb/comments-core`, and nothing from this package), so they run in React Server Components,
 * route handlers and plain Node.
 */
import { type CommentsTransport } from '@verbb/comments-core';
/**
 * The structured data as text for a `<script type="application/ld+json">`, with `<`, `>`, `&`,
 * U+2028 and U+2029 escaped, so a comment containing `</script>` can't end the script early.
 * `JSON.stringify()` alone isn't safe there.
 */
export { jsonLdScript } from '@verbb/comments-core';
export type CommentsServerOptions = {
    /** The data API's absolute URL, such as `https://cms.example.com/actions/comments/api`. */
    endpoint: string;
    ownerId?: number;
    /** The owner's URI, for headless sites that route by URI. */
    ownerUri?: string;
    siteId?: number | null;
    /** A `fetch` to use instead of the global one, such as one that adds your framework's caching options. */
    fetch?: typeof fetch;
    /** Headers for these public reads, such as a CDN's. Never send a secret here. */
    headers?: Record<string, string>;
    /** Milliseconds before the request is abandoned. Defaults to 30 seconds. */
    timeoutMs?: number;
    /** A transport other than REST, such as `graphqlTransport()` from `@verbb/comments-core/graphql`. */
    transport?: CommentsTransport;
    signal?: AbortSignal;
};
export type StructuredDataOptions = CommentsServerOptions & {
    /** The most comments to include. */
    limit?: number;
};
/**
 * The number of approved comments on an owner, replies included, for `<CommentsThread count>` and
 * your own headings. It reads the public thread at its default page size, which Craft and a CDN cache.
 */
export declare function fetchCommentsCount(options: CommentsServerOptions): Promise<number>;
/**
 * The thread's schema.org JSON-LD, without commenters' names unless the site turns them on. Write
 * it into a `<script type="application/ld+json">` with `jsonLdScript()`, never `JSON.stringify()`
 * alone, since it holds commenters' text.
 */
export declare function fetchStructuredData(options: StructuredDataOptions): Promise<Record<string, unknown>>;
