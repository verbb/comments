# Bundling in Your Own Build

A Craft site with its own build, such as Vite or webpack, can include Comments' script in its bundle rather than loading the plugin's separate file. You get one bundle to cache, the package's TypeScript types, and control over when threads start, which matters for single-page apps. The mount points `craft.comments.render()` writes, and everything on the server, work the same either way. The [Bundle Comments in Your Own Build](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/bundle-comments-in-your-own-build) user guide walks through the switch for a Vite site.

## Installing the Package

The script is `@verbb/comments-browser`. Install the version that matches your Comments plugin, because the two are released together with the same version number and the script reads the data the plugin sends. Your plugin's version is listed under **Settings** → **Plugins** in the control panel, or shown by `composer show verbb/comments` in your Craft project's folder. Install it from your front-end project's folder, replacing `4.0.0` with your plugin's version:

```bash
npm install @verbb/comments-browser@4.0.0
```

When you update the plugin, update the package to the same version.

## Starting Comments

Start Comments from your site's entry script, and import the stylesheet if your bundle handles CSS:

```js
import { startComments } from '@verbb/comments-browser';
import '@verbb/comments-browser/css/comments.css';

startComments({
    theme: 'auto',
    themeConfig: {
        primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
    },
});
```

`startComments()` takes the same settings as [`Comments.configure()`](/browser/configuring-threads), starts every thread on the page as the plugin's file does, and returns the same `Comments` object it sets as `window.Comments`. [Starting and Mounting](/browser/starting-and-mounting) covers what it does in full, and how to hold a thread back until your app is ready. For the **None** theme's base styles only, import `@verbb/comments-browser/css/comments-base.css` instead. To fit Comments' styles into a layered stylesheet, such as one built with Tailwind, see [Class Names and CSS Layers](/browser/class-names-css-layers#when-your-build-imports-the-stylesheet).

Then turn off **Load Bundled JavaScript** under **Comments** → **Settings** → **CSS & JavaScript**, or with `outputDefaultJs` in `config/comments.php`, so the page doesn't load a second copy. If you import the stylesheet too, turn off **Load Bundled CSS** (`outputDefaultCss`) as well. The plugin's [Loading Comment Assets](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/loading-comment-assets) guide covers both settings.

### If Both Copies Load

When the page has the plugin's copy and your bundle's copy, only the first one to start runs any threads, and the second hands its settings over to it. `startComments()` in the second copy returns the running one, so your code works whichever loaded first. The browser console shows a warning naming **Load Bundled JavaScript**, which is your prompt to turn it off and save the extra download.

## Code Splitting

Your bundler keeps the code that draws threads and the comment form in separate files, loaded only when a thread needs them, as long as it splits code at dynamic `import()` calls, which Vite and webpack do by default. [Lazy Loading](/browser/lazy-loading#what-loads-when) explains what loads when.

## Loading Editor {#loading-editor}

Rich comments use [Editor](https://verbb.io/packages/editor). By default the script loads Editor's own module from the address in the thread's data when someone starts writing, which works with the plugin's file and with your bundle alike, and if your page already has Editor running, the script uses that. For safety, that address is only trusted when it's on the page's own origin, the comments script's origin or an `endpoint` your code gave. An endpoint read from markup on another origin doesn't count, so a page like that loads Editor through `loadEditor`.

To bundle Editor yourself instead, give `loadEditor`, a function that resolves once `<verbb-editor>` is defined. Importing Editor alone defines nothing, so call its registration function:

```js
import { startComments } from '@verbb/comments-browser';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';

startComments({
    loadEditor: () => import('@verbb/editor').then((editor) => editor.registerVerbbEditorElement()),
});
```

The dynamic `import()` keeps Editor in a file of its own, downloaded only when someone starts writing. The web components and the React and Vue packages load Editor this way by default.

## Content Security Policy

A site with a strict Content Security Policy needs nothing special for the thread itself, which draws without inline styles or scripts, and reserves its space with classes rather than a `style` attribute. Pass your policy's `nonce` to `startComments()` or `configure()`, and the scripts and stylesheets a thread loads, such as Editor's and the spam protection's challenges, carry it. The plugin's own file reads the nonce from its `<script>` tag by itself. A site that enforces Trusted Types allows the `verbb-comments`, `lit-html` and `verbb-protect` policies.

## Pages That Load HTML

If your app fetches pages or partials and inserts their HTML, the scripts and styles Craft would add to a full page don't come with them. Load Comments' script and stylesheet with your app, from your bundle or the page layout, rather than relying on the HTML you insert. The script notices a mount point your app adds and starts it, and stops a thread your app removes. [Turbo, Sprig and Datastar](/browser/turbo-sprig-datastar) covers libraries that swap or morph the page.
