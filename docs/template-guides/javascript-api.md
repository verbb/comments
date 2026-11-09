# JavaScript API

The bundled JavaScript starts by itself, so you only need this page to connect comments to your own scripts, such as tracking a posted comment in your analytics, or rendering comments into a page after it's loaded.

The bundled script is a module, so it runs after the page has been parsed, just before the `DOMContentLoaded` event. Run anything using the `Comments` global inside a `DOMContentLoaded` listener, so it's there when your code runs.

## The `Comments` Global

`window.Comments` has three methods. Each set of comments on the page is an element with a `data-comments-root` attribute, and has its own controller.

| Method | What It Does |
| --- | --- |
| `Comments.mount(scope)` | Starts any comments inside `scope`, or the whole document if it's left out, and returns their controllers. Those already started keep their controller. |
| `Comments.destroy(scope)` | Stops any comments inside `scope`, or the whole document. |
| `Comments.get(root)` | Returns the controller for a root element, if it's started. |

Comments added to or removed from the page are noticed automatically, and pages using Turbo are handled too, so you rarely need `mount()` or `destroy()`.

A controller has these:

| Property or Method | What It Does |
| --- | --- |
| `root` | The root element. |
| `conversationUid` | The discussion's unique ID. |
| `refresh({ reason, sort })` | Fetches the header and list again. `sort` can be `newest`, `oldest` or `top`, and `reason` is passed on to the request events. Both are optional. |
| `openComposer({ mode, commentUid })` | Opens a reply or edit form, with a `mode` of `reply` or `edit`, on the comment with that UID. |
| `closeComposer({ reason })` | Closes the open reply or edit form. |
| `destroy()` | Stops this instance. |

For example, to show the newest comments when someone presses a button of your own:

```js
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-comments-root]');

    document.querySelector('#latest').addEventListener('click', () => {
        window.Comments.get(root)?.refresh({ sort: 'newest' });
    });
});
```

## Browser Events

Events are dispatched on the root element and bubble, so you can listen on `document` to catch them from every set of comments on the page. The bundled script starts each instance as soon as it runs, so add your listener in an ordinary script before it if you need `comments:mounted`.

```js
document.addEventListener('comments:comment-created', (event) => {
    console.log('Comment posted', event.detail.resource);
});
```

Each event's `detail` has `contractVersion`, `root`, `instanceId`, `conversationUid`, `controller` and `source`, along with these:

| Event | When It Fires | Also in `detail` |
| --- | --- | --- |
| `comments:mounted` | An instance has started. | |
| `comments:destroyed` | An instance has stopped. | |
| `comments:action-before` | A control with `data-comments-action` has been clicked. Call `event.preventDefault()` to stop it. | `action` |
| `comments:request-start` | A request has started. | `operationId`, and `reason` for anything but posting a comment |
| `comments:request-end` | A request succeeded, and the page has been updated. | `reason` |
| `comments:request-error` | A request failed. | `message`, and sometimes `reason`, `category`, `code`, `fieldErrors`, `recoverable` |
| `comments:viewer-hydrated` | Private controls have arrived on a page using lazy loading or shared caching. | |
| `comments:roots-loaded` | More comments have loaded. | |
| `comments:replies-loaded` | More replies have loaded. | `commentUid` |
| `comments:sorted` | The sort has changed. | `sort` |
| `comments:composer-opened` | A reply or edit form is opening. | `mode`, `commentUid` |
| `comments:composer-closed` | A reply or edit form has closed. | `reason` |
| `comments:comment-created` | A comment or reply was posted. | `operationId`, `resource` |
| `comments:comment-updated` | A comment was edited. | `operationId`, `resource` |
| `comments:comment-removed` | An author deleted their comment. | `commentUid` |
| `comments:comment-moderated` | A moderator acted on a comment. | `commentId`, `moderationAction` |
| `comments:interaction-changed` | A vote was made, changed or taken back. | `commentId`, `type` |
| `comments:report-submitted` | A report was sent, or sent again to withdraw it. | `commentId` |
| `comments:subscription-changed` | The **Follow** button was used. | `subscribed` |

`source` is `user` for `comments:action-before` and `api` for everything else. `resource` is the posted comment's UID, or `null` when the comment isn't public yet, such as when it's waiting for moderation. `type` is `upvote` or `downvote`, and a like is an `upvote`.

`comments:action-before` only covers clicks, so it can stop a reply form opening, or a comment being deleted. Use your own `submit` listener for a form.

## Frontend API

The bundled JavaScript talks to these endpoints, which you can use from your own client. Each returns JSON and is marked as private and uncacheable. All are under `comments/api/v1/`, so build URLs from the `data-comments-api-base` attribute on the root element, which works with both query-string and pretty routing. POST requests need Craft's CSRF token. `siteId` defaults to the current site, and `limit` to 20.

| Endpoint | Method | Parameters |
| --- | --- | --- |
| `conversation` | GET | `ownerId`, `siteId`, `sort`, `limit` (up to 50) |
| `roots` | GET | `ownerId`, `siteId`, `cursor`, `sort`, `limit` (up to 50) |
| `replies` | GET | `commentUid`, `limit` (up to 500) |
| `thread` | GET | `ownerId`, `siteId`, `commentId` |
| `viewer-controls` | GET | `ownerId`, `siteId`, `sort`, `limit` (up to 50) |
| `composer` | GET | `ownerId`, `siteId`, `mode` (`create`, `reply` or `edit`), `commentUid` |
| `protection` | GET | `ownerId`, `siteId`, `commentId`, `channel` |
| `create` | POST | The fields from `_composer/form`, including `commentId` for an edit |
| `remove` | POST | `commentId` |
| `interaction` | POST | `commentId`, `type` (`upvote` or `downvote`), which takes the vote back if it's sent again |
| `moderate` | POST | `commentId`, `moderationAction`, `reason` |
| `report` | POST | `commentId`, `reason`, `details`, which withdraws the report if it's sent again |
| `subscription` | POST | `ownerId`, `siteId`, `subscribed`, `email` |

Apart from `protection`, which returns [its own shape](docs:template-guides/displaying-and-protecting-comments#headless-and-graphql), they return an envelope with HTML fragments rendered from your [templates](docs:template-guides/custom-templates):

```json
{
    "contractVersion": 1,
    "status": "ok",
    "operationId": "3b2f6e0c-…",
    "resource": "9f3a2c1e-…",
    "workflowVersion": null,
    "replayed": false,
    "secondaryWorkPending": false,
    "fragments": [
        {
            "type": "comment",
            "mode": "insert",
            "resourceUid": "9f3a2c1e-…",
            "html": "<li class=\"comments__comment\" …>"
        }
    ],
    "state": [],
    "errors": [],
    "announcement": "Comment posted.",
    "rootsNext": null,
    "repliesNext": null
}
```

Each fragment's `type` says which part of the page it's for, which is `conversationHeader`, `rootList`, `viewerControls`, `reportDialog`, `composer`, `comment`, `thread` or `replies`, and its `mode` says whether it replaces, inserts, appends or removes that part. `announcement` is a message for the visitor, which the bundled script shows and reads out to screen readers.

On failure, `status` is `error` and `errors` holds objects with `category`, `code`, `message`, `recoverable`, and for validation, `fieldErrors` keyed by input name. Rate limits use HTTP 429 with `Retry-After`. The other GET endpoints return HTTP 404 for an element or comment that can't be found or shown, except `thread`, which returns an envelope with no fragments for a comment the visitor can't see.

For JSON data rather than HTML, use [GraphQL](docs:developers/graphql).
