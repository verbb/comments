# Change a Part's Structure

Colours, type and class names change how the comment thread looks, but sometimes you need it to be built differently, with an extra button, an icon in a badge, or your own avatar component in place of Comments' one. For that, Comments lets you replace the markup of one **part** of the thread and keep everything else, including the theme, your button classes, the wording and every behaviour.

In this guide you'll make four changes to the thread on a long-read magazine called The Reading Room, each one a little more involved than the last:

- A friendlier message for a post that has no comments yet, in place of Comments' own.
- A pen icon in the badge Comments gives staff members, leaving every other badge as it is.
- A **Copy link** button beside each comment's actions, wrapped around Comments' own actions rather than replacing them.
- The magazine's own avatar element, written as a custom element, in place of Comments' avatar.

Along the way you'll see what each part is given to work with, and the last section explains when a part override is the wrong tool and what to reach for instead.

## What You'll Need

You'll need Comments installed and its thread on a page, output with `craft.comments.render(entry)` in the entry's template. [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) walks through that. You'll also need a JavaScript file and a stylesheet that your layout loads on that page, and a basic knowledge of JavaScript, in particular functions and template literals.

This guide uses The Reading Room's files, `web/js/comments-parts.js` and `web/css/site.css`, linked from its layout template like this:

```twig
<link rel="stylesheet" href="/css/site.css">
<script src="/js/comments-parts.js"></script>
```

Comments draws its thread with its own script, which `render()` adds to the page. You don't need to build anything or install any packages to follow along.

Before changing a part, check that a lighter change won't do. Removing something, such as voting or avatars, is a setting under **Comments** → **Settings**, which also stops the server offering it. Changing the wording is a static translation, described in [Text & Translations](docs:template-guides/text-translations). Changing the look is a token or a class name, as [Make a Custom Theme](docs:user-guides/theming-markup/make-a-custom-theme) shows. A part override is for when the markup itself has to change.

## What a Part Is

The thread is drawn in the browser from data, rather than from Twig templates, and its markup is divided into named parts. Each part is one recognisable piece of the thread, and each can be replaced on its own:

| Group | Parts |
| --- | --- |
| The thread | `header` (the heading, sort options and **Follow**), `empty` (the message when there are no comments), `showButton`, `loading` and `notice` |
| Each comment | `comment` (the whole comment), `avatar`, `authorName`, `badge`, `meta` (the date and short custom field values), `content` (the comment's text), `fields` (custom field values under the text), `actions` (votes, **Reply** and the **···** menu), `review` (a moderator's decisions) and `placeholder` |
| The comment form | `join` (the sign-in card), `postingAs` and `formFields` (the custom fields) |

The comment form itself, the comment box and the report form can't be replaced, because they carry spam protection, Editor and keyboard focus handling. You change their look with tokens, class names and `themeConfig`.

You replace a part by giving Comments an **override** for it, through `Comments.configure()`, which applies settings to every thread on the page. An override is either a function that returns the part's markup, or the name of a custom element you've registered. Whichever you use, it's given the same things to work with:

- The part's data, such as `comment` for the parts of a comment, `badge` for a badge, and `viewer` for the person reading.
- `labels`, the thread's wording, already translated into the site's language.
- `actions`, functions such as `vote()`, `reply()`, `report()` and `copyLink()`, already bound to the comment.
- `controlAttributes(name)`, the attributes Comments would put on one of its buttons or links, including your `themeConfig` classes.
- `html`, the template tag to build markup with.
- `defaultRender()`, Comments' own markup for the part, to wrap or to fall back to.

## Setting Up the Script

Comments' script runs just before the browser's `DOMContentLoaded` event, so set your overrides inside a listener for that event. Then `window.Comments` is ready, whichever order the scripts load in. Put this in `web/js/comments-parts.js`:

```js
document.addEventListener('DOMContentLoaded', () => {
    // On a page without comments, Comments' script isn't loaded.
    if (!window.Comments) {
        return;
    }

    window.Comments.configure({
        parts: {
            // Each override goes here, keyed by the part's name.
        },
    });
});
```

The rest of the guide adds overrides inside `parts`. If a thread has already been drawn when `configure()` runs, Comments draws it again with the overrides, and a thread that's still waiting to load uses them from the start.

## Replacing the Empty Message

Start with the simplest kind of override, one that doesn't use Comments' markup at all. When a post has no comments, the thread says so under the heading. The Reading Room wants its own wording, and a second line inviting the reader to start the conversation, but only when they're allowed to comment. Add this inside `parts`:

```js
empty: ({ html, viewer }) => html`
    <div class="comments__empty reading-room-empty">
        <p>No one has written in yet.</p>
        ${viewer?.canComment ? html`<p>Start the conversation below.</p>` : ''}
    </div>`,
```

The function receives the part's context, and takes the two things it needs from it. `html` is a tag for JavaScript template literals, written directly before the backtick, and it turns the template into markup Comments can draw. `viewer` describes the person reading, and `viewer.canComment` is `true` when they can post here. It can be missing while the thread is still finding out who's reading, which is why the check uses `?.`.

The outer element keeps the `comments__empty` class, so Comments' theme still spaces the message as it does its own, and adds a class of the magazine's own for its stylesheet.

Open a post with no comments and reload. The thread's heading is followed by "No one has written in yet." and, if you can comment, "Start the conversation below." Posts with comments look exactly as before.

If you'd rather your wording was translated like the rest of the thread, use `labels` instead, as in `labels.empty`. The labels are the site's translations of Comments' own wording, and [Text & Translations](docs:template-guides/text-translations) explains how to change them.

### Always Build Markup with `html`

`html` escapes anything you insert into a template with `${…}`, so text is always shown as text. That matters because much of what the thread draws was written by visitors, such as their names and comments. A commenter called `<img src=x onerror=alert(1)>` is shown with exactly that name, rather than running code on your page.

So always build an override's markup with `html`, and never assemble it as a string with `innerHTML`, or insert anything from a comment, a commenter or a custom field as HTML.

## Adding an Icon to the Staff Badge

Badges appear beside a commenter's name, such as the staff badge Comments gives members of the **Staff Badge Groups** chosen under **Comments** → **Settings** → **Appearance**. The `badge` part is drawn once for each badge, with the badge in `badge`, holding its `type` and `label`. The Reading Room wants a pen before the staff badge's label, and every other badge left alone. Add this to `parts`:

```js
badge: ({ badge, html, defaultRender }) => badge.type === 'staff'
    ? html`<span class="comments__badge comments__badge--staff">✎ ${badge.label}</span>`
    : defaultRender(),
```

For a staff badge, the function returns its own markup, keeping the classes Comments' own badge has so it keeps the theme's look. For any other badge, such as the badge on the author of the post (`owner`) or a badge your site adds, it returns `defaultRender()`, which is exactly the markup Comments would have drawn. This is the usual shape of an override that only changes some cases.

`badge.label` is the label from your settings, so if you change **Staff Badge** later, the override follows it.

Reload a post where a staff member has commented. Their badge now starts with a pen, and other badges look as they did. If there's no staff badge on the page, add your own account to a staff badge group, comment, and reload.

## Wrapping Comments' Actions with a New Button

Under each comment is a row of actions, with the votes, **Reply** and a **···** menu. **Copy link** lives in that menu, and The Reading Room's readers share comments often enough that it should be a visible button. You could rebuild the whole row, but then you'd have to draw the votes, **Reply** and the menu yourself, and keep them working. Instead, wrap Comments' row and add one button beside it. Add this to `parts`:

```js
actions: ({ html, defaultRender, actions, controlAttributes }) => {
    const button = controlAttributes('quietButton');

    return html`
        <div class="reading-room-actions">
            ${defaultRender()}
            <button type="button" class=${button.class} @click=${() => actions.copyLink()}>Copy link</button>
        </div>`;
},
```

A few things are new here.

`defaultRender()` is placed inside the new markup, rather than returned instead of it, so Comments' whole row of actions is drawn inside the wrapper, working as it always does.

`actions.copyLink()` is the same action the menu's **Copy link** uses, already bound to this comment. It copies the comment's link and shows "Link copied." at the foot of the thread. The other actions work the same way, such as `actions.reply()` to open the reply form and `actions.vote('upvote')` to vote.

`controlAttributes('quietButton')` returns the attributes Comments puts on its quiet text buttons, such as **Reply**, as an object. Using its `class` gives the new button the same look. If you've set `themeConfig` for `quietButton`, to use your design system's classes, the result includes those, so the new button follows your settings too.

`@click=${…}` adds a click listener. It's a feature of the `html` tag, and the function runs each time the button is pressed.

Comments' row and your button sit in a plain `div`, so they need a little CSS to sit side by side. Add this to `web/css/site.css`:

```css
.reading-room-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
    margin-block-start: 0.25rem;
}

.reading-room-actions .comments__actions {
    margin-block-start: 0;
}
```

Reload a post. Each comment now has **Copy link** at the end of its row of actions, in the same style as **Reply**. Press it, and "Link copied." appears at the foot of the thread. Paste the link into a new tab, and the page opens with that comment highlighted.

Comments don't show their actions while they're waiting for a moderator's decision, so a held comment has no **Copy link** either. That's Comments deciding not to draw the part at all, which an override can't change.

## Using a Custom Element for Avatars

The Reading Room's design system has its own avatar, with rounded square corners and the initials in a particular weight. Rather than rebuild it in an override function, you can register a custom element, a small class that draws itself, and give its name as the override. This suits a component your site already has, or one that keeps its own state.

A custom element override is created once for each place the part appears, and every time the part is drawn, Comments sets the same things a function would get as properties on the element, such as `comment` and `labels`. The element reacts to those properties to draw itself. Add the element's class at the top of `web/js/comments-parts.js`, outside the `DOMContentLoaded` listener:

```js
class AcmeAvatar extends HTMLElement {
    // Comments sets `comment` every time it draws the avatar.
    set comment(comment) {
        const author = comment?.author;

        if (author?.avatarUrl) {
            const photo = document.createElement('img');
            photo.src = author.avatarUrl;
            photo.alt = '';
            photo.className = 'acme-avatar__photo';
            this.replaceChildren(photo);
        } else {
            const initials = document.createElement('span');
            initials.textContent = author?.initials ?? '';
            initials.className = 'acme-avatar__initials';
            this.replaceChildren(initials);
        }
    }
}

customElements.define('acme-avatar', AcmeAvatar);
```

Then add the override to `parts`, as the element's name:

```js
avatar: 'acme-avatar',
```

Each comment's `author` has the commenter's `name`, their `initials`, and `avatarUrl`, the address of their photo or `null` when they don't have one. The element draws the photo when there is one and the initials otherwise. It sets `textContent` rather than `innerHTML`, for the same reason the `html` tag escapes what it inserts. The photo's `alt` is empty, because the commenter's name is right beside it.

Comments' own avatar takes its place in the comment's layout from its own rules, so the new element needs to be put in the same place. Each comment has a column for the avatar beside its text, and the avatar sits in the comment's author area, whose class is `comments__author`. Add this to `web/css/site.css`:

```css
.comments__author > acme-avatar {
    grid-column: 1;
    grid-row: 1 / span 2;
    display: grid;
    place-items: center;
    width: var(--comments-avatar-size);
    height: var(--comments-avatar-size);
    overflow: hidden;
    border-radius: 0.5rem;
    background: var(--comments-color-surface-muted);
    font-size: 0.8em;
    font-weight: 700;
}

.acme-avatar__photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
```

The size reads Comments' `--comments-avatar-size` token, so the column and the avatar stay the same width if you change it.

Reload a post. Each commenter's avatar is now a rounded square, showing their photo or their initials on a pale background. Inspect one, and you'll find an `<acme-avatar>` element where Comments' avatar was.

The avatar beside the comment form isn't a part, so it keeps Comments' look. To change it, style its class, or give the `avatar` control your classes with `themeConfig`.

## The Finished Script

Here's `web/js/comments-parts.js` with all four overrides:

```js
class AcmeAvatar extends HTMLElement {
    // Comments sets `comment` every time it draws the avatar.
    set comment(comment) {
        const author = comment?.author;

        if (author?.avatarUrl) {
            const photo = document.createElement('img');
            photo.src = author.avatarUrl;
            photo.alt = '';
            photo.className = 'acme-avatar__photo';
            this.replaceChildren(photo);
        } else {
            const initials = document.createElement('span');
            initials.textContent = author?.initials ?? '';
            initials.className = 'acme-avatar__initials';
            this.replaceChildren(initials);
        }
    }
}

customElements.define('acme-avatar', AcmeAvatar);

document.addEventListener('DOMContentLoaded', () => {
    // On a page without comments, Comments' script isn't loaded.
    if (!window.Comments) {
        return;
    }

    window.Comments.configure({
        parts: {
            empty: ({ html, viewer }) => html`
                <div class="comments__empty reading-room-empty">
                    <p>No one has written in yet.</p>
                    ${viewer?.canComment ? html`<p>Start the conversation below.</p>` : ''}
                </div>`,

            badge: ({ badge, html, defaultRender }) => badge.type === 'staff'
                ? html`<span class="comments__badge comments__badge--staff">✎ ${badge.label}</span>`
                : defaultRender(),

            actions: ({ html, defaultRender, actions, controlAttributes }) => {
                const button = controlAttributes('quietButton');

                return html`
                    <div class="reading-room-actions">
                        ${defaultRender()}
                        <button type="button" class=${button.class} @click=${() => actions.copyLink()}>Copy link</button>
                    </div>`;
            },

            avatar: 'acme-avatar',
        },
    });
});
```

## Testing the Overrides

Test each override in the situations where its part appears.

1. Open a post with no comments, signed out and signed in, and check the empty message. If guests can't comment on your site, a signed-out reader sees only the first line.
2. Open a post with a staff member's comment and check their badge, then check that the post author's badge and any others are unchanged.
3. Press **Copy link** under a comment and a reply, and open each link in a new tab. Check that **Reply**, voting and the **···** menu still work beside it.
4. Check avatars for someone with a photo and someone without.
5. Post a comment, then reply to it. The new comment and reply are drawn with your overrides as soon as they appear, without a reload.

If an override throws an error, Comments logs it in the browser console, draws its own markup for that part instead, and carries on, so one mistake never breaks the whole thread. If an override seems to be ignored, check the console for that message, and check that the part's name is spelt exactly as in the table above.

## Replacing a Whole Comment, and When to Stop

The `comment` part replaces everything inside a comment. It's tempting, but it's rarely the right choice. An override of the whole comment has to draw everything the comment's smaller parts would, and also call `form()`, which draws the reply or edit form when someone presses **Reply** or **Edit**, and `replies()`, which draws the comment's replies with **Hide replies** and **Load more replies**. Leave either out and those features stop working for that comment. Most changes to a comment are better made with the smaller parts, as this guide does, or with CSS on the comment's class names, which can reorder and rearrange the parts without touching the markup.

If you find yourself replacing most of the parts, or fighting the thread's structure in every override, you've reached the point where your own interface is less work. The `@verbb/comments-core` package gives your code the thread's data, its actions and spam protection, with none of Comments' markup, so you can draw the thread however you like. The React and Vue packages have hooks for the same thing. The package docs' [Core overview](https://docs.verbb.io/comments/core/index) and [React hooks](https://docs.verbb.io/comments/react/hooks) cover that approach, and the [React starter](https://docs.verbb.io/comments/starters/react) has a page built that way.

## Part Overrides in React and Vue

In a React or Vue app that draws the thread with `<CommentsThread>`, a part override is a component of your own, passed in the `parts` prop. It receives the same context as props and is rendered inside your app, so it can use your app's state, context and other components. This React component draws the staff badge from earlier:

```jsx
import { CommentsThread } from '@verbb/comments-react';

// Defined outside your page component, so React keeps the same component between renders.
function Badge({ badge }) {
    if (badge.type !== 'staff') {
        return <span className={`comments__badge comments__badge--${badge.type}`}>{badge.label}</span>;
    }

    return <span className="comments__badge comments__badge--staff">✎ {badge.label}</span>;
}

const parts = { badge: Badge };

export function Discussion({ uri }) {
    return <CommentsThread key={uri} ownerUri={uri} parts={parts} />;
}
```

A component can't return `defaultRender()`, so for the badges it doesn't change, it draws Comments' markup itself. To wrap Comments' own markup, as the actions override does, a component calls `renderDefaultInto(element)` with an element it renders, from an effect, and Comments draws its markup into that element and keeps it up to date. [Part Overrides for React](https://docs.verbb.io/comments/react/part-overrides) and [for Vue](https://docs.verbb.io/comments/vue/part-overrides) explain both, and [Comments on a Headless Site on the Same Domain](docs:user-guides/front-end-headless/comments-on-a-headless-site-on-the-same-domain) sets up a React or Vue app with a thread to try them in.
