# The Thread Store

A thread store holds one thread's state, the comments loaded so far, who's reading and what they may do, and updates it as requests answer and the person acts. Your interface subscribes to it and draws from its state, much as the browser package's renderer does. The React hook `useCommentsThread()` and the Vue composable of the same name are a store underneath.

```js
import { createCommentsClient, createThreadStore, selectReplies, selectRoots } from '@verbb/comments-core';

const client = createCommentsClient({ endpoint: '/actions/comments/api' });
const store = createThreadStore(client, { ownerUri: 'blog/my-post', sort: 'top' });

const unsubscribe = store.subscribe((state) => {
    if (state.status === 'ready') {
        draw(selectRoots(state), (uid) => selectReplies(state, uid));
    }
});

await store.load();
```

Here `draw()` stands for your own code that puts the comments on the page. Each change gives subscribers a new state object, rather than changing the old one, so a framework can compare them cheaply.

## Options

| Option | What it does |
| --- | --- |
| `ownerId` or `ownerUri` | The element the comments belong to. |
| `siteId` | The site, on a multi-site install. |
| `sort` | `newest`, `oldest` or `top`. Defaults to the thread's own default. |
| `limit` | Top-level comments in each page, from 1 to 50. |
| `viewer` | Whether to make the private `viewer` read alongside each public read, for votes, permissions and the person's own pending comments. Defaults to `true`. |
| `evidence` | Produces spam protection evidence for posting and editing, covered in [Protection](/core/protection). |

## State {#state}

`store.getState()` returns the current state, which is what every subscriber is given.

<!-- #region store-state -->
| Key | What it holds |
| --- | --- |
| `status` | `idle`, `loading`, `ready` or `error`. |
| `error` | Why the thread couldn't load, as a [`CommentsRequestError`](/core/errors). Errors from actions are thrown to their caller instead. |
| `conversation` | The discussion, with its `commentCount`, whether it's open, the sort options it offers and its closed notice. |
| `comments` | Every loaded comment, keyed by UID, in the data API's shape. |
| `roots` | The UIDs of the top-level comments, in order. `selectRoots(state)` gives the comments themselves. |
| `next` and `loadingMore` | The cursor for the next page of top-level comments, and whether it's loading. |
| `replies` | Each comment's loaded reply UIDs, total count, next cursor and loading flag, by the comment's UID. `selectReplies(state, uid)` gives the loaded replies themselves. |
| `viewer` | Who's reading, with `isGuest`, `name`, `canComment`, `isModerator` and the rest. |
| `permissions` | What the person may do with each comment, by UID, such as `canReply`, `canEdit`, `canVote` and their own `vote`. |
| `moderation` | For a moderator, the held and reported counts. |
| `busy` | Actions waiting on the server, such as `vote:<uid>`, so a control can show it's busy. |
| `unavailable` | Actions the sign-in never allows, such as editing for a guest on another site. |
| `labels` and `locale` | The thread's translated wording and the site's language. |
| `sort` and `threadVersion` | The current sort, and a stamp of the public data the thread was drawn from. |

A comment's own `replies.items` is always empty in the store, because `replies` holds the order instead.
<!-- #endregion store-state -->

## Actions

<!-- #region store-actions -->
| Action | What it does |
| --- | --- |
| `load()` | Loads the first page, and the viewer's window alongside it. |
| `loadMore()` | Loads the next page of top-level comments. |
| `setSort(sort)` | Changes the sort and loads again from the start. |
| `loadReplies(uid)` | Loads the next page of one comment's replies. |
| `loadComment(target)` | Loads one comment and its parents, by UID or `{ id }` from its permalink, for a link to it. |
| `refreshViewer()` | Reads the viewer again for everything loaded, such as after signing in. |
| `create(input)` | Posts a comment, or a reply with `parentUid`. |
| `update(uid, input)` | Saves an edit to the person's own comment. |
| `delete(uid)` | Deletes the person's own comment. |
| `vote(uid, vote)` | Sets a vote, `upvote`, `downvote` or `null` to take it back. |
| `report(uid, { reason, details })` and `withdrawReport(uid)` | Reports a comment, or withdraws the person's report. |
| `follow(following, email)` | Follows or stops following the discussion. A guest gives an email address. |
| `moderate(uid, action, { reason, note })` | A moderator's decision, such as `approve` or `remove`. |

Every action that writes returns the data API's answer and updates the state from it. Starting the same write again while it's in flight, such as a double click on **Post**, gets the first one's answer rather than sending another. A write that fails throws a [`CommentsRequestError`](/core/errors), with `fieldErrors` keyed by field for a form to show, and leaves the state as it was.
<!-- #endregion store-actions -->

```js
try {
    const { comment, moderationState } = await store.create({
        content: { format: 'plain', text: 'Thanks for writing this up.' },
        guest: { name: 'Sam', email: 'sam@example.com', notifyReplies: false },
    });

    console.log(moderationState === 'pending' ? 'Waiting for approval' : `Posted ${comment.uid}`);
} catch (error) {
    console.log(error.fieldErrors);
}
```

The store also has `protection(uid)`, the protection flow for new comments or for editing one comment, which [Protection](/core/protection) covers, and `destroy()`, which stops it. Answers to requests still in flight are then ignored, and later actions are refused.

`content` is `{ format: 'plain', text }`, or `{ format: 'rich', document }` with Editor's document. `guest` is only for someone who isn't signed in, and `fields` holds custom field values by handle, in the shapes the data API takes. A site with spam protection on refuses a post without evidence, which [Protection](/core/protection) explains how to give.

## Events {#events}

`store.on(name, listener)` tells you as things happen to the thread, for code without a page. The events are the browser events about the thread's data, with the same names, and each listener gets the `detail` directly rather than an event, typed by `ThreadStoreEventMap`, with `apiVersion` on every one.

```js
const stop = store.on('comments:comment-created', ({ commentUid, moderationState, replayed }) => {
    if (!replayed) {
        notifyTeam(`New comment ${commentUid} (${moderationState})`);
    }
});
```

The [Browser Events](/browser/browser-events#every-event) table shows which events the store emits and the keys it adds that the browser events don't have, such as `cursor` and `count` on `comments:roots-loaded` and `replayed` on `comments:comment-created`, which is `true` when the server answered a repeated write with its first result. Its `comments:request-error` carries the `error` itself, and a general `message` for an error that isn't a `CommentsRequestError`. The page's own events, such as `comments:before-submit` and `comments:editor-opened`, come only from the browser package. `on()` returns a function that stops listening.
