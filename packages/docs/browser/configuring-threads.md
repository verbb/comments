# Configuring Threads

`Comments.configure()` gives settings to every thread on the page, such as your design system's button classes, a part override or different wording. It's the same on `window.Comments` from the plugin's script, on the package's `Comments` export, and as the `configure` export on its own. Settings can be given at any time, before or after the threads start, and threads already drawn redraw with them.

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({
        theme: 'auto',
        labels: {
            empty: 'Nobody has said anything yet.',
        },
        themeConfig: {
            primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
        },
    });
});
```

Every thread on the page switches to the automatic theme, an empty thread says “Nobody has said anything yet.”, and **Post** and the other main buttons use your `btn btn-primary` classes in place of Comments' own button styling.

## The Settings

| Setting | What it does |
| --- | --- |
| `themeConfig` | Classes and attributes for the ten controls, covered in [`themeConfig` for Controls](/browser/theme-config). |
| `parts` | Part overrides by name, covered in [Part Overrides](/browser/part-overrides). |
| `fields` | Custom field overrides by handle, covered in [Field Overrides](/browser/field-overrides). |
| `labels` | Wording to use instead of the site's translations, [below](#labels). |
| `theme` | `light`, `dark`, `auto` or `none`, covered in [Dark Mode](/browser/dark-mode). |
| `darkSelector` | A selector, such as `html.dark`, that switches every thread to dark while it matches `<html>`. |
| `loadMode` | When a thread without a load mode of its own loads, covered in [Lazy Loading](/browser/lazy-loading). |
| `loadEditor` | A function that loads Editor for rich comments, resolving once `<verbb-editor>` is defined, covered in [Bundling in Your Own Build](/browser/bundling-in-your-own-build#loading-editor). |
| `auth` | How the person is identified, from `@verbb/comments-core`, covered in [Signing In](/core/signing-in). Defaults to Craft's session cookie. |
| `nonce` | A Content Security Policy nonce for the scripts and stylesheets a thread loads, such as Editor's and the spam protection's challenges. |
| `fetch` | A `fetch` to use instead of the global one. |

## How Settings Combine

Each `configure()` call merges with the ones before it. `themeConfig` merges control by control and attribute by attribute, `parts` by part name, `fields` by handle and by `input` or `display`, and `labels` by key, so a later call can add one part override without losing another. Every other setting is replaced by the latest value given.

A thread can get settings from three places, and later ones win.

1. Its mount point, from the options passed to `craft.comments.render()` or set in `config/comments.php`.
2. `Comments.configure()` or `startComments()`.
3. The thread's own options, from `mountThread()`, a `<verbb-comments>` element's properties or a React or Vue component's props.

`loadMode` is the exception, because a thread's own load mode from `render()` always wins over `configure()`'s. `auth` and `fetch` are read when a thread starts, so changing them in `configure()` reaches only threads that start afterwards.

Settings reach threads drawn by the plugin's script, by your own call to `startComments()`, and by code that mounts threads through `@verbb/comments-browser/renderer`, such as the web components and the React and Vue packages, including settings given before that code was loaded. Once one copy of the script is running on the page, `configure()` in another copy passes its settings to the one that's running.

## Labels

<!-- #region labels-intro -->
Every word the thread shows comes from the site's translations in Craft's `comments` category, so changing wording per site and language is usually done with a static translation file, as the plugin's [Text & Translations](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/text-translations) explains. `labels` is for wording that belongs to your JavaScript rather than your Craft site, such as an app on another domain that wants its own tone. It takes the keys below, and wins over the site's translations.
<!-- #endregion labels-intro -->

```js
window.Comments.configure({
    labels: {
        post: 'Send',
        editorPlaceholder: 'What do you think?',
        charactersLeft: '{count} to go',
    },
});
```

<!-- #region labels-rules -->
Placeholders such as `{count}` and `{name}` are filled in by the renderer, so keep them in your wording. A label set to an empty string is ignored, and the site's own wording is used instead.

The keys marked “No” in the last column aren't in Craft's translation list. The renderer uses its own English for them unless you give `labels`.

<!--@include: ../_generated/labels.md-->
<!-- #endregion labels-rules -->
