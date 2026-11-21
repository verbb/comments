# Make a Custom Theme

Comments draws its thread with a neutral look that fits most sites well enough, but you'll usually want it to look like it belongs to yours, with your colours, your type and your corners. In this guide you'll restyle the whole thread to match a site's brand by changing a set of design tokens in your own stylesheet, add a few finer touches through the thread's class names, then give it a dark version that follows the site's own dark mode toggle. You won't touch any of Comments' files or markup, and every part of the thread keeps working as it did.

The example throughout is a blog for a native plant nursery called Wattle & Gum. Its brand is a deep eucalyptus green on warm off-white paper, with a serif typeface and soft, rounded corners. By the end, the comment thread on each blog post uses those colours for its buttons, links, names and focus rings, sets its text in the same serif as the articles, draws commenters' initials on the nursery's own palette of muted greens and browns, and switches to a dark green version when a reader turns on the site's dark mode.

The last section shows the other end of the scale, where you start from Comments' **None** theme and write the look yourself.

## What You'll Need

You'll need Comments installed, with its thread showing on a page of your site. If you haven't done that yet, [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) walks through it. This guide assumes the blog's entry template outputs the thread with:

```twig
{{ craft.comments.render(entry) }}
```

You'll also need a stylesheet that your site already loads on that page, and a way to edit it. Most sites have one, such as `web/css/site.css` linked from the layout template, or a file your front-end build compiles. Every snippet in this guide goes into that stylesheet.

Leave **Theme** under **Comments** → **Settings** → **Appearance** set to **Light**, which is the default. The dark mode section changes how it's used, and the last section switches it to **None**.

It helps to know how to open your browser's developer tools and inspect an element on the page, because that's the quickest way to see which tokens and classes an element uses. You don't need to know anything about how Comments draws the thread.

## How the Thread Gets Its Look

When a visitor opens the page, `render()` puts a small placeholder in it, and Comments' script draws the thread inside that placeholder. Inspect the thread in your browser and you'll find its outer element, which looks something like this:

```html
<section id="comments-109-1" class="comments" data-comments-root data-comments-theme="light" …>
```

Two things on that element matter for theming. The `data-comments-root` attribute marks the thread's root, and `data-comments-theme` says which theme it's using, which is `light`, `dark`, `auto` or `none`.

Comments' stylesheet takes its colours, its fonts and most of its sizes from **design tokens**. A design token is a CSS custom property whose name starts with `--comments-`, such as `--comments-color-accent` for the colour of the main buttons and selected controls, or `--comments-radius` for rounded corners. The tokens are set on the root element, and everything inside the thread reads them from there. Changing one token changes every place that uses it, so a handful of lines in your own stylesheet restyles the whole thread, including parts you might not have seen yet, such as the report dialog or the form a guest fills in.

You set your own values on the same root element, with the `[data-comments-root]` selector. You don't need a more specific selector, or to load your stylesheet after Comments' one, because Comments keeps its default token values in a separate [cascade layer](https://developer.mozilla.org/en-US/docs/Web/CSS/@layer) called `comments-tokens`, and any value outside a layer wins over a value inside one.

One thing that doesn't work is setting the tokens on `:root` or `body`. The thread's root sets every token itself, and an element's own value always beats one it would inherit from further up the page, so tokens set higher up never reach the thread.

When a token doesn't cover what you want to change, every element in the thread also has a class name starting with `comments__`, such as `comments__author-name` for a commenter's name. These class names stay the same between releases, so they're safe to write rules against. You'll use a few of them later in the guide.

[Theming](docs:template-guides/theming) lists every token with its light and dark defaults, and is worth keeping open in another tab while you work.

## Setting the Brand Colours

Start with colour, because it makes the biggest difference. Add this to your stylesheet:

```css
/* Wattle & Gum's colours for the comment thread. */
[data-comments-root] {
    --comments-color-text: #1d2b26;
    --comments-color-muted: #56645e;
    --comments-color-accent: #2f5d50;
    --comments-color-on-accent: #ffffff;
    --comments-color-link: #2f5d50;
    --comments-color-focus: #2f5d50;
    --comments-color-border: #dde3dc;
    --comments-color-control-border: #87938d;
    --comments-color-surface: #fffdf8;
    --comments-color-surface-muted: #f2eee3;
}
```

Here's what each line changes:

- `--comments-color-text` is the colour of comment text and headings, a very dark green here rather than black, to sit comfortably on the warm background.
- `--comments-color-muted` is the quieter text, such as dates, counts and the text buttons under each comment, like **Reply**.
- `--comments-color-accent` is the brand colour. It fills the **Post** and **Sign in** buttons and marks selected controls, such as the chosen sort order.
- `--comments-color-on-accent` is the text on top of the accent colour, such as the **Post** button's label. Pick it so it's easy to read against the accent.
- `--comments-color-link` colours links in comments and around the thread.
- `--comments-color-focus` is the outline keyboard users see on the control they've moved to. It's the brand green here so it suits the site, but it must stay clearly visible against the background.
- `--comments-color-border` draws light lines, such as the line joining replies to their comment, and the edges of buttons and menus.
- `--comments-color-control-border` is for borders that are a control's only edge, such as the frame around the comment box. It's darker than the ordinary border so people can see where to type.
- `--comments-color-surface` is the background of the comment box, menus and the report dialog, and `--comments-color-surface-muted` is the background of hovered buttons and the sort control.

Save the stylesheet and reload a blog post. When you scroll down to the comments, the **Sign in** button on the **Join the conversation** card is green, the chosen sort order sits on a pale cream pill, and the dates and **Reply** buttons are a soft grey-green. Press **Tab** a few times to move through the thread, and you'll see the green focus outline move with you.

If nothing changes, inspect the thread and look at the root element's styles. Your rule should be listed with the `[data-comments-root]` selector. If it's missing, the stylesheet you edited isn't loading on this page, or your browser is showing a cached copy of it. If it's there but crossed out, check that your selector is exactly `[data-comments-root]` and that the rule isn't inside an `@layer` block of your own, which [Sites That Use Cascade Layers](#sites-that-use-cascade-layers) below explains.

## Setting the Type

The thread uses the font of whatever surrounds it, so it often matches your site already. Wattle & Gum sets its articles in a serif, but its sidebar and footer use a sans-serif that the thread picked up from the page. Add these lines inside the same `[data-comments-root]` rule:

```css
    --comments-font-family: Georgia, "Times New Roman", serif;
    --comments-font-size: 1.0625rem;
    --comments-line-height: 1.6;
```

`--comments-font-family` takes any font stack, the same as the `font-family` property. Use a font your site already loads, because Comments doesn't load fonts itself. `--comments-font-size` is the base size of comment text, and the smaller text in the thread, such as dates and badges, is sized from it, so everything grows or shrinks together. `--comments-line-height` sets the spacing between lines of text.

Reload the page. The comments are now in the same serif as the article above them, slightly larger, with a little more space between lines, which makes longer comments easier to read.

## Adjusting Spacing, Corners and Sizes

Next, give the thread the nursery's softer shape. Add these lines inside the same rule:

```css
    --comments-radius: 0.875rem;
    --comments-space-4: 1.25rem;
    --comments-avatar-size: 2.75rem;
    --comments-thread-indent: 1.25rem;
```

`--comments-radius` rounds the corners of buttons, the comment box, menus and dialogs. `--comments-space-4` is one step in a scale of five spacing tokens, from `--comments-space-1` for the smallest gaps, such as between an icon and its label, to `--comments-space-5`. Step 4 is the gap between the thread's sections, such as the heading, the comment form and the list of comments, and between a comment's replies, so raising it gives the thread more room to breathe. `--comments-avatar-size` sets how large avatars are, and because each comment lines its text up beside the avatar, the text column moves with it. `--comments-thread-indent` is how far each level of replies is indented from the comment it answers.

Reload the page again. The buttons and the sign-in card have rounder corners, there's more space between the thread's sections and between replies, the avatars are larger, and replies sit a little further in.

Change one spacing token at a time and check the result before moving on. The scale is shared by many parts of the thread, so a large jump in one step can make some areas feel cramped and others loose.

## Giving Avatars the Brand's Colours

People who haven't uploaded a photo get an avatar with their initials on a coloured background. Comments picks one of eight colours from the person's name, so the same person always gets the same colour. Those eight colours are tokens too, `--comments-color-avatar-0` to `--comments-color-avatar-7`, along with `--comments-color-on-avatar` for the initials themselves. Add the nursery's palette to the rule:

```css
    --comments-color-avatar-0: #2f5d50;
    --comments-color-avatar-1: #7a5c1e;
    --comments-color-avatar-2: #44663a;
    --comments-color-avatar-3: #8a4b3c;
    --comments-color-avatar-4: #4a5f7a;
    --comments-color-avatar-5: #6b4f7a;
    --comments-color-avatar-6: #2e6b6b;
    --comments-color-avatar-7: #6e6a2c;
```

Each colour needs to be dark enough for white initials to be read on it, unless you also change `--comments-color-on-avatar`. Reload the page, and the initials avatars now use the muted greens, browns and blues above.

## Finer Touches with Class Names

Tokens cover the thread's colours, type and spacing, but not every detail. Avatars are always round, for example, and a commenter's name uses the ordinary text colour. Wattle & Gum wants slightly rounded square avatars, names in the brand green, and a gold badge for its staff. For changes like these, write rules against the thread's class names. Add these rules to your stylesheet, after the token rule:

```css
.comments .comments__avatar {
    border-radius: 0.625rem;
}

.comments .comments__author-name {
    color: var(--comments-color-accent);
}

.comments .comments__badge--staff {
    background: #f6e7b4;
    color: #5c4300;
}
```

Each rule starts with `.comments`, the class on the thread's root, followed by the class of the element to change. Comments' own rules use a single class, so this one extra class is enough for your rule to win, wherever your stylesheet loads. Without it, your rule would only win if your stylesheet happened to load after Comments' one.

The first rule squares off the avatars, both photos and initials. The second colours commenters' names, and reads the colour from `--comments-color-accent` rather than repeating the hex value, so it follows any later change to the accent, including the dark version you'll add next. The third styles the badge shown beside staff members' names. Every badge has the class `comments__badge` and a second class naming its type, such as `comments__badge--staff` for the staff badge and `comments__badge--owner` for the badge on the author of the entry, so you can style each kind separately.

Reload the page. The avatars have rounded corners instead of being circles, names are green, and a staff member's badge is gold. If you don't see a staff badge, it's because nobody on this post is in one of the **Staff Badge Groups** under **Comments** → **Settings** → **Appearance**.

To find the class of anything else you'd like to change, inspect it in your browser. The classes that start with `comments__` are the ones to use. Avoid styling the thread through its `data-comments-*` attributes, apart from the root's `data-comments-root` and `data-comments-theme`, because most of them describe the thread's behaviour rather than its look.

## The Light Theme So Far

Here's the whole stylesheet so far, in one place:

```css
/* Wattle & Gum's look for the comment thread. */
[data-comments-root] {
    --comments-color-text: #1d2b26;
    --comments-color-muted: #56645e;
    --comments-color-accent: #2f5d50;
    --comments-color-on-accent: #ffffff;
    --comments-color-link: #2f5d50;
    --comments-color-focus: #2f5d50;
    --comments-color-border: #dde3dc;
    --comments-color-control-border: #87938d;
    --comments-color-surface: #fffdf8;
    --comments-color-surface-muted: #f2eee3;

    --comments-font-family: Georgia, "Times New Roman", serif;
    --comments-font-size: 1.0625rem;
    --comments-line-height: 1.6;

    --comments-radius: 0.875rem;
    --comments-space-4: 1.25rem;
    --comments-avatar-size: 2.75rem;
    --comments-thread-indent: 1.25rem;

    --comments-color-avatar-0: #2f5d50;
    --comments-color-avatar-1: #7a5c1e;
    --comments-color-avatar-2: #44663a;
    --comments-color-avatar-3: #8a4b3c;
    --comments-color-avatar-4: #4a5f7a;
    --comments-color-avatar-5: #6b4f7a;
    --comments-color-avatar-6: #2e6b6b;
    --comments-color-avatar-7: #6e6a2c;
}

.comments .comments__avatar {
    border-radius: 0.625rem;
}

.comments .comments__author-name {
    color: var(--comments-color-accent);
}

.comments .comments__badge--staff {
    background: #f6e7b4;
    color: #5c4300;
}
```

## Adding a Dark Version

Wattle & Gum has a dark mode toggle in its header. When a reader turns it on, the site's own script adds a `dark` class to the `<html>` element, and the site's stylesheet switches to dark colours. The comment thread should follow it.

Comments has a dark theme of its own, with light text on a dark panel. There are two parts to using it with your brand. You tell Comments when to switch, and you give the dark theme your own colours.

### Telling Comments When to Switch

The `darkSelector` setting takes a CSS selector. While that selector matches the `<html>` element, every thread on the page uses the dark theme, and when it stops matching they go back to light. Comments watches for the change, so the thread switches the moment the toggle is pressed, without a reload.

`darkSelector` is set from JavaScript with `Comments.configure()`. Add this to a script that's on every page with comments, such as the site's main script:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments?.configure({ darkSelector: 'html.dark' });
});
```

Comments' script runs just before the browser's `DOMContentLoaded` event, so `window.Comments` is ready inside the listener whichever order the scripts load in. The `?.` skips the call on pages without comments, where Comments' script isn't loaded.

Reload a blog post and turn on dark mode with the toggle. Inspect the thread's root, and its `data-comments-theme` attribute now says `dark`. Turn dark mode off and it changes back to `light`.

### Choosing the Dark Colours

At this point the thread probably looks wrong in dark mode, with dark text on a dark background. That's because the tokens you set on `[data-comments-root]` apply in every theme, and your values win over the dark theme's own. Your very dark green `--comments-color-text`, which was right for the light theme, is now drawn on the dark panel.

The fix is to give each colour you changed a dark value as well. Add a rule for the dark theme, after the first one:

```css
[data-comments-root][data-comments-theme='dark'] {
    --comments-color-text: #e7eee9;
    --comments-color-muted: #a8b7af;
    --comments-color-accent: #8cc7a8;
    --comments-color-on-accent: #10231a;
    --comments-color-link: #8cc7a8;
    --comments-color-focus: #8cc7a8;
    --comments-color-border: #35443d;
    --comments-color-control-border: #63736b;
    --comments-color-surface: #1b2521;
    --comments-color-surface-muted: #25322c;
    --comments-color-panel: #131b18;
}
```

The selector adds the theme attribute, so this rule only applies while the thread is dark, and because it's more specific than the first rule, its values win there. The accent becomes a lighter green, because the deep green that works on off-white is too dark to read on a dark background, and `--comments-color-on-accent` becomes a dark green so the **Post** button's label stays readable on the lighter accent.

The new token is `--comments-color-panel`, which only the dark theme uses. In the dark theme the thread sits on a panel of its own, with padding and rounded corners, so its light text never ends up on a light part of your page. Here it's a very dark green to match the site's dark background.

You only need dark values for the colours you changed. Anything you left alone, such as the error red or the colours moderators see on held comments, keeps Comments' own dark value. The type, spacing and avatar tokens don't need dark versions either, because they're the same in both themes.

Reload the page and turn on dark mode. The thread now sits on a dark green panel, with pale text, light green buttons and names, and the same serif type, corners and spacing as the light version. Your class rules carry over too. Names still read `--comments-color-accent`, so they turn light green with it.

If the site's whole page goes dark with the toggle, you might not want the thread on a separate panel at all. To blend it into the page, make the panel transparent and remove its padding:

```css
[data-comments-root][data-comments-theme='dark'] {
    --comments-color-panel: transparent;
}

.comments[data-comments-root][data-comments-theme='dark'] {
    padding: 0;
}
```

The second rule has one more class than Comments' own rule for the panel, so it wins. Only do this when your page's dark background is always behind the thread.

### Following the Visitor's System Setting Instead

Some sites don't have a toggle, and follow the dark mode setting of the visitor's device instead. For that, leave `darkSelector` out and set **Theme** under **Comments** → **Settings** → **Appearance** to **Auto**. The thread's `data-comments-theme` is then always `auto`, and Comments uses its dark colours when the visitor's device prefers dark mode.

Your dark values then go inside a `prefers-color-scheme` media query, against the `auto` theme:

```css
@media (prefers-color-scheme: dark) {
    [data-comments-root][data-comments-theme='auto'] {
        --comments-color-text: #e7eee9;
        /* …and the rest of the dark values from above. */
    }
}
```

To check it, switch your operating system to dark mode, or use the rendering tools in your browser's developer tools to emulate a dark colour scheme, and reload.

## Testing the Theme

Work through the thread as each kind of visitor would see it, because some parts only appear in certain situations. Test in a private window, so you see what a signed-out visitor sees, and in your normal window signed in.

1. Signed out, scroll to the comments and check the **Join the conversation** card. If guests can comment on your site, press **Comment as a guest** and check the name and email fields, and the comment box with its frame.
2. Signed in, click into the comment box, write a comment and post it. Check the **Post** button while it's working, and the message at the foot of the thread that says the comment was posted. That message has its own tokens, listed under Messages in [Theming](docs:template-guides/theming).
3. Press **Reply** on a comment and check the reply form. Open the **···** menu on a comment and choose **Report** to see the report dialog, then cancel it.
4. Move through the thread with **Tab** and check that the focus outline is always clearly visible.
5. Turn on dark mode and repeat the first four checks.
6. If you moderate comments, check a held or reported comment while signed in as a moderator. They have their own tinted colours, which your theme leaves as Comments' defaults unless you set the held and reported tokens.

Check your colour pairs for contrast too, especially text on the surface and panel colours, and the label on the accent colour. Most browsers' developer tools show a contrast ratio when you inspect text, and the WCAG guideline is at least 4.5:1 for ordinary text.

## Starting from the None Theme

Tokens are the quickest way to a branded thread, and most sites never need anything else. Sometimes, though, a site's design is far enough from Comments' that overriding the theme would mean fighting it rule by rule. For that, set **Theme** under **Comments** → **Settings** → **Appearance** to **None**, or set [`theme`](docs:get-started/configuration#theme) to `'none'` in `config/comments.php`.

With **None**, Comments loads only its base stylesheet in place of the full one. The base styles are the few rules the thread needs to work, such as hiding the spam honeypot field and text meant only for screen readers, keeping hidden elements hidden, reserving space for the thread while it loads, and showing a spinner on a button that's waiting. Everything else, from colours and type to the layout of each comment, is yours to write. The tokens do nothing in this theme, because the rules that read them aren't loaded, so you style the thread through its class names alone.

Reload a page with comments after switching, and you'll see the thread with almost no styling, in your page's own fonts and colours, with plain browser buttons and inputs. A good way to begin is to lay out a comment, then style the controls. This example gives each comment an avatar column and styles the buttons:

```css
.comments {
    display: grid;
    gap: 1.5rem;
}

.comments__list,
.comments__replies-list {
    display: grid;
    gap: 1.25rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.comments__comment > article {
    display: grid;
    grid-template-columns: 2.75rem 1fr;
    column-gap: 0.75rem;
}

.comments__comment > article > * {
    grid-column: 2;
}

.comments__author {
    display: contents;
}

.comments__avatar {
    grid-column: 1;
    grid-row: 1 / span 2;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.625rem;
    object-fit: cover;
}

.comments__avatar--initials {
    display: grid;
    place-items: center;
    background: #2f5d50;
    color: #ffffff;
    font-size: 0.875em;
}

.comments__author-name {
    font-weight: 700;
    color: #2f5d50;
}

.comments__date {
    color: #56645e;
    font-size: 0.875em;
}

.comments__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

.comments__replies {
    margin-top: 1rem;
    padding-left: 1.25rem;
    border-left: 2px solid #dde3dc;
}

.comments__button {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.375rem 0.875rem;
    border: 1px solid #87938d;
    border-radius: 0.875rem;
    background: #fffdf8;
    font: inherit;
}

.comments__button--primary {
    border-color: #2f5d50;
    background: #2f5d50;
    color: #ffffff;
}
```

Reload the page. Each comment now has its avatar in a column of its own, with the name, date, text and actions lined up beside it, replies indented behind a line, and the vote, **Reply** and **···** buttons in a row with rounded borders. The sign-in card, the comment box and the menus still look plain, and that's where you carry on.

Build the rest up one area at a time, in the same order as the testing steps above, because each part of the thread has its own classes to style, such as the comment box (`comments__frame`), the sign-in card (`comments__join`) and the **···** menu (`comments__menu`). The package docs' [markup reference](https://docs.verbb.io/comments/browser/markup-reference) lists every class with the element it's on, and marks each one **stable** or **internal**. Style the stable ones. Internal classes are layout helpers inside a part, which can change in any release.

The None theme takes much more work than tokens, so it's worth trying tokens and a few class rules first, and only moving to None once you find yourself undoing most of the default look.

## Sites That Use Cascade Layers

If your site's CSS is built with Tailwind CSS 4, or otherwise organises its rules into cascade layers, a token you set inside one of your own layers loses to Comments' `comments-tokens` layer, unless `comments-tokens` comes first in your layer order. Either set the tokens outside any layer, as this guide does, or name `comments-tokens` at the start of your layer order:

```css
@layer comments-tokens, theme, base, components, utilities;
```

Tailwind sites usually want their utility classes and `@apply` rules to win over Comments' class rules too, which takes one more setting. [Style Comments with Tailwind or a Design System](docs:user-guides/theming-markup/style-comments-with-tailwind-or-a-design-system) covers that, along with putting your design system's button and input classes on Comments' controls.
