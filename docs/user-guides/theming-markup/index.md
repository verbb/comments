# Theming & Markup

Restyle the comment thread to match your site, use your own CSS framework or design system, and change how parts of the thread are built.

## [Make a Custom Theme](docs:user-guides/theming-markup/make-a-custom-theme)

Restyle the whole thread with your site's colours, type and corners by changing design tokens in your own stylesheet, then add a dark version that follows your site's dark mode toggle. The last section starts from the None theme and writes the look from scratch.

## [Style Comments with Tailwind or a Design System](docs:user-guides/theming-markup/style-comments-with-tailwind-or-a-design-system)

Put Comments' stylesheet in a cascade layer so Tailwind can win over it, restyle the thread with `@apply`, and give Comments' buttons and inputs your site's own classes with `themeConfig`. It also covers design systems such as Bootstrap that don't use layers.

## [Change a Part's Structure](docs:user-guides/theming-markup/change-a-parts-structure)

Replace the markup of one part of the thread, such as the empty message, a badge or the avatar, while keeping the theme, wording and every behaviour. Four examples build from a simple swap to your own avatar element.

## [Upgrade Comments 3 Custom Templates](docs:user-guides/theming-markup/upgrade-comments-3-custom-templates)

Move the changes in a Comments 3 template override folder across to Comments 4, one at a time, using settings, translations, class names, badges, comment data and part overrides. By the end the old folder can be deleted.
