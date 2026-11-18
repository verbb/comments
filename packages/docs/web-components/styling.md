# Styling

A thread draws with Comments' own stylesheet, which works as it is and suits most sites with a few changes. `<verbb-comments>` draws in the light DOM, so your page's CSS reaches the thread as usual. Work through the options in order and stop at the first that does what you need. Design tokens change colours, sizes and spacing. `themeConfig` puts your design system's classes on Comments' buttons and inputs. Your own CSS reaches anything else through stable class names.

## Importing the Stylesheets

Import the stylesheets once, from your site's entry script or main stylesheet. Editor's stylesheet is for rich comments.

```js
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';
```

The browser package has three stylesheets, so you can choose how much of Comments' look you start from.

<!--@include: ../browser/class-names-css-layers.md#classes-stylesheets-->

The `theme` attribute decides which theme a thread draws in, as [Dark Mode](#dark-mode) describes. With `theme="none"`, import `comments-base.css` in place of `comments.css`.

## Tokens First

The thread's colours, radii, spacing and type are CSS custom properties, such as `--comments-color-accent` and `--comments-radius`. Set them on `.comments` in your own stylesheet to change every thread at once.

```css
.comments {
    --comments-color-accent: #7c3aed;
    --comments-radius: 0.75rem;
}
```

The tokens sit in a cascade layer of their own, so your declaration wins whichever file loads last, with no `!important`. The plugin's [Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming) guide describes what each token styles.

::: details Every token, with its value in each theme
<!--@include: ../_generated/tokens.md-->
:::

## `themeConfig` Second {#themeconfig-second}

Sites built on a design system, such as Bootstrap, DaisyUI or a component library of their own, usually want that system's button and form classes on Comments' controls rather than a restyled copy of Comments' own. `themeConfig` adds classes and attributes to the ten controls design systems standardise, and can drop Comments' styling classes from them so the two don't compete.

```js
document.querySelector('verbb-comments').themeConfig = {
    primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary btn-sm' } },
    secondaryButton: { resetClass: true, attributes: { class: 'btn btn-outline-secondary btn-sm' } },
    quietButton: { attributes: { 'data-track': 'comments' } },
    input: { attributes: { class: 'form-control' } },
    textarea: { attributes: { class: 'form-control' } },
};
```

**Post** and the other main buttons get `btn btn-primary btn-sm` in place of Comments' button styling, **Cancel** and the other secondary buttons get the outline style, each comment's text actions gain a `data-track` attribute for your analytics, and the guest fields and comment box gain `form-control` alongside their own classes.

Setting the property redraws the thread with it. For every thread on the page, give the same object to `configure()` from `@verbb/comments-browser` before registering the element, and an element's own `themeConfig` merges over it, control by control and attribute by attribute.

```js
import { configure } from '@verbb/comments-browser';
import { registerCommentsWebComponents } from '@verbb/comments-web-components';

configure({ themeConfig: { primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary btn-sm' } } } });
registerCommentsWebComponents();
```

### The Controls

<!--@include: ../browser/theme-config.md#theme-config-controls-->

### `attributes` and `resetClass`

<!--@include: ../browser/theme-config.md#theme-config-keys-->

A [part override](/web-components/part-overrides) that draws a button or link of its own can give it the same classes as Comments' controls, through `controlAttributes(name)` in its context.

## Class Names Third

When a token or `themeConfig` doesn't reach what you want to change, every element the thread draws has a `comments__*` class you can style from your own CSS, Tailwind's `@apply` or a design system's selectors.

<!--@include: ../browser/class-names-css-layers.md#classes-stable-->

<!--@include: ../browser/class-names-css-layers.md#classes-rules-->

## Tailwind and Cascade Layers

<!--@include: ../browser/class-names-css-layers.md#layers-why-->

<!--@include: ../browser/class-names-css-layers.md#layers-build-->

Import the stylesheets this way in place of the plain imports [above](#importing-the-stylesheets), in your main CSS file.

## Dark Mode {#dark-mode}

Every thread has a theme, `light`, `dark`, `auto` or `none`, from its `theme` attribute, and `light` without one. `auto` follows the visitor's own light or dark preference from their system, and `none` draws no look at all, leaving it to your CSS. Changing the attribute, or the `theme` property, switches the drawn thread in place.

Many sites put a class or attribute on `<html>` when a visitor turns dark mode on, such as `<html class="dark">`. Give that selector as `darkSelector` to `configure()`, before registering the element, and every thread switches to the dark theme while it matches `<html>`, and back to light when it stops.

```js
import { configure } from '@verbb/comments-browser';
import { registerCommentsWebComponents } from '@verbb/comments-web-components';

// Given before the element starts, so the thread draws in the right theme.
configure({ darkSelector: 'html.dark' });
registerCommentsWebComponents();
```

<!--@include: ../browser/dark-mode.md#dark-selector-->

When your site decides the theme some other way, such as from a setting it stores itself, set the attribute.

```js
document.querySelector('verbb-comments').setAttribute('theme', prefersDark ? 'dark' : 'light');
```

<!--@include: ../browser/dark-mode.md#dark-theme-effects-->
