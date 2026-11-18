# Part Overrides with Custom Elements

A part override can be the name of a custom element you've registered, rather than a function. That suits a site that already builds its interface from custom elements, or a part that needs its own state and behaviour, such as a badge with a tooltip. Comments draws your element in the part's place and keeps it updated with the part's data, and everything else about the thread stays as it was.

## A Verified Badge

Suppose some commenters carry a `verified` badge, which a site adds on the server with the plugin's `Comments::EVENT_DEFINE_BADGES` event, and you want a tick and a tooltip on it. This element draws every badge, keeping Comments' own badge classes so its stylesheet still styles them:

```js
class CommentBadge extends HTMLElement {
    // Comments sets each of the part's context keys as a property, every time the part is drawn.
    set badge(badge) {
        const verified = badge.type === 'verified';

        this.className = `comments__badge comments__badge--${badge.type.replace(/[^\w-]/g, '')}`;
        this.textContent = verified ? `✓ ${badge.label}` : badge.label;
        this.title = verified ? 'This commenter has bought from us.' : '';
    }
}

customElements.define('acme-comment-badge', CommentBadge);
```

```js
document.querySelector('verbb-comments').parts = { badge: 'acme-comment-badge' };
```

Comments creates the element once for each badge and, each time the badge is drawn, sets every key of the part's context on it as a property, such as `badge`, `comment`, `viewer`, `labels`, `actions`, `controlAttributes`, `html` and `defaultRender`. A setter, as above, or a library such as Lit that turns properties into renders, lets the element follow along. The element sits in the thread's light DOM, so Comments' stylesheet and yours reach it. Draw any text from a comment or a commenter as text, as `textContent` does, never as HTML.

## Simpler Overrides as Functions

A custom element is worth registering when the part has state or behaviour of its own. For markup alone, a function is shorter and needs no registration:

```js
document.querySelector('verbb-comments').parts = {
    badge: ({ badge, html, defaultRender }) => badge.type === 'verified'
        ? html`<span class="badge badge-success">✓ ${badge.label}</span>`
        : defaultRender(),
};
```

## What an Override Is Given

A function is given the part's context as its argument, and a custom element gets each key as a property, with the part's own data from the [table](#the-parts) below.

<!--@include: ../browser/part-overrides.md#parts-context-->

<!--@include: ../browser/part-overrides.md#parts-throws-->

## Overriding the Whole Comment

<!--@include: ../browser/part-overrides.md#parts-whole-comment-->

## The Parts {#the-parts}

<!--@include: ../_generated/parts.md-->

The comment form itself, the comment box, the rich text editor and the report form can't be overridden, because they carry spam protection, the editor's loading and keyboard focus. Their look is reached through [styling](/web-components/styling), and custom fields on the form and the comment through [field overrides](/web-components/field-overrides).

## Removing Something

<!--@include: ../browser/part-overrides.md#parts-removing-->

## In Twig

A thread Craft renders can take custom elements for its field overrides through `craft.comments.render()`, because the mount point carries their names as data. Part overrides, which can be functions, are given in JavaScript through `Comments.configure()`, with the element's name in place of a function:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({ parts: { badge: 'acme-comment-badge' } });
});
```
