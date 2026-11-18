# The `<verbb-comments>` Element

`<verbb-comments>` is one thread. Its attributes say which thread to draw and how, its properties take the settings that aren't plain text, such as part overrides and sign-in, and the thread's browser events bubble from the element itself.

```html
<verbb-comments owner-uri="blog/hello-world" endpoint="https://example.com/actions/comments/api" load-mode="button" sort="top" theme="auto"></verbb-comments>
```

## Attributes

| Attribute | What it does |
| --- | --- |
| `owner-id` or `owner-uri` | The element the comments belong to, by its ID or its URI. One of them is needed, and the element waits until it has one. |
| `site-id` | The site, for a multi-site install. |
| `endpoint` | The Comments data API, such as `https://example.com/actions/comments/api`. Defaults to `/actions/comments/api` on the page's own origin. |
| `load-mode` | `visible`, the default, loads the thread just before it scrolls into view, `button` waits for **Show comments**, and `eager` loads it straight away, as [below](#when-the-thread-loads). |
| `sort` | `newest`, `oldest` or `top`. |
| `theme` | `light`, `dark`, `auto` or `none`, as [Dark Mode](/web-components/styling#dark-mode) explains. |

Each attribute is also a property, as `ownerId`, `ownerUri`, `siteId`, `endpoint`, `loadMode`, `sort` and `theme`, so your code can set them either way.

Changing `owner-id`, `owner-uri`, `site-id` or `endpoint` draws the new thread in place of the old one, once however many of them change together. Changing `sort` re-sorts a thread that has loaded and starts a waiting one again in the new order, and changing `theme` switches the drawn thread's theme. `load-mode` is read when the thread starts.

The `endpoint` attribute is markup, so it isn't trusted to say where Editor's module comes from. The element loads Editor from npm instead, as [Rich Comments](/web-components/#rich-comments) describes.

## Properties

| Property | What it does |
| --- | --- |
| `controller` | The thread's [controller](/browser/starting-and-mounting#the-controller), with `load()`, `refresh()`, `openEditor()`, `setTheme()` and the rest, or `null` before the thread starts. |
| `themeConfig` | Classes and attributes for the ten controls, covered in [Styling](/web-components/styling#themeconfig-second). |
| `parts` | Part overrides, each a function or a registered custom element's name, covered in [Part Overrides with Custom Elements](/web-components/part-overrides). |
| `fields` | Custom field overrides by handle, covered in [Field Overrides](/web-components/field-overrides). |
| `labels` | Wording to use instead of the site's translations, as [below](#labels). |
| `loadEditor` | How Editor is loaded for rich comments. Defaults to importing `@verbb/editor`. |
| `auth` | How the person is identified, from `@verbb/comments-core`, covered in [Signing In](/core/signing-in). |

Setting `themeConfig`, `parts`, `fields`, `labels` or `loadEditor` redraws the thread with them. `auth` is read when the thread starts, so setting it starts the thread again. A property set before `registerCommentsWebComponents()` runs, such as by a framework that renders the element first, still applies.

```js
const thread = document.querySelector('verbb-comments');

thread.themeConfig = {
    primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
    quietButton: { attributes: { class: 'btn btn-ghost' } },
};

thread.parts = {
    badge: ({ badge, html, defaultRender }) => badge.type === 'verified'
        ? html`<span class="badge badge-success">✓ ${badge.label}</span>`
        : defaultRender(),
};
```

Settings given to `Comments.configure()` from `@verbb/comments-browser` reach the element's thread too, beneath its own properties, which suits settings every thread on the page shares, such as `darkSelector`.

## When the Thread Loads

Most visitors to a post never scroll down to its comments, so a thread waits until it's needed before it loads anything. Until then the element has only the heading, the comment count and the space the thread will fill.

<!--@include: ../browser/lazy-loading.md#load-modes-->

`load-mode` is read when the thread starts. To load a `button` thread from a control of your own, such as a drawer's toggle, call the element's `controller.load()`, which does nothing to a thread that has loaded.

```js
document.querySelector('#open-drawer').addEventListener('click', () => {
    document.querySelector('#drawer verbb-comments').controller?.load();
});
```

<!--@include: ../browser/lazy-loading.md#load-pieces-->

## Labels {#labels}

<!--@include: ../browser/configuring-threads.md#labels-intro-->

```js
document.querySelector('verbb-comments').labels = {
    post: 'Send',
    editorPlaceholder: 'What do you think?',
};
```

::: details Every label, with its default
<!--@include: ../browser/configuring-threads.md#labels-rules-->
:::

## Events

The thread's events, such as `comments:comment-created` and `comments:before-submit`, bubble from the element, with the controller in `event.detail.controller`. [Events and Validation](/web-components/events) lists them all with what each one gives, and covers checking a comment before it's posted.

```js
thread.addEventListener('comments:comment-created', (event) => {
    analytics.track('Comment posted', { thread: event.detail.instanceId });
});
```

## Pages That Change {#pages-that-change}

The thread stops when the element is taken off the page and starts again when it's put back, while moving the element somewhere else on the page keeps the thread as it is, along with anything typed in it. Before Turbo Drive keeps a copy of the page, the thread stops, so the copy Turbo restores later starts afresh, and an element still on the page when Turbo's next page loads starts again. A page swap that morphs the element's contents away, such as Turbo 8's morphing refresh, Datastar or idiomorph, has the same thread drawn again, with its controller, its properties and anything typed in it, though it's smoother to leave the element out of the morph with `data-turbo-permanent` or `data-ignore-morph` and an `id`, as [Turbo, Sprig and Datastar](/browser/turbo-sprig-datastar) shows.

## In Frameworks

The element works in any framework that renders custom elements. Set `owner-uri` or `owner-id` as an attribute, and the object settings, such as `themeConfig` and `auth`, as properties, since attributes only carry text. For React and Vue apps, the dedicated [React](/react/) and [Vue](/vue/) packages draw parts with your own components and render on the server without a mismatch, so they're usually the better fit.
