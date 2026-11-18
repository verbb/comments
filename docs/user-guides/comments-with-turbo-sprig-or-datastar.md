# Comments with Turbo, Sprig or Datastar

Many Craft sites make their pages feel faster by swapping in new HTML rather than loading each page in full. Turbo Drive replaces the page's body when a visitor follows a link. Sprig re-renders a component on the server and swaps it into the page. Datastar patches parts of the page with HTML the server sends. All three can also **morph** the page, comparing the new HTML with what's on screen and changing only what differs, so the page doesn't flash.

Comments' thread is drawn in the browser by its script, inside a placeholder that `craft.comments.render()` puts in your HTML. This guide explains how the thread copes when that HTML is swapped or morphed, and the one attribute to add for each library, so the thread stays as it is when the same post is refreshed, and is replaced when a different post is shown. There's a section for each library, so read the one your site uses.

When you're done, visitors can move between posts with your library of choice and always see the right thread. With Sprig and Datastar, when the server refreshes the current post, the thread stays exactly as it is, including a comment someone's halfway through writing.

The [Craft starter](https://docs.verbb.io/comments/starters/craft) has a working page for each of the three libraries, which this guide's examples are taken from.

## What You'll Need

You'll need Comments installed and its thread on your entry template, output with `craft.comments.render(entry)`, as [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) describes. You'll also need Turbo, the Sprig plugin or the Datastar plugin already working on your site, and you should be comfortable editing the templates that use them.

## What Happens When a Page Is Swapped

When a page first loads, Comments' script finds every thread placeholder on it and draws each thread. It also keeps watching the page. When your library adds new HTML containing a placeholder, Comments draws that thread, and when it takes a thread off the page, Comments stops it. For Turbo, Comments also stops each thread just before Turbo saves a copy of the page for its back button, and starts the threads on the page Turbo shows next.

So swapping one post for another works with no changes at all. The old thread is stopped, the new post's thread is drawn, and nothing is left behind.

Refreshing the same post is where it gets interesting. The server sends the post's HTML again, and in it is the thread's placeholder, empty, the way `render()` outputs it. If your library swaps or morphs that into the page, it puts the empty placeholder back where the drawn thread was. Comments notices and draws the thread again from the data it already has, but the reader sees it redraw, and may lose their place.

The fix is to tell your library to leave the thread alone. Each library has an attribute that keeps an element as it is during a swap or morph, as long as the new HTML has an element with the same `id`. You wrap `render()` in an element with that attribute and an `id`. The `id` includes the entry's ID, so a refresh of the same post keeps the thread, while a different post has a different `id`, so its own thread replaces the old one.

## Turbo Drive

Turbo Drive works with Comments as soon as it's on the page. Follow a link from one post to another, and the new post's thread appears. Go back, and the earlier post's thread is drawn again.

Turbo 8 can also refresh the current page by morphing it, when the page has this in its `<head>`:

```twig
<meta name="turbo-refresh-method" content="morph">
```

A refresh happens when something visits the current URL again, such as a form that redirects back to the page, or your own code calling `Turbo.visit(location.href, { action: 'replace' })`. To keep the thread's element out of the morph, wrap `render()` in your entry template:

```twig
<div id="comments-wrapper-{{ entry.id }}" data-turbo-permanent>
    {{ craft.comments.render(entry) }}
</div>
```

`data-turbo-permanent` tells Turbo to keep this element as it is whenever the new page has an element with the same `id`, rather than morphing the server's empty placeholder into it. Here the `id` names the entry, so refreshing the post keeps the thread's element, while visiting another post, whose wrapper has a different `id`, brings that post's own thread.

Turbo stops and starts each thread around every visit, refreshes included, because Comments stops threads before Turbo saves its copy of the page. So on a refresh, the thread's element stays in place, and Comments starts the thread in it again, loading its comments afresh. A form that was open, such as a reply someone was writing, closes. If your pages refresh while people are likely to be writing, such as on a timer, refresh only the parts of the page that need it instead, with a Turbo Frame that doesn't contain the thread.

To check it, open a post, scroll to the comments, and trigger a refresh, such as by running `Turbo.visit(location.href, { action: 'replace' })` in your browser's console. The rest of the page updates, and the post's thread is drawn again where it was. Then follow a link to another post, and check its thread shows that post's comments.

## Sprig

A Sprig component is a template that Sprig re-renders on the server when something in it is used, such as a button, and then swaps into the page. Suppose your blog has a component that shows one post at a time, with buttons to choose a post and a **Reload this post** button. In the component's template, wrap the thread:

```twig
<div id="sprig-thread-{{ entry.id }}" s-preserve>
    {{ craft.comments.render(entry) }}
</div>
```

`s-preserve` is Sprig's name for htmx's `hx-preserve`, which keeps the element as it is on screen when the new HTML has an element with the same `id`. When the component re-renders the same post, the thread is kept, and when it renders another post, that post's thread is swapped in, and Comments draws it.

To check it, open the page, start writing a comment in the thread, then press a button that re-renders the same post. The component updates around the thread, and what you'd typed is still there. Then choose another post, and its thread replaces the first.

### Loading Comments' Files with Sprig

`render()` asks Craft to add Comments' script and stylesheet to the page. That works on the full page load, but when Sprig re-renders a component, only the component's HTML comes back, without the files a full page would load. So if a page shows no thread when it first loads, and a thread only appears after a re-render, the script was never loaded to draw it.

When that's possible on your site, add Comments' files to the page yourself, in the template that renders the full page, outside the component:

```twig
{% do view.registerAssetBundle('verbb\\comments\\web\\assets\\frontend\\CommentsScriptAsset') %}
{% do view.registerAssetBundle('verbb\\comments\\web\\assets\\frontend\\CommentsStyleAsset') %}
```

These are the same files `render()` adds, and the script draws any thread a later re-render brings. If you've turned off **Load Bundled JavaScript** or **Load Bundled CSS** because your own build includes Comments, leave out the line for that file.

## Datastar

Datastar asks the server for HTML and morphs it into the page, matching elements by their `id`. Suppose a post page has a **Refresh from the server** button that patches the whole post, thread included, with Datastar's `{% patchelements %}` tag. Wrap the thread in the post's template:

```twig
<div id="datastar-thread-{{ entry.id }}" data-ignore-morph>
    {{ craft.comments.render(entry) }}
</div>
```

`data-ignore-morph` tells Datastar's morph to leave this element as it is on screen when the new HTML has an element with the same `id`. Refreshing the post leaves the thread alone, and patching in a different post, with a different `id`, replaces it with that post's thread.

To check it, open the post, start writing a comment, and press the refresh button. The post updates around the thread, and what you'd typed is still there. Then patch in another post, and check its thread shows that post's comments.

Datastar patches can come back without a full page too, so the note on [Loading Comments' Files with Sprig](#loading-comments-files-with-sprig) applies to Datastar as well, if a thread can first appear in a patch.

## When You Can't Add the Attribute

If you can't wrap the thread, such as when a morph you don't control covers the page, the thread still works. When a morph puts the empty placeholder back over a drawn thread, Comments draws the same thread again from the data it already has, keeping what's typed in its forms and giving the keyboard focus back where it was. Readers may see it redraw, which is why the attribute is the better choice, but nothing breaks.

To control exactly when a thread starts, such as after your own code has finished with a swap, put `data-comments-auto-mount="false"` on the thread's wrapper. Comments then leaves that thread for your code to start with `Comments.mount()`, as [Bundle Comments in Your Own Build](docs:user-guides/bundle-comments-in-your-own-build#starting-the-thread-yourself) shows.

## Testing It

Run through these with your library, signed out and signed in:

1. Open a post directly, and check its thread loads.
2. Move to another post the way your library does, and check the thread shows that post's comments, not the last one's.
3. Refresh the current post the way your library does, and check the post's thread is still there. With Sprig and Datastar, start writing a comment first, and check it's still there afterwards.
4. With Turbo, use the browser's back and forward buttons, and check each post shows its own thread.
5. Open your browser's console and check there are no errors from Comments.

If a thread disappears after a swap, check that its wrapper's `id` is the same in the old and new HTML, and that it's unique on the page. If a different post keeps showing the old post's thread, the wrapper's `id` doesn't include the entry's ID, so your library is keeping it when it shouldn't.
