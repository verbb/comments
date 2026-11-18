# Web Components

`@verbb/comments-web-components` puts a Comments thread on any page with one element, `<verbb-comments>`. It suits a static site, a site without a framework, or a page on another domain that shows comments from a Craft site. The element draws the thread with the browser package, in the light DOM, so the thread looks and behaves exactly as it does on the Craft site itself, and your page's CSS reaches it as usual.

On a page Craft renders, `craft.comments.render()` is simpler and needs no package. Reach for the element when Craft doesn't render the page.

## Installing

Install the element with the browser package it draws with, and Editor for rich comments:

```bash
npm install @verbb/comments-web-components @verbb/comments-browser @verbb/editor
```

Then register the element once, from your site's entry script, and import the stylesheets:

```js
import { registerCommentsWebComponents } from '@verbb/comments-web-components';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';

registerCommentsWebComponents();
```

Importing the package defines nothing, and calling `registerCommentsWebComponents()` again does nothing. If another copy of the package defined the element first, that copy draws the threads, and the console says so.

## Your First Thread

Put the element where the comments belong, naming the entry by its URI and the Craft site's data API:

```html
<verbb-comments owner-uri="blog/hello-world" endpoint="https://example.com/actions/comments/api"></verbb-comments>
```

The thread waits until it's about to scroll into view, then loads the entry's comments and draws them, with the comment form for anyone who can post. Without `endpoint`, the element uses `/actions/comments/api` on the page's own origin, which is right for a page served from the Craft site.

A page on another domain needs two more things. Craft has to allow the page's origin under **Settings** → **Other Sites**, and visitors sign in there with a guest session or member tokens rather than Craft's cookies, which the element takes as its `auth` property. [Signing In](/core/signing-in) covers both, and the [web components starter](/starters/web) runs on another origin with guests.

```js
import { guestSession } from '@verbb/comments-core';

document.querySelector('verbb-comments').auth = guestSession({ storage: sessionStorage });
```

## Rich Comments

When the site uses rich authoring, the element imports `@verbb/editor` when someone starts writing, so a bundler that splits code at `import()` keeps Editor out of the page's first download. Editor's stylesheet is yours to import, as above. To load Editor another way, set the element's `loadEditor` property to a function that resolves once `<verbb-editor>` is defined.

## Going Further

- [The `<verbb-comments>` Element](/web-components/verbb-comments) covers its attributes and properties, loading, labels and pages that change.
- [Events and Validation](/web-components/events) lists every event, and covers checking a comment before it's posted.
- [Styling](/web-components/styling) covers design tokens, your design system's classes, Tailwind and dark mode.
- [Part Overrides](/web-components/part-overrides) and [Field Overrides](/web-components/field-overrides) draw a part of the thread, or one custom field, your own way.
- [Signing In](/core/signing-in) covers guests and members on another site.

The [web components starter](/starters/web) is a complete, tested site to compare against.

## On a Craft Site

On a page that also loads the plugin's script, the script leaves `<verbb-comments>` elements alone, `Comments.mount()` included, and the element leaves the plugin's mount points alone, so each thread is drawn once. Both would load the renderer, though, so turn off **Load Bundled JavaScript** in the Comments settings on a site that uses the element, and the console says so if it's on.

## Requirements

The package is ES modules only, for a bundler such as Vite, webpack or esbuild. It supports the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later. Sites that enforce Trusted Types need to allow the `verbb-comments`, `lit-html` and `verbb-protect` policies.
