# JavaScript API

The bundled JavaScript starts by itself, so you only need this page to connect comments to your own scripts, such as tracking a posted comment in your analytics, or rendering comments into a page after it's loaded.

The bundled script is a module, so it runs after the page has been parsed, just before the `DOMContentLoaded` event. Run anything using the `Comments` global inside a `DOMContentLoaded` listener, so it's there when your code runs. To include the script in your own build instead, with TypeScript types for everything on this page, see [Bundling the Script Into Your Own Build](docs:template-guides/loading-comment-assets#bundling-the-script-into-your-own-build).

## The `Comments` Global

`window.Comments` has three methods. Each set of comments on the page is an element with a `data-comments-root` attribute, and has its own controller.

| Method | What It Does |
| --- | --- |
| `Comments.mount(scope)` | Starts any comments inside `scope`, or the whole document if it's left out, and returns their controllers. Those already started keep their controller. |
| `Comments.destroy(scope)` | Stops any comments inside `scope`, or the whole document. |
| `Comments.get(root)` | Returns the controller for a root element, if it's started. |

Comments added to or removed from the page are noticed automatically, and pages using Turbo are handled too, so you rarely need `mount()` or `destroy()`. To start a thread yourself instead, such as after your own code has set something up, put `data-comments-auto-mount="false"` on it or on any element around it, and call `Comments.mount()` when you're ready. [Loading Comment Assets](docs:template-guides/loading-comment-assets#starting-threads-yourself) has an example.

A controller has these:

| Property or Method | What It Does |
| --- | --- |
| `root` | The root element. |
| `conversationUid` | The discussion's unique ID. |
| `refresh({ reason, sort })` | Fetches the header and list again. `sort` can be `newest`, `oldest` or `top`, and `reason` is passed on to the request events. Both are optional. |
| `openEditor({ mode, commentUid })` | Opens a reply or edit form, with a `mode` of `reply` or `edit`, on the comment with that UID. A `mode` of `create` opens the form for a new comment, with no `commentUid`. |
| `closeEditor({ reason })` | Closes the open reply or edit form. |
| `showFormErrors(form, { fieldErrors, message })` | Shows errors on a comment form the same way the script shows the server's, and moves the cursor to the first. `fieldErrors` holds messages keyed by input name, such as `fields[comment]`, and `message` goes at the top of the form when no field matches. |
| `clearFormErrors(form)` | Removes the errors shown on a form. |
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

Events are dispatched on the root element and bubble, so you can listen on `document` to catch them from every set of comments on the page. The bundled script starts each instance as soon as it runs, so add your listener in an ordinary script before it if you need `comments:mounted`. When a script removes a thread from the page, such as a single-page app swapping its content, `comments:destroyed` can't bubble from the detached root, so the document gets its own copy of the event.

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
| `comments:submit-before` | A comment, reply or edit is about to be posted. Call `event.preventDefault()` to stop it. | `form`, `mode`, `commentUid`, `formData` |
| `comments:request-start` | A request has started. | `operationId`, and `reason` for anything but posting a comment |
| `comments:request-end` | A request succeeded, and the page has been updated. | `reason` |
| `comments:request-error` | A request failed. | `message`, and sometimes `reason`, `category`, `code`, `fieldErrors`, `recoverable` |
| `comments:viewer-hydrated` | Private controls have arrived on a page using lazy loading or shared caching. | |
| `comments:roots-loaded` | More comments have loaded. | |
| `comments:replies-loaded` | More replies have loaded. | `commentUid` |
| `comments:sorted` | The sort has changed. | `sort` |
| `comments:editor-opened` | A reply or edit form is in the page and ready to use. | `mode`, `commentUid` |
| `comments:editor-closed` | A reply or edit form has closed. | `reason` |
| `comments:comment-created` | A comment or reply was posted. | `operationId`, `resource` |
| `comments:comment-updated` | A comment was edited. | `operationId`, `resource` |
| `comments:comment-removed` | An author deleted their comment. | `commentUid` |
| `comments:comment-moderated` | A moderator acted on a comment. | `commentId`, `moderationAction` |
| `comments:interaction-changed` | A vote was made, changed or taken back. | `commentId`, `type` |
| `comments:report-submitted` | A report was sent, or sent again to withdraw it. | `commentId` |
| `comments:subscription-changed` | The **Follow** button was used. | `subscribed` |

`source` is `user` for `comments:action-before` and `comments:submit-before`, and `api` for everything else. `resource` is the posted comment's UID, or `null` when the comment isn't public yet, such as when it's waiting for moderation. `type` is `upvote` or `downvote`, and a like is an `upvote`.

`comments:action-before` only covers clicks, so it can stop a reply form opening, or a comment being deleted. To stop a comment being posted, use `comments:submit-before`, described next. A `submit` listener of your own on `document` runs too late, because the script has already sent the comment by the time the event reaches it.

## Validating Comments Before They're Posted

`comments:submit-before` fires when someone presses **Post**, or presses ⌘↵ or Ctrl+↵, once the script's own checks have passed and before anything is sent. Its `detail` has the `form`, the `mode` (`create`, `reply` or `edit`), the `commentUid` of the comment being replied to or edited, or `null` for a new comment, and `formData` holding the values about to be posted. Call `event.preventDefault()` to stop the post, and the comment stays in the form for the visitor to fix.

For example, to ask for at least 20 characters, add this in a script on the page. It can go before or after the bundled script, as it listens on `document`:

```js
document.addEventListener('comments:submit-before', (event) => {
    const { form, formData, controller } = event.detail;
    const text = String(formData.get('fields[comment]') ?? '').trim();

    if (text.length < 20) {
        event.preventDefault();

        controller.showFormErrors(form, {
            fieldErrors: {
                'fields[comment]': ['Please write at least 20 characters.'],
            },
        });
    }
});
```

A short comment isn't sent. "Please write at least 20 characters." appears under the comment box, styled and announced to screen readers like the server's own errors, and the cursor goes back to the box. The error clears when the comment is next posted. Name a guest's fields or your [public fields](docs:template-guides/custom-fields) by their input names, such as `fields[myFieldHandle]`, to show an error beside them instead.

`formData` is for reading. To send something different, change the form's own fields in your listener, as the post is built from the form once your listener has finished.

Checks in the browser are a convenience for visitors, and anyone can skip them by posting to the site directly. Comments validates everything again in PHP when it arrives, including the length limit, required fields and spam protection, and PHP always has the final say. A rule that matters, rather than one that saves the visitor a round trip, belongs there too, in a [`beforeSaveComment`](docs:developers/events#the-beforesavecomment-event) event listener, so it also holds for posts without JavaScript and through GraphQL.

`comments:editor-opened` fires once the form has arrived from the server and been put under its comment, with the Editor running in it if the Editor could load, and with the cursor in it. There's only one reply or edit form open at a time, so you can find it from the root element, for example to keep it in view under a sticky header:

```js
document.addEventListener('comments:editor-opened', (event) => {
    const form = event.target.querySelector('[data-comments-form="reply"], [data-comments-form="edit"]');

    form?.scrollIntoView({ block: 'nearest' });
});
```

## Frontend API

The bundled JavaScript talks to these endpoints, which you can use from your own client. They're Craft controller actions under `comments/frontend-api/`, so they work the same whatever routes or element URIs your site has. Each returns JSON, is marked as private and uncacheable, and has an `X-Robots-Tag: noindex` header so search engines leave it out.

Build URLs from the `data-comments-api-base` attribute on the root element. It's the action URL on the current site, such as `https://example.com/actions/comments/frontend-api`, and you add the endpoint's name to it, keeping a `p` query parameter intact on a site that doesn't use pretty URLs. In Twig, `actionUrl('comments/frontend-api/conversation', { ownerId: entry.id, siteId: entry.siteId })` gives the same thing. POST requests need Craft's CSRF token. Pass `siteId` wherever the table lists it, so what comes back is for the site the comments belong to, as the bundled script does. Without it, `siteId` defaults to the current site. `limit` defaults to 20.

| Endpoint | Method | Parameters |
| --- | --- | --- |
| `conversation` | GET | `ownerId`, `siteId`, `sort`, `limit` (up to 50) |
| `roots` | GET | `ownerId`, `siteId`, `cursor`, `sort`, `limit` (up to 50) |
| `replies` | GET | `commentUid`, `limit` (up to 500) |
| `thread` | GET | `ownerId`, `siteId`, `commentId` |
| `viewer-controls` | GET | `ownerId`, `siteId`, `sort`, `limit` (up to 50) |
| `editor` | GET | `ownerId`, `siteId`, `mode` (`create`, `reply` or `edit`), `commentUid` |
| `protection` | GET | `ownerId`, `siteId`, `commentId`, `channel` |
| `create` | POST | The fields from `_editor/form`, including `commentId` for an edit |
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

Each fragment's `type` says which part of the page it's for, which is `conversationHeader`, `rootList`, `viewerControls`, `reportDialog`, `editor`, `comment`, `thread` or `replies`, and its `mode` says whether it replaces, inserts, appends or removes that part. `announcement` is a message for the visitor, which the bundled script shows and reads out to screen readers.

On failure, `status` is `error` and `errors` holds objects with `category`, `code`, `message`, `recoverable`, and for validation, `fieldErrors` keyed by input name. Rate limits use HTTP 429 with `Retry-After`. The other GET endpoints return HTTP 404 for an element or comment that can't be found or shown, except `thread`, which returns an envelope with no fragments for a comment the visitor can't see.

For JSON data rather than HTML, use [GraphQL](docs:developers/graphql).
