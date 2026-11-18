# `themeConfig` for Controls

Sites built on a design system, such as Bootstrap, DaisyUI or a component library of their own, usually want that system's button and form classes on Comments' controls rather than a restyled copy of Comments' own. `themeConfig` adds classes and attributes to the ten controls design systems standardise, and can drop Comments' styling classes from them so the two don't compete.

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({
        themeConfig: {
            primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary btn-sm' } },
            secondaryButton: { resetClass: true, attributes: { class: 'btn btn-outline-secondary btn-sm' } },
            quietButton: { attributes: { 'data-track': 'comments' } },
            input: { attributes: { class: 'form-control' } },
            textarea: { attributes: { class: 'form-control' } },
        },
    });
});
```

**Post** and the other main buttons get `btn btn-primary btn-sm` in place of Comments' button styling, **Cancel** and the other secondary buttons get the outline style, each comment's text actions gain a `data-track` attribute for your analytics, and the guest fields and comment box gain `form-control` alongside their own classes.

## The Controls

<!-- #region theme-config-controls -->
Each control follows how it looks rather than its tag, so **Sign in** is a `primaryButton` and **Review in Comments** a `secondaryButton`, though both are links.

<!--@include: ../_generated/theme-config-controls.md-->

Each control's element also carries `data-comments-control` with the control's name, so your CSS and tests can find it whatever classes it has.

The [Style Comments with Tailwind or a Design System](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/style-comments-with-tailwind-or-a-design-system) user guide puts a design system's classes on the controls step by step, alongside the class names and CSS layers.
<!-- #endregion theme-config-controls -->

## `attributes` and `resetClass`

<!-- #region theme-config-keys -->
Each control takes two keys. `attributes` is merged onto every element of that control. Its `class`, a string or an array, is added to Comments' classes, and `title`, `aria-label` and your own `data-*` attributes are added or replaced. Those are the only attributes it can set, because the script manages the rest, such as `id`, `name`, `type`, `disabled`, `href` and the `aria-*` attributes it uses, and because anything else could run script or change what a control does. `data-comments-*` attributes are the script's own too. An unknown control name, a protected attribute or a value that isn't text, a number, `true`, `false` or a list of classes logs a warning in the browser console and is left out.

`resetClass: true` drops Comments' styling classes from the control, the ones in the table's last column, before your `class` is added. The element keeps its identifying class, such as `comments__submit` on **Post**, which carries only layout, and every `data-comments-*` attribute, so the [Markup Reference](/browser/markup-reference), your CSS and the script still find it, and busy and disabled states still show through the base stylesheet. Without `resetClass`, your classes sit alongside Comments' own.
<!-- #endregion theme-config-keys -->

## Where to Set It

`themeConfig` can be given in several places, merged control by control and attribute by attribute, with later ones winning.

1. `config/comments.php`, for every thread on a Craft site.
2. `craft.comments.render()`'s `themeConfig` option, for one thread in Twig.
3. `Comments.configure()` or `startComments()`, for every thread on the page.
4. A thread's own options, from `mountThread()`, a `<verbb-comments>` element's `themeConfig` property, or a React or Vue component's `themeConfig` prop and provider settings.

A later source can add to a control without repeating what an earlier one set. For example, the config file can put `btn btn-primary` on every `primaryButton`, and one thread's `render()` call can add a `title` to its own.

Setting it in Twig has one extra benefit. Tailwind and other tools that scan your templates for class names find the classes there, whereas classes that only exist in a JavaScript file need that file in the tool's content paths.

```twig
{{ craft.comments.render(entry, {
    themeConfig: {
        primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary btn-sm' } },
        input: { attributes: { class: 'form-control' } },
    },
}) }}
```

`themeConfig` from `render()` travels in the mount point's `data-comments-config` attribute as JSON, so it holds only data. The plugin's [Rendering Comments](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/rendering-comments) guide covers `render()`'s other options.

## In Your Own Markup

A [part override](/browser/part-overrides) that draws a button or link of its own can give it the same resolved classes as Comments' controls, through `controlAttributes(name)` in its context. A React or Vue component override gets the same function as a prop, so a component's buttons match the rest of the thread.
