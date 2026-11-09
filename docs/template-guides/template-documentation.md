# Template Documentation

Twig calls are explained with the tasks they support, and this page points you to them.

### `craft.comments.render(elementId, options)`

Outputs the complete comments interface. See [Rendering Comments](docs:template-guides/rendering-comments).

### `craft.comments.fetch(criteria)`

Returns a comment query. See [Comment Queries](docs:getting-elements/comment-queries).

### `craft.comments.protect(ownerId, siteId, commentId)`

Outputs the protection fields for your own form. See [Displaying and Protecting Comments](docs:template-guides/displaying-and-protecting-comments).

### `craft.comments.getUserVotes(userId)`, `craft.comments.getUserUpvotes(userId)`, `craft.comments.getUserDownvotes(userId)`

Return a user's votes. See [Finding User Votes](docs:template-guides/finding-user-votes).

### `craft.comments.isSubscribed(element, comment)`

Returns whether the signed-in visitor follows an element's discussion, or replies to a comment when you pass one. See [Using Comment Details Elsewhere](docs:template-guides/rendering-comments#using-comment-details-elsewhere).

### `craft.comments.getSummary(element)`

Returns a summary of an element's discussion, including its `commentCount`, or `null` if it doesn't have one yet. See [Using Comment Details Elsewhere](docs:template-guides/rendering-comments#using-comment-details-elsewhere).

### `craft.comments.getConfig(element)`

Returns the settings that apply to an element's discussion. See [Using Comment Details Elsewhere](docs:template-guides/rendering-comments#using-comment-details-elsewhere).

### `craft.comments.getAvatar()`

Returns an asset for the signed-in visitor's photo, or otherwise the **Placeholder Avatar**, or an empty string if there's neither. See [Using Comment Details Elsewhere](docs:template-guides/rendering-comments#using-comment-details-elsewhere).
