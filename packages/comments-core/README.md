# Comments Core

`@verbb/comments-core` is the data layer for front ends built on [Comments](https://verbb.io/craft-plugins/comments) for Craft CMS. It reads and writes threads through the Comments data API over REST or GraphQL, keeps a thread's state in a store your own UI can subscribe to, handles signing in, spam protection and retries, and ships TypeScript types and a JSON Schema for every response. It never touches the page, so it runs in browsers, web workers, Node and server components.

Most sites don't need it directly. The plugin draws threads with `@verbb/comments-browser`, and the React and Vue packages build on that. Reach for core when you're building your own interface or reading comments on a server.

```js
import { createCommentsClient, createThreadStore, selectRoots } from '@verbb/comments-core';

const client = createCommentsClient({ endpoint: 'https://example.com/actions/comments/api' });
const store = createThreadStore(client, { ownerUri: 'blog/my-post' });

store.subscribe((state) => render(selectRoots(state)));
await store.load();
```

## Events

`store.on(name, listener)` tells you as things happen to the thread. The events are the browser events about the thread's data, with the same names, and each `detail` has every key the browser event's has, typed by `ThreadStoreEventMap`, with `apiVersion` on every one. A few add keys the browser events don't have, marked below.

| Event | Detail |
| --- | --- |
| `comments:request-start`, `comments:request-end` | `reason`, what the request is for, such as `thread` or `vote` |
| `comments:request-error` | `reason`, `message`, and for a `CommentsRequestError` its `category`, `code`, `fieldErrors` and `recoverable`, plus the `error` itself (store only) |
| `comments:roots-loaded` | `cursor` the page was loaded from, and its `count` (both store only) |
| `comments:viewer-loaded` | `viewer`, who's reading and what they may do, as the thread now has it |
| `comments:sorted` | `sort` |
| `comments:replies-loaded` | `commentUid`, and `count` (store only) |
| `comments:comment-created` | `commentUid` and `moderationState`, plus `parentUid` and `replayed` (store only) |
| `comments:comment-updated`, `comments:comment-deleted` | `commentUid` |
| `comments:comment-moderated` | `commentUid`, `commentId` and `moderationAction`, plus the comment's `state` afterwards (store only) |
| `comments:vote-changed` | `commentUid`, `commentId` and `vote`, which is null when a vote was taken back |
| `comments:report-changed` | `commentUid`, `commentId` and `reported`, which is false after a withdrawal |
| `comments:follow-changed` | `following` |

`commentId` is the number in the comment's permalink, such as `1446` for `#comment-1446`, or null. A `comments:request-error` for something other than a `CommentsRequestError` has a general `message`, where the browser event uses the thread's own translated wording. The page's own events, such as `comments:before-submit` and `comments:editor-opened`, come only from the browser package.

## GraphQL

REST is the default. To read and write through Craft's GraphQL API instead, pass the GraphQL transport, which lives in its own entry so sites that don't use it never bundle it. Anything GraphQL has no query for, such as protection and uploads, still goes through REST at `endpoint`.

```js
import { createCommentsClient } from '@verbb/comments-core';
import { graphqlTransport } from '@verbb/comments-core/graphql';

const client = createCommentsClient({
    endpoint: 'https://example.com/actions/comments/api',
    transport: graphqlTransport({ endpoint: 'https://example.com/api' }),
});
```

## Signing in from another site

On Craft's own domain, or another subdomain of the same site, `cookieSession()` (the default) uses Craft's session cookie. A front end on a different site can't send Craft's cookies, so it signs in another way.

- **Members** use `memberToken({ getToken })`. Your own server asks Comments for a short-lived token with a signed request, and `getToken()` fetches it from your server and returns `{ token, expiresAt }`. Core sends it as `Authorization: Bearer` on every request that identifies the person, never on public reads, keeps it in memory only, and fetches the next one shortly before it expires or when the server says it has expired or been revoked.
- **Guests** use `guestSession()`. Their first write starts a guest session through the `session` read, and core sends its token as `X-Comments-Session` from then on, taking the refreshed token each answer carries. Guests can't edit, mention, upload or moderate, so a thread store shows those as unavailable.

```js
import { createCommentsClient, guestSession, memberToken } from '@verbb/comments-core';

const auth = member
    ? memberToken({ getToken: () => fetch('/api/comments-token').then((response) => response.json()) })
    : guestSession({ storage: sessionStorage });

const client = createCommentsClient({ endpoint: 'https://cms.example.com/actions/comments/api', auth });
```

## Cookies and storage

Core stores nothing in a visitor's browser by itself. Craft's session and CSRF cookies, on Craft's own domain or a sibling subdomain, are set by Craft when someone starts writing, voting or following, and never just for reading. A guest on another site keeps their guest session token in memory, so a reload starts a new guest session, unless you pass `guestSession({ storage: sessionStorage })`. That keeps it for the tab under the key `verbb-comments-session:` followed by the API's endpoint, from the guest's first write until the tab closes or `reset()` is called. Member tokens are never stored.

## Caching

Structured data from `client.structuredData()` goes into a page as `<script type="application/ld+json">${jsonLdScript(structuredData)}</script>`. Use `jsonLdScript()` rather than `JSON.stringify()`, which would let a comment containing `</script>` end the script early and run the rest of its text as HTML.

Public reads (`thread`, `replies`, `comment` and `structured-data`) are cacheable, and the store fetches a thread again when a cache served one older than the visitor's own data. A thread with a comment from the last minute changes its `ETag` every second until that comment is a minute old, because the comment's relative time ("12 seconds ago") is part of it. That's expected, and quieter threads keep the same `ETag` until something changes.

## Requirements

- The package is ES modules only, with no CommonJS build. Use `import`, or `await import('@verbb/comments-core')` from CommonJS code.
- TypeScript projects need `moduleResolution` set to `bundler`, `node16` or `nodenext`. With `node16` or `nodenext`, a CommonJS file can only load the package with a dynamic `import()`.
- Its types use the standard `fetch` types, so `lib` needs `DOM` or `WebWorker`. Server-only projects can use `WebWorker` with `@types/node`.
- It needs a global `fetch`, which every supported browser and Node 20 or later has, or one passed as the `fetch` option.

## Documentation

The [Comments package docs](https://docs.verbb.io/comments) cover the client, the thread store, signing in across sites, protection and errors.

## Licence

Comments Core is released under the Verbb licence in `LICENSE.md`, the same as the Comments plugin.
