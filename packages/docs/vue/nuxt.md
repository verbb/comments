# Nuxt

In a Nuxt app, a post's comment count and its structured data render on the server with the rest of the page, and the thread itself loads in the browser when it's about to scroll into view. Everything the server renders comes from public reads that Craft and a CDN cache. The [Nuxt starter](/starters/nuxt) is a complete app on a subdomain of the Craft site, sharing Craft's session cookie.

## Installing the Plugin

Give the settings in a Nuxt plugin, so the server and the browser render the same mount point:

```ts
// plugins/comments.ts
import { cookieSession } from '@verbb/comments-core';
import { CommentsPlugin } from '@verbb/comments-vue';

export default defineNuxtPlugin((nuxtApp) => {
    const config = useRuntimeConfig();

    nuxtApp.vueApp.use(CommentsPlugin, {
        endpoint: config.public.commentsEndpoint,
        // The app is on a subdomain of the Craft site, so Craft's session cookie identifies the person.
        auth: cookieSession({ profile: 'same-site-credentialed' }),
        theme: 'auto',
    });
});
```

Import Comments' stylesheet, and Editor's for rich comments, through your `nuxt.config.ts` `css` list or your main stylesheet. Choose `auth` for where your app runs, as [Signing In](/core/signing-in) explains. The starter's app is on another subdomain, which needs Craft's `defaultCookieDomain` set to the parent domain and the app's origin listed with **Shared Cookies**.

## A Post with Comments

The page reads the count and the structured data with `useAsyncData()`, so they're fetched on the server for the first page and in the browser after a client-side move:

```vue
<script setup>
import { CommentsThread } from '@verbb/comments-vue';
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-vue/server';

const route = useRoute();
const config = useRuntimeConfig();
const uri = computed(() => `blog/${route.params.slug}`);

const { data } = await useAsyncData(() => `comments:${uri.value}`, async () => {
    const endpoint = config.public.commentsEndpoint;
    const [count, jsonLd] = await Promise.all([
        fetchCommentsCount({ endpoint, ownerUri: uri.value }).catch(() => null),
        fetchStructuredData({ endpoint, ownerUri: uri.value }).catch(() => null),
    ]);

    return { count, jsonLd };
});

useHead(() => ({
    script: data.value?.jsonLd ? [{ type: 'application/ld+json', innerHTML: jsonLdScript(data.value.jsonLd) }] : [],
}));
</script>

<template>
    <CommentsThread :key="uri" :owner-uri="uri" :count="data?.count ?? undefined" />
</template>
```

Here `commentsEndpoint` is the Craft site's data API, such as `https://cms.example.com/actions/comments/api`, set in `runtimeConfig.public`, and the post's URI on the Craft site is `blog/` followed by its slug. The heading shows the right count from the first paint, so nothing moves when the thread arrives. `useAsyncData()` keeps its answer under its key, here one per post, so a client-side visit reuses what the server read. Give each owner its own key.

Write the structured data with `jsonLdScript()`, never `JSON.stringify()` alone, which would let a comment containing a closing script tag end the script and run the rest as HTML. Inside a `<script setup>` block, don't write a closing script tag anywhere, comments and strings included, because Vue reads it as the end of the block, and the type check then fails with an error that doesn't point at it.

## The Server Helpers

`@verbb/comments-vue/server` has the same helpers as React's, which import nothing that touches the page, so they run in Nuxt's server routes, in `useAsyncData()` on the server and in plain Node. [Next.js](/react/nextjs#the-server-helpers) describes their options. Both throw a [`CommentsRequestError`](/core/errors) when the read fails, so catch them, as above, where the page should render without its comments.

The server can reach Craft at a different address from the browser, such as an internal one. The Nuxt starter keeps it in private runtime config, `commentsServerUrl`, and uses it for reads made on the server.

## Showing Who's Signed In

The thread reads who's asking in the browser, so a header that shows the signed-in person can follow the thread's `comments:viewer-loaded` event, whose `viewer` holds who's reading:

```vue
<script setup>
const viewer = useState('comments-viewer', () => null);

function readViewer(event) {
    viewer.value = event.detail.viewer;
}
</script>

<template>
    <CommentsThread :owner-uri="uri" @viewer-loaded="readViewer" />
</template>
```

## Static Generation

`nuxt generate` bakes the count and the structured data in at build time, and a thread on another subdomain needs its own origin to sign people in, so run an app that signs people in through Craft as a Node server with `nuxt build`. The Nuxt starter explains why in its config.
