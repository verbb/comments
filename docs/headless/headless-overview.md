# Headless Overview

Comments doesn't need Craft to render the page. The thread is drawn in the browser from JSON, so the same thread `craft.comments.render()` puts on a Twig page can appear in a React or Vue app, on a static site, or on a site built with Next.js or Nuxt, with Craft running only as the place comments are stored and moderated. This page helps you choose how your front end reaches Comments, and points you at the page that covers each part.

Your choices come down to which front end draws the thread, where that front end lives relative to Craft, and whether it reads through the data API or GraphQL.

## Choosing What Draws the Thread

All of these draw the same thread, with the same themes, class names, settings and spam protection, so you can start with the lightest one that fits your front end and move on later without your visitors seeing a difference.

| Front End | Use | Where It's Covered |
| --- | --- | --- |
| Craft's own Twig templates | `craft.comments.render()` | [Rendering Comments](docs:template-guides/rendering-comments) |
| A static site, or a site without a framework | The `<verbb-comments>` web component from `@verbb/comments-web-components` | [Package docs](https://docs.verbb.io/comments/web-components/) |
| A React or Next.js app | `<CommentsThread>` from `@verbb/comments-react` | [Package docs](https://docs.verbb.io/comments/react/) |
| A Vue or Nuxt app | `<CommentsThread>` from `@verbb/comments-vue` | [Package docs](https://docs.verbb.io/comments/vue/) |
| Your own interface, with none of Comments' markup | The client and thread store from `@verbb/comments-core` | [Package docs](https://docs.verbb.io/comments/core/) |
| Another language or platform, such as a mobile app | The [data API](docs:headless/data-api) or [GraphQL](docs:graphql/queries) directly | This section |

Your settings under **Comments** → **Settings** apply whichever you choose, because the server decides what each visitor is offered. Turning off guest commenting, voting or a custom field there turns it off for every front end.

The [starters](https://docs.verbb.io/comments/starters/) are working examples of each front end against the same Craft site, which is the quickest way to see a setup that works before building your own.

## Choosing How Visitors Sign In

Reading comments works from any site with no setup at all, because the data API's public reads answer every origin the same way. What needs setting up is identifying the person, for posting, voting and moderating, and that depends on where your front end lives:

- **On Craft's own domain**, such as a React app served from Craft's templates or under Craft's host, Craft's session cookie works as it does for any page, and there's nothing to set up.
- **On another subdomain of the same site**, such as `www.example.com` with Craft on `cms.example.com`, Craft's session cookie can be shared across the subdomains, so members sign in on Craft and the front end is listed under **Allowed Origins** with **Shared Cookies**.
- **On a different site**, such as `example.com` with Craft on `example-cms.net`, browsers won't send Craft's cookies, so your front end's own server asks Comments for a short-lived member token for whoever is signed in there.

A front end on another domain can also forward Comments' requests to Craft through its own server, so the browser only ever talks to one origin. That needs no member tokens or allowed origins, but the forwarding has to meet [three requirements](docs:headless/signing-in-across-sites#forwarding-requests-through-your-front-end) so Craft sees the right origin, cookies and visitor address. [Signing In Across Sites](docs:headless/signing-in-across-sites) explains each of these setups on the Craft side.

## Choosing the Data API or GraphQL

The data API is a set of JSON actions under `comments/api/`, and it's what the JavaScript packages use by default. Its public reads are built to be cached by a CDN, with the visitor's own details in a separate private request, so a busy thread doesn't reach Craft on every view. [Data API](docs:headless/data-api) is the reference.

If your front end already reads everything else through Craft's GraphQL API, you can read and write comments there too. The `commentsThread`, `commentsReplies`, `commentsComment` and `commentsViewer` queries return the same data as the data API, and `@verbb/comments-core` can use them in place of the data API with its GraphQL transport. Spam protection and file uploads still go through the data API either way. [GraphQL](docs:graphql/queries) covers the schema.

## Rendering on the Server

A front end that renders on the server, such as Next.js or Nuxt, can output the thread's place on the page, the comment count and the comments' structured data there, with the thread itself loading in the browser as the visitor nears it. The count and structured data come from public reads, so a page that includes them can still be cached or statically generated. [Structured Data](docs:template-guides/structured-data#structured-data-on-a-headless-site) explains what the structured data holds, and the package docs cover [Next.js](https://docs.verbb.io/comments/react/nextjs) and [Nuxt](https://docs.verbb.io/comments/vue/nuxt).

## Complete Walkthroughs

For a complete walkthrough, from an empty app to a working thread you've tested, follow [Comments on a Headless Site on the Same Domain](docs:user-guides/comments-on-a-headless-site-on-the-same-domain) or [Comments on a Headless Site on Another Domain](docs:user-guides/comments-on-a-headless-site-on-another-domain).
