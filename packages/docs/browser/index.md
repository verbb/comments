# Browser Package

`@verbb/comments-browser` draws Comments threads in the browser. It finds the mount points `craft.comments.render()` writes, loads each thread when it's about to scroll into view, and handles everything a reader, a commenter and a moderator does in it, from votes and replies to the comment editor, reports, following and moderation. The plugin's own script is this package, so a Craft site already runs it on every page with a thread, and the web components, React and Vue packages draw their threads with it too.

## When to Install It

You don't need the package to customise a thread Craft draws. The plugin's script sets `window.Comments`, which has everything on these pages, so a script on your page can configure threads, listen for their events and override their parts without any npm package. The script is a module, which runs once the page has been parsed, so use `window.Comments` from a `DOMContentLoaded` listener:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({
        themeConfig: {
            primaryButton: { attributes: { class: 'btn btn-primary' } },
        },
    });
});
```

Install the package when your own build should include it instead of the plugin's file, for a single bundle to cache and control over when threads start. [Bundling in Your Own Build](/browser/bundling-in-your-own-build) covers that, including turning off the plugin's copy so the page runs one.

```bash
npm install @verbb/comments-browser
```

The package is released with the plugin and shares its version number, so install the version that matches your Comments plugin and update them together.

## What You Can Change

The thread is drawn from JSON, so there are no templates to copy and edit. Instead there's a set of options, each reaching a little further than the one before. Start with the lightest one that does what you need, because each keeps everything the lighter ones give you. For most sites that's a custom theme, which the [Make a Custom Theme](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/theming-markup/make-a-custom-theme) user guide walks through from start to finish. A site that replaces how a commenter's name is drawn still gets its theme, its translated wording, its own button classes, the events and all of the thread's behaviour.

| What you want | Where it's covered |
| --- | --- |
| Turn a feature on or off, such as voting, guests or avatars | The plugin's [Configuration](https://verbb.io/craft-plugins/comments/docs/v4/get-started/configuration), because a setting also stops the server offering it |
| Change any label or message | The plugin's [Text & Translations](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/text-translations), or `labels` in [Configuring Threads](/browser/configuring-threads#labels) |
| Change colours, fonts, sizes or dark mode | The plugin's [Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming), with [Dark Mode](/browser/dark-mode) for a site's own toggle |
| Style any element from your own CSS or Tailwind | [Class Names and CSS Layers](/browser/class-names-css-layers) |
| Put your design system's classes on buttons and inputs | [`themeConfig` for Controls](/browser/theme-config) |
| React to what happens, or check a comment before it's posted | [Browser Events](/browser/browser-events) and [Validating Before Posting](/browser/validating-before-posting) |
| Draw one part with your own markup | [Part Overrides](/browser/part-overrides) and [Field Overrides](/browser/field-overrides) |
| Draw everything yourself | [Core](/core/), or the hooks in [React](/react/hooks) and composables in [Vue](/vue/composables) |

## The Stylesheets

The package has three stylesheets. `css/comments.css` is the default and holds both of the others. `css/comments-base.css` holds only what the thread needs to work, such as visually hidden text, reserved space and the editor's swap, and is what the **None** theme loads. `css/comments-theme.css` holds the look, for a site that brings its own base. [Class Names and CSS Layers](/browser/class-names-css-layers) explains how they fit with your own CSS.

## Requirements

The package is ES modules only, with no CommonJS build. It supports the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later. Sites that enforce Trusted Types need to allow the `verbb-comments`, `lit-html` and `verbb-protect` policies.
