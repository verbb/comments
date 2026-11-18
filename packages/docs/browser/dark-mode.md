# Dark Mode

Every thread has a theme, which is `light`, `dark`, `auto` or `none`. `auto` follows the visitor's own light or dark preference from their system, and `none` loads only the base styles the thread needs to work, leaving the look to your CSS. A Craft site usually sets the theme once, with the plugin's **Theme** setting or `render()`'s `theme` option, as the plugin's [Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming) guide explains along with the colours each theme uses. This page covers changing the theme from JavaScript, which is what a site with its own dark mode toggle needs.

## Following Your Site's Own Toggle

Many sites put a class or attribute on `<html>` when a visitor turns dark mode on, such as `<html class="dark">`. Give that selector as `darkSelector`, and every thread switches to the dark theme while it matches `<html>`, and back to light when it stops.

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({ darkSelector: 'html.dark' });
});
```

<!-- #region dark-selector -->
The script watches `<html>`'s attributes, so your toggle needs no extra code. Threads still waiting to load take the right theme too, so each draws in it from the start, and a thread whose theme is `none` is left alone. A selector that isn't valid CSS logs a warning in the browser console and changes nothing. Any selector that can match `<html>` works, such as `[data-theme="dark"]` or `:root.theme-dark`.
<!-- #endregion dark-selector -->

The [web components starter](/starters/web) works this way, with a toggle that keeps a `dark` class on `<html>`:

```js
import { configure } from '@verbb/comments-browser';
import { registerCommentsWebComponents } from '@verbb/comments-web-components';

// Given before the element starts, so the thread draws in the right theme.
configure({ darkSelector: 'html.dark' });
registerCommentsWebComponents();
```

## Switching from Your Own Code

When your site decides the theme some other way, such as from a setting it stores itself, switch the threads directly.

```js
// Every thread on the page.
window.Comments.setTheme('dark');

// One thread.
window.Comments.get(document.querySelector('#comments-109-1'))?.setTheme('auto');
```

`Comments.setTheme()` reaches every thread, including those still waiting to load, while a controller's `setTheme()` changes only its own thread. `Comments.configure({ theme })` switches every thread too, and gives threads that start later that theme in place of the one their mount point had. The React and Vue components take `theme` and `darkSelector` as props and provider settings, and `<verbb-comments>` takes a `theme` attribute.

## What Changes with the Theme

<!-- #region dark-theme-effects -->
The theme is the thread root's `data-comments-theme` attribute, which the stylesheet reads to choose its colours, so your own CSS can follow it as well.

```css
/* A softer shadow on the editor in the dark theme. */
.comments[data-comments-theme='dark'] .comments__frame {
    box-shadow: 0 1px 2px rgb(0 0 0 / 40%);
}
```

The dark theme gives the thread a padded panel of its own, coloured by `--comments-color-panel`, because its light text can't rely on whatever is behind it on your page. `auto` does the same while the visitor prefers dark. Rich comments' editor follows the thread's theme, and when the theme is `auto` it takes the visitor's preference as the editor opens.
<!-- #endregion dark-theme-effects -->
