# Flag

Users can flag a comment as inappropriate. Note that only logged in users should be able to flag a comment.

### Attributes

Attribute | Description
--- | ---
`id` | ID of the flag.
`comment` | The [Comment](docs:developers/comment) this flag was made on.
`user` | The [User](https://docs.craftcms.com/api/v3/craft-elements-user.html) for the user who made this flag.

## Flagging a comment

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
