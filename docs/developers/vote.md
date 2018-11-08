# Vote

Users can vote on a comment, which they may agree, or disagree with. Note that only logged in users should be able to vote on a comment.

### Attributes

Attribute | Description
--- | ---
`id` | ID of the vote.
`comment` | The [Comment](/craft-plugins/comments/docs/developers/comment) this vote was made on.
`user` | The [User](https://docs.craftcms.com/api/v3/craft-elements-user.html) for the user who made this vote.
`upvote` | If this vote was recorded as an upvote.
`downvote` | If this vote was recorded as an downvote.