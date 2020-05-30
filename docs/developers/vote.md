# Vote

Users can vote on a comment, which they may agree, or disagree with. Note that only logged in users should be able to vote on a comment.

### Attributes

Attribute | Description
--- | ---
`id` | ID of the vote.
`comment` | The [Comment](docs:developers/comment) this vote was made on.
`user` | The [User](https://docs.craftcms.com/api/v3/craft-elements-user.html) for the user who made this vote.
`upvote` | If this vote was recorded as an upvote.
`downvote` | If this vote was recorded as an downvote.

## Up-vote a comment

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

## Down-vote a comment

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
