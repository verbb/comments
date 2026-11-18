# Hooks for Your Own UI

When every element of the interface should be your own, such as an app with its own design system all the way through, the hooks give you a thread's state and actions with none of Comments' markup. `useCommentsThread()` reads and writes the thread through `@verbb/comments-core`'s [thread store](/core/thread-store), and `useCommentForm()` posts from a form of your own with the site's spam protection mounted on it. Signing in, retries and errors are still handled for you.

Drawing your own interface means drawing everything the thread would, such as states, permissions and moderation, so start with [`<CommentsThread>`](/react/comments-thread) and [part overrides](/react/part-overrides) unless you need more than they give.

## A Discussion of Your Own

This draws a post's comments with a vote button each, and a form for a new comment. It runs inside a `<CommentsProvider>`, which gives it the data API's address and the sign-in.

```tsx
import { useCommentForm, useCommentsThread } from '@verbb/comments-react';

export function Discussion({ uri }: { uri: string }) {
    const thread = useCommentsThread({ ownerUri: uri });
    const form = useCommentForm({ thread });
    const { state } = thread;

    if (state.status === 'error') return <p role="alert">{state.error?.message}</p>;
    if (state.status !== 'ready') return <p>Loading comments…</p>;

    return (
        <section>
            <h2>{state.conversation?.commentCount ?? 0} comments</h2>

            {state.viewer?.canComment ? (
                <form ref={form.ref} onSubmit={form.handleSubmit}>
                    <textarea name="content" />
                    {form.fieldErrors.content?.map((message) => <p key={message}>{message}</p>)}
                    {form.error ? <p role="alert">{form.error}</p> : null}
                    <button disabled={form.posting}>Post</button>
                </form>
            ) : null}

            <ul>
                {thread.roots.map((comment) => {
                    const vote = state.permissions[comment.uid]?.vote ?? null;

                    return (
                        <li key={comment.uid}>
                            <strong>{comment.author?.name}</strong> {comment.content?.text}
                            {comment.interaction ? (
                                <button type="button" aria-pressed={vote === 'upvote'} onClick={() => thread.vote(comment.uid, vote === 'upvote' ? null : 'upvote')}>
                                    ▲ {comment.interaction.upvotes}
                                </button>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
```

Give the component a `key` of the post, such as `<Discussion key={uri} uri={uri} />`, so another post gets a thread of its own. The [React starter](/starters/react)'s second page is a fuller version, with guests' name and email fields, replies, states and **Load more**.

## `useCommentsThread()`

| Option | What it does |
| --- | --- |
| `ownerId` or `ownerUri` | The element the comments belong to. |
| `siteId`, `endpoint` and `auth` | Default to the provider's. |
| `sort` and `limit` | As the thread store takes them. |
| `viewer` | Whether to read the person's own details alongside each page, for votes, permissions and their own pending comments. Defaults to `true`. |
| `autoLoad` | Whether to load the thread as soon as the component mounts. Defaults to `true`. With `false`, call `load()` yourself. |
| `fetch` | A `fetch` to use instead of the provider's or the global one. |

The store is made in the browser when the component mounts, so a server render has the empty state and nothing is fetched there. A change to the owner, site, endpoint, sort, limit or kind of sign-in makes a new store. A new provider of the same kind is taken over without one.

### Return Value

| Result | What it is |
| --- | --- |
| `state` | The thread's state, [below](#state). Your component renders again whenever it changes. |
| `roots` | The top-level comments to show, in order. |
| `replies(uid)` | One comment's loaded replies, in order. |
| Every action | The thread store's actions, bound to it, [below](#actions). |
| `store` and `client` | `@verbb/comments-core`'s store and client themselves, once the component has mounted in the browser, and `null` on the server. |

### State {#state}

<!--@include: ../core/thread-store.md#store-state-->

### Actions {#actions}

<!--@include: ../core/thread-store.md#store-actions-->

```tsx
try {
    await thread.vote(comment.uid, 'upvote');
} catch (error) {
    setNotice(error instanceof CommentsRequestError ? error.message : 'Your vote couldn’t be saved.');
}
```

`CommentsRequestError` comes from `@verbb/comments-core`, and [Errors](/core/errors) lists every code it can carry.

## `useCommentForm()`

`useCommentForm({ thread })` posts from your own form. Put its `ref` on your `<form>`, so Protect can add its fields and run its checks there, and its `handleSubmit` on `onSubmit`. The spam protection is mounted when someone starts writing, kept fresh while the form is open, and its evidence goes with each post.

The form reads `content`, `guest[name]`, `guest[email]`, `guest[notifyReplies]` and `fields[handle]` from its own fields, or takes them as `submit({ content, guest, fields })`. After a post it empties the fields the browser holds, keeping a guest's name and email for their next comment. A controlled input's value is your own state, so empty it yourself when `submit()` resolves with an answer rather than `null`. Trying the same comment again after a failure reuses its operation ID, so the server never posts it twice.

| Option or result | What it is |
| --- | --- |
| `thread` | What `useCommentsThread()` returns, or a store. |
| `mode`, `parentUid` and `commentUid` | `create` by default, `reply` with `parentUid`, or `edit` with `commentUid`. |
| `nonce` | A Content Security Policy nonce for the spam protection's challenge scripts. Defaults to the provider's. |
| `submit(input)` | Posts, resolving with the data API's answer, or `null` when it was refused. |
| `fieldErrors` and `error` | Why the last post was refused, keyed by field as `content`, `guest.name`, `guest.email` or `fields.handle`, and a message for the whole form. |
| `posting` | Whether a post is on its way. |
| `reset()` | Clears the errors. |

```tsx
// A reply form under one comment.
const replyForm = useCommentForm({ thread, mode: 'reply', parentUid: comment.uid });
```

## Listening for Events {#listening-for-events}

`useCommentsEvent(name, handler)` listens for a browser event from any thread on the page while your component is mounted. Pass `{ instanceId }` for one thread, or `{ target: ref }` to listen on an element of your own.

```tsx
import { useCommentsEvent } from '@verbb/comments-react';

useCommentsEvent('comments:comment-created', (event) => {
    analytics.track('Comment posted', { thread: event.detail.instanceId });
});
```

With `{ thread }`, it hears the events of a thread from `useCommentsThread()` instead, which has no page to dispatch them on. The handler then gets each event's `detail` directly, as the [thread store](/core/thread-store#events) emits it.

```tsx
useCommentsEvent('comments:comment-created', (detail) => {
    if (!detail.replayed) setNotice('Thanks for your comment.');
}, { thread });
```
