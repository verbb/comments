# Theming

The bundled CSS draws the thread from a set of CSS custom properties, such as `--comments-color-accent` for buttons and links, and `--comments-radius` for rounded corners. Changing those properties in your own stylesheet restyles the whole thread to match your site, without copying or overriding the bundled rules. This page lists every property you can set and its default in the light and dark themes.

Start with the **Theme** setting under **Comments** → **Settings** → **Appearance**, or the [`editorTheme`](docs:get-started/configuration#editortheme) config setting, which chooses **Light**, **Dark**, **Auto** or **None**. Auto follows the visitor's colour-scheme preference. None outputs no default CSS at all, for sites that [supply their own](docs:template-guides/loading-comment-assets#supplying-your-own-assets), and the properties on this page then do nothing.

## Setting a Property

Each thread's root element has a `data-comments-root` attribute, and the bundled CSS sets the properties there. Set yours on the same element, in any stylesheet your site already loads. For example, to give the thread your brand colour and square corners, add this to your site's CSS:

```css
[data-comments-root] {
    --comments-color-accent: #c2185b;
    --comments-color-link: #c2185b;
    --comments-color-focus: #c2185b;
    --comments-radius: 0;
}
```

The Post button, links and focus rings turn pink, and the editor, buttons and menus lose their rounded corners. You don't need a more specific selector or to load your CSS after the bundled file, because the bundled properties sit in their own [cascade layer](https://developer.mozilla.org/en-US/docs/Web/CSS/@layer) named `comments-tokens`, and anything outside a layer wins over it.

A wrapper selector works too, which is useful when only some pages should change, such as `.recipe-page [data-comments-root]`. Setting the properties on `:root` or `body` doesn't reach the thread, because the thread's own root sets every property and an element's own value always beats one it would inherit.

The Editor's toolbar, menus and buttons use the same colours. Comments maps its properties onto the Editor's own `--verbb-editor-*` properties, so changing `--comments-color-accent` changes the Editor's accent as well.

### Dark and Auto Themes

A property you set on `[data-comments-root]` applies in every theme. To set a different value for the dark theme, add the theme's attribute to the selector:

```css
[data-comments-root] {
    --comments-color-accent: #c2185b;
}

[data-comments-root][data-comments-theme='dark'] {
    --comments-color-accent: #f48fb1;
    --comments-color-panel: #1a1016;
}
```

With **Auto**, the attribute stays `auto`, so pair it with a `prefers-color-scheme` media query to match the visitor's preference:

```css
@media (prefers-color-scheme: dark) {
    [data-comments-root][data-comments-theme='auto'] {
        --comments-color-accent: #f48fb1;
    }
}
```

### Sites That Use Cascade Layers

If your own CSS is in cascade layers, as Tailwind CSS 4's is, a property you set inside one of your layers loses to the `comments-tokens` layer unless that layer comes first. Name it at the start of your layer order, before anything that sets these properties:

```css
@layer comments-tokens, theme, base, components, utilities;
```

Properties you set outside any layer always win, so this only matters for declarations inside your own layers.

## Properties

These tables list every supported property, with its light and dark defaults. The Auto theme uses the dark values when the visitor prefers a dark colour scheme.

### Type

| Property | Light | Dark | What It Styles |
| --- | --- | --- | --- |
| `--comments-font-family` | `inherit` | `inherit` | The thread's font. By default, it's whatever the surrounding page uses. |
| `--comments-font-size` | `1rem` | `1rem` | The base text size. Smaller text, such as dates, is sized from it. |
| `--comments-line-height` | `1.5` | `1.5` | The base line height. |

### Colour

| Property | Light | Dark | What It Styles |
| --- | --- | --- | --- |
| `--comments-color-text` | `#1f2328` | `#eceef0` | Comment text and headings. |
| `--comments-color-muted` | `#5a6068` | `#b4b9c0` | Dates, counts, hints and quiet buttons. |
| `--comments-color-link` | `#0b57d0` | `#8ab4f8` | Links in comments and around the thread. |
| `--comments-color-border` | `#d6d9dd` | `#43484f` | The editor's frame, buttons, menus, the report dialog and the lines joining replies. |
| `--comments-color-surface` | `#fff` | `#1f2226` | The background of the editor, menus, the report dialog and a guest's sign-in choice. |
| `--comments-color-surface-muted` | `#f5f6f7` | `#2a2e33` | Hovered buttons, the sort control, a pressed **Follow** button and the quoted comment in the report dialog. |
| `--comments-color-panel` | | `#17191c` | The background behind the whole thread in the dark theme, which also gets padding and rounded corners so its light text never sits on your page's background. It has no effect in the light theme. |
| `--comments-color-accent` | `#0b57d0` | `#8ab4f8` | The Post button, selected and pressed controls, and the character count. |
| `--comments-color-on-accent` | `#fff` | `#10223f` | Text on the accent colour, such as the Post button's label. |
| `--comments-color-danger` | `#b3261e` | `#f2b8b5` | Errors and destructive actions. |
| `--comments-color-success` | `#146c2e` | `#7dd99b` | Confirmations. |
| `--comments-color-warning` | `#a85d00` | `#a85d00` | The character count as a comment nears the limit. |
| `--comments-color-focus` | `#0b57d0` | `#8ab4f8` | Focus outlines for keyboard users. |
| `--comments-color-scrim` | `rgb(0 0 0 / 45%)` | `rgb(0 0 0 / 45%)` | The shade behind the report dialog. |
| `--comments-color-like` | `#c4314b` | `#ff8a9e` | A liked comment's heart. |

### Spacing and Size

| Property | Light | Dark | What It Styles |
| --- | --- | --- | --- |
| `--comments-space-1` | `0.25rem` | `0.25rem` | The smallest gap, such as between an icon and its label. |
| `--comments-space-2` | `0.5rem` | `0.5rem` | Gaps between controls. |
| `--comments-space-3` | `0.75rem` | `0.75rem` | Padding inside the editor and between a comment's parts. |
| `--comments-space-4` | `1rem` | `1rem` | Gaps between comments and sections, and the dark panel's padding. |
| `--comments-space-5` | `1.5rem` | `1.5rem` | The largest step in the scale, for your own rules to use alongside the others. |
| `--comments-radius` | `0.5rem` | `0.5rem` | Rounded corners on the editor, buttons, menus, dialogs and the dark panel. |
| `--comments-avatar-size` | `2.25rem` | `2.25rem` | Avatars, and the column they sit in. |
| `--comments-thread-indent` | `0.875rem` | `0.875rem` | How far each level of replies is indented. It's `0.75rem` on screens narrower than `40rem`. |
| `--comments-control-size` | `2.25rem` | `2.25rem` | The smallest height of buttons and other controls. It's `2.75rem` on touch screens, so they're easier to tap. |

### Avatars

People without a photo get their initials on one of eight colours, picked from their name so the same person always gets the same one.

| Property | Light | Dark | What It Styles |
| --- | --- | --- | --- |
| `--comments-avatar-0` | `#3b6ea5` | `#3b6ea5` | The first initials colour. |
| `--comments-avatar-1` | `#8a5a2b` | `#8a5a2b` | The second initials colour. |
| `--comments-avatar-2` | `#2f7d4f` | `#2f7d4f` | The third initials colour. |
| `--comments-avatar-3` | `#8a3f7d` | `#8a3f7d` | The fourth initials colour. |
| `--comments-avatar-4` | `#b0532a` | `#b0532a` | The fifth initials colour. |
| `--comments-avatar-5` | `#237a7a` | `#237a7a` | The sixth initials colour. |
| `--comments-avatar-6` | `#5a4fa3` | `#5a4fa3` | The seventh initials colour. |
| `--comments-avatar-7` | `#6b7a2a` | `#6b7a2a` | The eighth initials colour. |
| `--comments-color-on-avatar` | `#fff` | `#fff` | The initials themselves. |

### Held and Reported Comments

Only moderators see these. A held comment is waiting for approval or was caught as spam, and a reported comment has an open report.

| Property | Light | Dark | What It Styles |
| --- | --- | --- | --- |
| `--comments-color-held-surface` | `#fff8eb` | `#33291a` | A held comment's background, and the moderator bar's. |
| `--comments-color-held-border` | `#f2d39b` | `#6b5428` | A held comment's border, and the buttons on it. |
| `--comments-color-held-text` | `#8a5300` | `#f3c77a` | The label saying why a comment is held, and the moderator bar's text. |
| `--comments-color-reported-surface` | `#fff1f0` | `#3a2422` | A reported comment's background. |
| `--comments-color-reported-border` | `#f5c2bd` | `#74403b` | A reported comment's border, and the buttons on it. |
| `--comments-color-reported-text` | `#a8322a` | `#f2a8a1` | The label saying what a comment was reported for. |

### Messages

Confirmations and errors, such as "Comment posted.", appear as a small message at the bottom of the thread.

| Property | Light | Dark | What It Styles |
| --- | --- | --- | --- |
| `--comments-color-toast` | `#1f2328` | `#eceef0` | The message's background. |
| `--comments-color-on-toast` | `#fff` | `#17191c` | The message's text. |
| `--comments-color-toast-success` | `#7dd99b` | `#146c2e` | The tick beside a confirmation. |
| `--comments-color-toast-danger` | `#ff9a93` | `#b3261e` | The icon beside an error. |

### Internal Properties

The bundled CSS also sets `--comments-tint-border` on held and reported comments, and `--comments-toast-icon` on the message, to share a value between its own rules. They aren't part of the supported set, so they can change in any release. Use the properties above instead.

## Changing Individual Elements

When a property doesn't reach what you want to change, target the `comments__*` classes in your own stylesheet. The theme is on the root element as `data-comments-theme`, holding `light`, `dark`, `auto` or `none`, so a rule can follow it:

```css
[data-comments-root][data-comments-theme='dark'] .comments__author-name {
    color: #fff;
}
```

Bundled rules use ordinary specificity, so a rule of yours needs at least the same specificity, or to load after the bundled CSS, to win. [Custom Templates](docs:template-guides/custom-templates) lists the classes and the markup each template outputs.
