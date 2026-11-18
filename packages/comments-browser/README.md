# Comments Browser

`@verbb/comments-browser` draws [Comments](https://verbb.io/craft-plugins/comments) threads in the browser. It's what the plugin's own script runs on every site, and what a site's own bundle, the web components and the React and Vue packages build on. It loads a thread when it's about to scroll into view or when a visitor presses **Show comments**, draws it from the Comments data API through `@verbb/comments-core`, and handles everything a reader, a commenter and a moderator does in it: the comment editor with Editor loaded only when someone starts writing, votes, replies, edits, reports, following and moderation.

```js
import { startComments } from '@verbb/comments-browser';
import '@verbb/comments-browser/css/comments.css';

// Starts every thread `craft.comments.render()` put on the page, and any added later.
startComments({
    theme: 'auto',
    themeConfig: {
        primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
    },
});
```

Importing the package starts nothing. Turn off **Load Bundled JavaScript** in the Comments settings when your own bundle calls `startComments()`, so the page doesn't run two copies.

## Loading in pieces

`startComments()` is small. It finds the threads, draws their loading line or **Show comments**, and imports the renderer with `import()` when a thread nears view, at once for `loadMode: 'eager'` or a link to a comment. The comment forms are a piece of their own, imported when someone heads for one, and straight away for a signed-in member. A bundler that splits on `import()` keeps these pieces apart; the plugin's own script does.

Because the renderer may not have arrived yet, `Comments.mount()` and `mountThread()` from the main entry return promises. They reject with an `AbortError` for a thread destroyed or taken off the page before the renderer arrived. Code that mounts threads itself and wants a controller at once, such as the web components and the React and Vue packages, imports `mountThread()` from `@verbb/comments-browser/renderer` instead. Turn off **Load Bundled JavaScript** in the Comments settings when you do, so the page runs one copy. The plugin's script, `Comments.mount()` included, leaves alone any thread another copy is drawing (each carries `data-comments-js`) and any mount point marked `data-comments-adapter`, which an adapter's server markup carries so the script never draws it without the adapter's options. If the plugin's script has already started an element that `/renderer`'s `mountThread()` is given, that thread stops and the new one is drawn with the options given.

If a piece can't load, the thread says so where it would have been, with **Try again**: the renderer in place of the loading line or **Show comments**, the form in place of the comment box, and the file tray in its field, where an edit keeps the comment's files meanwhile. This can happen to a tab left open across an update of the plugin, whose pieces have new names; Try again, or reloading the page, fixes it. Chrome and other Chromium browsers never fetch a failed piece again on the same page, so there Try again for the renderer or the form reloads the page once the server answers, and a thread that waited for **Show comments** then shows straight away. The file tray's Try again loads it again without a reload, so a comment being written stays.

A long thread draws its first 45 comments at once and the rest in batches while the browser is idle. A link to a comment, focus moving to one and **Load more** draw what they need straight away, and each ··· menu builds its items when it first opens.

## Styling

The thread's look comes from CSS variables and stable `comments__*` class names, listed with each one marked stable or internal in `markup/classes.json`. `css/comments.css` holds both the base and the theme, `css/comments-base.css` holds only the structure the thread needs to work (what theme None loads), and `css/comments-theme.css` holds the look for a site with its own base. To put Comments below a layered site's utilities, import it into a layer: `@import "@verbb/comments-browser/css/comments.css" layer(comments);`.

`themeConfig` adds classes and attributes to the ten controls design systems standardise (buttons, inputs, avatars and links), and part overrides replace the markup of one part with a function or a registered custom element. `themeConfig` sets only `class`, `title`, `aria-label` and your own `data-*` attributes. Settings merge per control from `craft.comments.render()` to `Comments.configure()` to `mountThread()`, with later ones winning.

A part override receives the part's data, `labels`, `actions`, `controlAttributes()`, the `html` template tag and `defaultRender()`. Build its markup with `html`, which escapes what it inserts, and never insert text from a comment, a commenter or a custom field as HTML. An override of the whole `comment` part must also draw `form()`, where Reply and Edit open their form, and `replies()`.

## Custom fields

The comment form draws the custom fields the site collects, each the way its type needs: text, numbers, dates, choices, a switch, and for Assets fields a tray of files that upload as they're chosen, with Remove and Try again. Fields shown on comments appear after the date or in a labelled list under the text. Comments links a web address or file only when it's `http` or `https`, or a path that stays on the site, always with `rel="ugc nofollow noopener"`, and shows every value as text, never as HTML. Image files show a thumbnail loaded from the file's address, so a file stored on another host is fetched from there by every reader, which tells that host their IP address.

A guest's files upload one at a time, each with a fresh protection preparation, so each waits the site's minimum time before it's sent, as the comment itself does.

Field overrides by handle change how one field is filled in and shown, wherever it appears. Each is a function or a registered custom element's name, given through `Comments.configure()`, `mountThread()` or, as element names, `render()`'s config. An `input` override is drawn inside Comments' own label, instructions and error, and gets the field's description, its `value`, `setValue()`, its `error`, the `id`, `labelId` and `describedBy` for its own control, and `defaultRender()` for the built-in input; the form posts whatever it last set. For an Assets field, `value` is the list of file IDs the form posts, `files` holds an edited comment's files (`id`, `url`, `filename`, `kind`), and `upload(file)` uploads a `File` to the field, answering with its `id` to add to `setValue()`. A `display` override gets the comment's entry in `fields` (`field`, `value` and `text`) and `defaultRender()`. Both get `html`, which escapes what it inserts.

```js
import { configure } from '@verbb/comments-browser';

configure({
    fields: {
        rating: {
            // Five stars on the form, which post a number from 1 to 5.
            input: ({ html, value, setValue, labelId }) => html`
                <div role="radiogroup" aria-labelledby=${labelId}>
                    ${[1, 2, 3, 4, 5].map((stars) => html`
                        <button type="button" aria-label=${`${stars} stars`} aria-pressed=${String(Number(value) >= stars)} @click=${() => setValue(stars)}>★</button>`)}
                </div>`,
            // And on the comment.
            display: ({ html, value }) => html`<span aria-label=${`${value} out of 5`}>${'★'.repeat(value)}${'☆'.repeat(5 - value)}</span>`,
        },
    },
});
```

## Framework components

The React and Vue packages draw part and field overrides with the app's own components, and any other framework can do the same through `@verbb/comments-browser/renderer`. An override given as `{ component }` makes the renderer draw an empty `<verbb-comments-host>` element in the part's place, the same element on every draw for as long as the part is there, and report it, so the app renders its component into it with a portal and the component stays in the app's own tree.

```js
import { mountThread } from '@verbb/comments-browser/renderer';

const controller = mountThread(element, {
    ownerUri: 'blog/hello',
    parts: { badge: { component: VerifiedBadge } },
    fields: { rating: { input: { component: StarsInput } } },
});

controller.subscribeHosts((hosts) => {
    // Each host has a stable `key`, its `element`, the `component` and the `context` a function
    // override would get, such as an input's `value`, `setValue()` and `error`.
    renderPortals(hosts);
});
```

Hosts are reported once after each draw that adds, changes or removes any, with every current host, and with none when the thread stops. A host keeps its object and `context` until its component or data changes, so a draw that changes nothing reports nothing. `context.renderDefaultInto(element)` draws Comments' own markup for the part into an element of the component's and keeps it current; for the whole `comment` part, that's only what goes inside the comment, since Comments keeps its list item, form and replies around the component. `getHosts()` gives the last report, and both are bound to the controller, so React's `useSyncExternalStore(controller.subscribeHosts, controller.getHosts)` takes them as they are. `controller.setOptions()` replaces a mounted thread's own `themeConfig`, `parts`, `fields`, `labels` or `loadEditor`, for an app whose props change, and ignores options equal to those it has.

## Your own interface

A site without a framework that draws every element itself reads the thread from `@verbb/comments-core`'s thread store and posts from its own `<form>` with `commentForm()`, from `@verbb/comments-browser/form`. It mounts the site's spam protection on the form when someone starts writing, sends its evidence with each post, and gives the server's field errors through `getState()` and `subscribe()`. It's an entry of its own, so it never loads the renderer. React's and Vue's `useCommentForm()` are built on it.

```js
import { commentForm } from '@verbb/comments-browser/form';

const element = document.querySelector('#comment-form');
const form = commentForm(element, store);

element.addEventListener('submit', (event) => form.handleSubmit(event));
```

## Swapped and morphed pages

The script starts threads that Turbo Drive, Sprig or htmx add to the page and stops the ones they take away. A page swap that morphs the page instead (Turbo 8's morph refresh, Datastar or idiomorph) would put the server's empty mount point back over a drawn thread. The same controller then draws the thread again from the data it has, in batches as a first load does, keeping its options, listeners, what's typed in its forms and its component hosts, and giving focus back where it was; a thread still waiting to load draws its loading line or **Show comments** again. An element your own code adds beside the thread, inside its root, is left where it is. It's smoother to leave the thread out of the morph, though. Wrap `craft.comments.render()` in an element with its own `id` and `data-turbo-permanent` for Turbo, or `data-ignore-morph` for Datastar.

## Requirements

- The package is ES modules only, with no CommonJS build.
- Browsers: the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later.
- Sites that enforce Trusted Types allow the `verbb-comments`, `lit-html` and `verbb-protect` policies.
- Editor's module, when it's loaded from the URL in the thread's data rather than through `loadEditor`, comes only from the page's own origin, the comments script's, or an `endpoint` given in code. An endpoint read from markup on another origin doesn't count, so a page like that gives `loadEditor`.

## Documentation

The [Comments package docs](https://docs.verbb.io/comments) cover starting and mounting, lazy loading, dark mode, class names and CSS layers, `themeConfig` for controls, part overrides, browser events and the markup reference.

## Licence

Comments Browser is released under the Verbb licence in `LICENSE.md`, the same as the Comments plugin.
