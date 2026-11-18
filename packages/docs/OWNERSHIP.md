# Docs Ownership

Each topic has one owning page across the plugin docs (`docs/`, published at verbb.io) and these package docs (`packages/docs/`, published at docs.verbb.io/comments). The other site gets a summary paragraph and a link at most, never a second explanation. Plugin docs paths are relative to `docs/`, and package docs paths to `packages/docs/`. This file isn't part of the built site.

Within the package docs, each framework's section reads on its own, so a Web Components, React or Vue reader never has to switch to the browser pages to finish a task. Where an adapter page needs a framework-neutral explanation, it includes the owning page's region, `<!--@include: ../browser/dark-mode.md#dark-theme-effects-->`, rather than linking to it or explaining it again. The owning page marks the region with `<!-- #region name -->` and `<!-- #endregion name -->` around text that reads correctly in any framework, with no `Comments.configure()` calls or links to vanilla-only pages inside it. Adapter pages write only what differs, which is props, settings, component events and examples. `check:docs` fails when a region an adapter includes is missing.

| Topic | Owning page |
| --- | --- |
| Installation, settings, the control panel, moderation, anti-spam, notifications and permissions | Plugin docs, Get Started and Feature Tour |
| `craft.comments.render()` and its options, `loadMode` in Twig and reserved space | Plugin `template-guides/rendering-comments` |
| The plugin's bundled CSS and script, and turning them off | Plugin `template-guides/loading-comment-assets` |
| Themes, and each public CSS custom property with what it styles (its table is held to the code by `check:docs`) | Plugin `template-guides/theming` |
| The generated list of CSS custom properties with their values, as part of the markup reference | Package `browser/markup-reference` |
| Translating labels with Craft's static translations | Plugin `template-guides/text-translations` |
| The `labels` setting in JavaScript, with the generated list of keys and defaults | Package `browser/configuring-threads` |
| Cookies and storage | Plugin `template-guides/cookies-storage` |
| Custom fields, with their modes, conditions, replies, display, supported types and the field type registry | Plugin `template-guides/custom-fields` |
| Field overrides by handle in JavaScript | Package `browser/field-overrides`, with each adapter's `field-overrides` page for components |
| `structuredData()` in Twig | Plugin `template-guides/structured-data` |
| Choosing a front end and a sign-in tier | Plugin `headless/headless-overview` |
| Signing in across sites on the server, with `allowedOrigins`, the Member Token Key and its endpoints | Plugin `headless/signing-in-across-sites` |
| The sign-in providers in the browser | Package `core/signing-in` |
| The data API's endpoints, shapes, cursors, limits, privacy and error responses | Plugin `headless/data-api` |
| GraphQL | Plugin `graphql/*` |
| PHP events, element queries and models | Plugin `developers/*`, `getting-elements/*` and `reference/*` |
| User guides | Plugin `user-guides/*` |
| `startComments()`, `Comments` and the controller | Package `browser/starting-and-mounting` |
| `Comments.configure()` and how settings combine | Package `browser/configuring-threads` |
| Load modes from JavaScript | Package `browser/lazy-loading` |
| Dark mode from JavaScript, `darkSelector` and `setTheme()` | Package `browser/dark-mode` |
| Stable and internal class names, and CSS layers | Package `browser/class-names-css-layers`, with the generated list in `browser/markup-reference` |
| `themeConfig` for controls | Package `browser/theme-config` |
| Part overrides | Package `browser/part-overrides` |
| Browser events | Package `browser/browser-events` |
| Validation before posting | Package `browser/validating-before-posting` |
| `comments:before-render` | Package `browser/changing-data-before-its-drawn` |
| Bundling the browser package, Editor loading and CSP | Package `browser/bundling-in-your-own-build` |
| Turbo, Sprig, htmx and Datastar | Package `browser/turbo-sprig-datastar` |
| TypeScript types | Package `browser/typescript` |
| Core's client and transports | Package `core/client-transports` |
| The thread store and its events | Package `core/thread-store` |
| Spam protection for a custom interface | Package `core/protection` |
| `CommentsRequestError` and the generated list of error codes | Package `core/errors` |
| `<verbb-comments>`, including each shared topic's element properties and examples | Package `web-components/*` |
| React and Next.js, including each shared topic's React props and examples | Package `react/*` |
| Vue and Nuxt, including each shared topic's Vue props and examples | Package `vue/*` |
| The starters | Package `starters/*` |

The generated tables come from `npm run docs:reference`. `npm run check:docs` fails when they fall behind the code, when the plugin docs' hand-kept tables (Theming's properties, Text & Translations' labels and the Data API's error codes) disagree with it, when a link or anchor in either site breaks, or when either site's prose breaks the writing rules a script can check.
