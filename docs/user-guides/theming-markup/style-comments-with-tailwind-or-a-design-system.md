# Style Comments with Tailwind or a Design System

If your site is built with Tailwind CSS, or with a design system that has its own buttons and form fields, you'll want the comment thread to use them too. A **Post** button that looks like every other primary button on the site, and comment fields that match your contact form, make the thread feel like part of the page rather than something dropped into it.

In this guide you'll style a thread on a Tailwind CSS 4 site in three stages. First you'll put Comments' stylesheet in its own cascade layer, so Tailwind's utilities and your `@apply` rules can win over it. Then you'll restyle parts of the thread with `@apply` against the thread's class names. Finally you'll put the site's own button and field classes on Comments' buttons and inputs with `themeConfig`. The last section shows the same `themeConfig` with a design system that doesn't use layers, such as Bootstrap.

The example is a small agency's blog, built with Tailwind. Its stylesheet already defines a `.btn` class with `.btn-primary` and `.btn-secondary` variants for its buttons, and a `.field` class for text inputs. By the end, the thread's **Post**, **Sign in** and **Cancel** buttons are the site's own buttons, its name, email and comment fields are the site's own fields, and the thread's heading, names and badges follow the site's type and colours.

The same approach drives the [web components starter](https://docs.verbb.io/comments/starters/web), which uses Tailwind and Comments' stylesheet the way this guide does, so you can see the finished result running there.

## What You'll Need

You'll need Comments installed and its thread on a page, output with `craft.comments.render(entry)` in the entry's template. [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) walks through that.

You'll also need a site whose CSS is built with Tailwind CSS 4, through the Tailwind CLI, Vite or another build tool, with a main stylesheet that starts with `@import "tailwindcss";`. This guide calls that file `src/css/site.css`. The site's layout links to the built file in its `<head>`, as most Tailwind sites do.

This guide assumes your stylesheet already has some component classes for buttons and fields. If yours doesn't, add these to `src/css/site.css` to follow along:

```css
@layer components {
    .btn {
        @apply inline-flex items-center justify-center gap-1.5 rounded-lg border border-transparent px-3 py-1.5 text-sm font-medium transition disabled:opacity-50;
    }

    .btn-primary {
        @apply bg-indigo-600 text-white hover:bg-indigo-500;
    }

    .btn-secondary {
        @apply border-slate-300 bg-white text-slate-700 hover:bg-slate-100;
    }

    .field {
        @apply block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none;
    }
}
```

For colours, type and spacing alone, you don't need anything in this guide. [Make a Custom Theme](docs:user-guides/theming-markup/make-a-custom-theme) changes those through Comments' design tokens, which works the same on a Tailwind site. This guide is for when you want your site's own classes on the thread.

## Why Tailwind's Rules Lose to Comments' at First

Try a rule before setting anything up, so you can see the problem this guide solves. Add this to `src/css/site.css`, inside the `@layer components` block:

```css
    .comments__heading {
        @apply text-2xl font-semibold tracking-tight;
    }
```

Rebuild your CSS and reload a blog post. The thread's **Comments** heading doesn't change.

Tailwind CSS 4 puts everything it writes into [cascade layers](https://developer.mozilla.org/en-US/docs/Web/CSS/@layer) named `theme`, `base`, `components` and `utilities`, in that order, and a rule in a later layer wins over a rule in an earlier one. Comments' own stylesheet isn't in any layer, and in CSS a rule outside every layer always wins over one inside a layer, however specific the layered rule is. So Comments' `.comments__heading` rule beats your `@apply`, and it would beat a utility class such as `text-2xl` put on the element too.

The fix is to put Comments' stylesheet in a layer of its own, between Tailwind's `base` and `components`. Then Tailwind's base styles (its preflight, which resets the browser's defaults) don't flatten the thread, while your components and utilities win over it.

## Putting Comments' Stylesheet in a Layer

Go to **Comments** → **Settings** → **CSS & JavaScript** and turn on **CSS Layer**, then save. You can set [`useCssLayers`](docs:get-started/configuration#useCssLayers) to `true` in `config/comments.php` instead. With the setting on, the stylesheet Comments adds to the page wraps all of its rules in a layer named `comments`.

Next, tell the browser where that layer goes. Add this line at the very top of `src/css/site.css`, before the Tailwind import:

```css
@layer theme, base, comments, components, utilities;

@import "tailwindcss";
```

An `@layer` statement with only names sets the order of those layers, and the order is fixed by the first time the browser sees each name. Your stylesheet is linked in the layout's `<head>`, and Craft adds Comments' stylesheet at the end of the `<head>`, so your statement comes first and puts `comments` between `base` and `components`.

Rebuild your CSS and reload the page. The **Comments** heading is now large and semibold, from your `@apply` rule.

If it still hasn't changed, view the page's source and check the order of the stylesheets in `<head>`. Your site's stylesheet must come before the one whose address ends in `comments.layered.css`. If your layout outputs `{{ head() }}` before linking its own stylesheet, move the link above it. Also check that the built CSS file still starts with the `@layer` line, because some build setups move it.

## Styling the Thread with `@apply`

With the layer in place, you can style any part of the thread from your stylesheet with Tailwind. Every element in the thread has a class name starting with `comments__`, such as `comments__author-name` for a commenter's name and `comments__badge` for the badges beside it. These class names are kept the same between releases, so rules written against them keep working when you update. To find the class of anything you want to change, inspect it in your browser's developer tools.

Replace the heading rule you added earlier with this set of rules, still inside `@layer components`:

```css
    .comments__heading {
        @apply text-2xl font-semibold tracking-tight;
    }

    .comments__author-name {
        @apply text-indigo-700;
    }

    .comments__badge {
        @apply rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700;
    }

    .comments__badge--staff {
        @apply bg-emerald-50 text-emerald-700;
    }

    .comments__content {
        @apply leading-7;
    }

    .comments__replies {
        @apply border-indigo-100;
    }
```

Rebuild and reload. Commenters' names are indigo, badges are rounded pills, a staff member's badge is green, and comment text has a little more space between lines, and the line joining replies to their comment is a pale indigo.

Each badge has a second class naming its type, such as `comments__badge--staff` for the badge Comments gives staff members, which is how the last badge rule styles one kind of badge differently from the rest.

These rules change how the thread looks and leave its structure to Comments. Because the rules are in a layer above Comments', a single class is enough for them to win, and you never need `!important`. For a change Comments' design tokens can make, such as the accent colour or the corner radius, the tokens are usually quicker, as [Make a Custom Theme](docs:user-guides/theming-markup/make-a-custom-theme) shows. On a site using layers, set the tokens outside any layer, or inside `@layer components`, which comes after `comments`.

## Using the Site's Own Buttons and Fields

Restyling Comments' buttons with `@apply` would mean repeating what your `.btn` classes already say. It's simpler to put your classes on Comments' buttons and fields directly, with `themeConfig`.

`themeConfig` covers the ten controls a design system usually standardises. Each one is named after how it looks rather than what it does:

| Control | Where It Appears |
| --- | --- |
| `primaryButton` | **Post**, **Reply** and **Save** on the comment form, **Sign in**, the report dialog's submit button, and a moderator's main decision |
| `secondaryButton` | **Cancel**, **Show comments**, **Load more comments** and replies, **Try again**, **Follow**, and **Comment as a guest** |
| `quietButton` | The text buttons on each comment, such as **Like**, **Reply** and the **···** menu, the sort options and **Show replies** |
| `input` | A guest's name and email, and single-line custom fields |
| `textarea` | The plain comment box, the report dialog's details and multi-line custom fields |
| `select`, `checkbox`, `radio` | Custom fields of those kinds, the reply email opt-in and the report reasons |
| `link` | Text links Comments draws, such as each comment's date |
| `avatar` | Every avatar |

Open the blog's entry template and give `render()` a `themeConfig` with your classes:

```twig
{{ craft.comments.render(entry, {
    themeConfig: {
        primaryButton: {
            resetClass: true,
            attributes: { class: 'btn btn-primary' },
        },
        secondaryButton: {
            resetClass: true,
            attributes: { class: 'btn btn-secondary' },
        },
        input: {
            resetClass: true,
            attributes: { class: 'field' },
        },
        textarea: {
            resetClass: true,
            attributes: { class: 'field' },
        },
    },
}) }}
```

`attributes.class` adds your classes to every element of that control. `resetClass: true` first takes away Comments' own styling classes from it, such as `comments__button` and `comments__button--primary`, so your `.btn` isn't competing with Comments' button styles. Each control keeps one class that identifies it, such as `comments__submit` on the **Post** button, which only carries its position in the layout, and keeps all its `data-comments-*` attributes. Comments relies on those for its behaviour, so nothing stops working, and a button that's busy or disabled still shows it.

Because the classes are written in your template, Tailwind finds them when it scans your templates, the same as any other class you use. If you'd rather set `themeConfig` once for every thread, put the same settings in [`themeConfig`](docs:get-started/configuration#themeConfig) in `config/comments.php`. Tailwind doesn't scan PHP files in `config/` unless you ask it to, so add an `@source` line for that file to `src/css/site.css`, with a path relative to the stylesheet, such as `@source "../../config/comments.php";`.

Reload the page. Signed out, the **Join the conversation** card's **Sign in** button is now your indigo `.btn-primary`, and **Comment as a guest** is your white `.btn-secondary`. Press **Comment as a guest**, and the name and email fields use your `.field` class, with its indigo focus ring. Inspect the **Sign in** button, and its classes are `btn btn-primary` with Comments' identifying class beside them, and no `comments__button` classes.

The quiet text buttons under each comment, such as **Reply**, keep Comments' look, because this example doesn't set `quietButton`. They're designed to stay out of the way of the comments, and most sites leave them. To change them, add `quietButton` the same way, perhaps without `resetClass`, so your classes only add to Comments' own.

### What `themeConfig` Can and Can't Change

`themeConfig` sets classes, a `title`, an `aria-label` and your own `data-*` attributes, such as a `data-track` attribute your analytics reads. It can't change attributes that Comments manages itself, such as `id`, `name`, `type`, `disabled` and the `aria-*` attributes it sets for accessibility. If you give it an unknown control name or one of those attributes, the browser console shows a warning saying so, and the rest of your settings still apply.

`themeConfig` doesn't change the structure of the thread, add elements or remove them. To remove something, such as voting or avatars, turn it off in Comments' settings, which also stops the server offering it. To change the markup of a part of the thread, see [Change a Part's Structure](docs:user-guides/theming-markup/change-a-parts-structure).

## Testing It

Check each control in the situation where it appears, because some only show up for certain visitors.

1. Signed out, check **Sign in** and **Comment as a guest** on the sign-in card. Press **Comment as a guest** and check the name, email and comment fields. Click into the comment box and check the **Post** button.
2. Signed in, write a comment and press **Post**. While it's posting, the button shows a spinner and can't be pressed again, even with your classes on it.
3. Press **Reply** on a comment and check the reply form's **Reply** and **Cancel** buttons.
4. Open the **···** menu on a comment and choose **Report**. The report dialog's submit button is a `primaryButton`, and its reasons are `radio` controls.
5. If you've changed the load mode to **When a button is pressed**, the **Show comments** button is a `secondaryButton` too.

Look out for one thing in particular. If one of your classes sets `display`, such as `.btn` setting `inline-flex`, check that buttons Comments hides at certain moments still hide. For example, the sign-in card's buttons should disappear once a guest presses **Comment as a guest**. Comments hides them in a way that wins over your classes, so if one stays visible, look for an `!important` in your own CSS that's overriding it.

## Following the Site's Dark Mode

Many Tailwind sites switch to dark mode with a `dark` class on `<html>`, and Tailwind CSS 4 needs a custom variant for that, such as `@custom-variant dark (&:where(.dark, .dark *));`. Your `dark:` utilities then work inside the thread like anywhere else, so you can add them to your `@apply` rules:

```css
    .comments__author-name {
        @apply text-indigo-700 dark:text-indigo-300;
    }
```

To switch Comments' own colours with the same class, give it the same selector with `darkSelector`, as [Adding a Dark Version](docs:user-guides/theming-markup/make-a-custom-theme#adding-a-dark-version) in the theme guide explains:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments?.configure({ darkSelector: 'html.dark' });
});
```

## Bundling Comments' Stylesheet into Your Own Build

Instead of the plugin's stylesheet and the **CSS Layer** setting, you can import Comments' stylesheet straight into your Tailwind build. You'd do this when you already bundle Comments' script with your own JavaScript, as [Bundle Comments in Your Own Build](docs:user-guides/front-end-headless/bundle-comments-in-your-own-build) describes, or when you'd like one stylesheet for the whole site.

Install the `@verbb/comments-browser` package with the same version number as your Comments plugin, then import its stylesheet into the `comments` layer in `src/css/site.css`:

```css
@layer theme, base, comments, components, utilities;

@import "tailwindcss";
@import "@verbb/comments-browser/css/comments.css" layer(comments);
```

If your site uses rich authoring and loads Editor from npm, Editor's styles go in the same layer, as `@import "@verbb/editor/styles" layer(comments);`.

Then turn off **Load Bundled CSS** under **Comments** → **Settings** → **CSS & JavaScript**, so the page doesn't load a second copy of the stylesheet. **CSS Layer** has no effect once the plugin's stylesheet is off.

## Using a Design System Without Layers

Design systems such as Bootstrap don't use cascade layers, so the layer steps above don't apply. Comments' rules and your design system's rules are then both outside any layer, and the usual CSS rules decide between them, so the more specific selector wins, or the one that loads later if they're equally specific.

That makes `themeConfig` the main tool. With `resetClass: true`, Comments' styling classes are gone from the control, so your design system's classes have nothing to compete with:

```twig
{{ craft.comments.render(entry, {
    themeConfig: {
        primaryButton: {
            resetClass: true,
            attributes: { class: 'btn btn-primary btn-sm' },
        },
        secondaryButton: {
            resetClass: true,
            attributes: { class: 'btn btn-outline-secondary btn-sm' },
        },
        input: {
            resetClass: true,
            attributes: { class: 'form-control' },
        },
        textarea: {
            resetClass: true,
            attributes: { class: 'form-control' },
        },
        select: {
            resetClass: true,
            attributes: { class: 'form-select' },
        },
        checkbox: {
            resetClass: true,
            attributes: { class: 'form-check-input' },
        },
    },
}) }}
```

For anything else you want to change, write ordinary CSS against the thread's class names with one extra class in front, such as `.comments .comments__author-name`. The thread's root has the class `comments`, and Comments' own rules use a single class, so the extra class is enough for your rule to win wherever your stylesheet loads.
