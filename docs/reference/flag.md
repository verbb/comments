# Flag

A flag is a report that a comment is inappropriate. Flagging is turned on with the [allowFlagging](docs:get-started/configuration#allowflagging) setting, and guests can only flag comments when [allowGuestFlagging](docs:get-started/configuration#allowguestflagging) is also on. In templates, you’ll usually work with a comment’s flags through its `getFlags()`, `isFlagged()` and `hasFlagged()` methods on the [Comment](docs:reference/comment) rather than with `Flag` objects. Flag events and the `flagComment` GraphQL mutation give you a `Flag`.

## Properties

::: reference
### `id`

**Type:** `int|null`

The ID of the flag.
:::

::: reference
### `commentId`

**Type:** `int|null`

The ID of the comment that was flagged.
:::

::: reference
### `userId`

**Type:** `int|null`

The ID of the user who flagged the comment. It’s null when a guest flagged it.
:::

::: reference
### `sessionId`

**Type:** `int|null`

The ID of the session the flag was made from, which identifies a guest’s flags.
:::

::: reference
### `lastIp`

**Type:** `string|null`

The IP address the flag was made from. It’s only stored when Craft’s `storeUserIps` setting is on.
:::

## Methods

::: reference
### `getComment()`

**Returns:** `verbb\comments\elements\Comment|null`

Returns the [Comment](docs:reference/comment) that was flagged.
:::

## Flagging a Comment

You can flag a comment using a POST request to an action controller, and the following template code. You must supply a `commentId` and `siteId` in your form.

```twig
<form role="form" method="post" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/comments/flag">
    <input type="hidden" name="siteId" value="{{ comment.siteId }}">
    <input type="hidden" name="commentId" value="{{ comment.id }}">
    {{ csrfInput() }}

    <button type="submit">{{ 'Flag' | t('comments') }}</button>
</form>
```
