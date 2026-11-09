# Loading Comment Assets

`craft.comments.render()` registers the bundled CSS and JavaScript by itself, so you don't need to add anything to your layout. Craft publishes them as asset bundles and generates their URLs, including on Craft Cloud.

The bundled files are:

- `comments-frontend.css`, which provides the default presentation.
- `comments-frontend.js`, which handles replies, edits, voting, reports, following, sorting, pagination and API requests. It's a module script, so it doesn't hold up the page.
- The Editor's own script and styles, when [rich authoring](docs:feature-tour/rich-authoring) is on.

Both settings that control these are under **Comments** → **Settings** → **Templates & Resources**.

## Theming

Choose **Light**, **Dark**, **Auto** or **None** as the **Theme** under **Comments** → **Settings** → **Appearance**, or with the [`editorTheme`](docs:get-started/configuration#editortheme) setting. **Light** is the default. **Auto** follows the visitor's colour-scheme preference, and the Editor uses the same theme as the comments around it. **None** outputs no default CSS at all, for sites providing their own.

To adjust the default look rather than replace it, target the `comments__*` classes in your own stylesheet. The theme is also on the root element as `data-comments-theme`, holding `light`, `dark`, `auto` or `none`, so your CSS can follow it:

```css
[data-comments-root][data-comments-theme='dark'] .comments__author-name {
    color: #fff;
}
```

With **Auto**, that attribute stays `auto`, so pair it with a `prefers-color-scheme` media query to match the visitor's preference.

## Supplying Your Own Assets

Turn off **Load Bundled CSS** when your site provides all the comment styling. The rendered HTML keeps its `comments__*` classes and `data-comments-*` attributes for you to style. Your CSS will need a few rules to keep things working:

```css
/* Hide the honeypot field from visitors */
.protect-honeypot {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip-path: inset(50%) !important;
    white-space: nowrap !important;
}

/* Keep screen reader text and announcements out of sight */
.comments__live,
.comments__visually-hidden {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

/* Hide controls that need JavaScript until it's running, as links take their place without it */
[data-comments-root]:not([data-comments-js]) [data-comments-js-only] {
    display: none;
}

/* Let moderators hide held comments */
.comments--hide-held .comments__comment--held {
    display: none;
}

/* With rich authoring, show either the Editor or the plain box, never both */
.comments verbb-editor:not(:defined),
.comments form:not([data-comments-editor-mounted]) verbb-editor,
form[data-comments-editor-mounted] .comments__textarea-fallback,
.comments form[data-comments-editor-mounted] .comments__composer-actions:has(.comments__submit) {
    display: none;
}
```

The last rule also hides the form's own **Post comment** button once the Editor is running, because the Editor has its own.

Turn off **Load Bundled JavaScript** only when your site provides all the public interaction itself, which also turns off the Editor. Comments still work [without JavaScript](docs:template-guides/working-without-javascript), and the [frontend API](docs:template-guides/javascript-api#frontend-api) is there for a custom client. To hook into the bundled script rather than replacing it, use the [JavaScript API](docs:template-guides/javascript-api).

If you're building your own form rather than using `render()`, [Displaying and Protecting Comments](docs:template-guides/displaying-and-protecting-comments) explains the fields it needs.
