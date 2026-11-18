# Styling

A thread draws with Comments' own stylesheet, which works as it is and suits most sites with a few changes. Work through the options in order and stop at the first that does what you need. Design tokens change colours, sizes and spacing. `themeConfig` puts your design system's classes on Comments' buttons and inputs. Your own CSS reaches anything else through stable class names.

## Importing the Stylesheets

Import the stylesheets once, such as in your app's entry or, in Next.js, the root layout. Editor's stylesheet is for rich comments.

```tsx
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';
```

The browser package has three stylesheets, so you can choose how much of Comments' look you start from.

<!--@include: ../browser/class-names-css-layers.md#classes-stylesheets-->

The `theme` prop and provider setting decide which theme a thread draws in, as [Dark Mode](#dark-mode) describes. With `theme="none"`, import `comments-base.css` in place of `comments.css`.

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

Apps built on a design system, such as Bootstrap, DaisyUI or a component library of their own, usually want that system's button and form classes on Comments' controls rather than a restyled copy of Comments' own. `themeConfig` adds classes and attributes to the ten controls design systems standardise, and can drop Comments' styling classes from them so the two don't compete.

```tsx
import { CommentsProvider, type ThemeConfig } from '@verbb/comments-react';
import type { ReactNode } from 'react';

const themeConfig: ThemeConfig = {
    primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary btn-sm' } },
    secondaryButton: { resetClass: true, attributes: { class: 'btn btn-outline-secondary btn-sm' } },
    quietButton: { attributes: { 'data-track': 'comments' } },
    input: { attributes: { class: 'form-control' } },
    textarea: { attributes: { class: 'form-control' } },
};

export function CommentsSettings({ children }: { children: ReactNode }) {
    return <CommentsProvider endpoint="https://cms.example.com/actions/comments/api" themeConfig={themeConfig}>{children}</CommentsProvider>;
}
```

**Post** and the other main buttons get `btn btn-primary btn-sm` in place of Comments' button styling, **Cancel** and the other secondary buttons get the outline style, each comment's text actions gain a `data-track` attribute for your analytics, and the guest fields and comment box gain `form-control` alongside their own classes.

A thread's own `themeConfig` prop merges over the provider's, control by control and attribute by attribute, so one thread can add a `title` to its **Post** button without repeating the provider's classes. A new `themeConfig` redraws the thread in place, and writing it inline is fine, because the thread compares what it holds.

### The Controls

<!--@include: ../browser/theme-config.md#theme-config-controls-->

### `attributes` and `resetClass`

<!--@include: ../browser/theme-config.md#theme-config-keys-->

A [part override](/react/part-overrides) component that draws a button or link of its own can give it the same classes as Comments' controls, through its `controlAttributes(name)` prop.

## Class Names Third

When a token or `themeConfig` doesn't reach what you want to change, every element the thread draws has a `comments__*` class you can style from your own CSS, Tailwind's `@apply` or a design system's selectors.

<!--@include: ../browser/class-names-css-layers.md#classes-stable-->

<!--@include: ../browser/class-names-css-layers.md#classes-rules-->

## Tailwind and Cascade Layers

<!--@include: ../browser/class-names-css-layers.md#layers-why-->

<!--@include: ../browser/class-names-css-layers.md#layers-build-->

Import the stylesheets this way in place of the plain imports [above](#importing-the-stylesheets), in your main CSS file rather than from a component.

## Dark Mode {#dark-mode}

Every thread has a theme, `light`, `dark`, `auto` or `none`, from its `theme` prop or the provider's, and `light` when neither gives one. `auto` follows the visitor's own light or dark preference from their system, and `none` draws no look at all, leaving it to your CSS. A new `theme` switches the drawn thread in place.

Many apps put a class or attribute on `<html>` when a visitor turns dark mode on, such as `<html class="dark">`. Give that selector as `darkSelector`, and every thread switches to the dark theme while it matches `<html>`, and back to light when it stops.

```tsx
<CommentsProvider endpoint="https://cms.example.com/actions/comments/api" darkSelector="html.dark">
    {children}
</CommentsProvider>
```

<!--@include: ../browser/dark-mode.md#dark-selector-->

When your app decides the theme some other way, such as from its own state, pass it as `theme`.

```tsx
// Your app's own dark mode state.
const { dark } = useAppearance();

<CommentsThread ownerUri={uri} theme={dark ? 'dark' : 'light'} />
```

<!--@include: ../browser/dark-mode.md#dark-theme-effects-->
