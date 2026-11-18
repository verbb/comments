# Events and Validation

Every event a thread dispatches is a prop of `<CommentsThread>`, named after it, such as `onCommentCreated` for `comments:comment-created`. They're the place for analytics, your own validation and small changes to what's drawn. Each handler gets the event itself, so a cancellable one can stop what's about to happen with `event.preventDefault()`.

```tsx
<CommentsThread
    ownerUri={uri}
    onCommentCreated={(event) => analytics.track('Comment posted', {
        thread: event.detail.instanceId,
        waitingForApproval: event.detail.moderationState === 'pending',
    })}
/>
```

The handlers are read when each event happens, so a new handler on each render is fine, and `onMounted` hears the thread start. `useCommentsEvent()` listens from anywhere else in your app, as [below](#listening-from-elsewhere).

## Every Event

<!--@include: ../browser/browser-events.md#events-shared-keys-->

<!--@include: ../_generated/event-props-react.md-->

## Lifecycle

<!--@include: ../browser/browser-events.md#events-lifecycle-->

```tsx
import type { ViewerData } from '@verbb/comments-core';

const [viewer, setViewer] = useState<ViewerData | null>(null);

<p>{viewer && !viewer.isGuest ? `Commenting as ${viewer.name}` : 'Commenting as a guest'}</p>
<CommentsThread ownerUri={uri} onViewerLoaded={(event) => setViewer(event.detail.viewer)} />
```

`onBeforeRender` fires before new data is drawn, and can change it or skip drawing it, as [Changing Data Before It's Drawn](#changing-data-before-it-s-drawn) explains.

## Requests

<!--@include: ../browser/browser-events.md#events-requests-->

```tsx
<CommentsThread
    ownerUri={uri}
    onRequestError={(event) => {
        const { reason, code, message } = event.detail;

        errorTracker.captureMessage(`Comments ${reason} failed: ${code ?? 'unknown'} (${message})`);
    }}
/>
```

## The Editor

<!--@include: ../browser/browser-events.md#events-editor-->

`onBeforeSubmit` fires just before a comment, reply or edit is posted, and can stop it, as [Validating Before Posting](#validating-before-posting) explains.

## Comment Changes

<!--@include: ../browser/browser-events.md#events-changes-->

```tsx
<CommentsThread
    ownerUri={uri}
    onBeforeAction={(event) => {
        const { action, controller } = event.detail;

        if (action === 'reply' && controller?.store.getState().viewer?.isGuest) {
            event.preventDefault();
            openSignInDialog();
        }
    }}
/>
```

## Validating Before Posting

`onBeforeSubmit` fires when someone presses **Post**, or presses ⌘↵ or Ctrl+↵, once the thread's own checks have passed and before anything is sent. It's the one place for checks of your own, such as a minimum length or a word you don't want posted, and it can show its message in the form exactly as the server's errors appear.

```tsx
<CommentsThread
    ownerUri={uri}
    onBeforeSubmit={(event) => {
        const { form, formData, controller } = event.detail;

        if (String(formData.get('content') ?? '').trim().length >= 20) return;

        event.preventDefault();
        controller?.showFormErrors(form, { fieldErrors: { content: ['Please write at least 20 characters.'] } });
    }}
/>
```

A short comment isn't sent. “Please write at least 20 characters.” appears under the comment box, styled and announced to screen readers like the server's own errors, and the cursor goes back to the box with the comment still in it. The error clears when the comment is next posted.

### What the Event Gives You

<!--@include: ../browser/validating-before-posting.md#validate-detail-->

### Showing Errors

<!--@include: ../browser/validating-before-posting.md#validate-errors-->

```tsx
// A "rating" field that must be given on top-level comments.
<CommentsThread
    ownerUri={uri}
    onBeforeSubmit={(event) => {
        const { form, formData, mode, controller } = event.detail;

        if (mode === 'create' && !formData.get('fields[rating]')) {
            event.preventDefault();
            controller?.showFormErrors(form, { fieldErrors: { 'fields.rating': ['Choose a rating.'] } });
        }
    }}
/>
```

### The Server Has the Final Say

<!--@include: ../browser/validating-before-posting.md#validate-server-->

## Changing Data Before It's Drawn

`onBeforeRender` fires each time new data is about to be drawn, with the thread's state in `event.detail.data`. A handler can change that state in place, and the thread draws what it's left with. It suits small adjustments that don't justify a [part override](/react/part-overrides), such as renaming a badge in one part of your app.

```tsx
// The Staff badge reads Team in this thread, including on comments that load later.
<CommentsThread
    ownerUri={uri}
    onBeforeRender={(event) => {
        for (const comment of Object.values(event.detail.data.comments)) {
            for (const badge of comment.author?.badges ?? []) {
                if (badge.type === 'staff') badge.label = 'Team';
            }
        }
    }}
/>
```

### What the Event Gives You

<!--@include: ../browser/changing-data-before-its-drawn.md#before-render-detail-->

### Data from the Server

<!--@include: ../browser/changing-data-before-its-drawn.md#before-render-server-->

## Listening from Elsewhere {#listening-from-elsewhere}

`useCommentsEvent(name, handler)` listens for an event from any thread on the page while your component is mounted, such as a header that counts new comments. Pass `{ instanceId }` for one thread.

```tsx
import { useCommentsEvent } from '@verbb/comments-react';

useCommentsEvent('comments:comment-created', (event) => {
    setUnread((count) => count + 1);
});
```

It also hears a thread from `useCommentsThread()`, which has no page to dispatch on, as [Hooks for Your Own UI](/react/hooks#listening-for-events) shows.
