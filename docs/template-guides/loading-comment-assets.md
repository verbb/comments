# Loading Comment Assets

`craft.comments.render()` registers the bundled CSS and JavaScript by itself, so you don't need to add anything to your layout. Craft publishes them as asset bundles and generates their URLs, including on Craft Cloud.

The bundled files are:

- `comments-frontend.css`, which provides the default presentation.
- `comments-frontend.js`, which handles the comment editor, replies, edits, voting, reports, following, sorting, pagination and API requests. It's a module script, so it doesn't hold up the page.
- The Editor's own script and styles, with [rich authoring](docs:feature-tour/rich-authoring) only. The page doesn't load them up front. `comments-frontend.js` fetches them once a visitor clicks into the comment box, opens a reply or edits a comment, and swaps the Editor in for the plain box. With plain authoring they never load, unless someone edits a comment that was written with rich authoring.

Both settings that control these are under **Comments** → **Settings** → **Templates & Resources**.

## Theming

Choose **Light**, **Dark**, **Auto** or **None** as the **Theme** under **Comments** → **Settings** → **Appearance**, or with the [`editorTheme`](docs:get-started/configuration#editortheme) setting. **Light** is the default. **Auto** follows the visitor's colour-scheme preference, and the Editor uses the same theme as the comments around it. **None** outputs no default CSS at all, for sites providing their own.

To match the default look to your site, set its CSS custom properties, such as `--comments-color-accent`, in your own stylesheet. [Theming](docs:template-guides/theming) lists them all, with their defaults, and explains how your values win over the bundled ones in every theme.

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

/* With rich authoring, show either the Editor or the plain editor, never both */
.comments verbb-editor:not(:defined),
.comments form:not([data-comments-editor-mounted]) verbb-editor,
form[data-comments-editor-mounted] :is(.comments__frame-head, .comments__textarea-fallback, .comments__frame-notice, .comments__frame-foot) {
    display: none;
}
```

The last selector hides the plain editor's own header, box, draft notice and buttons once the Editor has taken over, because the Editor draws its own.

Icons don't need a rule of their own. Each one carries its size and line style as attributes, so without the bundled CSS they show as small outlined icons, at 16 or 18 pixels, until your own CSS sizes them.

Turn off **Load Bundled JavaScript** when your site provides the public interaction itself, or [bundles Comments' script](#bundling-the-script-into-your-own-build) into its own build. Comments still work [without JavaScript](docs:template-guides/working-without-javascript), and the [frontend API](docs:template-guides/javascript-api#frontend-api) is there for a custom client. To hook into the bundled script rather than replacing it, use the [JavaScript API](docs:template-guides/javascript-api).

If you're building your own form rather than using `render()`, [Displaying and Protecting Comments](docs:template-guides/displaying-and-protecting-comments) explains the fields it needs.

## Bundling the Script Into Your Own Build

A site with its own build, such as Vite or webpack, can include Comments' script in its bundle instead of loading the separate file. That gives you one bundle to cache and control over when threads start, which matters for single-page apps. Comments' templates and the server work the same either way.

The script is installed with the plugin, at `vendor/verbb/comments/src/resources/dist/js/comments-frontend.js` in your Craft project. It's a standalone ES module with no dependencies to install, and the stylesheet beside it, `comments-frontend.css`, is a separate file you can import too. In your site's entry script:

```js
import Comments from '../vendor/verbb/comments/src/resources/dist/js/comments-frontend.js';
import '../vendor/verbb/comments/src/resources/dist/js/comments-frontend.css';
```

Adjust the relative path to suit where your entry script lives, or give your bundler an alias for it. Importing the script starts every thread on the page, as the bundled file does, and `Comments` is the same object as `window.Comments`.

Then turn off **Load Bundled JavaScript** under **Comments** → **Settings** → **Templates & Resources**, or with [`outputDefaultJs`](docs:get-started/configuration#outputdefaultjs), so the page doesn't load a second copy. If you import the stylesheet as well, turn off **Load Bundled CSS** too.

### If Both Copies Load

When the page has the plugin's copy and your bundle's copy, only the first one to run starts any threads, and the other hands over to it. Importing the second copy gives you the running one, so your code works whichever loaded first. The browser console shows a warning naming **Load Bundled JavaScript**, which is your prompt to turn it off and save the extra download.

### Starting Threads Yourself

Add `data-comments-auto-mount="false"` to a thread's root or to any element around it, `<html>` included, and the script leaves those threads alone until you call `Comments.mount()`. The setting covers threads in the page when it loads, ones added later and ones on pages Turbo loads. For example, to start the thread once your app has finished setting up, wrap it in your template:

```twig
<div data-comments-auto-mount="false" id="discussion">
    {{ craft.comments.render(entry.id) }}
</div>
```

Then start it from your script when you're ready:

```js
import Comments from '../vendor/verbb/comments/src/resources/dist/js/comments-frontend.js';

await setUpMyApp();
Comments.mount(document.querySelector('#discussion'));
```

`Comments.mount()` returns the controllers it started, the same as `Comments.get()` would.

### TypeScript

A declaration file, `comments-frontend.d.ts`, sits beside the script, so TypeScript picks up its types when you import it, with no extra setup. It types `window.Comments`, each thread's controller, and every `comments:*` event with its `detail`, so a listener such as `document.addEventListener('comments:submit-before', …)` knows what `event.detail` holds. The types are exported too, such as `CommentsController` and `CommentsEventMap`:

```ts
import Comments, { type CommentsController } from '../vendor/verbb/comments/src/resources/dist/js/comments-frontend.js';

const controller: CommentsController | undefined = Comments.get(document.querySelector('[data-comments-root]')!);
controller?.refresh({ sort: 'newest' });
```

### Rich Authoring

With [rich authoring](docs:feature-tour/rich-authoring), each thread says where the Editor's script and styles are, in its `data-comments-editor-module` and `data-comments-editor-css` attributes, even with **Load Bundled JavaScript** off. Your bundled copy of the script loads the Editor from there when someone starts writing, as the plugin's copy does, so there's nothing extra to set up. If your page already has the Editor running, the script uses that instead.

### Turbo and Single-Page Apps

The script stops threads before Turbo caches a page and starts them on the page Turbo shows next. It also watches the page, so a thread your app adds is started, and one your app removes is stopped, with `comments:destroyed` reaching `document` even though the thread has already gone.

If your app fetches pages or partials and inserts their HTML, the scripts and styles Craft would add to a full page don't come with them. Load Comments' script and stylesheet with your app, from your bundle or the page layout, rather than relying on the HTML you insert.
