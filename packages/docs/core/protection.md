# Protection

Comments checks every new comment and edit for spam with Protect, which can combine a honeypot, a minimum time to write, browser signals and a challenge such as Turnstile or reCAPTCHA, as the plugin's [Anti-Spam](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/anti-spam) page explains. The server gives each form a preparation, a short-lived, single-use description of the checks to run. Protect mounts it on the form, and when the comment is posted, Protect's evidence goes with it. The browser package and the React and Vue form hooks do all of this for you, so this page is for a custom interface built on core alone.

## The Protection Flow

A page left open all day would hold a preparation long past its lifetime, and two forms posting together could try to use one preparation twice. The protection flow keeps one form's preparation usable. While the form is active it fetches a fresh preparation at about 80% of the current one's lifetime, it fetches one when a stale one is needed (such as when a hidden tab becomes visible), and it replaces the preparation after every post, because each can only be used once. When the server says a preparation expired or was missing, the store fetches a new one and posts once more by itself. A `challenge_failed` or `possible_spam` answer is never retried, because the visitor or the comment has to change.

`store.protection()` gives the thread store's flow for new comments and replies, and `store.protection(uid)` the flow for editing one comment. Both need the thread loaded first, so the store knows its owner.

| Method | What it does |
| --- | --- |
| `current()` | The current preparation, or `null`. |
| `ensure()` | A fresh, unused preparation, fetching one if needed. Resolves with `null` when the site doesn't use protection here. |
| `refresh()` | Fetches a new preparation now. |
| `activate()` and `deactivate()` | Starts and stops keeping the preparation fresh on a timer, while a form is open. |
| `subscribe(listener)` | Calls `listener` with each new preparation, so the form can mount it. |
| `destroy()` | Stops the flow. |

`createProtectionFlow({ client, ownerId })` makes a flow of your own, outside a store.

## Evidence for Your Own Form

In a browser, `commentForm()` from `@verbb/comments-browser/form` does all of this for a form of your own, as [Your Own Interface](/browser/your-own-interface) explains, and React's and Vue's `useCommentForm()` are built on it. The rest of this section is what it does underneath, for code that handles the spam protection itself.

A write's `evidence` option, or the store's, is a function that's given the preparation and resolves with Protect's evidence. Mount the preparation on your form with Protect's own package, `@verbb/protect`, which core depends on, and Protect adds its hidden fields and any challenge to the form and gathers its signals while someone writes. Mounting starts when someone starts writing, as the thread's own forms do, so Protect's timing reflects the time they spent.

```js
import { mountProtection } from '@verbb/protect';

const form = document.querySelector('#comment-form');
let protect = null;
let mounted = null;

// Mounts a preparation on the form, once for each.
const mount = (preparation) => {
    if (!preparation || preparation.manifest === mounted) return;

    protect?.destroy();
    protect = mountProtection(form, preparation.manifest);
    mounted = preparation.manifest;
};

// After `await store.load()`, so the store knows the thread's owner.
const flow = store.protection();

form.addEventListener('focusin', () => {
    flow.activate();
    flow.subscribe(mount);
    flow.ensure().then(mount);
}, { once: true });

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    await store.create({ content: { format: 'plain', text: form.elements.content.value } }, {
        evidence: async (preparation) => {
            mount(preparation);
            return protect.prepare();
        },
    });
});
```

Pass your Content Security Policy's nonce as `mountProtection(form, manifest, { nonce })` when a challenge's script needs one. Without evidence, a site that protects comments refuses the post with `protection_required`.

## Where Protection Can't Run

Protect's browser signals need a real form on a page. A transport tells the server which kind of client is asking, as its `protectionChannel`, which is `browser` for REST and `headless` for GraphQL, and the server prepares only the checks that can run there.
