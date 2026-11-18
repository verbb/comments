# Comments Vue

`@verbb/comments-vue` puts [Comments](https://verbb.io/craft-plugins/comments) threads in Vue 3.5 apps, including Nuxt. `<CommentsThread>` draws a thread with the same renderer the plugin uses on Craft's own pages, so readers, commenters and moderators get everything the plugin offers, while your app keeps control of the settings, the events and any part you'd rather draw with your own components. For a completely custom interface, the composables give you the thread's state and actions from `@verbb/comments-core` as refs.

```bash
npm install @verbb/comments-vue
```

```js
// main.js
import { createApp } from 'vue';
import { CommentsPlugin } from '@verbb/comments-vue';
import '@verbb/comments-browser/css/comments.css';

createApp(App).use(CommentsPlugin, { endpoint: 'https://cms.example.com/actions/comments/api' }).mount('#app');
```

```vue
<script setup>
import { CommentsThread } from '@verbb/comments-vue';

defineProps(['entry']);
</script>

<template>
    <article><!-- … --></article>
    <CommentsThread :key="entry.uri" :owner-uri="entry.uri" theme="auto" @comment-created="(event) => track(event.detail.commentUid)" />
</template>
```

The thread loads when it's about to scroll into view (`load-mode="visible"`, the default), when a visitor presses **Show comments** (`load-mode="button"`), or straight away (`load-mode="eager"`). `load-mode` is read when the thread starts. Give the component a `key` of the owner, as above, so moving to another post starts a fresh thread rather than reusing the old one. It also starts a new thread by itself when `owner-id`, `owner-uri`, `site-id`, `endpoint` or `limit` changes. A new `sort` re-sorts a loaded thread in place, keeping a half-written comment and the reader's place, as `<verbb-comments>` does, and starts a thread that's still waiting again in the new order, as it does a thread whose `sort` is taken away, which goes back to the default. `count` is the number shown before the thread's data arrives, read when the thread starts.

## Settings

`CommentsPlugin` gives every thread and composable the same `endpoint`, `auth`, `siteId`, `themeConfig`, `parts`, `fields`, `labels`, `theme`, `darkSelector` and `loadEditor`, and `provideCommentsSettings()` changes them for the components below one. Either takes a plain object, a `reactive()` object, a ref or a getter, so settings can change later, such as when someone signs in. A thread's own props win, and `themeConfig` merges per control, so the plugin can put your design system's classes on every button while one thread adds a title to its own. With `darkSelector`, such as `html.dark`, threads follow your site's own dark-mode toggle.

### Signing in

`auth` chooses how the person is identified, and depends on where your app runs relative to Craft. Each way comes from `@verbb/comments-core`.

- **On Craft's own domain**, or behind a proxy that forwards `/actions/comments/*` to Craft (Nuxt's `routeRules` can), leave `auth` out. Craft's session cookie identifies the person.
- **On another subdomain of the same site**, such as `www.example.com` with Craft on `cms.example.com`, use `cookieSession({ profile: 'same-site-credentialed' })`. Craft's `defaultCookieDomain` must be the parent domain (`.example.com`), and the app's origin must be listed in **Settings → Other Sites** with **Shared Cookies** on.
- **On a different site**, use `memberToken()` for signed-in members and `guestSession()` for guests. List the app's origin in **Settings → Other Sites**, with **Member Tokens** on for members. `getToken()` fetches a short-lived token from your own server, which asks Comments for it with a request signed with the **Member Token Key**, and answers `{ token, expiresAt }`.

```js
import { cookieSession, guestSession, memberToken } from '@verbb/comments-core';

// Another subdomain of the same site.
app.use(CommentsPlugin, { endpoint: 'https://cms.example.com/actions/comments/api', auth: cookieSession({ profile: 'same-site-credentialed' }) });

// A different site, following who's signed in.
const member = ref(null);
app.use(CommentsPlugin, () => ({
    endpoint: 'https://cms.example.com/actions/comments/api',
    auth: member.value ? memberToken({ getToken: () => $fetch('/api/comments-token') }) : guestSession({ storage: sessionStorage }),
}));
```

Make each provider only when who's signed in changes, as the getter above does. Each provider keeps its own token, so a `guestSession()` without `storage` made afresh starts a new guest session, losing the guest's pending comment. A new provider of the same kind is taken over without starting the thread again, so nobody loses the comment they're writing, while a different kind (a guest signing in as a member) starts the thread again. On sign-out, call `reset()` on the provider you gave last, and every thread and composable using it clears what was private and reads the viewer again.

### Editor

Rich authoring loads Editor from npm when someone starts writing, with `import('@verbb/editor')`, so your bundler keeps it in a chunk of its own, and registers its element. Editor's stylesheet is yours to import alongside Comments', as `import '@verbb/editor/styles'`. Pass `loadEditor` to load Editor another way.

## Events

Every browser event is a component event, named after it: `@mounted`, `@comment-created`, `@vote-changed`, `@before-submit` and the rest. Each handler gets the event itself, so a cancelable one can stop what's about to happen.

```vue
<CommentsThread :owner-id="entry.id" @before-submit="checkLength" />
```

```js
function checkLength(event) {
    if (String(event.detail.formData.get('content') ?? '').trim().length >= 20) return;
    event.preventDefault();
    event.detail.controller?.showFormErrors(event.detail.form, { fieldErrors: { content: ['Please write at least 20 characters.'] } });
}
```

`useCommentsEvent('comments:comment-created', handler)` listens for an event from any thread on the page, or from one with `{ instanceId }`, or on an element of your own with `{ target: elementRef }`.

## Component overrides

A part override replaces the markup of one part of the thread, such as the name of a comment's author, its badges or the empty state, and keeps everything else. Pass a Vue component and it's rendered through a `Teleport` into the place the renderer leaves for it, so it stays inside your app's tree, with your provides, stores and router, and it follows its comment when a vote or a reply redraws the thread. The component gets the part's data as props, with `labels`, `actions` bound to the comment (`vote()`, `reply()`, `report()` and the rest) and `controlAttributes()` for your `themeConfig`. Declare the props it uses, in the component or through its `extends` or mixins, and it gets only those, so the rest never lands on its element as attributes. A component that declares none gets none.

```vue
<!-- CommentAuthor.vue -->
<script setup>
const props = defineProps(['comment']);
const session = useSession();
</script>

<template>
    <strong class="author">{{ comment.author.name }}<template v-if="comment.author.name === session.member?.name"> (you)</template></strong>
</template>
```

```vue
<CommentsThread :owner-id="entry.id" :parts="{ authorName: CommentAuthor }" />
```

Import override components rather than defining them in a render function, which would make a new component each time. If one throws, the error reaches your app's `errorHandler` and Comments' own markup is drawn in its place.

`renderDefaultInto(element)` draws Comments' own markup for the part into an element of your component, for a component that wraps it; call it after each render. For static markup that needs no Vue at all, `templatePart()` passes a function to the renderer instead, which is cheaper. A registered custom element's name works too.

```js
import { html } from 'lit-html';
import { templatePart } from '@verbb/comments-vue';

const parts = { badge: templatePart(({ badge, defaultRender }) => badge.type === 'verified' ? html`<span class="badge">✓ ${badge.label}</span>` : defaultRender()) };
```

Custom fields can be overridden by handle with `fields`. An input component draws its control from `value` and calls `setValue()`, and Comments keeps the label, the instructions and the error around it and posts whatever was set last. Put the context's `id` on the control (or on a group that holds it), and set `aria-invalid` while `error` is set, so a refused post can move focus there.

```vue
<!-- StarsInput.vue -->
<script setup>
defineProps(['value', 'setValue', 'error', 'id', 'labelId']);
</script>

<template>
    <div :id="id" role="radiogroup" :aria-labelledby="labelId" :aria-invalid="error ? 'true' : undefined">
        <button v-for="n in 5" :key="n" type="button" :aria-pressed="String(Number(value) >= n)" @click="setValue(n)">★</button>
    </div>
</template>
```

```vue
<CommentsThread :owner-id="entry.id" :fields="{ rating: { input: StarsInput } }" />
```

## Your own interface

`useCommentsThread()` gives you the thread's state and actions as refs, without any of Comments' markup, and `useCommentForm()` posts from a form of your own, mounting the site's spam protection on it when someone starts writing. Options can be a getter, so a new owner makes a new thread.

```vue
<script setup>
import { useCommentForm, useCommentsThread } from '@verbb/comments-vue';

const props = defineProps(['uri']);
const thread = useCommentsThread(() => ({ ownerUri: props.uri }));
const form = useCommentForm({ thread });
const { handleSubmit, fieldErrors, posting } = form;
</script>

<template>
    <p v-for="comment in thread.roots.value" :key="comment.uid">
        {{ comment.content.text }}
        <button @click="thread.vote(comment.uid, 'upvote')">Like ({{ comment.interaction.upvotes }})</button>
    </p>
    <form :ref="form.ref" @submit="handleSubmit">
        <textarea name="content" />
        <p v-for="message in fieldErrors.content ?? []" :key="message">{{ message }}</p>
        <button :disabled="posting">Post</button>
    </form>
</template>
```

Bind the form with `:ref="form.ref"`, which hands Vue the ref itself. A string `ref="formRef"` with `formRef` taken out of the result works in the browser too, but `vue-tsc` doesn't count it as a use, so a project checking for unused variables fails its type check.

The form reads `content`, `guest[name]`, `guest[email]` and `fields[<handle>]` from its own fields, or takes them as `submit({ content, guest, fields })`. After a post it empties the fields the browser holds, keeping a guest's name and email for their next comment. A `v-model` value is your own state, so empty it yourself when `submit()` resolves with an answer rather than `null`. Trying the same comment again after a failure reuses its operation ID, so the server never posts it twice. Pass `mode: 'reply'` with `parentUid`, or `mode: 'edit'` with `commentUid`, for replies and edits.

## Server rendering

On the server `<CommentsThread>` renders only its mount point (the reserved space, the heading and the count), so there's nothing inside it for hydration to disagree with, and the thread itself loads in the browser.

The helpers in `@verbb/comments-vue/server` import nothing that touches the page, so they run in Nuxt's server routes, in `useAsyncData()` on the server and in plain Node. They read the data API's public responses, which Craft and a CDN cache. Write the structured data with `jsonLdScript()`, never `JSON.stringify()` alone, which would let a comment containing `</script>` end the script and run the rest as HTML.

```vue
<script setup>
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-vue/server';
import { CommentsThread } from '@verbb/comments-vue';

const route = useRoute();
const endpoint = 'https://cms.example.com/actions/comments/api';
const { data } = await useAsyncData(`comments:${route.path}`, async () => ({
    count: await fetchCommentsCount({ endpoint, ownerUri: route.path }).catch(() => 0),
    jsonLd: await fetchStructuredData({ endpoint, ownerUri: route.path }).catch(() => null),
}));

useHead(() => ({ script: data.value?.jsonLd ? [{ type: 'application/ld+json', innerHTML: jsonLdScript(data.value.jsonLd) }] : [] }));
</script>

<template>
    <CommentsThread :key="route.path" :endpoint="endpoint" :owner-uri="route.path" :count="data?.count" />
</template>
```

Both helpers throw when the read fails, such as for an owner that doesn't take comments or after the 30-second timeout (`timeoutMs`), so catch them, as above, where the page should render without its comments. `useAsyncData()` keeps its answer under its key, here one per page, so a client-side visit reuses what the server read. Give each owner its own key.

Inside a `<script setup>` block, don't write a closing script tag anywhere, comments and strings included. Vue reads it as the end of the block, and the type check then fails with an error that doesn't point at it.

## Alongside the plugin's own script

A page that also loads the plugin's bundled script leaves `<CommentsThread>` to the component, because its mount point is marked as an adapter's, even when a site script calls `Comments.mount()` over the whole page. Turn off **Load Bundled JavaScript** in the Comments settings for an app that draws its threads with this package, so the page runs one copy of the renderer.

## Requirements

- Vue 3.5.
- The package is ES modules only.
- Browsers: the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later.

## Documentation

The [Comments package docs](https://docs.verbb.io/comments) cover the component, the composables, signing in across sites, part and field overrides, events and Nuxt.

## Licence

Comments Vue is released under the Verbb licence in `LICENSE.md`, the same as the Comments plugin.
