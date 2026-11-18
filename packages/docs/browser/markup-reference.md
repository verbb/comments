# Markup Reference

This page lists every class and `data-comments-*` attribute the thread draws, and every CSS custom property its stylesheets read. The tables are generated from the package's code, so they always match the version these docs describe. [Class Names and CSS Layers](/browser/class-names-css-layers) explains how to use them.

Each class and attribute is marked **Stable** or **Internal**. Stable ones are the parts and states a site would reasonably style or select. They change only in a major release, and the upgrade guide lists any that do. Internal ones are helpers inside a part, listed so you can see them, but free to change in any release, so style the stable element around them instead. A class ending in `<value>` stands for a family, such as `comments__badge--<value>` for `comments__badge--staff` and `comments__badge--verified`.

The last column says which element carries each one, and where it's first drawn among the states the package's tests draw, such as a member's view of a thread, a guest's or a moderator's. Some only appear in particular states, such as `comments__comment--held` for a moderator.

## Thread

The thread's root, its header, the list, and what's shown while it waits, when it's empty or when it fails.

<!--@include: ../_generated/markup-thread.md-->

## Comment

Each comment, from its author and text to its actions, its custom field values, a moderator's review and its replies.

<!--@include: ../_generated/markup-comment.md-->

## Editor

The comment form, the sign-in card for guests, the guest fields, custom fields on the form and Assets fields' file tray.

<!--@include: ../_generated/markup-editor.md-->

## Report Dialog

The dialog that opens from a comment's **Report…** action.

<!--@include: ../_generated/markup-report.md-->

## Controls

The styling classes of the ten controls [`themeConfig`](/browser/theme-config) reaches. `resetClass` drops these from a control.

<!--@include: ../_generated/markup-controls.md-->

## State Attributes

The thread's behaviour relies on its `data-comments-*` attributes rather than its classes. The stable ones are safe for your CSS and scripts to select, such as `data-comments-state` on each comment, which holds its state (`approved`, `pending`, `held`, `reported` or `placeholder`), `data-comments-phase` on the root as it loads, and `data-comments-action` on every control that does something. Those on the mount point are written by `craft.comments.render()` and the React and Vue packages rather than the renderer.

<!--@include: ../_generated/markup-attributes.md-->

## CSS Custom Properties

The properties the stylesheets read, with their values in the light theme and in the dark theme (which the automatic theme uses while the visitor prefers dark). The plugin's [Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming) guide explains how to set them. Properties starting `--_comments-` are internal and aren't listed.

<!--@include: ../_generated/tokens.md-->

`--comments-reserve` sets the height reserved for a thread before it loads, which otherwise comes from an estimate based on its comment count.
