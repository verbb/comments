# Comment

Whenever you’re dealing with a comment in your template, you’re working with a `Comment` object. You get one from a [comment query](docs:getting-elements/comment-queries), or as `comment` inside the default comment templates.

```twig
{% set comments = craft.comments.fetch().owner(entry).all() %}

{% for comment in comments %}
    <p>{{ comment.author.fullName }}, {{ comment.getTimeAgo() }}</p>
    <p>{{ comment.comment }}</p>
{% endfor %}
```

<span id="attributes"></span>

## Properties

::: reference
### `id`

**Type:** `int|null`

The ID of the comment.
:::

::: reference
### `ownerId`

**Type:** `int|null`

The ID of the element the comment was made on, such as an entry or asset.
:::

::: reference
### `ownerSiteId`

**Type:** `int|null`

The ID of the site of the element the comment was made on.
:::

::: reference
### `owner`

**Type:** `craft\base\ElementInterface|null`

The element the comment was made on, such as an entry or asset. It’s null if that element has been deleted. This is the same as calling `getOwner()`.
:::

::: reference
### `userId`

**Type:** `int|null`

The ID of the user who posted the comment. It’s null for a guest.
:::

::: reference
### `author`

**Type:** `craft\elements\User`

The [user](https://craftcms.com/docs/5.x/reference/element-types/users.html) who posted the comment. For a guest, it’s a new user holding the name and email address they entered, and for a user who has since been deleted, it’s a new user named “[Deleted User]”. This is the same as calling `getAuthor()`.
:::

::: reference
### `parent`

**Type:** `verbb\comments\elements\Comment|null`

The comment this one replies to. It’s null for a top-level comment.
:::

::: reference
### `level`

**Type:** `int|null`

The depth of the comment in its thread. Top-level comments are at level 1 and replies to them are at level 2.
:::

::: reference
### `status`

**Type:** `string|null`

The comment’s status, which is `approved`, `pending`, `spam` or `trashed`.
:::

::: reference
### `name`

**Type:** `string|null`

The name a guest entered when posting the comment. It’s null for comments from logged-in users, so use `author.fullName` to show a name for every comment.
:::

::: reference
### `email`

**Type:** `string|null`

The email address a guest entered when posting the comment. Take care not to output it on public pages.
:::

::: reference
### `comment`

**Type:** `string|null`

The comment’s text.
:::

::: reference
### `gifUrl`

**Type:** `string|null`

The URL of the GIF attached to the comment. It’s null when the comment has no GIF.
:::

::: reference
### `url`

**Type:** `string|null`

The URL of the page the comment was posted from.
:::

::: reference
### `ipAddress`

**Type:** `string|null`

The IP address the comment was posted from.
:::

::: reference
### `userAgent`

**Type:** `string|null`

The user agent of the browser the comment was posted from.
:::

::: reference
### `commentDate`

**Type:** `DateTime|null`

The date the comment was posted.
:::

## Methods

::: reference
### `getAuthor()`

**Returns:** `craft\elements\User`

Returns the user who posted the comment. For a guest or a deleted user, it returns a new user, as described under `author`.
:::

::: reference
### `getOwner()`

**Returns:** `craft\base\ElementInterface|null`

Returns the element the comment was made on, or null if it has been deleted.

```twig
<a href="{{ comment.getOwner().url }}">{{ comment.getOwner().title }}</a>
```
:::

::: reference
### `getExcerpt()`

**Arguments:** `startPos` (`int`, default `0`), `maxLength` (`int`, default `100`)

**Returns:** `string|null`

Returns the start of the comment’s text, cut at the last whole word before the length limit and ending in `...`. A comment shorter than the limit is returned in full.

```twig
{{ comment.getExcerpt(0, 50) }}
```
:::

::: reference
### `getTimeAgo()`

**Returns:** `string`

Returns how long ago the comment was posted, as a short phrase such as “2 min ago”.
:::

::: reference
### `isGuest()`

**Returns:** `bool`

Returns whether the comment was posted by a guest rather than a logged-in user.
:::

::: reference
### `getFlags()`

**Returns:** `int`

Returns the number of times the comment has been flagged.
:::

::: reference
### `isFlagged()`

**Returns:** `bool`

Returns whether the comment has reached the number of flags set by the [flaggedCommentLimit](docs:get-started/configuration#flaggedcommentlimit) setting.
:::

::: reference
### `hasFlagged()`

**Returns:** `bool`

Returns whether the current user, or guest session, has already flagged the comment.
:::

::: reference
### `getVotes()`

**Returns:** `int`

Returns the comment’s score, which is its upvotes minus its downvotes.
:::

::: reference
### `getAllVotes()`

**Returns:** `int`

Returns the total number of votes on the comment, counting upvotes and downvotes together.
:::

::: reference
### `getUpvotes()`

**Returns:** `int`

Returns the number of upvotes on the comment.
:::

::: reference
### `getDownvotes()`

**Returns:** `int`

Returns the number of downvotes on the comment.
:::

::: reference
### `isPoorlyRated()`

**Returns:** `bool`

Returns whether the comment has reached the number of downvotes set by the [downvoteCommentLimit](docs:get-started/configuration#downvotecommentlimit) setting.
:::

## Permission Methods

These methods check what the current user, or guest, can do with the comment, so you can show only the controls that will work.

::: reference
### `canReply()`

**Returns:** `bool`

Returns whether the current user can reply to the comment. It checks the same rules as posting a comment, plus the [maxReplyDepth](docs:get-started/configuration#maxreplydepth) setting.
:::

::: reference
### `canEdit()`

**Returns:** `bool`

Returns whether the current user can edit the comment, which is only allowed for its logged-in author.
:::

::: reference
### `canTrash()`

**Returns:** `bool`

Returns whether the current user can delete the comment, which is only allowed for its logged-in author.
:::

::: reference
### `canVote()`

**Returns:** `bool`

Returns whether the current user can vote on the comment. Voting needs to be enabled, guests need guest voting enabled, and a poorly rated comment can hide voting when [hideVotingForThreshold](docs:get-started/configuration#hidevotingforthreshold) is on.
:::

::: reference
### `canFlag()`

**Returns:** `bool`

Returns whether the current user can flag the comment. Flagging needs to be enabled, and guests need guest flagging enabled.
:::

::: reference
### `can()`

**Arguments:** `property` (`string`)

**Returns:** `bool`

Returns whether the current user can perform an action on the comment, as a shorthand for the methods above. Pass `reply`, `edit`, `trash`, `vote` or `flag`. You can also pass the name of a [plugin setting](docs:get-started/configuration), which returns that setting’s value as `true` or `false`.

```twig
{% if comment.can('reply') %}
    <a href="#reply-{{ comment.id }}">Reply</a>
{% endif %}
```
:::

## Deleting a Comment

You can delete a comment using a POST request to an action controller, and the following template code. You must supply a `commentId` and `siteId` in your form.

```twig
<form role="form" method="post" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/comments/trash">
    <input type="hidden" name="siteId" value="{{ comment.siteId }}">
    <input type="hidden" name="commentId" value="{{ comment.id }}">
    {{ csrfInput() }}

    <button type="submit">{{ 'Delete' | t('comments') }}</button>
</form>
```
