# Mutations

Comments adds GraphQL mutations for posting, editing, voting on, flagging, subscribing to and deleting comments, so a headless front end can offer the same interactions as the default comment form.

## Enable Mutations in Your Schema

Edit the schema under **GraphQL** → **Schemas** and enable the **Comments** mutation permissions you need.

| Permission | Allows |
| --- | --- |
| **Create comments** | Posting new comments with `saveComment`. |
| **Save comments** | Editing existing comments with `saveComment`, and the `voteComment`, `flagComment` and `subscribeComment` mutations. |
| **Delete comments** | Deleting comments with `deleteComment`. |

Comments only adds `voteComment` when voting is enabled in the plugin settings, `flagComment` when flagging is enabled, and `subscribeComment` when subscription notifications are enabled.

Mutations follow the same rules as the front-end form. Guests can only comment, vote or flag when the plugin settings allow guests to, and a guest can’t post using a registered user’s email address. A logged-in user always comments as themselves. Only a user with permission to edit other users’ comments, or a request using a private schema token without a logged-in user, can set `userId` to post as someone else.

## Posting and Editing Comments

### The `saveComment` Mutation

Creates a comment, or updates one when you pass its `id` or `uid`. New comments are set to pending when moderation is required, and approved otherwise. It returns the saved comment as a [Comment](docs:graphql/interfaces-types#the-commentinterface-interface) type.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID` | The ID of the comment to update. Leave it out to create a comment. |
| `uid` | `String` | The UID of the comment to update, as an alternative to `id`. |
| `ownerId` | `ID` | The ID of the element the comment is made on, such as an entry. |
| `siteId` | `ID` | The ID of the site the comment belongs to. |
| `newParentId` | `ID` | The ID of the comment this one replies to. |
| `userId` | `ID` | The ID of the comment’s author. Only trusted callers can set this, as explained above. |
| `name` | `String` | The author’s name, for a guest comment. |
| `email` | `String` | The author’s email address, for a guest comment. |
| `comment` | `String` | The comment text. |
| `commentDate` | `DateTime` | The date the comment was posted. |
| `title` | `String` | The comment’s title. |
| `enabled` | `Boolean` | Whether the comment is enabled. |

Custom fields from the comment form’s layout are also available as arguments, by handle.

This example posts a reply to the comment with an ID of 30, on the entry with an ID of 7.

```graphql
mutation NewComment($ownerId: ID, $newParentId: ID, $name: String, $email: String, $comment: String) {
  saveComment(ownerId: $ownerId, newParentId: $newParentId, name: $name, email: $email, comment: $comment) {
    id
    ownerId
    name
    comment
  }
}
```

Query variables:

```json
{
  "ownerId": 7,
  "newParentId": 30,
  "name": "Jane Smith",
  "email": "jane@example.com",
  "comment": "Here’s a reply to your comment."
}
```

```json
{
  "data": {
    "saveComment": {
      "id": "34",
      "ownerId": 7,
      "name": "Jane Smith",
      "comment": "Here’s a reply to your comment."
    }
  }
}
```

To edit a comment, pass its `id` with the values to change.

```graphql
mutation UpdateComment($id: ID, $comment: String) {
  saveComment(id: $id, comment: $comment) {
    id
    comment
  }
}
```

Query variables:

```json
{
  "id": 34,
  "comment": "I’ve changed what I said."
}
```

```json
{
  "data": {
    "saveComment": {
      "id": "34",
      "comment": "I’ve changed what I said."
    }
  }
}
```

### The `deleteComment` Mutation

Deletes a comment and returns `true`. A user can only delete their own comments. It also returns `true` when no comment has that ID.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID!` | The ID of the comment to delete. |
| `siteId` | `ID` | The ID of the site the comment belongs to. |

```graphql
mutation DeleteComment($id: ID!) {
  deleteComment(id: $id)
}
```

Query variables:

```json
{
  "id": 34
}
```

```json
{
  "data": {
    "deleteComment": true
  }
}
```

## Voting, Flagging and Subscribing

### The `voteComment` Mutation

Records an upvote or downvote on a comment for the current user or guest session, and returns the [Vote](docs:graphql/interfaces-types#the-voteinterface-interface). Pass `upvote: true` to upvote. Anything else records a downvote. Voting the opposite way to an earlier vote cancels the earlier one, so a downvote followed by an upvote leaves no vote.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID!` | The ID of the comment to vote on. |
| `siteId` | `ID` | The ID of the site the comment belongs to. |
| `upvote` | `Boolean` | Whether the vote is an upvote. |
| `downvote` | `Boolean` | Whether the vote is a downvote. |

```graphql
mutation UpvoteComment($id: ID!) {
  voteComment(id: $id, upvote: true) {
    id
    upvote
    downvote
    comment {
      upvotes
      downvotes
    }
  }
}
```

Query variables:

```json
{
  "id": 34
}
```

```json
{
  "data": {
    "voteComment": {
      "id": "12",
      "upvote": true,
      "downvote": null,
      "comment": {
        "upvotes": 5,
        "downvotes": 0
      }
    }
  }
}
```

### The `flagComment` Mutation

Flags a comment for moderation and returns the [Flag](docs:graphql/interfaces-types#the-flaginterface-interface). Flagging a comment you’ve already flagged removes your flag. When a new flag is saved and flag notifications are enabled, the admin notification addresses are emailed.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID!` | The ID of the comment to flag. |
| `siteId` | `ID` | The ID of the site the comment belongs to. |

```graphql
mutation FlagComment($id: ID!) {
  flagComment(id: $id) {
    id
    comment {
      flags
    }
  }
}
```

Query variables:

```json
{
  "id": 34
}
```

```json
{
  "data": {
    "flagComment": {
      "id": "3",
      "comment": {
        "flags": 1
      }
    }
  }
}
```

### The `subscribeComment` Mutation

Subscribes the current user to notifications about new comments on an element, or unsubscribes them if they’re already subscribed. Pass `commentId` to subscribe to a single thread instead, which needs per-comment subscriptions enabled in the plugin settings. It returns a message describing the result.

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `ID!` | The ID of the element to subscribe to, such as an entry. |
| `siteId` | `ID` | The ID of the element’s site. |
| `commentId` | `ID` | The ID of the comment that starts the thread to subscribe to. |

```graphql
mutation SubscribeThread($ownerId: ID!, $commentId: ID) {
  subscribeComment(ownerId: $ownerId, commentId: $commentId)
}
```

Query variables:

```json
{
  "ownerId": 7,
  "commentId": 30
}
```

```json
{
  "data": {
    "subscribeComment": "Subscribed to discussion."
  }
}
```

Run the same mutation again to unsubscribe, which returns `"Unsubscribed from discussion."`.
