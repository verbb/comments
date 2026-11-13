# Interfaces & Types

These are the GraphQL interfaces and types Comments adds. The [Queries](docs:graphql/queries) and [Mutations](docs:graphql/mutations) pages show them in use.

## Comments

### The `CommentInterface` Interface

The interface implemented by every comment. Comments use a single type, `Comment`, which also holds any custom fields from the comment form’s layout. Request those inside a `... on Comment` fragment, as shown in [Querying Custom Fields](docs:graphql/queries#querying-custom-fields).

As well as the fields below, the interface includes Craft’s standard element and structure fields, such as `id`, `uid`, `status`, `siteId`, `dateCreated`, `dateUpdated`, `level`, `prev` and `next`.

```graphql
{
  comments(ownerId: 2460, limit: 1) {
    id
    name
    comment
    gifUrl
    votes
    flags
    user {
      fullName
    }
  }
}
```

::: reference
#### `ownerId`

**Type:** `Int`

The ID of the element the comment was made on.
:::

::: reference
#### `commentDate`

**Type:** `DateTime`

The date the comment was posted. Use Craft’s `@formatDateTime` directive to format it.
:::

::: reference
#### `comment`

**Type:** `String`

The comment text.
:::

::: reference
#### `gifUrl`

**Type:** `String`

The URL of the GIF attached to the comment, or `null` when there isn’t one.
:::

::: reference
#### `name`

**Type:** `String`

The name of the comment’s author.
:::

::: reference
#### `email`

**Type:** `String`

The email address a guest posted the comment with. It’s `null` for comments posted by a logged-in user, whose address belongs to their user account. A public schema always returns `null`, so query `email` with a private schema’s token from your own server.
:::

::: reference
#### `url`

**Type:** `String`

The URL of the page the comment was posted from.
:::

::: reference
#### `children`

**Type:** `[CommentInterface]`

The replies to the comment. It accepts the same arguments as the `comments` query.
:::

::: reference
#### `parent`

**Type:** `CommentInterface`

The comment this one replies to, or `null` for a top-level comment.
:::

::: reference
#### `votes`

**Type:** `Int`

The comment’s score, its upvotes minus its downvotes.
:::

::: reference
#### `upvotes`

**Type:** `Int`

The number of upvotes the comment has.
:::

::: reference
#### `downvotes`

**Type:** `Int`

The number of downvotes the comment has.
:::

::: reference
#### `flags`

**Type:** `Int`

The number of times the comment has been flagged.
:::

::: reference
#### `userId`

**Type:** `Int`

The ID of the user who posted the comment, or `null` for a guest. It’s only available when the schema can view users.
:::

::: reference
#### `user`

**Type:** `User`

The user who posted the comment. It’s only available when the schema can view users.
:::

## Votes and Flags

### The `VoteInterface` Interface

The interface implemented by the `Vote` type, which the [voteComment](docs:graphql/mutations#the-votecomment-mutation) mutation returns.

```graphql
mutation UpvoteComment($id: ID!) {
  voteComment(id: $id, upvote: true) {
    id
    upvote
    downvote
    comment {
      votes
    }
  }
}
```

::: reference
#### `id`

**Type:** `ID`

The ID of the vote.
:::

::: reference
#### `sessionId`

**Type:** `ID`

The ID of the session the vote was made from.
:::

::: reference
#### `lastIp`

**Type:** `String`

The IP address the vote was made from. Comments only stores it when Craft’s `storeUserIps` setting is enabled.
:::

::: reference
#### `upvote`

**Type:** `Boolean`

Whether the vote is an upvote.
:::

::: reference
#### `downvote`

**Type:** `Boolean`

Whether the vote is a downvote.
:::

::: reference
#### `commentId`

**Type:** `ID`

The ID of the comment the vote was made on.
:::

::: reference
#### `comment`

**Type:** `CommentInterface`

The comment the vote was made on.
:::

::: reference
#### `userId`

**Type:** `Int`

The ID of the user who voted, or `null` for a guest. It’s only available when the schema can view users.
:::

::: reference
#### `user`

**Type:** `User`

The user who voted. It’s only available when the schema can view users.
:::

### The `FlagInterface` Interface

The interface implemented by the `Flag` type, which the [flagComment](docs:graphql/mutations#the-flagcomment-mutation) mutation returns.

```graphql
mutation FlagComment($id: ID!) {
  flagComment(id: $id) {
    id
    commentId
    comment {
      flags
    }
  }
}
```

::: reference
#### `id`

**Type:** `ID`

The ID of the flag.
:::

::: reference
#### `sessionId`

**Type:** `ID`

The ID of the session the flag was made from.
:::

::: reference
#### `lastIp`

**Type:** `String`

The IP address the flag was made from. Comments only stores it when Craft’s `storeUserIps` setting is enabled.
:::

::: reference
#### `commentId`

**Type:** `ID`

The ID of the comment that was flagged.
:::

::: reference
#### `comment`

**Type:** `CommentInterface`

The comment that was flagged.
:::
