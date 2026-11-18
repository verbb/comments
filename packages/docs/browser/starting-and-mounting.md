# Starting and Mounting

Each thread on a page starts from a mount point, the element `craft.comments.render()` writes with a `data-comments-root` attribute. Something has to find those mount points and draw a thread in each, and that's `startComments()`. The plugin's script calls it once on every page with a thread, and a site that [bundles the package](/browser/bundling-in-your-own-build) calls it from its own entry script instead. This page covers what starting does, how to hold a thread back until you're ready, and how to start a thread on any element of your own.

## What `startComments()` Does

```js
import { startComments } from '@verbb/comments-browser';

const Comments = startComments({ theme: 'auto' });
```

Importing the package starts nothing. `startComments()` takes the same settings as [`Comments.configure()`](/browser/configuring-threads), along with `observe`, and then:

- Prepares every mount point on the page once it has been parsed, according to its [load mode](/browser/lazy-loading).
- Watches the page, so a mount point your code or a library adds later is started, and one that's removed is stopped. Pass `observe: false` to leave that to you.
- Stops threads before Turbo Drive caches a page, and starts the ones on the page Turbo shows next.
- Loads a waiting thread straight away when the address changes to a link to a comment, such as `#comment-1446`.
- Sets `window.Comments`, and returns the same object.

Calling it again only passes the new settings on. If a second copy of the script starts on the same page, such as the plugin's file alongside your own bundle, the second copy hands its settings to the first and draws nothing itself, so threads are never drawn twice. It logs a warning naming **Load Bundled JavaScript**, which is the setting to turn off.

## The `Comments` Object

`window.Comments`, the return value of `startComments()` and the package's `Comments` export are the same object.

| Method | What it does |
| --- | --- |
| `mount(scope)` | Starts the threads inside `scope`, or the whole document, and resolves with their controllers. A thread already started keeps its controller. |
| `mountThread(element, options)` | Starts one thread on any element, with options that win over the page's settings, and resolves with its controller. |
| `destroy(scope)` | Stops the threads inside `scope`, or the whole document, and puts the server's mount point back. |
| `get(root)` | The controller for a thread's root element, once it has started. |
| `configure(settings)` | Settings for every thread, covered in [Configuring Threads](/browser/configuring-threads). |
| `setTheme(theme)` | Switches every thread's theme, covered in [Dark Mode](/browser/dark-mode). |

The script that draws threads is loaded only when the first thread needs it, so `mount()` and `mountThread()` return promises. They reject with an `AbortError` for a thread that was destroyed or taken off the page before it could be drawn, and with a `NotSupportedError` for an element another copy of the renderer is already drawing, such as a `<verbb-comments>` element's.

## Holding a Thread Back

Add `data-comments-auto-mount="false"` to a mount point, or to any element around it, `<html>` included, and the script leaves those threads alone until you call `Comments.mount()`. It covers threads in the page when it loads, ones added later and ones on pages Turbo loads. For example, to start a thread once your own app has set itself up, wrap it in your Twig template:

```twig
<div id="discussion" data-comments-auto-mount="false">
    {{ craft.comments.render(entry) }}
</div>
```

Then start it from your script when you're ready:

```js
import { startComments } from '@verbb/comments-browser';

const Comments = startComments();

// Stands in for your own app's set-up, such as fetching data or rendering components.
await setUpMyApp();

const [controller] = await Comments.mount(document.querySelector('#discussion'));
```

`mount()` resolves with the controllers it started, the same ones `Comments.get()` gives once they're running.

## Starting a Thread on Your Own Element

`mountThread()` starts a thread on any element, not only a mount point from `render()`. It gives the element what the stylesheets and the script look for, such as `data-comments-root`, the `comments` class and an `id`, so you can start from an empty `<div>`:

```js
import { mountThread } from '@verbb/comments-browser';

const controller = await mountThread(document.querySelector('#discussion'), {
    ownerUri: 'blog/hello-world',
    sort: 'top',
    loadMode: 'eager',
});
```

Its options are the [settings](/browser/configuring-threads) every thread shares, which win over the page's, and these for the one thread:

| Option | What it does |
| --- | --- |
| `ownerId` or `ownerUri` | The element the comments belong to, by its ID or its URI. |
| `siteId` | The site, for a multi-site install. |
| `endpoint` | The Comments data API, such as `https://example.com/actions/comments/api`. Defaults to `/actions/comments/api` on the page's own origin. |
| `sort` | `newest`, `oldest` or `top`. |
| `limit` | Root comments in each page of the thread, from 1 to 50. |
| `onHosts` | Called with the component hosts after each draw, for [framework components](/browser/part-overrides#framework-components). |

## The Renderer Entry

`mountThread()` from the main entry waits for the renderer to load. Code that mounts threads itself and wants a controller at once, such as the web components and the React and Vue packages, imports it from `@verbb/comments-browser/renderer` instead, where the renderer is already loaded and `mountThread()` returns the controller directly:

```js
import { mountThread } from '@verbb/comments-browser/renderer';

const controller = mountThread(element, { ownerUri: 'blog/hello-world' });
```

Settings given to `Comments.configure()` reach threads mounted this way too, including settings given before the renderer entry was imported. If the plugin's script has already started the element you pass, that thread stops first, and the new one is drawn with your options. The plugin's script leaves alone any thread another copy is drawing, which carries `data-comments-js`, and any mount point marked `data-comments-adapter`, which the React and Vue packages' server markup carries so the script never draws it without the component's options. Turn off **Load Bundled JavaScript** when you mount threads this way, so the page runs one copy, and the console says so if it's on.

## The Controller

Every thread has a controller, from `Comments.get(root)`, `mount()`, `mountThread()` or any event's `detail.controller`.

| Property or method | What it does |
| --- | --- |
| `root` | The thread's root element. |
| `instanceId` | The root's `id`, such as `comments-109-1`, which every event's `detail.instanceId` gives. |
| `conversationUid` | The discussion's unique ID. |
| `sort` | The thread's current sort. |
| `store` and `client` | The thread's [store](/core/thread-store) and [client](/core/client-transports) from `@verbb/comments-core`. |
| `load()` | Loads the thread now, whatever its load mode. |
| `refresh({ reason, sort })` | Fetches the thread again, optionally in another order. |
| `openEditor({ mode, commentUid })` | Opens the form for a new comment (`create`), or a `reply` or `edit` form under the comment with `commentUid`. |
| `closeEditor({ reason })` | Closes the open reply or edit form. |
| `showFormErrors(form, errors)` and `clearFormErrors(form)` | Show or clear errors on a form, as [Validating Before Posting](/browser/validating-before-posting) explains. |
| `setTheme(theme)` | Switches this thread's theme. |
| `setOptions(options)` | Replaces this thread's own `themeConfig`, `parts`, `fields`, `labels` or `loadEditor`, and redraws. |
| `getHosts()` and `subscribeHosts(listener)` | The component hosts, for [framework components](/browser/part-overrides#framework-components). |
| `destroy()` | Stops the thread and puts the server's mount point back. |

For example, to show the newest comments when someone presses a button of your own, add the button and a script beside the thread in your Twig template. Craft's `{% js %}` tag outputs the script where your layout calls `{{ endBody() }}`.

```twig
{{ craft.comments.render(entry) }}

<button type="button" id="latest-comments">Show the newest comments</button>

{% js %}
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-comments-root]');

        document.querySelector('#latest-comments').addEventListener('click', () => {
            window.Comments.get(root)?.refresh({ sort: 'newest' });
        });
    });
{% endjs %}
```

Pressing the button fetches the thread again with the newest comments first. Before the thread has started, such as while it waits to scroll into view, `Comments.get()` returns nothing and the press does nothing. On a page with more than one thread, find the one you want by its `id`, which `render()` sets to `comments-` followed by the element's ID and its site ID, such as `comments-{{ entry.id }}-{{ entry.siteId }}` in Twig.
