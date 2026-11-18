# Web Components Starter

The `web/` folder is a Vite and Tailwind CSS page with one `<verbb-comments>` element, which shows the posts you choose, and a second page, `custom.html`, that draws the same thread with its own markup. Locally it runs at `http://localhost:5275`, and its hosted copy is at [comments-starters.verbb.io/web/](https://comments-starters.verbb.io/web/).

## How It Reaches Craft

Locally, the page runs on another origin from Craft, where the browser won't send Craft's cookies, so visitors comment as guests with a guest session token, kept in `sessionStorage` so a guest's own pending comment and votes stay with them across reloads. The Craft site lists the page's origin in its `config/comments.php`. The hosted copy is served from the Craft site's own domain, where Craft's session applies instead, and `src/main.ts` chooses between the two:

```js
thread.auth = endpoint.origin === window.location.origin ? cookieSession() : guestSession({ storage: sessionStorage });
thread.endpoint = endpoint.href;
```

## Styling with Tailwind

`src/style.css` puts Comments' stylesheet, and Editor's, in a cascade layer of their own, between Tailwind's base styles and its components, so Tailwind's utilities and `@apply` rules win over Comments' rules:

```css
@layer theme, base, comments, components, utilities;

@import 'tailwindcss';
@import '@verbb/comments-browser/css/comments.css' layer(comments);
@import '@verbb/editor/styles' layer(comments);
```

It then restyles the thread through the stable class names, such as `.comments__heading`, `.comments__author-name` and `.comments__badge--verified`, with `@apply`. [Class Names and CSS Layers](/browser/class-names-css-layers) explains the approach.

## The Site's Own Buttons and Fields

`src/main.ts` sets the element's `themeConfig`, so Comments' buttons and fields use the page's own `btn` and `field` classes, with `resetClass` dropping Comments' styling from them:

```js
thread.themeConfig = {
    primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
    secondaryButton: { resetClass: true, attributes: { class: 'btn btn-secondary' } },
    input: { resetClass: true, attributes: { class: 'field' } },
    textarea: { resetClass: true, attributes: { class: 'field' } },
};
```

## Your Own Interface

`custom.html` and `src/custom.ts` draw the same thread with the page's own markup and no framework, from `@verbb/comments-core`'s thread store, and post from the page's own forms with `commentForm()` from `@verbb/comments-browser/form`, which mounts the site's spam protection on each one. The page handles loading and errors, a guest's name and email fields, states such as a comment waiting for approval, votes, replies and **Load more**. It keeps each comment's element from one draw to the next, so a reply being written, and focus, survive a vote or a new comment arriving. [Your Own Interface](/browser/your-own-interface) explains `commentForm()`.

## Dark Mode

The page's toggle keeps a `dark` class on `<html>`, and `configure({ darkSelector: 'html.dark' })` from the browser package has the thread follow it, switching as it changes. It's given before the element is registered, so the thread draws in the right theme from the start. [Dark Mode](/browser/dark-mode) covers the setting.
