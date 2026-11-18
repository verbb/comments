# Bundle Comments in Your Own Build

Out of the box, `craft.comments.render()` brings its own script and stylesheet, which Craft adds to the page for you. That suits most sites. A site with its own JavaScript build, though, often wants everything to come from that build, so there's one set of files to cache and version, one place to set things up, and control over when each piece of the page starts.

In this guide you'll move Comments' script and stylesheet into a Craft site's Vite build. You'll install Comments' browser package, start it from the site's own entry script, turn off the plugin's copies, then take control of when the thread starts, so it waits until the rest of the page has set itself up. Finally, you'll load Editor, the rich text editor, from your build too.

The example is a magazine site whose templates are rendered by Craft, and whose JavaScript and CSS are built by Vite from `src/main.js`. When you're done, the page loads no files from Comments' plugin at all, and the thread looks and works exactly as before.

## What You'll Need

You'll need Comments installed, with its thread on an entry template, output with `craft.comments.render(entry)`, as [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) describes.

You'll also need a front-end build that already runs, with an entry script your layout loads as a module. This guide uses Vite, with its entry at `src/main.js`, but webpack, esbuild and other bundlers work the same way. Your build needs to split code at dynamic imports, which Vite and webpack do by default, because Comments loads parts of itself only when they're needed. You'll run `npm` commands in the folder that holds your front end's `package.json`.

## What Comments Loads, and Why You'd Bundle It

`render()` outputs a small mount point for the thread, and registers two files with Craft. One is Comments' stylesheet. The other is a small script that finds each mount point on the page, waits until the thread is about to scroll into view, then loads the code that draws the thread, and the comment form's code only when someone starts writing.

That script is published as an npm package, `@verbb/comments-browser`, which is exactly what the plugin's own file is built from. When you bundle the package yourself, your bundler keeps those pieces apart in the same way, so the page still only downloads what it needs. Comments' mount point and everything on the server stay the same, so nothing in your templates changes.

## Installing the Package

The package and the plugin are released together, with the same version number, and the script reads the data the plugin sends, so install the version that matches your plugin. Find your plugin's version under **Settings** → **Plugins** in the control panel, or by running this in your Craft project's root folder:

```bash
composer show verbb/comments
```

Then install that version of the package in your front end's folder, replacing `4.0.0` with your plugin's version:

```bash
npm install @verbb/comments-browser@4.0.0
```

When you update the plugin later, update the package to the same version at the same time.

## Starting Comments from Your Script

Add this to the top of `src/main.js`:

```js
import { startComments } from '@verbb/comments-browser';
import '@verbb/comments-browser/css/comments.css';

startComments();
```

Importing the package doesn't do anything by itself. `startComments()` does what the plugin's script does. It finds every thread on the page, waits for each one according to its load mode, starts threads added to the page later, and sets up `window.Comments` for any other scripts that use it. The second line imports Comments' stylesheet, which Vite adds to your built CSS. If your bundler doesn't handle CSS, leave that line out and keep using the plugin's stylesheet.

Rebuild and reload an article. The thread works as before, but there's a warning in your browser's console saying the comments script has loaded twice. Both copies are on the page now, the plugin's and yours, so the next step turns the plugin's off.

### Turning Off the Plugin's Copies

Go to **Comments** → **Settings** → **CSS & JavaScript** and turn off **Load Bundled JavaScript** and **Load Bundled CSS**, then save. You can set them in `config/comments.php` instead:

```php
<?php

return [
    'outputDefaultJs' => false,
    'outputDefaultCss' => false,
];
```

Reload the article. The warning has gone, and the thread draws from your bundle alone.

If you see the warning again later, both copies are loading. Only the first copy to start draws any threads, and the second hands its settings to it, so nothing breaks, but the page downloads the code twice. If the thread doesn't appear at all, with only the heading showing, your bundle isn't starting it. Check that `src/main.js` is loading on the page and calls `startComments()`, and look in the console for errors.

### Checking Where the Files Come From

Open your browser's developer tools at the **Network** panel and reload the article. The page loads your own built files, and as you scroll down to the comments, one or two more files from your build arrive, which are the pieces of Comments your bundler split off. Nothing loads from a `cpresources` folder with `comments` in its name. Click into the comment box, and another piece of your build loads, for the comment form.

## Passing Settings to `startComments()`

`startComments()` takes the same settings as `Comments.configure()`, so the settings for every thread can live in your build rather than in a separate script. For example, to follow your site's dark mode toggle and give the thread your site's button classes:

```js
startComments({
    darkSelector: 'html.dark',
    themeConfig: {
        primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
    },
});
```

[Make a Custom Theme](docs:user-guides/make-a-custom-theme) explains `darkSelector`, and [Style Comments with Tailwind or a Design System](docs:user-guides/style-comments-with-tailwind-or-a-design-system) explains `themeConfig`. `startComments()` also returns the `Comments` object, the same one as `window.Comments`, for anything else your script needs to do with the threads.

## Starting the Thread Yourself

By default, `startComments()` looks after every thread on the page by itself. Sometimes your site needs to finish setting something up first. The magazine, for example, fetches the reader's saved preferences before showing the page's interactive parts, and wants the thread to wait for them.

To take over starting a thread, wrap it in an element with `data-comments-auto-mount="false"` in your template:

```twig
<div id="discussion" data-comments-auto-mount="false">
    {{ craft.comments.render(entry) }}
</div>
```

Comments leaves any thread inside an element with that attribute alone, including threads added to the page later. The attribute can go on the thread's own parent, as here, or on any element further up, up to `<html>`, to take over every thread on the page.

Then start it from `src/main.js` once you're ready:

```js
import { startComments } from '@verbb/comments-browser';
import '@verbb/comments-browser/css/comments.css';

const Comments = startComments();

// The site's own set-up, such as fetching the reader's preferences.
await loadPreferences();

const discussion = document.querySelector('#discussion');

if (discussion) {
    await Comments.mount(discussion);
}
```

`loadPreferences()` stands in for whatever your site does first. `Comments.mount()` starts every thread inside the element you give it, and returns a promise, because the code that draws threads may still be loading. Once started, a thread follows its load mode as usual, so with the default mode it still waits until it's about to scroll into view.

The `if` matters, because your script runs on every page, and only some have comments. `await` at the top level of a module works in every browser Comments supports, but if your build targets older browsers, put the steps in an `async` function instead.

Reload the article and scroll straight down. The thread's heading is there from the start, because it's in the page's HTML, and the thread appears below it once `loadPreferences()` has finished.

## Loading Editor from Your Build

When **Authoring Mode** under **Comments** → **Settings** → **Authoring** is set to **Rich (Editor)**, Comments uses Editor for writing comments, and by default loads Editor's script and stylesheet from your Craft site when someone starts writing. That works with your bundle as it is. To load Editor from your build too, install its package in your front end's folder, choosing the version that matches the Editor plugin in your Craft project, which `composer show verbb/editor` shows:

```bash
npm install @verbb/editor
```

Then tell `startComments()` how to load it, and import its styles:

```js
import { startComments } from '@verbb/comments-browser';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';

startComments({
    loadEditor: () => import('@verbb/editor').then((editor) => editor.registerVerbbEditorElement()),
});
```

`loadEditor` is a function Comments calls when someone first starts writing. It imports Editor with a dynamic import, so your bundler keeps Editor in a piece of its own that only downloads then, and registers Editor's `<verbb-editor>` element, which is what Comments waits for. Importing Editor without registering it defines nothing, so the `.then()` is needed.

Reload the article, open the **Network** panel and click into the comment box. The rich editor appears, its file comes from your build, and nothing loads from Editor's folder on your Craft site.

## Testing It

Check the whole thread works from your bundle, because some parts only load when they're used:

1. Reload an article and scroll to the comments. The thread loads as it nears view, and the console has no warnings.
2. Click into the comment box, write a comment and post it. Reply to a comment and edit your own.
3. Vote on a comment, open the **···** menu and report it, then cancel.
4. Open the page with `#comment-` and a comment's ID on the end of its URL. The thread loads straight away and highlights that comment.
5. In the **Network** panel, check that none of the files come from Comments' or Editor's plugin folders.

If you use TypeScript, the package includes its types, so `startComments()`, `window.Comments`, each thread's controller and every `comments:` event are typed with no extra setup. [TypeScript](https://docs.verbb.io/comments/browser/typescript) in the package docs has the details, and [Bundling in Your Own Build](https://docs.verbb.io/comments/browser/bundling-in-your-own-build) covers the package's other options.

If your site swaps pages with Turbo, Sprig or Datastar rather than loading them in full, your bundle handles that the same way the plugin's script does, as [Comments with Turbo, Sprig or Datastar](docs:user-guides/comments-with-turbo-sprig-or-datastar) explains.
