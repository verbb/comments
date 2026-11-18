# Class Names and CSS Layers

The quickest way to change how a thread looks is through its CSS custom properties, such as `--comments-color-accent` and `--comments-radius`, which the plugin's [Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming) guide covers. When a property doesn't reach what you want to change, every element the thread draws has a `comments__*` class you can style from your own CSS, Tailwind's `@apply` or a design system's selectors. This page explains which of those classes you can rely on, how to make your rules win, and how to fit Comments' styles into a site whose CSS uses cascade layers.

## Stable and Internal Classes

<!-- #region classes-stable -->
Every class the thread draws is listed in the [Markup Reference](/browser/markup-reference), each marked stable or internal.

- **Stable** classes are the parts and states a site would reasonably style, such as `comments__comment`, `comments__author-name`, `comments__content`, `comments__actions` and `comments__replies`, and state modifiers such as `comments__comment--held` and `comments__comment--highlight`. They change only in a major release, and the upgrade guide lists any that do.
- **Internal** classes are layout helpers inside a part, such as the editor frame's rows and the header's inner wrapper. They're listed so you can see them, but they can change in any release, so style the stable class around them instead.

The package's `markup/classes.json` holds the same list as data, for tooling of your own.
<!-- #endregion classes-stable -->

## Making Your Rules Win

<!-- #region classes-rules -->
Comments' own rules use one class each, plus a state modifier where it needs one, so a rule of yours wins with one more class or by loading after Comments' stylesheet.

```css
.comments .comments__author-name {
    font-weight: 600;
    text-transform: uppercase;
}
```

Controls, such as buttons and inputs, take their look from their styling classes alone, such as `comments__button--primary`. If your design system already styles buttons, putting its classes on Comments' controls with `themeConfig` is usually simpler than restyling Comments' own.

The thread's behaviour relies on its `data-comments-*` attributes rather than its classes, so restyling any element, or hiding one, can't stop anything working. Some of those attributes are stable too, such as `data-comments-state` on each comment and `data-comments-action` on each control, so your CSS and scripts can rely on them. The [Markup Reference](/browser/markup-reference#state-attributes) lists them.

```css
/* Comments waiting for approval, which only their authors and moderators see. */
.comments__comment[data-comments-state='pending'] > article {
    opacity: 0.8;
}
```
<!-- #endregion classes-rules -->

## The Three Stylesheets

<!-- #region classes-stylesheets -->
| Stylesheet | What it holds | Loaded for |
| --- | --- | --- |
| `css/comments.css` | The base and the theme together | The **Light**, **Dark** and **Auto** themes |
| `css/comments-base.css` | Only what the thread needs to work, such as visually hidden text, reserved space, collapsed replies and the editor's swap | The **None** theme |
| `css/comments-theme.css` | Only the look | A site that brings its own base |

The base rules target `data-comments-*` attributes rather than classes, so even with the **None** theme and every class restyled, the thread still works. For a design that starts from scratch, choose the `none` theme and style the stable classes yourself.
<!-- #endregion classes-stylesheets -->

## Sites with Cascade Layers

<!-- #region layers-why -->
Comments' design tokens, the `--comments-*` custom properties, sit in a cascade layer of their own, `comments-tokens`. Any declaration of them in your ordinary stylesheet wins over that layer, whatever its specificity and whichever file loads last, so overriding a token never needs `!important`.

Comments' other rules aren't in a layer, which keeps them safe from a site's broad element styles. That matters for sites whose own CSS is layered, such as anything built with Tailwind CSS, because CSS outside a layer beats CSS inside one. Tailwind's utilities and `@apply` rules would lose to Comments' rules, and Tailwind's preflight couldn't reset the thread either. The fix is to put Comments' stylesheet in a layer of its own, ordered between your base styles and your components.
<!-- #endregion layers-why -->

### When Your Build Imports the Stylesheet

<!-- #region layers-build -->
Declare the order of your layers, then import Comments' stylesheet into its layer. With Tailwind CSS 4, that's at the top of your main stylesheet:

```css
@layer theme, base, comments, components, utilities;

@import 'tailwindcss';
@import '@verbb/comments-browser/css/comments.css' layer(comments);
@import '@verbb/editor/styles' layer(comments);
```

Tailwind's preflight in `base` leaves the thread alone, and your components and utilities win over Comments' rules, so `@apply` on the stable classes works as you'd expect.

```css
@layer components {
    .comments__heading {
        @apply text-2xl font-semibold tracking-tight;
    }

    .comments__badge--verified {
        @apply bg-emerald-50 text-emerald-700;
    }
}
```

Every [starter](/starters/) imports its stylesheets this way. Editor's stylesheet, for rich comments, goes in the same layer. The [Style Comments with Tailwind or a Design System](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/style-comments-with-tailwind-or-a-design-system) user guide builds a styled thread this way step by step.
<!-- #endregion layers-build -->

### When the Plugin Loads the Stylesheet

A Craft site using the plugin's `<link>` can turn on the plugin's **CSS Layer** setting (`useCssLayers`), which loads a copy of the stylesheet wrapped in `@layer comments`. The same `@layer theme, base, comments, components, utilities;` statement in your own CSS then puts it in place. Without the setting, write your rules for the thread outside any layer, with the extra class, so they still win. The plugin's [Loading Comment Assets](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/loading-comment-assets) guide covers the setting.
