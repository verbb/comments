# Errors

When the data API refuses a request, or a request can't complete, core throws a `CommentsRequestError`. Its `message` is always safe to show a visitor, in the site's language where the server wrote it, and the rest says what went wrong in a form your code can act on. The thread store throws these from its actions, keeps one in `state.error` when a thread couldn't load, and puts its details in the `comments:request-error` event.

```js
import { isCommentsRequestError } from '@verbb/comments-core';

try {
    await store.vote(commentUid, 'upvote');
} catch (error) {
    if (!isCommentsRequestError(error)) throw error;

    if (error.code === 'rate_limited') {
        showNotice(`Too many votes. Try again in ${error.retryAfter ?? 60} seconds.`);
    } else if (error.category === 'auth') {
        showSignInPrompt();
    } else {
        showNotice(error.message);
    }
}
```

## What an Error Holds

| Property | What it is |
| --- | --- |
| `message` | A sentence for the visitor. |
| `code` | The first error's code, from the table below. |
| `category` | A broad group, such as `validation` or `auth`. |
| `status` | The HTTP status, or `0` when there was no response. |
| `errors` | Every error the server gave, each with its own `code`, `category`, `message`, `fieldErrors`, `recoverable` and `retryAfter`. |
| `fieldErrors` | Every error's field messages merged, keyed by field as `content`, `guest.name`, `guest.email` or `fields.handle`, ready for a form. |
| `recoverable` | Whether trying again, after a refresh or a wait, can succeed. |
| `retryAfter` | Seconds to wait before trying again, for `rate_limited`, or `null`. |
| `operationId` | The write's ID, so a retry can reuse it, as [Client and Transports](/core/client-transports#writes-and-retries) explains. |
| `detail` | What went wrong for a developer, such as the API versions that didn't match. Never shown to visitors. |

`error.has(code)` checks every error rather than only the first, which matters for a post refused for more than one reason.

## Handling Codes by Category

New codes may be added in any release, so handle a code you don't recognise by its `category`. Every code belongs to one.

| Category | What it covers |
| --- | --- |
| `validation` | Something posted didn't pass, with `fieldErrors` saying where. Show the messages beside the fields. |
| `protection` | The spam protection refused the post or couldn't run. |
| `rate` | Too many requests from one address. Wait `retryAfter` seconds. |
| `auth` | Who's asking couldn't be established, or isn't allowed from this origin. The sign-in provider recovers from the ones it can by itself. |
| `policy` | The person isn't allowed to do this, such as in a closed thread. |
| `state` | What was asked about doesn't exist, or changed in the meantime. |
| `request` | The request itself was wrong, usually a mistake in code rather than anything a visitor did. |
| `server` | The server failed or answered with something other than the data API's JSON. |
| `network` | The request never reached the server, or took too long. |

## Every Code

The HTTP status is the one the server answers with. Codes without one are raised by core itself, when a request never reached the server or the answer wasn't the data API's JSON. `COMMENTS_ERROR_CODES` holds the same list, with each code's category and status.

<!--@include: ../_generated/errors.md-->

The plugin's [Data API](https://verbb.io/craft-plugins/comments/docs/v4/headless/data-api) reference shows the error response these come from.
