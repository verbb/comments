# Events and Validation

Each thread dispatches `comments:*` events as things happen in it, such as a comment being posted, a vote changing or a request failing, and they bubble from the `<verbb-comments>` element. They're the place for analytics, your own validation and small changes to what's drawn. A cancellable one can stop what's about to happen with `event.preventDefault()`.

```js
const thread = document.querySelector('verbb-comments');

thread.addEventListener('comments:comment-created', (event) => {
    analytics.track('Comment posted', {
        thread: event.detail.instanceId,
        waitingForApproval: event.detail.moderationState === 'pending',
    });
});
```

Listen on the element to hear one thread, or on `document` to hear every thread on the page. A listener added before the element starts, such as before `registerCommentsWebComponents()` runs, hears `comments:mounted` too.

## Every Event

<!--@include: ../browser/browser-events.md#events-shared-keys-->

<!--@include: ../_generated/events.md-->

The last column shows which events the [thread store](/core/thread-store#events) in `@verbb/comments-core` emits too, for code without a page.

## Lifecycle

<!--@include: ../browser/browser-events.md#events-lifecycle-->

```js
thread.addEventListener('comments:viewer-loaded', (event) => {
    const { viewer } = event.detail;

    document.querySelector('#commenting-as').textContent = viewer && !viewer.isGuest
        ? `Commenting as ${viewer.name}`
        : 'Commenting as a guest';
});
```

`comments:before-render` fires before new data is drawn, and can change it or skip drawing it, as [Changing Data Before It's Drawn](#changing-data-before-it-s-drawn) explains.

## Requests

<!--@include: ../browser/browser-events.md#events-requests-->

```js
thread.addEventListener('comments:request-error', (event) => {
    const { reason, code, message } = event.detail;

    errorTracker.captureMessage(`Comments ${reason} failed: ${code ?? 'unknown'} (${message})`);
});
```

## The Editor

<!--@include: ../browser/browser-events.md#events-editor-->

`comments:before-submit` fires just before a comment, reply or edit is posted, and can stop it, as [Validating Before Posting](#validating-before-posting) explains.

## Comment Changes

<!--@include: ../browser/browser-events.md#events-changes-->

```js
thread.addEventListener('comments:before-action', (event) => {
    const { action, controller } = event.detail;

    if (action === 'reply' && controller?.store.getState().viewer?.isGuest) {
        event.preventDefault();
        openSignInDialog();
    }
});
```

`comments:before-action` only covers clicks. To stop a comment being posted, use `comments:before-submit`, because a `submit` listener of your own runs after the thread has already sent the comment.

## Validating Before Posting

`comments:before-submit` fires when someone presses **Post**, or presses ⌘↵ or Ctrl+↵, once the thread's own checks have passed and before anything is sent. It's the one place for checks of your own, such as a minimum length or a word you don't want posted, and it can show its message in the form exactly as the server's errors appear.

```js
thread.addEventListener('comments:before-submit', (event) => {
    const { form, formData, controller } = event.detail;

    if (String(formData.get('content') ?? '').trim().length >= 20) return;

    event.preventDefault();
    controller?.showFormErrors(form, { fieldErrors: { content: ['Please write at least 20 characters.'] } });
});
```

A short comment isn't sent. “Please write at least 20 characters.” appears under the comment box, styled and announced to screen readers like the server's own errors, and the cursor goes back to the box with the comment still in it. The error clears when the comment is next posted.

### What the Event Gives You

<!--@include: ../browser/validating-before-posting.md#validate-detail-->

### Showing Errors

<!--@include: ../browser/validating-before-posting.md#validate-errors-->

```js
// A "rating" field that must be given on top-level comments.
thread.addEventListener('comments:before-submit', (event) => {
    const { form, formData, mode, controller } = event.detail;

    if (mode === 'create' && !formData.get('fields[rating]')) {
        event.preventDefault();
        controller?.showFormErrors(form, { fieldErrors: { 'fields.rating': ['Choose a rating.'] } });
    }
});
```

### The Server Has the Final Say

<!--@include: ../browser/validating-before-posting.md#validate-server-->

## Changing Data Before It's Drawn

`comments:before-render` fires each time new data is about to be drawn, with the thread's state in `event.detail.data`. A listener can change that state in place, and the thread draws what it's left with. It suits small adjustments that don't justify a [part override](/web-components/part-overrides), such as renaming a badge on one page.

```js
// The Staff badge reads Team in this thread, including on comments that load later.
thread.addEventListener('comments:before-render', (event) => {
    for (const comment of Object.values(event.detail.data.comments)) {
        for (const badge of comment.author?.badges ?? []) {
            if (badge.type === 'staff') badge.label = 'Team';
        }
    }
});
```

### What the Event Gives You

<!--@include: ../browser/changing-data-before-its-drawn.md#before-render-detail-->

### Data from the Server

<!--@include: ../browser/changing-data-before-its-drawn.md#before-render-server-->
