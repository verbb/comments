# Comments Web Components

`@verbb/comments-web-components` puts a [Comments](https://verbb.io/craft-plugins/comments) thread on any page with one element, `<verbb-comments>`. It suits a site without a framework, a static site, or a page on another domain that shows comments from a Craft site, and it draws the thread with `@verbb/comments-browser`, so the thread looks and behaves exactly as it does on the Craft site itself.

```js
import { registerCommentsWebComponents } from '@verbb/comments-web-components';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';

registerCommentsWebComponents();
```

```html
<verbb-comments owner-uri="blog/hello-world" endpoint="https://example.com/actions/comments/api"></verbb-comments>
```

Importing the package defines nothing. `registerCommentsWebComponents()` defines `<verbb-comments>`, and calling it again does nothing.

## Attributes

| Attribute | What it does |
| --- | --- |
| `owner-id` or `owner-uri` | The element the comments belong to, by its ID or its URI. One of them is needed; until then the element waits. |
| `site-id` | The site, for a multi-site install. |
| `endpoint` | The Comments data API, `https://example.com/actions/comments/api`. Defaults to `/actions/comments/api` on the page's own origin. Being markup, it isn't trusted to name where Editor's module comes from, which the element loads from npm instead. |
| `load-mode` | `visible` (the default) loads the thread just before it scrolls into view, `button` waits for **Show comments**, and `eager` loads it straight away. |
| `sort` | `newest`, `oldest` or `top`. |
| `theme` | `light`, `dark`, `auto` or `none`. |

Changing `owner-id`, `owner-uri`, `site-id` or `endpoint` draws the new thread in place of the old one, once however many of them change together. Changing `sort` sorts a thread that has loaded and starts a waiting one again in the new order, and changing `theme` switches the drawn thread's theme. `load-mode` is read when the thread starts, so to load a waiting thread later, call `element.controller.load()`. Each attribute is also a property (`ownerId`, `ownerUri`, `siteId`, `endpoint`, `loadMode`, `sort` and `theme`).

## Properties

| Property | What it does |
| --- | --- |
| `controller` | The thread's controller, with `load()`, `refresh()`, `openEditor()`, `setTheme()` and the rest, or `null` before the thread starts. |
| `themeConfig` | Classes and attributes for the ten controls, such as `{ primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } } }`. |
| `parts` | Part overrides, each a function returning a template or a registered custom element's name. |
| `fields` | Custom field overrides by handle, `{ rating: { input, display } }`. |
| `labels` | Wording to use instead of the site's translations. |
| `loadEditor` | How Editor is loaded for rich comments. Defaults to importing `@verbb/editor`. |
| `auth` | How the person is identified, from `@verbb/comments-core`, such as `memberToken({ getToken })` on a site on another domain. |

Setting `themeConfig`, `parts`, `fields`, `labels` or `loadEditor` redraws the thread with them. `auth` is read when the thread starts, so setting it starts the thread again. A property set before `registerCommentsWebComponents()` runs still applies.

```js
const thread = document.querySelector('verbb-comments');

thread.themeConfig = { quietButton: { attributes: { class: 'btn btn-ghost' } } };
thread.parts = {
    badge: ({ badge, html, defaultRender }) => badge.type === 'verified'
        ? html`<span class="badge badge-success">✓ ${badge.label}</span>`
        : defaultRender(),
};
```

## Events

The thread's browser events, such as `comments:comment-created` and `comments:before-submit`, bubble from the element itself, with the controller in `event.detail.controller`.

```js
thread.addEventListener('comments:comment-created', (event) => {
    analytics.track('comment', { thread: event.detail.instanceId });
});
```

## Editor

Rich comments use [Editor](https://verbb.io/packages/editor). By default the element imports `@verbb/editor` when someone starts writing, so a bundler that splits on `import()` keeps it out of the page's first download. Import Editor's stylesheet, `@verbb/editor/styles`, alongside Comments' own. To load Editor some other way, set `loadEditor` to a function that resolves once `<verbb-editor>` is defined.

## Pages and the plugin's script

The thread stops when the element is taken off the page and starts again when it's put back, and moving the element somewhere else on the page keeps the thread as it is, along with anything typed in it. Before Turbo Drive keeps a copy of the page, the thread stops, so the copy it restores starts afresh, and an element still on the page when Turbo's next page loads starts again. A page swap that morphs the element's contents away (Turbo 8's morph refresh, Datastar or idiomorph) has the same thread drawn again, with its controller, properties and anything typed in it, though it's smoother to leave the element out of the morph with `data-turbo-permanent` or `data-ignore-morph` and an `id`.

On a Craft site, the plugin's own script leaves `<verbb-comments>` elements alone, `Comments.mount()` included, and the element leaves the plugin's mount points alone, so each thread is drawn once. Both would load the renderer, though, so turn off **Load Bundled JavaScript** in the Comments settings when a page uses the element, and the console says so if it's on.

## Requirements

- The package is ES modules only, with no CommonJS build, for a bundler such as Vite, webpack or esbuild.
- Browsers: the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later.
- Sites that enforce Trusted Types allow the `verbb-comments`, `lit-html` and `verbb-protect` policies.

## Documentation

The [Comments package docs](https://docs.verbb.io/comments) cover the element, the controller, `themeConfig`, part and field overrides, browser events and signing in from another domain.

## Licence

Comments Web Components is released under the Verbb licence in `LICENSE.md`, the same as the Comments plugin.
