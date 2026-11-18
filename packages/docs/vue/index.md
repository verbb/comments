# Vue

`@verbb/comments-vue` puts Comments threads in Vue 3.5 apps, including Nuxt. `<CommentsThread>` draws a thread with the same renderer the plugin uses on Craft's own pages, so readers, commenters and moderators get everything the plugin offers, while your app keeps control of the settings, the events and any part you'd rather draw with your own components. For a completely custom interface, the composables give you the thread's state and actions from `@verbb/comments-core` as refs.

## Installing

```bash
npm install @verbb/comments-vue @verbb/comments-browser @verbb/comments-core @verbb/editor
```

`@verbb/comments-browser` holds the stylesheet, `@verbb/comments-core` the sign-in providers, and `@verbb/editor` the rich text editor, which the thread loads only when someone starts writing. Install the plugin with your app's settings, and import the stylesheets once:

```js
// main.js
import { createApp } from 'vue';
import { CommentsPlugin } from '@verbb/comments-vue';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';
import App from './App.vue';

createApp(App)
    .use(CommentsPlugin, { endpoint: 'https://cms.example.com/actions/comments/api' })
    .mount('#app');
```

## Your First Thread

```vue
<script setup>
import { CommentsThread } from '@verbb/comments-vue';

defineProps(['entry']);
</script>

<template>
    <article>
        <h1>{{ entry.title }}</h1>
    </article>

    <CommentsThread :key="entry.uri" :owner-uri="entry.uri" theme="auto" />
</template>
```

`CommentsPlugin` gives every thread in the app the Craft site's data API, and `<CommentsThread>` draws the entry's comments, loading them just before they scroll into view. The `key` of the entry's URI starts a fresh thread when the app moves to another post.

Where your app runs relative to Craft decides how people sign in. An app served from Craft's own domain, or behind a proxy that forwards `/actions/comments/*` to Craft, needs nothing more. An app on another subdomain or another site passes a sign-in provider as the plugin's `auth`, which [The `<CommentsThread>` Component](/vue/comments-thread#signing-in) shows.

## Going Further

- [The `<CommentsThread>` Component](/vue/comments-thread) covers its props, the plugin's settings, signing in, loading and labels.
- [Events and Validation](/vue/events) lists every component event, and covers checking a comment before it's posted.
- [Styling](/vue/styling) covers design tokens, your design system's classes, Tailwind and dark mode.
- [Part Overrides](/vue/part-overrides) and [Field Overrides](/vue/field-overrides) draw a part of the thread, or one custom field, with your own components.
- [Composables for Your Own UI](/vue/composables) builds a completely custom interface.
- [Nuxt](/vue/nuxt) covers server rendering.

The [Vue starter](/starters/vue) and [Nuxt starter](/starters/nuxt) are complete, tested apps to compare against.

## On a Craft Site

A page that also loads the plugin's script leaves `<CommentsThread>` to the component, because its mount point is marked as an adapter's, even when a site script calls `Comments.mount()` over the whole page. Turn off **Load Bundled JavaScript** in the Comments settings for an app that draws its threads with this package, so the page runs one copy of the renderer.

## Requirements

Vue 3.5. The package is ES modules only, and supports the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later.
