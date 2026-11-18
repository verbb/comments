# Client and Transports

The client is one object with a method for each endpoint of the Comments data API. It adds the headers each request needs, checks the answer is in the format the package reads, raises errors you can act on, and retries what's safe to retry. The thread store, the browser package and the server helpers in the React and Vue packages all use one, and your own code can too, for anything the store doesn't cover or for reads on a server.

```js
import { createCommentsClient } from '@verbb/comments-core';

const client = createCommentsClient({ endpoint: 'https://example.com/actions/comments/api' });

const thread = await client.thread({ ownerUri: 'blog/my-post', sort: 'top', limit: 10 });

console.log(`${thread.conversation.commentCount} comments`);
```

## Options

| Option | What it does |
| --- | --- |
| `endpoint` | The data API's address, such as `https://example.com/actions/comments/api`, or a path such as `/actions/comments/api` in a browser. Required. |
| `siteId` | The site to read and write, on a multi-site install where the endpoint's own site isn't the one you want. |
| `auth` | How the person is identified, covered in [Signing In](/core/signing-in). Defaults to Craft's session cookie. |
| `profile` | The request profile, which picks the default sign-in when `auth` is left out. |
| `transport` | How requests travel. REST by default, or [GraphQL](#graphql). |
| `fetch` | A `fetch` to use instead of the global one, such as one that adds your framework's caching options. |
| `headers` | Extra headers, as `public` ones for the cacheable public reads and `private` ones for everything that identifies the person. They're kept apart so a secret meant for private requests can't end up in a shared cache. |
| `timeoutMs` | Milliseconds before a request is abandoned. Defaults to 30 seconds. |

## Methods

Public reads are the same for every visitor. They're sent without cookies or sign-in headers, so any cache can hold them. Private reads and writes go through the sign-in provider.

| Method | What it does |
| --- | --- |
| `thread(params)` | Public. The discussion and a page of approved top-level comments, by `ownerId` or `ownerUri`, with `sort`, `limit` and `cursor`. |
| `replies(params)` | Public. A page of a comment's approved replies, by `parentUid`. |
| `comment(target)` | Public. One approved comment and its parents, by UID or by the number in its permalink, for links to a comment. |
| `structuredData(params)` | Public. The thread's schema.org JSON-LD. |
| `viewer(params)` | Private. Everything personal for a window of the thread, such as the person's votes, their own pending comments and what they may do. |
| `session()` | Private. Who's signed in, and the CSRF token for a cookie sign-in. |
| `protection(params)` | Private. A fresh spam protection preparation, covered in [Protection](/core/protection). |
| `edit(uid)` | Private. A comment's editable content and field values, for its author. |
| `create()`, `update()`, `delete()`, `vote()`, `report()`, `follow()` and `moderate()` | Writes. `delete()` is an author deleting their own comment, while a moderator's removal is `moderate()` with `remove`. |
| `mentions()` and `upload()` | Editor's mention search and uploads, and uploads for Assets custom fields. |

Every method takes request options last, with a `signal` to cancel it and the fetch `cache` mode. The plugin's [Data API](https://verbb.io/craft-plugins/comments/docs/v4/headless/data-api) reference describes each endpoint's parameters and response.

## Writes and Retries

Every write gets an `operationId`, a random ID that the server remembers with its answer for ten minutes. If a write loses its connection, the client sends it once more with the same ID, and the server answers the repeat with its first result rather than acting twice, so a comment is never posted twice because of a flaky connection. To retry a write yourself later, such as after the visitor presses **Try again**, pass the same ID back. A failed write's error carries it.

```js
import { createOperationId, isCommentsRequestError } from '@verbb/comments-core';

const operationId = createOperationId();

try {
    await client.vote({ uid: commentUid, vote: 'upvote' }, { operationId });
} catch (error) {
    if (isCommentsRequestError(error) && error.recoverable) {
        // Later, the same vote with the same ID, which the server won't count twice.
        await client.vote({ uid: commentUid, vote: 'upvote' }, { operationId: error.operationId ?? operationId });
    }
}
```

When a private read or a write fails because the sign-in has run out, such as an expired member token or CSRF token, the client asks the sign-in provider to recover and tries once more. Public reads are never retried.

## The API Version

The JSON format has a version of its own, `COMMENTS_API_VERSION`, which changes only when the format changes in a way an older package can't read. The client sends it as `X-Comments-Api-Version` on every request and checks every answer carries the same, raising `unsupported_api_version` when they differ. That usually means a page left open while the plugin was updated, and reloading fixes it. `assertApiVersion(payload, header)` makes the same check on a response your own code fetched.

## GraphQL

REST is the default. To read and write through Craft's GraphQL API instead, pass the GraphQL transport, which lives in its own entry so sites that don't use it never bundle it:

```js
import { createCommentsClient } from '@verbb/comments-core';
import { graphqlTransport } from '@verbb/comments-core/graphql';

const client = createCommentsClient({
    endpoint: 'https://example.com/actions/comments/api',
    transport: graphqlTransport({ endpoint: 'https://example.com/api' }),
});
```

The `thread`, `replies`, `comment` and `viewer` reads, and the `create`, `update`, `delete`, `vote`, `report`, `follow` and `moderate` writes, go through GraphQL, built by the same serialiser on the server, so the answers have the same shape. Everything GraphQL has no query for, such as `session`, `protection`, `structured-data`, `edit`, mentions and uploads, still goes through REST at `endpoint`. The GraphQL schema needs Comments' queries and mutations enabled, as the plugin's [GraphQL](https://verbb.io/craft-plugins/comments/docs/v4/graphql/queries) docs describe.

## Your Own Transport

A transport is an object with a `name`, a `protectionChannel` (`browser` when the spam protection's passive checks can run in a form, `headless` when they can't), an optional `handles(request)` and a `send(request)` that resolves with the HTTP `status`, the `headers` and the JSON `payload` in the shape the REST endpoints send. The client around it adds the headers, checks the version, raises the errors and retries, so a transport only moves data. Whatever its `handles()` turns down goes through REST.

```js
import { createCommentsClient, restTransport } from '@verbb/comments-core';

// Logs how long each request takes, and otherwise sends it through REST.
const rest = restTransport({ endpoint: 'https://example.com/actions/comments/api' });

const timedTransport = {
    name: 'timed-rest',
    protectionChannel: rest.protectionChannel,
    async send(request) {
        const started = performance.now();

        try {
            return await rest.send(request);
        } finally {
            console.debug(`${request.action} took ${Math.round(performance.now() - started)}ms`);
        }
    },
};

const client = createCommentsClient({ endpoint: 'https://example.com/actions/comments/api', transport: timedTransport });
```
