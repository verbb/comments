# Vote

A vote is someone’s upvote or downvote on a comment. Voting is turned on with the [allowVoting](docs:get-started/configuration#allowvoting) setting, and guests can only vote when [allowGuestVoting](docs:get-started/configuration#allowguestvoting) is also on. In templates, you’ll usually show a comment’s votes through its `getVotes()`, `getUpvotes()` and `getDownvotes()` methods on the [Comment](docs:reference/comment) rather than with `Vote` objects. Vote events and the `voteComment` GraphQL mutation give you a `Vote`.

## Properties

::: reference
### `id`

**Type:** `int|null`

The ID of the vote.
:::

::: reference
### `commentId`

**Type:** `int|null`

The ID of the comment the vote was made on.
:::

::: reference
### `userId`

**Type:** `int|null`

The ID of the user who voted. It’s null when a guest voted.
:::

::: reference
### `sessionId`

**Type:** `string|null`

The ID of the session the vote was made from, which identifies a guest’s votes.
:::

::: reference
### `lastIp`

**Type:** `string|null`

The IP address the vote was made from. It’s only stored when Craft’s `storeUserIps` setting is on.
:::

::: reference
### `upvote`

**Type:** `int|null`

A value of `1` when the vote is an upvote, and null otherwise.
:::

::: reference
### `downvote`

**Type:** `int|null`

A value of `1` when the vote is a downvote, and null otherwise.
:::

## Methods

::: reference
### `getComment()`

**Returns:** `verbb\comments\elements\Comment|null`

Returns the [Comment](docs:reference/comment) the vote was made on.
:::

::: reference
### `getUser()`

**Returns:** `craft\elements\User|null`

Returns the user who voted, or null for a guest.
:::

## Upvoting a Comment
You can upvote a comment using a POST request to an action controller, and the following template code. You must supply a `commentId`, `siteId` and `upvote` in your form.

```twig
<form role="form" method="post" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/comments/vote">
    <input type="hidden" name="siteId" value="{{ comment.siteId }}">
    <input type="hidden" name="commentId" value="{{ comment.id }}">
    <input type="hidden" name="upvote" value="true">
    {{ csrfInput() }}

    <button type="submit">{{ 'Upvote' | t('comments') }}</button>
</form>
```

## Downvoting a Comment
You can downvote a comment using a POST request to an action controller, and the following template code. You must supply a `commentId`, `siteId` and `downvote` in your form.

```twig
<form role="form" method="post" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/comments/vote">
    <input type="hidden" name="siteId" value="{{ comment.siteId }}">
    <input type="hidden" name="commentId" value="{{ comment.id }}">
    <input type="hidden" name="downvote" value="true">
    {{ csrfInput() }}

    <button type="submit">{{ 'Downvote' | t('comments') }}</button>
</form>
```
