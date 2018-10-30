# Flag Model

Users can flag a comment as inappropriate. Note that only logged in users should be able to flag a comment.

### Attributes

Attribute | Description
--- | ---
`id` | ID of the flag.
`comment` | The [CommentModel](/craft-plugins/comments/docs/developers/comment-model) this flag was made on.
`user` | The [UserModel](https://craftcms.com/docs/templating/usermodel) for the user who made this flag.
