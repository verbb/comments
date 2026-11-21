# Part Overrides

When you need different markup rather than a different look, replace one part of the thread and keep everything else. A part override draws one part, such as a badge, the commenter's name or the empty state, and the thread keeps its theme, your control classes, its wording, its events and all of its behaviour around it. Overrides are given by part name in `parts`, through `Comments.configure()`, `startComments()` or a thread's own options.

For example, to draw the **Staff** badge with your design system's badge classes, and leave every other badge as it is, add this to a script on the page:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({
        parts: {
            badge: ({ badge, html, defaultRender }) => badge.type === 'staff'
                ? html`<span class="badge text-bg-primary">${badge.label}</span>`
                : defaultRender(),
        },
    });
});
```

Threads already drawn redraw with the override, and threads that haven't loaded yet use it from the start. The [Change a Part's Structure](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/theming-markup/change-a-parts-structure) user guide walks through an override like this from scratch.

## The Parts

The parts you can override are deliberately fewer than the elements in the [Markup Reference](/browser/markup-reference), because each one is a promise that it'll keep working between releases. Everything else is reached with stable class names, CSS custom properties and [`themeConfig`](/browser/theme-config).

<!--@include: ../_generated/parts.md-->

The comment form itself, the comment box, the rich text editor and the report form can't be overridden, because they carry spam protection, the editor's loading and keyboard focus. Their look is reached through class names, custom properties and `themeConfig`, and the custom fields on the form through the `formFields` part or a [field override](/browser/field-overrides).

## What an Override Is Given

Every override is given the same context, with the part's own data from the table above:

<!-- #region parts-context -->
| Key | What it is |
| --- | --- |
| `labels` | The thread's translated wording, keyed as [Configuring Threads](/browser/configuring-threads#labels) lists. |
| `actions` | For a comment's parts, `vote(vote)`, `reply()`, `edit()`, `delete()`, `report()`, `copyLink()`, `moderate(action)`, `toggleReplies()` and `loadReplies()`, bound to the comment. The thread's own parts get an empty object. |
| `controlAttributes(name)` | The attributes of one of the ten controls with your `themeConfig` applied, for a button or link your override draws. |
| `html` | lit-html's template tag, which escapes everything it inserts. |
| `defaultRender()` | Comments' own markup for the part, to wrap or fall back to. |

A comment's parts also get `comment`, the comment's data in the data API's shape, `permissions`, what the person reading may do with it (such as `canReply`, `canVote` and their own `vote`), and `viewer`, who's reading.
<!-- #endregion parts-context -->

## Three Kinds of Override

### A Function

A function returns the part's markup, as an `html` template, a string (drawn as text) or a DOM node. Build markup with the `html` tag it's given, and never insert text from a comment, a commenter or a custom field as HTML, because `html` is what stops what visitors write from becoming markup on your page. Comments' own `content` part inserts a rich comment's HTML only after Editor has sanitised it on the server.

```js
window.Comments.configure({
    parts: {
        // A friendlier empty state, with the default's "Be the first to comment." for anyone who can post.
        empty: ({ html, viewer, labels }) => html`
            <div class="comments__empty">
                <p>No one has commented on this post yet.</p>
                ${viewer?.canComment ? html`<p>${labels.beFirst}</p>` : ''}
            </div>`,
        // Staff names in bold, from the badges each comment's author already carries.
        authorName: ({ comment, html, defaultRender }) => comment.author?.badges.some((badge) => badge.type === 'staff')
            ? html`<strong>${defaultRender()}</strong>`
            : defaultRender(),
    },
});
```

<!-- #region parts-throws -->
If an override throws, the error is logged in the browser console and Comments' own markup is drawn in its place, so the rest of the thread keeps working.
<!-- #endregion parts-throws -->

### A Custom Element

A string is the name of a custom element you've registered. Comments creates the element once and sets the part's context on it as properties every time the part is drawn, so the element can draw itself however it likes.

```js
class CommentAuthor extends HTMLElement {
    set comment(comment) {
        this.textContent = comment.author?.name ?? '';
        this.classList.toggle('is-guest', Boolean(comment.author?.isGuest));
    }
}

customElements.define('acme-comment-author', CommentAuthor);

window.Comments.configure({ parts: { authorName: 'acme-comment-author' } });
```

Custom elements suit sites that already build their interface from them. [Part Overrides with Custom Elements](/web-components/part-overrides) covers them in more depth.

### A Framework Component {#framework-components}

An object with a `component` key, such as `{ component: VerifiedBadge }`, is for a framework to draw. The React and Vue packages make these from your components, so you only need the details below to connect another framework.

The renderer never looks at the component itself. It draws an empty `<verbb-comments-host>` element in the part's place, the same element on every draw for as long as the part is there, and reports it, so your framework can render the component into it with a portal and keep it inside your app's tree.

```js
import { mountThread } from '@verbb/comments-browser/renderer';

const controller = mountThread(element, {
    ownerUri: 'blog/hello',
    parts: { badge: { component: VerifiedBadge } },
    fields: { rating: { input: { component: StarsInput } } },
});

controller.subscribeHosts((hosts) => {
    // Each host has a stable `key`, its `kind` and `name`, the `element` to render into, the
    // `component` as given, and the `context` a function override would get.
    renderPortals(hosts);
});
```

Hosts are reported once after each draw that adds, changes or removes any, with every current host, and with none when the thread stops. A host keeps the same object, and the same `context`, until its component or data changes, so a draw that changes nothing reports nothing. `getHosts()` gives the last report, and both are bound to the controller, so React's `useSyncExternalStore(controller.subscribeHosts, controller.getHosts)` takes them as they are. The `onHosts` option of `mountThread()` hears the first report too, from the thread's first draw.

A host's `context` has `renderDefaultInto(element)` in place of `defaultRender()`, which draws Comments' own markup for the part into an element of your component's and keeps it current. For the whole `comment` part, Comments keeps its list item, the comment's form and its replies around the component, so the component draws only what goes inside.

## Overriding the Whole Comment

<!-- #region parts-whole-comment -->
A function override of the `comment` part replaces the comment's list item along with everything in it. It has to return an `<li>`, because it sits in the thread's list, and it has to draw `form()`, where **Reply** and **Edit** open their form, and `replies()`, or those stop working. Overriding the smaller parts inside a comment, such as `meta`, `content` and `actions`, keeps all of that for you, and a framework component override of `comment` draws inside Comments' own list item.
<!-- #endregion parts-whole-comment -->

## Removing Something

<!-- #region parts-removing -->
An override can return nothing, but to remove a feature, turn it off in the plugin's settings first. A setting also stops the server offering the feature, so hiding the vote buttons with an empty `actions` override would still let anyone vote through the data API, whereas setting **Interaction Mode** to **None** removes voting altogether.
<!-- #endregion parts-removing -->
