# Browser Events

Each thread dispatches `comments:*` events from its root element as things happen in it, such as a comment being posted, a vote changing or a request failing. They're the place for analytics, your own validation and small changes to what's drawn, as the [Validate and Track Comments](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/validate-and-track-comments) user guide shows step by step. Events bubble, so a listener on `document` hears every thread on the page.

```js
document.addEventListener('comments:comment-created', (event) => {
    analytics.track('Comment posted', {
        thread: event.detail.instanceId,
        waitingForApproval: event.detail.moderationState === 'pending',
    });
});
```

A thread starts once the code that draws it has loaded, which is after `DOMContentLoaded`, so a listener added in any script on the page, or in a `DOMContentLoaded` listener, hears every event from `comments:mounted` on. When a script removes a thread's root from the page, such as a single-page app swapping its content, events can't bubble from the detached root, so `document` gets its own copy of each one, apart from cancellable events.

## Every Event

<!-- #region events-shared-keys -->
Every event's `detail` has these keys, along with the event's own:

<!--@include: ../_generated/events-shared.md-->

`apiVersion` is the version of the data format the script reads. `root` is the thread's root element and `instanceId` its `id`, such as `comments-109-1`. `controller` is the thread's [controller](/browser/starting-and-mounting#the-controller). `source` is `user` for the cancellable events a visitor causes, and `api` for everything else.
<!-- #endregion events-shared-keys -->

<!--@include: ../_generated/events.md-->

A cancellable event can be stopped with `event.preventDefault()`. The last column shows which events the [thread store](/core/thread-store#events) in `@verbb/comments-core` emits too, under the same names, for code without a page. The React and Vue components turn every event into a prop or a component event, as [React](/react/events) and [Vue](/vue/events) describe.

## Lifecycle

<!-- #region events-lifecycle -->
`comments:mounted` fires when a thread starts and `comments:destroyed` when it stops. `comments:roots-loaded` fires when a page of comments has arrived and been drawn, `comments:replies-loaded` when a comment's replies have, with the comment's `commentUid`, and `comments:sorted` when the sort changes, with the new `sort`.

`comments:viewer-loaded` fires when the visitor's own details have arrived and been drawn, such as their votes and what they're allowed to do. Its `viewer` holds who's reading, with `isGuest`, `name`, `isModerator`, `canComment` and the rest, so a site can show the person in its own header.
<!-- #endregion events-lifecycle -->

```js
document.addEventListener('comments:viewer-loaded', (event) => {
    const { viewer } = event.detail;

    document.querySelector('#commenting-as').textContent = viewer && !viewer.isGuest
        ? `Commenting as ${viewer.name}`
        : 'Commenting as a guest';
});
```

`comments:before-render` fires before new data is drawn, and can change it or skip drawing it, as [Changing Data Before It's Drawn](/browser/changing-data-before-its-drawn) explains.

## Requests

<!-- #region events-requests -->
`comments:request-start` and `comments:request-end` bracket each request, with a `reason` saying what it's for, such as `thread`, `vote` or `create`. `comments:request-error` fires when one fails, with a `message` that's safe to show a visitor, and for an error from the data API its `code`, `category`, `fieldErrors` and whether it's `recoverable`. [Errors](/core/errors) lists every code. The thread already shows the visitor what went wrong, so this event is for logging and monitoring.
<!-- #endregion events-requests -->

```js
document.addEventListener('comments:request-error', (event) => {
    const { reason, code, message } = event.detail;

    errorTracker.captureMessage(`Comments ${reason} failed: ${code ?? 'unknown'} (${message})`);
});
```

## The Editor

<!-- #region events-editor -->
`comments:editor-opened` fires once a form has been drawn and is ready, with the rich text editor running in it if the thread uses one, and with the cursor in it. Its `mode` is `create`, `reply` or `edit`, and `commentUid` names the comment being replied to or edited. `comments:editor-closed` fires when a reply or edit form closes, with a `reason`. Only one reply or edit form is open at a time, so you can find it from the thread's root, for example to keep it in view under a sticky header.
<!-- #endregion events-editor -->

```js
document.addEventListener('comments:editor-opened', (event) => {
    const form = event.target.querySelector('.comments__editor--reply, .comments__editor--edit');

    form?.scrollIntoView({ block: 'nearest' });
});
```

`comments:before-submit` fires just before a comment, reply or edit is posted, and can stop it, as [Validating Before Posting](/browser/validating-before-posting) explains.

## Comment Changes

<!-- #region events-changes -->
`comments:comment-created` fires once a comment or reply is posted, with its `commentUid` and its `moderationState`, which is `approved`, or `pending` when it waits for a moderator and only its author can see it. `comments:comment-updated` fires after an edit and `comments:comment-deleted` after an author deletes their own comment, each with its `commentUid`.

`comments:vote-changed` fires when a vote is made, changed or taken back, with `vote` as `upvote`, `downvote` or `null` for a vote taken back. A like is an `upvote`. `comments:report-changed` has `reported`, which is `false` once a report is withdrawn, and `comments:comment-moderated` has the moderator's `moderationAction`, such as `approve` or `remove`. These three also give `commentId`, the number in the comment's permalink, such as `1446` for `#comment-1446`. `comments:follow-changed` fires when someone follows or stops following the discussion, with `following`.

`comments:before-action` fires when a control with a `data-comments-action` attribute is clicked, with its `action`, such as `reply`, `edit`, `delete` or `vote`, and the `commentUid` it acts on. Calling `event.preventDefault()` stops it, for example to send people to sign in on your own page before they can reply.
<!-- #endregion events-changes -->

```js
document.addEventListener('comments:before-action', (event) => {
    const { action, controller } = event.detail;

    if (action === 'reply' && controller?.store.getState().viewer?.isGuest) {
        event.preventDefault();
        openSignInDialog();
    }
});
```

`comments:before-action` only covers clicks. To stop a comment being posted, use `comments:before-submit`, because a `submit` listener of your own on `document` runs after the script has already sent the comment.

## Listening to One Thread

Listen on a thread's root, rather than `document`, to hear only that thread, or compare `event.detail.instanceId`. In TypeScript, every event is typed on `HTMLElementEventMap`, `DocumentEventMap` and `WindowEventMap`, so `event.detail` is known without any casting, as [TypeScript](/browser/typescript) shows.
