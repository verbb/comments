# Part Overrides with Components

A part override replaces the markup of one part of the thread, such as the name of a comment's author, its badges or the empty state, and keeps everything else. In React, the override is a component. It's rendered through a portal into the place the renderer leaves for it, so it stays inside your app's tree, with your context, state and router, and it follows its comment when a vote or a reply redraws the thread.

## A Verified Badge

Suppose some commenters carry a `verified` badge, which your site adds on the server with the plugin's `Comments::EVENT_DEFINE_BADGES` event. This component draws it with a tick, and keeps Comments' own badge classes for every other badge so its stylesheet still styles them:

```tsx
import { CommentsThread, type CommentsPartOverrides, type CommentsPartProps } from '@verbb/comments-react';

type Badge = { type: string; label: string };

function VerifiedBadge(props: CommentsPartProps) {
    const badge = props.badge as Badge;

    if (badge.type !== 'verified') {
        return <span className={`comments__badge comments__badge--${badge.type.replace(/[^\w-]/g, '')}`}>{badge.label}</span>;
    }

    return <span className="badge badge-success">✓ {badge.label}</span>;
}

// Defined once, outside the component that renders the thread.
const parts: CommentsPartOverrides = { badge: VerifiedBadge };

export function Comments({ uri }: { uri: string }) {
    return <CommentsThread key={uri} ownerUri={uri} parts={parts} />;
}
```

The [React starter](/starters/react) draws its badges with this component.

## What a Component Is Given

A component gets the part's context as props, typed as `CommentsPartProps`.

| Prop | What it is |
| --- | --- |
| The part's data | Such as `comment`, `viewer`, `permissions` and `conversation`, or `badge` for a badge. The [table](#the-parts) lists each part's. |
| `labels` | The thread's translated wording. |
| `actions` | For a comment's parts, `vote()`, `reply()`, `edit()`, `delete()`, `report()`, `copyLink()`, `moderate()`, `toggleReplies()` and `loadReplies()`, bound to the comment. |
| `controlAttributes(name)` | The attributes of one of the ten controls with your `themeConfig` applied. It returns HTML attribute names, so use its `class` as `className`. |
| `renderDefaultInto(element)` | Draws Comments' own markup for the part into an element of your component's. |

A part's own keys, such as `badge`, are typed `unknown`, because each part has its own, so give them their type as the example does. `BadgeData` and the other data types come from `@verbb/comments-core`.

```tsx
import type { CommentsPartProps } from '@verbb/comments-react';
import type { CommentData } from '@verbb/comments-core';

// A Reply button of your own that looks like Comments' other text actions.
function Actions({ comment, actions, permissions, controlAttributes }: CommentsPartProps) {
    const { class: className, ...attributes } = controlAttributes('quietButton');

    return (
        <div className="comments__actions">
            {permissions?.canReply ? <button type="button" className={String(className)} {...attributes} onClick={() => actions.reply?.()}>Reply to {(comment as CommentData).author?.name}</button> : null}
        </div>
    );
}
```

## Wrapping Comments' Own Markup

`renderDefaultInto(element)` draws Comments' own markup for the part into an element of your component's, for a component that adds something around it. Call it from an effect, again whenever the props change, to keep it current.

```tsx
import { useEffect, useRef } from 'react';
import type { CommentsPartProps } from '@verbb/comments-react';

function HighlightedContent(props: CommentsPartProps) {
    const inner = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (inner.current) props.renderDefaultInto(inner.current);
    });

    return <div className="content-card" ref={inner} />;
}
```

For the whole `comment` part, Comments keeps its list item, the comment's form and its replies around your component, so the component draws only what goes inside, and `renderDefaultInto()` draws Comments' own author, text, fields and actions.

## Defining Components {#defining-components}

Define override components outside your own component, as above, or memoise them. A component written inline is a new component on every render, so React would mount it afresh each time and lose its state. The `parts` object itself can be written inline, because the thread compares what it holds.

If a component throws, the error is logged and Comments' own markup is drawn in its place, so the rest of your app keeps working.

## Overrides Without React

For static markup that needs no React at all, `templatePart()` passes a function to the renderer instead, which is cheaper than a portal. It's given the same context as a [function override](/browser/part-overrides#a-function), with lit-html's `html` tag.

```tsx
import type { BadgeData } from '@verbb/comments-core';
import { templatePart } from '@verbb/comments-react';
import { html } from 'lit-html';

const parts = {
    badge: templatePart(({ badge, defaultRender }) => {
        const { type, label } = badge as BadgeData;

        return type === 'verified' ? html`<span class="badge">✓ ${label}</span>` : defaultRender();
    }),
};
```

A registered custom element's name works too, as [Part Overrides with Custom Elements](/web-components/part-overrides) describes.

## The Parts {#the-parts}

<!--@include: ../_generated/parts.md-->

The comment form itself, the comment box, the rich text editor and the report form can't be overridden, because they carry spam protection, the editor's loading and keyboard focus. Their look is reached through [styling](/react/styling), and custom fields on the form and the comment have components of their own, by handle, as [Field Overrides with Components](/react/field-overrides) describes.

## Removing Something

<!--@include: ../browser/part-overrides.md#parts-removing-->

In React, a component that returns `null` draws nothing in the part's place.
