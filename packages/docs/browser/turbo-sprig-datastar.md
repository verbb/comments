# Turbo, Sprig and Datastar

Libraries such as Turbo, Sprig, htmx and Datastar change a page without a full load, by swapping in new HTML from the server or morphing the page into it. Comments' script follows those changes by itself, starting threads that arrive and stopping threads that leave, so a thread rendered with `craft.comments.render()` works on these pages with no extra code. This page explains what happens in each case, and how to keep a drawn thread exactly as it is while the page around it changes. The [Craft starter](/starters/craft) has a working example of each, and the [Comments with Turbo, Sprig or Datastar](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/front-end-headless/comments-with-turbo-sprig-or-datastar) user guide sets one up step by step.

## Turbo Drive

Turbo Drive replaces the page's `<body>` as visitors move between pages. Comments stops every thread before Turbo keeps a copy of the page for its cache, so the copy Turbo restores later starts afresh rather than showing a stale thread, and it starts the threads on each page Turbo shows. Nothing needs configuring.

## Pages That Swap Their Content

Sprig, htmx and similar tools replace part of a page with new HTML from the server. The script watches the page, so a mount point that arrives in the new HTML is started according to its load mode, and a thread that's swapped out is stopped. When the new HTML has a thread for a different entry, that thread is drawn in place of the old one.

A swap draws the thread afresh, losing anything a visitor had typed, so keep the thread out of swaps that don't change it. htmx keeps an element with `hx-preserve` and an `id` when the new HTML has one with the same `id`, and Sprig's `s-preserve` does the same. Give the wrapper an `id` that names the entry, so a swap to another entry still brings in that entry's thread:

```twig
<div id="sprig-thread-{{ entry.id }}" s-preserve>
    {{ craft.comments.render(entry) }}
</div>
```

## Pages That Morph

Turbo 8's morphing refresh, Datastar and idiomorph change the page's existing elements to match the new HTML rather than replacing them. That would put the server's empty mount point back over a drawn thread, so Comments notices and draws the thread again from the data it already has, with the same controller. It keeps its options, its event listeners, anything typed in its forms and any framework components in it, and gives focus back where it was. A thread still waiting to load draws its waiting state again, and an element your own code added beside the thread, inside its root, is left where it is.

That works, but it's smoother to leave the thread out of the morph altogether. Turbo leaves an element with `data-turbo-permanent` and an `id` alone:

```twig
<div id="turbo-thread-{{ entry.id }}" data-turbo-permanent>
    {{ craft.comments.render(entry) }}
</div>
```

Datastar skips an element with `data-ignore-morph` when the new HTML has one with the same `id`:

```twig
<div id="datastar-thread-{{ entry.id }}" data-ignore-morph>
    {{ craft.comments.render(entry) }}
</div>
```

In each case the `id` names the entry, so the thread is kept while the page shows the same entry, and a page for another entry brings its own thread. A morph that turns a thread's root into a different entry's mount point stops the old thread and starts the new one.

## Threads from `<verbb-comments>`

The `<verbb-comments>` element follows the same rules on its own. It stops when it's taken off the page and starts again when it's put back, moving it keeps the thread as it is, and a morph that empties it draws the same thread again. [The `<verbb-comments>` Element](/web-components/verbb-comments#pages-that-change) has the details.
