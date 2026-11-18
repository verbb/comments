# Composables for Your Own UI

When every element of the interface should be your own, the composables give you a thread's state and actions as refs, with none of Comments' markup. `useCommentsThread()` reads and writes the thread through `@verbb/comments-core`'s [thread store](/core/thread-store), and `useCommentForm()` posts from a form of your own with the site's spam protection mounted on it. Signing in, retries and errors are still handled for you.

Drawing your own interface means drawing everything the thread would, such as states, permissions and moderation, so start with [`<CommentsThread>`](/vue/comments-thread) and [part overrides](/vue/part-overrides) unless you need more than they give.

## A Discussion of Your Own

This draws a post's comments with a vote button each, and a form for a new comment, using the app's `CommentsPlugin` settings.

```vue
<script setup>
import { useCommentForm, useCommentsThread } from '@verbb/comments-vue';

const props = defineProps(['uri']);
const thread = useCommentsThread(() => ({ ownerUri: props.uri }));
const form = useCommentForm({ thread });
const { state, roots } = thread;
const { handleSubmit, fieldErrors, error, posting } = form;

function toggleVote(comment) {
    const vote = state.value.permissions[comment.uid]?.vote ?? null;

    thread.vote(comment.uid, vote === 'upvote' ? null : 'upvote');
}
</script>

<template>
    <p v-if="state.status === 'error'" role="alert">{{ state.error?.message }}</p>
    <p v-else-if="state.status !== 'ready'">Loading comments…</p>
    <section v-else>
        <h2>{{ state.conversation?.commentCount ?? 0 }} comments</h2>

        <form v-if="state.viewer?.canComment" :ref="form.ref" @submit="handleSubmit">
            <textarea name="content" />
            <p v-for="message in fieldErrors.content ?? []" :key="message">{{ message }}</p>
            <p v-if="error" role="alert">{{ error }}</p>
            <button :disabled="posting">Post</button>
        </form>

        <ul>
            <li v-for="comment in roots" :key="comment.uid">
                <strong>{{ comment.author?.name }}</strong> {{ comment.content?.text }}
                <button v-if="comment.interaction" type="button" @click="toggleVote(comment)">▲ {{ comment.interaction.upvotes }}</button>
            </li>
        </ul>
    </section>
</template>
```

The options are a getter, so a new `uri` makes a new thread. The [Vue starter](/starters/vue)'s second page is a fuller version, with guests' name and email fields, replies, states and **Load more**.

Bind the form with `:ref="form.ref"`, which hands Vue the ref itself. A string `ref="formRef"` with `formRef` taken out of the result works in the browser too, but `vue-tsc` doesn't count it as a use, so a project checking for unused variables fails its type check.

## `useCommentsThread()`

| Option | What it does |
| --- | --- |
| `ownerId` or `ownerUri` | The element the comments belong to. |
| `siteId`, `endpoint` and `auth` | Default to the plugin's settings. |
| `sort` and `limit` | As the thread store takes them. |
| `viewer` | Whether to read the person's own details alongside each page, for votes, permissions and their own pending comments. Defaults to `true`. |
| `autoLoad` | Whether to load the thread as soon as the component mounts. Defaults to `true`. With `false`, call `load()` yourself. |
| `fetch` | A `fetch` to use instead of the plugin's or the global one. |

The options can be a plain object, a ref or a getter, so the thread can follow a route. The store is made in the browser when the component mounts, so a server render has the empty state and nothing is fetched there. A change to the owner, site, endpoint, sort, limit or kind of sign-in makes a new store.

### Return Value

| Result | What it is |
| --- | --- |
| `state` | A shallow ref of the thread's state, [below](#state), which changes with every load, write and answer. |
| `roots` | A computed list of the top-level comments to show, in order. |
| `replies(uid)` | One comment's loaded replies, in order, which is reactive when called in a template or a computed. |
| Every action | The thread store's actions, bound to it, [below](#actions). |
| `store` and `client` | Refs of `@verbb/comments-core`'s store and client, once the component has mounted in the browser, and `null` on the server. |

### State {#state}

<!--@include: ../core/thread-store.md#store-state-->

### Actions {#actions}

<!--@include: ../core/thread-store.md#store-actions-->

`CommentsRequestError` comes from `@verbb/comments-core`, and [Errors](/core/errors) lists every code it can carry. Its `message` is always safe to show the person.

## `useCommentForm()`

`useCommentForm({ thread })` posts from your own form. Bind its `ref` to your `<form>`, so Protect can add its fields and run its checks there, and its `handleSubmit` to `@submit`. The spam protection is mounted when someone starts writing, kept fresh while the form is open, and its evidence goes with each post.

The form reads `content`, `guest[name]`, `guest[email]`, `guest[notifyReplies]` and `fields[handle]` from its own fields, or takes them as `submit({ content, guest, fields })`. After a post it empties the fields the browser holds, keeping a guest's name and email for their next comment. A `v-model` value is your own state, so empty it yourself when `submit()` resolves with an answer rather than `null`. Trying the same comment again after a failure reuses its operation ID, so the server never posts it twice.

| Option or result | What it is |
| --- | --- |
| `thread` | What `useCommentsThread()` returns, or a store. |
| `mode`, `parentUid` and `commentUid` | `create` by default, `reply` with `parentUid`, or `edit` with `commentUid`. |
| `nonce` | A Content Security Policy nonce for the spam protection's challenge scripts. Defaults to the plugin's setting. |
| `ref` | A template ref to bind to your `<form>`. |
| `submit(input)` and `handleSubmit(event)` | Posts, resolving with the data API's answer, or `null` when it was refused. |
| `fieldErrors` and `error` | Refs of why the last post was refused, keyed by field as `content`, `guest.name`, `guest.email` or `fields.handle`, and a message for the whole form. |
| `posting` | A ref of whether a post is on its way. |
| `reset()` | Clears the errors. |

```js
// A reply form under one comment.
const replyForm = useCommentForm({ thread, mode: 'reply', parentUid: comment.uid });
```

## Listening for Events {#listening-for-events}

`useCommentsEvent(name, handler)` listens for a browser event from any thread on the page while your component is mounted. Pass `{ instanceId }` for one thread, or `{ target }` with a template ref to listen on an element of your own.

```js
import { useCommentsEvent } from '@verbb/comments-vue';

useCommentsEvent('comments:comment-created', (event) => {
    analytics.track('Comment posted', { thread: event.detail.instanceId });
});
```

With `{ thread }`, it hears the events of a thread from `useCommentsThread()` instead, and the handler gets each event's `detail` directly, as the [thread store](/core/thread-store#events) emits it.
