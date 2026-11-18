# Next.js

In a Next.js app with the App Router, a post's comment count and its structured data render on the server with the rest of the page, and the thread itself loads in the browser when it's about to scroll into view. Everything the server renders comes from public reads that Craft and a CDN cache, so the page can be cached too, including with Next's incremental static regeneration. The [Next.js starter](/starters/next) is a complete app on another domain from Craft, with members signed in through member tokens, and the [Comments on a Headless Site on Another Domain](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/comments-on-a-headless-site-on-another-domain) user guide builds one step by step.

## What Renders Where

| Where | What |
| --- | --- |
| The server | The thread's mount point, with the reserved space, the heading and the count, and the structured data |
| The browser | The thread itself, loaded as it nears view, and everything personal, such as votes and the comment form |

`<CommentsThread>` renders only its mount point on the server, so there's nothing inside it for hydration to disagree with. The package's main entry is a client module, so a server component can render `<CommentsThread>` directly as long as it passes no functions. Event props and component overrides are functions, so a thread with those goes in a client component of your own.

## A Post with Comments

The thread, in a client component because it has an event prop:

```tsx
// app/blog/[slug]/comments.tsx
'use client';

import { CommentsThread } from '@verbb/comments-react';

export function Comments({ uri, count, endpoint }: { uri: string; count: number; endpoint: string }) {
    return (
        <CommentsThread
            key={uri}
            endpoint={endpoint}
            ownerUri={uri}
            count={count}
            onCommentCreated={(event) => console.info('Comment posted', event.detail.commentUid)}
        />
    );
}
```

The page, a server component that reads the count and the structured data:

```tsx
// app/blog/[slug]/page.tsx
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-react/server';
import { Comments } from './comments';

export const revalidate = 60;

export default async function Post({ params }: { params: Promise<{ slug: string }> }) {
    const { slug } = await params;
    const uri = `blog/${slug}`;
    const endpoint = process.env.NEXT_PUBLIC_COMMENTS_ENDPOINT!;
    const [count, jsonLd] = await Promise.all([
        fetchCommentsCount({ endpoint, ownerUri: uri }).catch(() => 0),
        fetchStructuredData({ endpoint, ownerUri: uri }).catch(() => null),
    ]);

    return (
        <article>
            {jsonLd ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(jsonLd) }} /> : null}
            {/* The post itself. */}
            <Comments uri={uri} count={count} endpoint={endpoint} />
        </article>
    );
}
```

Here `NEXT_PUBLIC_COMMENTS_ENDPOINT` is the Craft site's data API, such as `https://cms.example.com/actions/comments/api`, and the post's URI on the Craft site is `blog/` followed by its slug. The heading shows the right count from the first paint, so nothing moves when the thread arrives, and search engines see the comments as structured data. Write the structured data with `jsonLdScript()`, never `JSON.stringify()` alone, which would let a comment containing `</script>` end the script and run the rest as HTML.

## The Server Helpers

`@verbb/comments-react/server` imports nothing that touches the page, so its helpers run in server components, route handlers and plain Node.

| Helper | What it returns |
| --- | --- |
| `fetchCommentsCount(options)` | The number of approved comments, replies included, for `count` and your own headings. |
| `fetchStructuredData(options)` | The thread's schema.org JSON-LD, describing the page as an `Article` with its comment count and up to `limit` approved comments, newest first. Commenters' names are always left out, because the helper takes no option to ask for them. |
| `jsonLdScript(data)` | The structured data as text that's safe inside a `<script>` element. |

Both fetch helpers take the data API's absolute `endpoint`, the `ownerId` or `ownerUri`, and optionally `siteId`, `fetch`, `headers` for these public reads (never a secret), `timeoutMs` (30 seconds by default), a `transport` such as GraphQL, and a `signal`. They throw a [`CommentsRequestError`](/core/errors) when the read fails, such as `not_found` for an owner that doesn't take comments, or `network_error` after the timeout, so catch them, as above, where the page should render without its comments.

The server can reach Craft at a different address from the browser, such as an internal one, so the starters keep it in a separate variable, `COMMENTS_SERVER_URL`, for the helpers.

## Caching

Next caches the page for `revalidate` seconds. To cache the reads on their own, pass a `fetch` that adds Next's options:

```tsx
const count = await fetchCommentsCount({
    endpoint,
    ownerUri: uri,
    fetch: (input, init) => fetch(input, { ...init, next: { revalidate: 60 } }),
});
```

The count and structured data a cached page shows can be a little behind, and the thread corrects the heading's count as soon as it loads.

## Signing In

Where the app runs relative to Craft decides how people sign in, and [Signing In](/core/signing-in) explains each way.

On a different site from Craft, members sign in with member tokens. Your app's server asks Comments for a short-lived token with a request signed with the **Member Token Key**, in a route handler such as `app/api/comments-token/route.ts`, and the browser's `memberToken()` provider calls that route. The plugin's [Signing In Across Sites](https://verbb.io/craft-plugins/comments/docs/v4/headless/signing-in-across-sites) guide covers the signed request and configuring Craft, and the [Next.js starter](/starters/next) has a working route handler, with one that revokes a person's tokens when they sign out. Keep the key in a server-only environment variable, never one starting `NEXT_PUBLIC_`.

Alternatively, an app can forward `/actions/comments/*` to Craft, so the browser sees one origin and Craft's own session applies with no tokens. The proxy has to pass requests on as Craft's own origin, keep Craft's cookies valid for your app's host, and pass the visitor's address in `X-Forwarded-For` from a proxy Craft trusts, or every visitor shares one rate limit. The Next.js starter's `/proxy/…` pages do this in a route handler that explains each step.
