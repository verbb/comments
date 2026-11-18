# Nuxt Starter

The `nuxt/` folder is a Nuxt app on a subdomain of the Craft site, with the comment count and structured data rendered on the server. Locally it runs at `https://nuxt.comments-starters.ddev.site:3102`, over HTTPS with a certificate `npm run dev` makes with mkcert, and its hosted copy runs as a Node server on a subdomain of the hosted Craft site, linked from the [Craft starter](/starters/craft)'s home page.

## Sharing Craft's Session

Craft sets its session cookie for the parent domain, `.comments-starters.ddev.site`, and Comments answers the subdomain with credentialed CORS, because the Craft site lists the app's origin with **Shared Cookies**. Signing in on the Craft site signs you in on the Nuxt site as well. `app/plugins/comments.ts` installs `CommentsPlugin` with that sign-in:

```ts
nuxtApp.vueApp.use(CommentsPlugin, {
    endpoint: config.public.commentsEndpoint,
    auth: cookieSession({ profile: 'same-site-credentialed' }),
    theme: 'auto',
});
```

[Signing In](/core/signing-in#on-another-subdomain) explains this setup.

## Rendering on the Server

`app/pages/blog/[...slug].vue` reads the post's count and structured data with `useAsyncData()`, on the server for the first page and from the public endpoint after a client-side move. It uses the private `commentsServerUrl` from the runtime config for reads on the server, which can be an internal address. [Nuxt](/vue/nuxt) explains each part.

The page also shows who's reading in the app's header, from the thread's `@viewer-loaded` event, since nothing personal is read on the server. It uses the same badge and rating components as the Vue starter.

## Running It

The app needs its own origin to share Craft's session, so it runs with `nuxt build` as a Node server rather than as a static `nuxt generate` site, as `nuxt.config.ts` explains.
