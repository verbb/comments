# Structured Data

The thread is drawn in the browser, so the comment text isn't in your page's HTML. Search engines that run JavaScript still see comments that load as they scroll into view or with the page, as [Choosing When Comments Load](docs:template-guides/rendering-comments#choosing-when-comments-load) explains, but crawlers that read a page without running scripts don't. `craft.comments.structuredData()` gives those crawlers the approved comments as [schema.org](https://schema.org/Comment) JSON-LD, a block of data in the page that they read without running anything.

## Adding Structured Data to a Page

Add the call to the same template as `render()`, anywhere in the page. This example is an entry template for a News section:

```twig
{{ craft.comments.render(entry) }}
{{ craft.comments.structuredData(entry) }}
```

This outputs a `<script type="application/ld+json">` tag describing the page as an `Article` at the entry's URL, with its `commentCount` and the 20 newest approved top-level comments. For an entry with two comments, one of which has a reply, it looks like this:

```html
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "url": "https://example.com/news/trail-running-knees",
    "commentCount": 3,
    "comment": [
        {
            "@type": "Comment",
            "datePublished": "2026-11-09T21:04:00+00:00",
            "text": "Ice and rest for a week, then build back up slowly.",
            "url": "https://example.com/news/trail-running-knees#comment-1445",
            "upvoteCount": 3,
            "comment": [
                {
                    "@type": "Comment",
                    "datePublished": "2026-11-10T08:12:00+00:00",
                    "text": "That worked for me too.",
                    "url": "https://example.com/news/trail-running-knees#comment-1447",
                    "upvoteCount": 0
                }
            ]
        },
        {
            "@type": "Comment",
            "datePublished": "2026-11-08T17:30:00+00:00",
            "text": "Has anyone tried a knee strap?",
            "url": "https://example.com/news/trail-running-knees#comment-1440",
            "upvoteCount": 1
        }
    ]
}
</script>
```

The tag is output on one line, and is shown here spread out to make it easier to read. Each comment has its date, its text as plain text, a link to it on the page and, while voting is on, its upvote count. Replies are nested under the comment they reply to, with up to three for each comment, the same replies the thread shows before more are loaded, and down to four levels of replies. When a comment has been removed but its replies are still shown, the replies take its place. `commentCount` is the number of public comments in the whole discussion, so it can be larger than the number of comments included.

Commenters' names are left out by default, because JSON-LD puts them directly in front of search engines in a way the drawn thread doesn't. Nothing about the visitor is included either, so the output is the same for everyone and safe on a page cached with `{% cache %}`, a full-page cache or a CDN.

`structuredData()` takes the element the comments belong to, or its ID, which uses the current site. A draft or revision, such as the one Live Preview shows, gives the comments of the element it belongs to. It outputs nothing when the element doesn't exist or comments aren't live for it.

## Options

These options change what's output:

| Option | Default | What It Does |
| --- | --- | --- |
| `limit` | `20` | How many top-level comments to include, from 1 to 50. Anything else outputs nothing. |
| `includeAuthors` | `false` | Adds each commenter's public name as an `author`, as a `Person`. |
| `id` | None | Attaches the comments to a node your SEO plugin already outputs, given as that node's `@id`, instead of describing the page as an `Article` of its own. |
| `asArray` | `false` | Returns the data as an array instead of the tag, for adding to JSON-LD you output another way. |

For example, to include the 10 newest comments with their commenters' names:

```twig
{{ craft.comments.structuredData(entry, {
    limit: 10,
    includeAuthors: true,
}) }}
```

## Working with an SEO Plugin

SEO plugins such as SEOmatic usually describe the page already, often as an `Article` or `BlogPosting` with an `@id`. A second `Article` from Comments would make search engines see two separate things on the page. Give `structuredData()` that node's `@id` instead, and the comments are attached to it, so crawlers merge the two.

For example, if your SEO plugin gives the page's main entity an `@id` of the entry's URL followed by `#article`, attach the comments to it:

```twig
{{ craft.comments.structuredData(entry, {
    id: entry.url ~ '#article',
}) }}
```

The output then has `"@id": "https://example.com/news/trail-running-knees#article"` in place of `@type` and `url`, along with the same `commentCount` and `comment`. View your page's source to find the `@id` your SEO plugin uses, because it varies between plugins and settings.

To add the comments to JSON-LD your own template builds, use `asArray` and merge the result in:

```twig
{% set commentsData = craft.comments.structuredData(entry, { asArray: true }) %}

{% set article = {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: entry.title,
    url: entry.url,
    commentCount: commentsData.commentCount ?? 0,
    comment: commentsData.comment ?? [],
} %}

<script type="application/ld+json">{{ article | json_encode(constant('JSON_HEX_TAG') b-or constant('JSON_HEX_AMP') b-or constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE')) | raw }}</script>
```

`JSON_HEX_TAG` matters here, because it escapes `<` and `>`, so a comment containing `</script>` can't end the script early. `structuredData()` already does this when it outputs the tag itself.

## What Structured Data Does and Doesn't Do

Structured data makes your comments machine-readable for crawlers that don't run JavaScript. It doesn't earn the page a rich result, because search engines don't show one for article comments, so don't expect the comments to change how the page appears in search results.

## Structured Data on a Headless Site

A front end that isn't built with Twig gets the same data from the data API's [`structured-data`](docs:headless/data-api#structured-data) read, which takes `ownerId` or `ownerUri` with the same `limit`, `includeAuthors` and `id` options. It's a public read, cacheable like the thread itself. In a React or Vue app, `fetchStructuredData()` fetches it during server rendering, and `jsonLdScript()` escapes it for the page, as the [package docs](https://docs.verbb.io/comments/react/nextjs) show. That helper only takes `limit`, so to include commenters' names or attach the comments to your SEO node, request the `structured-data` read yourself with `includeAuthors` or `id`.
