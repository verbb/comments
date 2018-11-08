# Comment

Whenever you're dealing with a comment in your template, you're actually working with a `Comment` object.

### Attributes

Attribute | Description
--- | ---
`id` | ID of the comment.
`ownerId` | The element this comment was made on (Entry, Asset, etc).
`owner` | The element this comment was made on (Entry, Asset, etc).
`userId` | [User](https://docs.craftcms.com/api/v3/craft-elements-user.html) ID for the author of a comment.
`author` | [User](https://docs.craftcms.com/api/v3/craft-elements-user.html) for the author of a comment. For anonymous, this will still return a new User, with their email, first/last name attributes populated.
`parent` | Comment object of any parent. Only applicable when replying to another comment. For new comments, this will be null.
`status` | The status of this comment. Available values are `approved`, `pending`, `spam`, `trashed`.
`name` | Name of the commenter. Anonymous users only.
`email` | Email address of the commenter. Anonymous users only.
`url` | The URL that this comment was made from.
`ipAddress` | Commenters IP Address.
`userAgent` | Commenters User Agent.
`comment` | The comment text.
`flags` | A collection of [Flag](/craft-plugins/comments/docs/developers/flag) objects for this comment.
`votes` | A collection of [Vote](/craft-plugins/comments/docs/developers/vote) objects for this comment.

### Methods

Method | Description
--- | ---
`isGuest()` | Returns true/false if a comment was made by an anonymous user.
`getTimeAgo()` | Returns a human-friendly string of how long ago a comment was made, ie: `2 min ago`.
`getExcerpt($startPos = 0, $maxLength = 100)` | Returns an excerpt of the comment. You can also supply parameters to control length.
`hasFlagged()` | Whether the user has already flagged a comment.
`isFlagged()` | If a comment receives more than a certain amount of flags, `isFlagged` will be true. This limit is configurable through the plugin settings.
`isPoorlyRated()` | If a comment receives more than a certain amount of downvotes, `isPoorlyRated` will be true. This limit is configurable through the plugin settings.

### Permission Methods

There are a number of methods for checking if the commenter can do certain tasks. Additionally, you can pass in any [Configuration](/craft-plugins/comments/docs/get-started/configuration) settings to test against.

Method | Description
--- | ---
`canReply()` | Returns true/false if the current user can reply to other comments.
`canEdit()` | Returns true/false if the current user can edit this comment.
`canTrash()` | Returns true/false if the current user can trash this comment.
`canVote()` | Returns true if the user can vote on a comment. Must be a registered user and cannot be their own comment.
`canFlag()` | Returns true if the user can flag a comment. Must be a registered user and cannot be their own comment.

You can also use a shorthand method if you prefer:

```twig
{{ can('reply') }}
{{ can('edit') }}
{{ can('trash') }}
{{ can('vote') }}
{{ can('flag') }}
```

### Action URLs

There are a number of controller endpoints for various tasks related to commenting.

URL | Description
--- | ---
`trashUrl` | The url action end-point to trash a comment. This can be called directly, or via Ajax.
`flagUrl` | The url action end-point to record a flag on a comment. This can be called directly, or via Ajax.
`upvoteUrl` | The url action end-point to upvote a comment. This can be called directly, or via Ajax.
`downvoteUrl` | The url action end-point to downvote a comment. This can be called directly, or via Ajax.
