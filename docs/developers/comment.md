# Comment
Whenever you're dealing with a comment from a [comment query](docs:getting-elements/comment-queries), you're working with a `Comment` element. The [templates](docs:template-guides/custom-templates) used by `craft.comments.render()` get a simpler prepared object instead, which that page describes.

For example, this lists the latest comments on an entry, with each `comment` being a `Comment` element. A query returns approved comments unless you ask for another status.

```twig
{% set comments = craft.comments.fetch()
    .ownerId(entry.id)
    .orderBy('commentDate desc')
    .limit(5)
    .all() %}

{% for comment in comments %}
    <article>
        <p>{{ comment.getAuthorName() }}, {{ comment.getTimeAgo() }}</p>

        {% if comment.isEditorContent() %}
            {{ comment.getRenderedBodyHtml() }}
        {% else %}
            <p>{{ comment.getComment() }}</p>
        {% endif %}
    </article>
{% endfor %}
```

<span id="attributes"></span>

## Properties

::: reference
### `id`

**Type:** `int|null`

ID of the comment.
:::

::: reference
### `ownerId`

**Type:** `int|null`

The element ID this comment was made on (Entry, Asset, etc).
:::

::: reference
### `ownerSiteId`

**Type:** `int|null`

The site ID of the element this comment was made on.
:::

::: reference
### `owner`

**Type:** `craft\base\ElementInterface|null`

The element this comment was made on (Entry, Asset, etc).
:::

::: reference
### `userId`

**Type:** `int|null`

[User](https://docs.craftcms.com/api/v5/craft-elements-user.html) ID for the author of a comment. This is null for guests.
:::

::: reference
### `author`

**Type:** `craft\elements\User|bool`

[User](https://docs.craftcms.com/api/v5/craft-elements-user.html) for the author of a comment. For a guest, this is a new User with their name and email populated. For a deleted user, this is a new User named “[Deleted User]”.
:::

::: reference
### `parent`

**Type:** `verbb\comments\elements\Comment|null`

The comment this is a reply to, whatever its status. For a top-level comment, this is null.
:::

::: reference
### `parentId`

**Type:** `int|null`

The ID of the comment this is a reply to.
:::

::: reference
### `rootId`

**Type:** `int|null`

The ID of the comment at the top of this comment's thread. On a top-level comment, this is its own ID.
:::

::: reference
### `depth`

**Type:** `int|null`

How many replies deep this comment is. Top-level comments are `0`.
:::

::: reference
### `status`

**Type:** `string|null`

The comment's stored status, which is `approved`, `pending`, `spam` or `removed`. A comment its author deleted keeps the status it had, so use [`getStatus()`](#getstatus) when you need to tell those apart.
:::

::: reference
### `moderationState`

**Type:** `string|null`

Where the comment is in moderation, using the same four values as `status`, which is kept in step with it. Only `approved` comments are shown publicly.
:::

::: reference
### `name`

**Type:** `string|null`

Name of the commenter. Guest users only.
:::

::: reference
### `email`

**Type:** `string|null`

Email address of the commenter. Guest users only. Take care not to output this on public pages.
:::

::: reference
### `url`

**Type:** `string|null`

The URL that this comment was made from.
:::

::: reference
### `ipAddress`

**Type:** `string|null`

The commenter's IP address, recorded when posting through a public form. See [Client IP Addresses](docs:feature-tour/security#client-ip-addresses).
:::

::: reference
### `userAgent`

**Type:** `string|null`

The commenter's user agent, recorded when posting through a public form.
:::

::: reference
### `comment`

**Type:** `string|null`

The comment's plain text. A rich comment also has this, without its formatting. Use `getRenderedBodyHtml()` to show one.
:::

::: reference
### `contentFormat`

**Type:** `string|null`

How the comment was written, which is `plain` or `editor`.
:::

::: reference
### `commentDate`

**Type:** `DateTime|null`

When the comment was posted.
:::

::: reference
### `editedAt`

**Type:** `DateTime|null`

When the comment's content last changed after it was posted. This is null if it hasn't.
:::

::: reference
### `authorRemovedAt`

**Type:** `DateTime|null`

When the author deleted the comment. These comments are left out of a query for `approved` comments. Use `.status('author-removed')` to find them.
:::

::: reference
### `gifUrl`

**Type:** `string|null`

The URL of a GIF shown with the comment, if it has one. Comments can't have a GIF attached when they're posted, so this is only set on comments that already had one.
:::

::: reference
### `conversationId`

**Type:** `int|null`

The ID of the conversation, which holds an element's comments on one site.
:::

::: reference
### `identityId`

**Type:** `int|null`

The ID of the commenter's identity, which represents a registered user or a guest.
:::


## Methods

::: reference
### `getStatus()`

**Returns:** `string|null`

Returns the comment's status, the same as [`status`](#status), except that a comment its author deleted returns `author-removed`. These are the values the `status` parameter of a [comment query](docs:getting-elements/comment-queries#status) accepts.
:::

::: reference
### `isGuest()`

**Returns:** `bool`

Returns true if the comment was made by a guest.
:::

::: reference
### `getComment()`

**Returns:** `string|null`

Returns the comment's plain text, with any emoji shortcodes stored in it converted to emoji.
:::

::: reference
### `isEditorContent()`

**Returns:** `bool`

Returns true if this is a rich comment.
:::

::: reference
### `getRenderedBodyHtml()`

**Returns:** `Twig\Markup`

Returns the rendered HTML of a rich comment, or an empty value for a plain comment.
:::

::: reference
### `getTimeAgo()`

**Returns:** `string`

Returns a human-friendly string of how long ago a comment was made, such as `2 minutes ago`, in the current site's language.
:::

::: reference
### `getExcerpt(startPos, maxLength)`

**Returns:** `string|null`

Returns an excerpt of the comment. `startPos` defaults to `0` and `maxLength` to `100`.
:::

::: reference
### `getPermalink()`

**Returns:** `string|null`

Returns a URL for the comment on the page of its element, which works wherever it is in the thread. This is null if the element doesn't have a URL. See [Linking to a Comment](docs:template-guides/rendering-comments#linking-to-a-comment).
:::

::: reference
### `getReplies()`

**Returns:** `verbb\comments\elements\db\CommentQuery`

Returns a query for the comment's direct replies. It includes every status, so add `.status('approved')` before showing them publicly.

```twig
{% for reply in comment.getReplies().status('approved').all() %}
    <p>{{ reply.getAuthorName() }}: {{ reply.getExcerpt() }}</p>
{% endfor %}
```
:::

::: reference
### `getParentComment()`

**Returns:** `verbb\comments\elements\Comment|null`

Returns the comment this is a reply to, whatever its status, or null for a top-level comment.
:::

::: reference
### `hasFlagged()`

**Returns:** `bool`

Returns true if the current visitor has reported this comment and the report is still open, so you can show that they've already reported it.
:::

::: reference
### `isFlagged()`

**Returns:** `bool`

Returns true if the comment has at least one open report.
:::

::: reference
### `getFlags()`

**Returns:** `int`

Returns the number of open reports on this comment. Reports that a moderator has dealt with, or that their author withdrew, aren't counted.
:::

::: reference
### `isSubscribed()`

**Returns:** `bool`

Returns true if the current visitor is subscribed to replies to this comment.
:::

::: reference
### `getVotes()`

**Returns:** `int|float`

Returns the comment's net score, which is its upvotes minus its downvotes. When **Interaction Mode** only allows likes, use `getUpvotes()` for the number of likes.
:::

::: reference
### `getAllVotes()`

**Returns:** `int`

Returns the number of votes made on this comment, counting upvotes and downvotes.
:::

::: reference
### `getUpvotes()`

**Returns:** `int`

Returns the number of upvotes made on this comment.
:::

::: reference
### `getDownvotes()`

**Returns:** `int`

Returns the number of downvotes made on this comment.
:::

::: reference
### `hasVoted()`

**Returns:** `bool`

Returns true if the current visitor has voted on this comment, or liked it when **Interaction Mode** only allows likes. Signed-in users are recognised by their account, and guests by the browser they voted from. When **Interaction Mode** is None, this is always false.

```twig
{% if comment.hasVoted() %}
    <p>Thanks for your vote.</p>
{% endif %}
```
:::

::: reference
### `hasUpvoted()`

**Returns:** `bool`

Returns true if the current visitor has upvoted or liked this comment.
:::

::: reference
### `hasDownvoted()`

**Returns:** `bool`

Returns true if the current visitor has downvoted this comment. This is always false when **Interaction Mode** only allows likes, even for a downvote made before it changed.

```twig
<button type="button" {{ comment.hasUpvoted() ? 'aria-pressed="true"' }}>Upvote</button>
<button type="button" {{ comment.hasDownvoted() ? 'aria-pressed="true"' }}>Downvote</button>
```
:::

::: reference
### `getAuthor()`

**Returns:** `craft\elements\User|bool`

Returns a Craft User for the author of this comment. See [`author`](#author).
:::

::: reference
### `getUser()`

**Returns:** `craft\elements\User|null`

Returns the signed-in user who wrote this comment. Unlike [`getAuthor()`](#getauthor), this is null for a guest or a deleted user.
:::

::: reference
### `getAuthorName()`

**Returns:** `string|null`

Returns the author's name, for a user or a guest.
:::

::: reference
### `getAuthorEmail()`

**Returns:** `string|null`

Returns the author's email address, for a user or a guest. Take care not to output this on public pages.
:::

::: reference
### `getAvatar()`

**Returns:** `craft\elements\Asset|string`

Returns the author's photo, or otherwise the **Placeholder Avatar**, or an empty string if there's neither. An image whose file is missing is skipped.
:::

::: reference
### `getOwner()`

**Returns:** `craft\base\ElementInterface|null`

Returns the element that owns this comment.
:::


## Permission Methods

These check what the current visitor can do, apart from `canDelete()`, which checks the user you pass to it.

::: reference
### `canReply()`

**Returns:** `bool`

Returns true if the visitor can comment on the element and a reply to this comment wouldn't go deeper than **Maximum Reply Depth**.
:::

::: reference
### `canEdit()`

**Returns:** `bool`

Returns true if the visitor is signed in and wrote this comment, it's within the **Author Edit Window**, and it's approved or pending rather than marked as spam, removed or deleted. Users with the **Edit other users’ comments** permission aren't held to the window for their own comments.
:::

::: reference
### `canDelete(user)`

**Returns:** `bool`

Returns true if `user` can delete this comment. That's anyone with the **Delete comments** permission, or the comment's author within the **Author Edit Window**. Pass `currentUser`, and check it's set first, as guests can't delete comments.
:::

::: reference
### `canVote()`

**Returns:** `bool`

Returns true if **Interaction Mode** isn't None, and the visitor is signed in or **Allow Guest Interactions** is on. Only approved comments accept votes.
:::

::: reference
### `canFlag()`

**Returns:** `bool`

Returns true if **Allow Reporting** is on, and the visitor is signed in or **Allow Guest Reports** is on. Only approved comments accept reports.
:::


## Deleting a Comment

An author can delete their own comment within the **Author Edit Window**, and someone with the **Delete comments** permission can delete anyone's. Deleting hides the comment from the site, and its approved replies stay in place under a placeholder. Send a POST request with the comment's `commentId`:

```twig
{% if currentUser and comment.canDelete(currentUser) %}
    <form method="post" accept-charset="UTF-8">
        {{ actionInput('comments/frontend-api/remove') }}
        {{ csrfInput() }}
        {{ hiddenInput('commentId', comment.id) }}
        {{ hiddenInput('siteId', comment.siteId) }}

        <button type="submit">{{ 'Delete' | t('comments') }}</button>
    </form>
{% endif %}
```

`siteId` is optional when the comment was made on the current site. The visitor returns to the page they were on, where [`craft.comments.render()`](docs:template-guides/rendering-comments) shows “Comment deleted.” or the reason it couldn't be deleted.
