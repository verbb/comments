# Working Without JavaScript

`craft.comments.render()` works when JavaScript doesn't run, such as for a visitor who has turned it off, or when a script fails to load. Everything the JavaScript does has a plain link or form behind it, so there's nothing to set up. You can also rely on this if you turn off **Load Bundled JavaScript** and don't want to write your own.

## What Visitors Can Do

Visitors can post, reply, edit and delete, vote, report a comment, follow a discussion, sort and page through comments, and moderators can moderate. Each of these reloads the page, instead of updating it in place:

- **Replying and editing** are links back to the same page, which opens the form under the comment.
- **Reporting** is a link that opens the **Report this comment** form in the page.
- **Loading more comments or replies** and **sorting** are links.
- **Posting** returns to the page at the comment. After an error, the form comes back with the message and what was typed.
- **Deleting, voting, reporting, following and moderating** return to the same page, which shows the result, along with a message if something went wrong.
- **Links to a comment** with `?comment=123` show that comment's thread at the top of the list.

Links keep anything else in the URL, such as your own site's pagination. The comment menu (**···**) is a disclosure element, so it opens without JavaScript too.

## What Needs JavaScript

Some things can't work without it, so these visitors get a simpler result rather than a broken one:

- **Rich authoring** falls back to a plain text box for writing a comment. A rich comment can't be edited this way, and trying shows an error, rather than flattening it to plain text.
- **Mentions and attachments** aren't available in that plain text box.
- **Require JavaScript** and **challenge providers** reject submissions without it, as they can't complete verification. Leave them off if you need to support these visitors. See [Anti-Spam](docs:feature-tour/anti-spam).
- **Shared caching** only has the public comments, so these visitors can read them, but the form and controls don't arrive. See [Caching Comments](docs:template-guides/rendering-comments#caching-comments).
- **Hiding replies**, **Copy link**, a moderator's **Hide held**, and the confirmation before deleting a comment are left out.

## URL Parameters

These are how the page moves around without JavaScript. You don't normally need them, and most of the links that use them are available as properties in [custom templates](docs:template-guides/custom-templates), such as `comment.hrefs`. If any of them is in the URL, lazy loading is skipped so the page can show what was asked for. A `sharedCache` render ignores all of them except `commentsSort`, because a page that's cached for everyone can't vary by comment.

| Parameter | What It Does |
| --- | --- |
| `comment` | Shows this comment's thread first. |
| `commentsCompose` | With `comment`, opens a `reply` or `edit` form on it, if the visitor can do that. |
| `commentsFlag` | With `comment`, opens the **Report this comment** form for it. |
| `commentsReplyLimit` | With `comment`, how many of its replies to show, up to 500. |
| `commentsRootsNext` | The cursor for the next page of comments. |
| `commentsSort` | The sort, which is `newest`, `oldest` or `top`. |
| `commentsLoad` | Shows lazy-loaded comments. |

When you pass a `sort` option to `craft.comments.render()`, it takes priority over `commentsSort`.

## Testing It

Turn off JavaScript in your browser's developer tools, or view the page with **Load Bundled JavaScript** off. Post a comment and reply to it, then trigger an error by leaving a required field empty with your browser's validation bypassed, and check what you typed is still there.
