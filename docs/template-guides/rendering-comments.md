# Rendering Comments

Use `craft.comments.render()` in an element's Twig template to display its comments and a form for posting one. It's a single call that brings its own CSS and JavaScript, so it's the quickest way to add comments to a page. Put it in your entry template wherever the comments should appear:

```twig
{{ craft.comments.render(entry.id) }}
```

This outputs a heading with the comment count, links for choosing the sort order, the form, and threaded comments with their replies and controls. Which of these a visitor sees depends on your settings and whether they can comment. Signed-out visitors get a **Join the conversation** card with a way to sign in and, when guests can comment, a **Comment as a guest** button that opens the form. A closed discussion shows a notice in place of the form. Nothing is output at all if the element doesn't exist, comments are turned off for it, it isn't public and the visitor can't view it, or it has no comments yet and the visitor can't post one.

![An article's comment thread as a visitor sees it, with sort links, threaded replies, Author and Staff badges beside commenters' names, and like counts](../../screenshots/site/thread.png)

## Parameters

| Parameter | Type | Description |
| --- | --- | --- |
| `elementId` | `int` | The ID of the element the comments belong to. Required. |
| `options` | `array` | Options for how they're rendered, as below. |

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `siteId` | `int` | The current site | Which site's version of the element to show comments for. |
| `sort` | `string` | The **Frontend Comment Order** setting | The initial order, which is `newest` (or `desc`), `oldest` (or `asc`), or `top`. |
| `limit` | `int` | `20` | How many top-level comments to show at first, and with each **Load more comments**, up to 50. |
| `lazy` | `bool` | The **Load Comments When Reached** setting | See [Lazy Loading](#lazy-loading). |
| `sharedCache` | `bool` | `false` | See [Caching Comments](#caching-comments). |

For example, to show the ten oldest comments first:

```twig
{{ craft.comments.render(entry.id, { sort: 'oldest', limit: 10 }) }}
```

**Top** ranks comments by their votes, so it's only offered while **Interaction Mode** isn't set to **None**. Asking for `top` without interactions uses **Frontend Comment Order** instead.

Any other options are ignored. To change the markup, see [Custom Templates](docs:template-guides/custom-templates). For control over the CSS and JavaScript, see [Loading Comment Assets](docs:template-guides/loading-comment-assets). Most of what's shown is controlled by settings under **Comments** → **Settings**, which are explained in [Configuration](docs:get-started/configuration).

## Lazy Loading

Comments at the bottom of a long article add work to every page view, even though many visitors won't reach them. With lazy loading, the page only has a placeholder, and the browser fetches the comments and form when a visitor scrolls within about 600 pixels of them. When [rich authoring](docs:feature-tour/rich-authoring) is on, the Editor's files wait until then too.

Turn it on for the whole site with **Load Comments When Reached** under **Comments** → **Settings** → **Templates & Resources**, or the [`lazyLoad`](docs:get-started/configuration#lazyload) setting. Use the `lazy` option for one page, which overrides the setting in either direction:

```twig
{{ craft.comments.render(entry.id, { lazy: true }) }}
```

The trade-off is that comments aren't in the page's initial HTML, which search engines may read. Keep this off if your comments are part of what you want found.

Comments load straight away when the URL asks for something specific, such as [a link to a comment](#linking-to-a-comment). Without JavaScript, visitors get a **Show comments** link, which reloads the page with them included.

## Caching Comments

Comments vary by visitor, because they show who can edit what, the form has a CSRF token, and moderators see more than others. That's why `craft.comments.render()` can't be put inside a `{% cache %}` tag, or behind a full-page cache or CDN, by itself, as the first visitor's version would be shown to everyone.

The `sharedCache` option renders a version that's safe to share. It has the public comments and nothing about the person viewing it, so there's no form, CSRF token, voting or report controls, and comments show their date rather than “2 hours ago”, which would go stale. Once the page has loaded, the browser asks the `comments/frontend-api/viewer-controls` action for the parts belonging to that visitor and puts them in place. That response is marked `private, no-store`, so it isn't cached.

```twig
{% cache %}
    {{ craft.comments.render(entry.id, { sharedCache: true }) }}
{% endcache %}
```

Craft's cache tag keeps the asset bundles registered inside it, so the bundled CSS and JavaScript still load on a cached page.

Check it works by viewing the page signed out, then again as a signed-in user. Each should get the form and controls they'd expect, and the page's source shouldn't contain a CSRF token inside the comments.

Comments posted after the page was cached arrive along with those private controls, so visitors still see them. The `sharedCache` version needs JavaScript for its form and controls, so visitors without it can read comments but not post. It also outputs nothing for an element that isn't public, such as a disabled entry being previewed, because that version can't depend on who's looking.

## Linking to a Comment

Every comment has an anchor of `#comment-123`, using its ID. Without JavaScript, the date on each comment links to it, and with JavaScript, the comment's **···** menu has **Copy link**. Links work wherever the comment is, whether that's past the first page, below a collapsed reply or deeper than replies are shown.

With JavaScript, opening a link with that hash finds the comment on the page, or fetches its thread from the `comments/frontend-api/thread` action when it isn't there, then scrolls to it and highlights it. This works on a cached page too. Adding `?comment=123` does the same on the server, putting the comment's thread at the top of the list in the page's HTML, which is how visitors without JavaScript reach it. The server ignores this parameter when you're using `sharedCache`, and leaves it to the hash.

`comment.getPermalink()` gives you a full URL with both, as long as the element has a URL of its own:

```twig
<a href="{{ comment.getPermalink() }}">View comment</a>
```

Links in the default notification emails use the hash. A link to a comment that isn't public, or doesn't exist, lands on the page without highlighting anything.

## Using Comment Details Elsewhere

You'll sometimes want a little information about an element's discussion away from the comments themselves, such as a count on a listing page or a note that comments are closed. These calls each take the element itself rather than its ID.

`craft.comments.getSummary(element)` returns a summary of the element's discussion, or `null` if it doesn't have one yet, which is usually until its first comment. Its `commentCount` is the number of public comments, and `lastActivityAt` is when the discussion last changed. This shows a count beside each article in a news listing:

```twig
{% for entry in craft.entries.section('news').all() %}
    {% set summary = craft.comments.getSummary(entry) %}

    <a href="{{ entry.url }}">{{ entry.title }}</a>
    <a href="{{ entry.url }}#comments-{{ entry.id }}-{{ entry.siteId }}">{{ summary.commentCount ?? 0 }} comments</a>
{% endfor %}
```

The second link jumps to the comments, because the outer element output by `render()` has an `id` of `comments-` followed by the element's ID and site ID.

`craft.comments.getConfig(element)` returns the settings that apply to the element's discussion, once the global settings and the element's Comments field have been taken into account. Its `enabled`, `closed` and `allowGuests` properties are true or false, while `moderationMode` and `sortOrder` hold values you can read with `.value`, such as `pre` and `newest`. `isCommentingEnabled()` is true when comments are turned on and not closed, before the visitor's permissions and **Auto-close Comments** are checked.

```twig
{% set config = craft.comments.getConfig(entry) %}

{% if config and config.enabled and not config.isCommentingEnabled() %}
    <p>This discussion is closed to new comments.</p>
{% endif %}
```

`craft.comments.isSubscribed(element)` returns whether the signed-in visitor follows the element's discussion, and always returns false for a guest. Pass a comment as the second argument to check whether they're following replies to that comment instead.

```twig
{% if craft.comments.isSubscribed(entry) %}
    <p>You're following this discussion.</p>
{% endif %}
```

`craft.comments.getAvatar()` returns the signed-in visitor's photo as an asset, or otherwise the **Placeholder Avatar**, or an empty string if there's neither.
