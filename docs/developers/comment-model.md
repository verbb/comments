# Comment Model

CommentModel’s have the following attributes and methods:

### Attributes

Attribute | Description
--- | ---
`id` | ID of the comment.
`element` | The element this comment was made on (Entry, Asset, etc).
`author` | [UserModel](https://craftcms.com/docs/templating/usermodel) for the author of a comment. For anonymous, this will still return a new UserModel, with their email, first/last name attributes populated.
`parent` | CommentModel of comment responding to. Only applicable when replying to another comment. For new comments, this will be null.
`status` | The status of this comment. Available values are `approved`, `pending`, `spam`, `trashed`.
`name` | Name of the commenter. Anonymous users only.
`email` | Email address of the commenter. Anonymous users only.
`url` | The URL that this comment was made from.
`ipAddress` | Commenters IP Address.
`userAgent` | Commenters User Agent.
`comment` | The comment text.
`flags` | A collection of [FlagModel’s](/craft-plugins/comments/docs/developers/flag-model) for this comment.
`votes` | A collection of [VoteModel’s](/craft-plugins/comments/docs/developers/vote-model) for this comment.
`voteCount` | The total number of votes for this comment. Takes into account downvotes and upvotes.

### Methods

Attribute | Description
--- | ---
`canEdit()` | Returns true/false if the current user can edit this comment.
`canTrash()` | Returns true/false if the current user can trash this comment.
`isGuest()` | Returns true/false if a comment was made by an anonymous user.
`isClosed()` | Comments can be closed for an element, allowing existing comments to be visible, but no new comments to be made. Editing, deleting and replying are disabled. This may also return true if you’ve set a value for ‘Auto-close comments’ in the plugin settings.
`isFlagged()` | If a comment receives more than a certain amount of flags, `isFlagged` will be true. This limit is configurable through the plugin settings.
`canVote()` | Returns true if the user can vote on this comment. Must be a registered user and cannot be their own comment.
`canUpVote()` | Checks `canVote` first, then checks to see if this user has already upvoted.
`canDownVote()` | Checks `canVote` first, then checks to see if this user has already downvoted.
`isPoorlyRated()` | If a comment receives more than a certain amount of downvotes, `isPoorlyRated` will be true. This limit is configurable through the plugin settings.

### Actions

Action | Description
--- | ---
`trashActionUrl` | The url action end-point to trash a comment. This can be called directly, or via Ajax.
`flagActionUrl` | The url action end-point to record a flag on a comment. This can be called directly, or via Ajax.
`upvoteActionUrl` | The url action end-point to upvote a comment. This can be called directly, or via Ajax.
`downvoteActionUrl` | The url action end-point to downvote a comment. This can be called directly, or via Ajax.
