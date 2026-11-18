# The `<CommentsThread>` Component

`<CommentsThread>` is one thread. It renders the same mount point `craft.comments.render()` writes, with the reserved space, the heading and the count, and the browser package draws the thread inside it once the component has mounted in the browser.

```vue
<script setup>
import { CommentsThread } from '@verbb/comments-vue';

defineProps(['entry']);

function track(event) {
    console.info('Comment posted', event.detail.moderationState);
}
</script>

<template>
    <CommentsThread :key="entry.uri" :owner-uri="entry.uri" sort="top" load-mode="button" @comment-created="track" />
</template>
```

## Props

| Prop | What it does |
| --- | --- |
| `owner-id` or `owner-uri` | The element the comments belong to, by its ID or its URI. |
| `site-id` | The site, for a multi-site install. Defaults to the plugin's setting. |
| `endpoint` | The data API's address. Defaults to the plugin's setting, then `/actions/comments/api` on the page's own origin. |
| `load-mode` | `visible`, the default, loads the thread just before it scrolls into view, `button` waits for **Show comments**, and `eager` loads it straight away, as [below](#when-the-thread-loads). |
| `sort` | `newest`, `oldest` or `top`. |
| `limit` | Top-level comments in each page, from 1 to 50. |
| `theme` | `light`, `dark`, `auto` or `none`. Defaults to the plugin's setting, then `light`, as [Dark Mode](/vue/styling#dark-mode) explains. |
| `dark-selector` | A selector, such as `html.dark`, that switches the thread to dark while it matches `<html>`. |
| `theme-config` | Classes and attributes for the ten controls, merged control by control over the plugin's, as [Styling](/vue/styling#themeconfig-second) explains. |
| `parts` and `fields` | Part and field overrides, as [Part Overrides](/vue/part-overrides) and [Field Overrides](/vue/field-overrides) explain. |
| `labels` | Wording to use instead of the site's translations, as [below](#labels). |
| `count` | The comment count to show before the thread's data arrives, such as from `fetchCommentsCount()`. |
| `id` | The mount point's `id`. Defaults to `comments-<ownerId>-<siteId>`, as `render()` writes it. |
| `@mounted`, `@comment-created` and the rest | A component event for every event, as [Events and Validation](/vue/events) lists. |

## When Props Change

Give the component a `key` of the owner, as above, so moving to another post starts a fresh thread rather than reusing the old one. It also starts a new thread by itself when `owner-id`, `owner-uri`, `site-id`, `endpoint` or `limit` changes. A new `sort` re-sorts a loaded thread in place, keeping a half-written comment and the reader's place, and starts a thread that's still waiting again in the new order, as it does a thread whose `sort` is taken away, which goes back to the site's default. A new `theme` switches the drawn thread in place, and new `theme-config`, `parts`, `fields` or `labels` redraw it. `load-mode` and `count` are read when the thread starts.

## The Plugin's Settings

`app.use(CommentsPlugin, settings)` gives every thread and composable the same settings, and `provideCommentsSettings(settings)` changes them for the components below the one that calls it. Either takes a plain object, a `reactive()` object, a ref or a getter, so settings can change later, such as when someone signs in. A thread's own props win, and `themeConfig` merges control by control, so the plugin can put your design system's classes on every button while one thread adds a title to its own.

| Setting | What it does |
| --- | --- |
| `endpoint` | The data API's address, such as `https://cms.example.com/actions/comments/api`. |
| `auth` | How the person is identified, [below](#signing-in). |
| `siteId` | The site, for a multi-site install. |
| `themeConfig`, `parts`, `fields` and `labels` | The same as the props, for every thread. |
| `theme` and `darkSelector` | The same as the props, for every thread. |
| `loadEditor` | Loads Editor for rich comments. Defaults to `import('@verbb/editor')`, which your bundler keeps in a file of its own. |
| `nonce` | A Content Security Policy nonce for the scripts and stylesheets a thread loads, such as the spam protection's challenges. |
| `fetch` | A `fetch` to use instead of the global one. |

## Signing In {#signing-in}

`auth` chooses how the person is identified, and depends on where your app runs relative to Craft. Each way comes from `@verbb/comments-core`, and [Signing In](/core/signing-in) explains them in full.

- On Craft's own domain, or behind a proxy that forwards `/actions/comments/*` to Craft, such as with Nuxt's `routeRules`, leave `auth` out. Craft's session cookie identifies the person.
- On another subdomain of the same site, use `cookieSession({ profile: 'same-site-credentialed' })`. Craft's `defaultCookieDomain` must be the parent domain, and the app's origin must be listed under **Settings** → **Other Sites** with **Shared Cookies** on.
- On a different site, use `memberToken()` for signed-in members and `guestSession()` for guests, with the app's origin listed under **Settings** → **Other Sites**, and **Member Tokens** on for members.

```js
import { guestSession, memberToken } from '@verbb/comments-core';
import { CommentsPlugin } from '@verbb/comments-vue';
import { ref } from 'vue';

// Whether someone is signed in to your own site, which your sign-in code keeps up to date.
export const signedIn = ref(false);

app.use(CommentsPlugin, () => ({
    endpoint: 'https://cms.example.com/actions/comments/api',
    auth: signedIn.value
        ? memberToken({ getToken: () => fetch('/api/comments-token', { cache: 'no-store' }).then((response) => response.json()) })
        : guestSession({ storage: sessionStorage }),
}));
```

The getter makes a new provider only when who's signed in changes. Each provider keeps its own token, so a `guestSession()` without `storage` made afresh starts a new guest session, losing the guest's pending comment. A new provider of the same kind is taken over without starting the thread again, so nobody loses the comment they're writing, while a different kind, such as a guest signing in as a member, starts the thread again. On sign-out, call `reset()` on the provider you gave last, and every thread and composable using it clears what was private and reads the viewer again.

## When the Thread Loads

Most visitors to a post never scroll down to its comments, so a thread waits until it's needed before it loads anything. Until then the page has only the mount point, with the heading, the comment count and the space the thread will fill.

<!--@include: ../browser/lazy-loading.md#load-modes-->

`load-mode` is read when the thread starts. To load a `button` thread from a control of your own, such as a drawer's toggle, keep its controller from `@mounted` and call `load()`, which does nothing to a thread that has loaded.

```vue
<script setup>
import { shallowRef } from 'vue';

const controller = shallowRef();
</script>

<template>
    <button type="button" @click="controller?.load()">Comments</button>
    <CommentsThread :owner-uri="uri" load-mode="button" @mounted="(event) => controller = event.detail.controller" />
</template>
```

<!--@include: ../browser/lazy-loading.md#load-pieces-->

## Labels {#labels}

<!--@include: ../browser/configuring-threads.md#labels-intro-->

```js
app.use(CommentsPlugin, {
    endpoint: 'https://cms.example.com/actions/comments/api',
    labels: { post: 'Send', editorPlaceholder: 'What do you think?' },
});
```

A thread's own `labels` merge over the plugin's, key by key.

::: details Every label, with its default
<!--@include: ../browser/configuring-threads.md#labels-rules-->
:::

## Events {#events}

Every event a thread dispatches is a component event, named after the event, such as `@comment-created` for `comments:comment-created`. [Events and Validation](/vue/events) lists them all with what each one gives, and covers checking a comment before it's posted.

## Server Rendering

On the server, `<CommentsThread>` renders only its mount point, so there's nothing inside it for hydration to disagree with, and the thread loads in the browser. [Nuxt](/vue/nuxt) covers the count and the structured data.
