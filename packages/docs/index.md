# Comments Front-End Packages

These docs cover the npm packages that draw [Comments](https://verbb.io/craft-plugins/comments) threads in the browser and in JavaScript apps. The Comments plugin for Craft CMS ships the same code as its own script, so every thread on a Craft site is already drawn by these packages, whether or not you ever install one. The [plugin docs](https://verbb.io/craft-plugins/comments/docs/v4/get-started/installation-setup) cover installing Comments, its settings, moderation, `craft.comments.render()` in Twig, themes and translations, and the server side of signing in from another site. These docs pick up where the thread reaches the browser.

Start with whichever of these is closest to what you're doing.

## Craft Draws the Thread and You Want to Change It

If your templates call `craft.comments.render(entry)` and you want the thread to look or behave differently, you don't need to install anything. The plugin's script exposes `window.Comments`, and the [Browser](/browser/) section explains everything you can do with it.

- To restyle the thread, start with the [Make a Custom Theme](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/theming-markup/make-a-custom-theme) user guide, which walks through changing the colours, fonts and sizes from scratch. The plugin's [Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming) guide covers the CSS custom properties, and [Class Names and CSS Layers](/browser/class-names-css-layers) covers the stable `comments__*` classes and sites built with Tailwind.
- To put your design system's button and form classes on Comments' controls, use [`themeConfig` for Controls](/browser/theme-config).
- To draw one part of the thread with your own markup, such as a badge or the empty state, use [Part Overrides](/browser/part-overrides), or [Field Overrides](/browser/field-overrides) for one custom field.
- To track comments in your analytics, add your own validation or change what's drawn, use the [Browser Events](/browser/browser-events).

## Your Own Build Starts the Thread

A site with its own Vite or webpack build can include `@verbb/comments-browser` in its bundle rather than loading the plugin's script, and decide when threads start. [Bundling in Your Own Build](/browser/bundling-in-your-own-build) covers the switch, and [Turbo, Sprig and Datastar](/browser/turbo-sprig-datastar) covers pages that swap their content without a full load.

## Comments on a Page Craft Doesn't Draw

A static site, a page on another domain or any page without a framework can show a thread with one element, `<verbb-comments>`, from `@verbb/comments-web-components`. Start with [Web Components](/web-components/).

## A React or Vue App

`@verbb/comments-react` and `@verbb/comments-vue` draw the same thread as a component, with your own components for any part you replace and hooks or composables for a completely custom interface. Start with [React](/react/) or [Vue](/vue/), and see [Next.js](/react/nextjs) or [Nuxt](/vue/nuxt) for server rendering.

Where your app runs relative to Craft decides how people sign in. A front end on Craft's own domain uses Craft's session, while one on another subdomain or another site needs Craft configured to allow it. The plugin's [Headless Overview](https://verbb.io/craft-plugins/comments/docs/v4/headless/headless-overview) helps you choose, and [Signing In](/core/signing-in) covers the browser side.

## Your Own Interface or a Server

`@verbb/comments-core` reads and writes threads through the Comments data API with no DOM at all, so it runs in browsers, workers, Node and server components. It keeps a thread's state in a store your own interface can follow, and handles signing in, spam protection and retries. Start with [Core](/core/), and the plugin's [Data API](https://verbb.io/craft-plugins/comments/docs/v4/headless/data-api) reference for the endpoints and response shapes underneath.

## Step-by-Step Guides

The plugin docs' user guides take one task from start to finish, explaining each step for someone new to it. They suit a first attempt at a task these docs cover as reference, such as [Make a Custom Theme](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/theming-markup/make-a-custom-theme), [Change a Part's Structure](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/theming-markup/change-a-parts-structure), [Bundle Comments in Your Own Build](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/front-end-headless/bundle-comments-in-your-own-build) or [Comments on a Headless Site on Another Domain](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/front-end-headless/comments-on-a-headless-site-on-another-domain).

## Working Examples

The [Starters](/starters/) are small, tested apps that show each package against one seeded Craft site, from the one-line Twig default to a Next.js app on another domain. Each starter page says what it demonstrates and where to look in its code.

## The Packages

| Package | What it's for |
| --- | --- |
| `@verbb/comments-browser` | The renderer and everything on the page, including `startComments()`, `Comments.configure()`, lazy loading, `themeConfig`, part overrides, the browser events and the CSS. |
| `@verbb/comments-core` | The data layer, with the client, the REST and GraphQL transports, sign-in providers, the thread store, spam protection and error codes. |
| `@verbb/comments-web-components` | The `<verbb-comments>` element. |
| `@verbb/comments-react` | `<CommentsThread>`, a provider, hooks and server helpers for React 19 and Next.js. |
| `@verbb/comments-vue` | `<CommentsThread>`, a plugin, composables and server helpers for Vue 3.5 and Nuxt. |

Each package is released with the plugin and shares its version number, so install the version that matches your Comments plugin and update them together.
