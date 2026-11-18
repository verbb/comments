# Core Package

`@verbb/comments-core` is the data layer under every Comments front end. It reads and writes threads through the Comments data API, over REST or Craft's GraphQL, keeps a thread's state in a store your own interface can follow, and handles signing in, spam protection, retries and the API version check. It never touches the page, so it runs in browsers, web workers, Node and server components, and it ships TypeScript types and a JSON Schema for every response.

Most sites never use it directly. The browser package draws threads with it, and the React and Vue packages build on that. Reach for core when you're building a completely custom interface, reading comments on a server, or working in a framework the other packages don't cover.

```bash
npm install @verbb/comments-core
```

## A First Thread

This reads a post's comments and logs each top-level one as it arrives. It runs in a browser on the Craft site's own domain, where Craft's session identifies the person, and in Node for public reads.

```js
import { createCommentsClient, createThreadStore, selectRoots } from '@verbb/comments-core';

const client = createCommentsClient({ endpoint: 'https://example.com/actions/comments/api' });
const store = createThreadStore(client, { ownerUri: 'blog/my-post' });

store.subscribe((state) => {
    for (const comment of selectRoots(state)) {
        console.log(`${comment.author?.name}: ${comment.content?.text}`);
    }
});

await store.load();
```

The client makes requests, and the store keeps the thread's state as they answer, calling every subscriber after each change. `store.load()` reads the first page of comments and, alongside it, the private `viewer` read that says what the person may do. From there, `store.vote()`, `store.create()` and the other actions write through the same client and update the state.

## The Pieces

| Piece | What it does | Covered in |
| --- | --- | --- |
| `createCommentsClient()` | One method for each data API endpoint, with the API version check, retries and errors | [Client and Transports](/core/client-transports) |
| `restTransport()` and `graphqlTransport()` | How requests travel, REST by default | [Client and Transports](/core/client-transports#graphql) |
| `cookieSession()`, `memberToken()` and `guestSession()` | How the person is identified, by where your front end runs | [Signing In](/core/signing-in) |
| `createThreadStore()` | A thread's state and actions, with events for code without a page | [The Thread Store](/core/thread-store) |
| `createProtectionFlow()` | Keeps the spam protection ready for a form of your own | [Protection](/core/protection) |
| `CommentsRequestError` and `COMMENTS_ERROR_CODES` | What went wrong, by code and category | [Errors](/core/errors) |

The plugin's [Data API](https://verbb.io/craft-plugins/comments/docs/v4/headless/data-api) reference describes the endpoints and response shapes these wrap, which is useful when you're reading the store's state or a response's fields.

## Storage and Caching

Core stores nothing in a visitor's browser by itself. Craft sets its own session and CSRF cookies when someone starts writing, voting or following, never just for reading, and a guest on another site keeps their guest session token in memory unless you give `guestSession()` somewhere to keep it. The plugin's [Cookies & Storage](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/cookies-storage) guide lists everything, for your consent notice.

Public reads, the `thread`, `replies`, `comment` and `structured-data` endpoints, are cacheable by a CDN, and the store reads a thread again when a cache served one older than the visitor's own data. A thread with a comment from the last minute changes its `ETag` every second until that comment is a minute old, because the comment's relative time, such as “12 seconds ago”, is part of it. Quieter threads keep the same `ETag` until something changes.

Structured data from `client.structuredData()` goes into a page as `<script type="application/ld+json">`. Write it with `jsonLdScript()` rather than `JSON.stringify()`, which would let a comment containing `</script>` end the script early and run the rest of its text as HTML.

```js
import { jsonLdScript } from '@verbb/comments-core';

const { structuredData } = await client.structuredData({ ownerUri: 'blog/my-post' });
const tag = `<script type="application/ld+json">${jsonLdScript(structuredData)}</script>`;
```

## Requirements

- The package is ES modules only. From CommonJS, load it with `await import('@verbb/comments-core')`.
- TypeScript projects need `moduleResolution` set to `bundler`, `node16` or `nodenext`.
- Its types use the standard `fetch` types, so `lib` needs `DOM` or `WebWorker`. A server-only project can use `WebWorker` with `@types/node`.
- It needs a global `fetch`, which every supported browser and Node 20 or later has, or one passed as the `fetch` option.
