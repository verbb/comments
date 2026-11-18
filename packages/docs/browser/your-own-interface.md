# Your Own Interface

When every element of the interface should be your own and your site has no framework, draw the thread from `@verbb/comments-core`'s [thread store](/core/thread-store) and post from your own `<form>` with `commentForm()`, from `@verbb/comments-browser/form`. The store gives the thread's state and actions with none of Comments' markup, and `commentForm()` mounts the site's spam protection on your form. Signing in, retries and errors are still handled for you. React's and Vue's `useCommentForm()` are built on the same function.

Drawing your own interface means drawing everything the thread would, such as states, permissions and moderation, so start with the thread itself, [`themeConfig`](/browser/theme-config) and [part overrides](/browser/part-overrides) unless you need more than they give.

```js
import { createCommentsClient, createThreadStore, selectRoots } from '@verbb/comments-core';
import { commentForm } from '@verbb/comments-browser/form';

const client = createCommentsClient({ endpoint: '/actions/comments/api' });
const store = createThreadStore(client, { ownerUri: 'blog/my-post' });

store.subscribe((state) => draw(selectRoots(state)));
await store.load();

const element = document.querySelector('#comment-form');
const form = commentForm(element, store);

element.addEventListener('submit', async (event) => {
    const response = await form.handleSubmit(event);

    // Null means it was refused, with the reasons in form.getState().
    if (response) element.reset();
});

form.subscribe(({ fieldErrors, error, posting }) => {
    element.querySelector('button').disabled = posting;
    element.querySelector('[role="alert"]').textContent = error ?? '';
});
```

Here `draw()` stands for your own code that puts the comments on the page. The [web components starter](/starters/web)'s **Your own interface** page is a complete example, with replies, votes and a guest's name and email.

## `commentForm()`

`commentForm(form, store, options)` posts from your own form to a thread store. The spam protection is mounted when someone starts writing in the form, kept fresh while it's open, and its evidence goes with each post.

The form reads `content`, `guest[name]`, `guest[email]`, `guest[notifyReplies]` and `fields[handle]` from its own fields, or takes them as `submit({ content, guest, fields })`. After a post it empties the form's fields, keeping a guest's name and email for their next comment. Trying the same comment again after a failure reuses its operation ID, so the server never posts it twice.

| Option | What it does |
| --- | --- |
| `mode` | `create` by default, `reply` with `parentUid`, or `edit` with `commentUid`. |
| `parentUid` and `commentUid` | The comment being replied to or edited. |
| `nonce` | A Content Security Policy nonce for the spam protection's challenge scripts. |

| Method | What it does |
| --- | --- |
| `submit(input)` | Posts, resolving with the data API's answer, or `null` when it was refused. |
| `handleSubmit(event)` | Stops the browser's own submission and calls `submit()`, for a `submit` listener. |
| `getState()` | `fieldErrors`, why the last post was refused, keyed by field as `content`, `guest.name`, `guest.email` or `fields.handle`, `error`, a message for the whole form, and `posting`. |
| `subscribe(listener)` | Calls `listener` with the state after each change, and returns a function that stops it. |
| `set({ mode, parentUid, commentUid })` | Changes what the form posts, such as which comment one reply form answers. |
| `reset()` | Clears the errors. |
| `destroy()` | Takes the spam protection off the form, for when you remove it from the page. |

```js
// A reply form under one comment.
const replyForm = commentForm(replyElement, store, { mode: 'reply', parentUid: comment.uid });
```

`@verbb/comments-browser/form` is its own entry, so importing it doesn't load the thread's renderer. [Protection](/core/protection#evidence-for-your-own-form) explains what it does underneath, for a form that needs to handle the spam protection itself.
