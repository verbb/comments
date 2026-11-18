# Lazy Loading

Most visitors to a blog post never scroll down to its comments, so a thread waits until it's needed before it loads anything. Until then the page has only the mount point, with the heading, the comment count and the space the thread will fill, and the script that draws threads hasn't been downloaded. The plugin's [Rendering Comments](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/rendering-comments) guide covers choosing the load mode in Twig and the space reserved for the thread. This page covers the same choices from JavaScript, and what happens as a thread loads.

## Load Modes

<!-- #region load-modes -->
| `loadMode` | When the thread loads |
| --- | --- |
| `visible` | Just before it scrolls into view, about 600 pixels ahead. This is the default. |
| `button` | When the visitor presses **Show comments (42)**, which is drawn in the thread's place. |
| `eager` | As soon as the script starts. |

A link to a comment, such as `#comment-1446`, `?comment=1446` or a comment's UID after `#comment-`, loads the thread straight away whatever its mode, and scrolls to the comment. So does following such a link within the page, while other in-page links, such as a table of contents, leave a waiting thread waiting.
<!-- #endregion load-modes -->

A thread's load mode can come from `craft.comments.render()`, from `Comments.configure()`, or from its own options in `mountThread()`, a `<verbb-comments>` element's `load-mode` attribute or a component's `loadMode` prop. A thread's own load mode wins over `configure()`'s, and `render()` always gives its thread one, from its `loadMode` option or the plugin's **Load Comments** setting. So `configure({ loadMode })` only reaches threads mounted from JavaScript without one of their own.

```js
import { mountThread } from '@verbb/comments-browser';

// A thread in a sidebar drawer, which loads when it's opened rather than when it scrolls into view.
const controller = await mountThread(document.querySelector('#drawer-comments'), {
    ownerUri: 'blog/hello-world',
    loadMode: 'button',
});

document.querySelector('#open-drawer').addEventListener('click', () => controller.load());
```

`controller.load()` loads a waiting thread now, whatever its mode, and does nothing to one that has loaded. The load mode is read when a thread starts, so changing it afterwards has no effect on that thread.

## What Loads When

<!-- #region load-pieces -->
The script a page downloads first is small. It finds the threads, draws their waiting state, and imports the renderer when a thread is about to be needed. The comment forms are a separate piece again, imported when someone heads for one, or straight away for a signed-in member, whose form shows at the top of the thread. A bundler that splits code at dynamic `import()` calls keeps these pieces apart, as Vite and webpack do, and the plugin's own script is built the same way.

If a piece can't load, the thread says so where it would have been, with **Try again**. That can happen to a tab left open while the plugin is updated, because the pieces have new names, and **Try again** or reloading the page fixes it. Chrome and other Chromium browsers never fetch a failed piece again on the same page, so there **Try again** for the thread or the comment form reloads the page once the server answers, and a thread that waited for **Show comments** then shows straight away. The file tray's **Try again** loads it again without a reload, so a comment being written stays.

While a thread loads, its waiting state stays exactly where it is, with `aria-busy="true"` on the loading line, and the heading's count is updated from the response in case a cached page held an old number. A long thread draws its first 45 comments at once and the rest in batches while the browser is idle. A link to a comment, focus moving to one and **Load more** draw what they need straight away.
<!-- #endregion load-pieces -->

## Watching a Thread Load

Each thread's root has a `data-comments-phase` attribute, which is `waiting`, `loading` or `button` before the thread is drawn, `ready` once it's drawn, and `error` if it couldn't load, so your CSS can follow it. From JavaScript, `comments:mounted` fires when a thread starts and `comments:roots-loaded` when its comments arrive, as [Browser Events](/browser/browser-events) describes.

```css
/* A border that appears once the thread is drawn. */
.comments[data-comments-phase='ready'] {
    border-top: 1px solid var(--comments-color-border);
}
```
