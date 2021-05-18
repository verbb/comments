# GraphQL

Comments supports accessing comments via GraphQL. Be sure to read about [Craft's GraphQL support](https://docs.craftcms.com/v3/graphql.html).

## Example query and response

### Query payload

```
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        name
        email
        comment
    }
}
```

### The response

```
{
    "data": {
        "comments": [
            {
                "commentDate": "2019-01-23",
                "name": "Test User",
                "email": "some-user@gmail.com",
                "comment": "Hey there, this is a great article, love your work!"
            },
            {
                "commentDate": "2019-01-23",
                "name": "John Smith",
                "email": "john-user@gmail.com",
                "comment": "Thanks, appreciate your comments! 😀"
            },
        ]
    }
}
```

### The `comments` query
This query is used to query for comments.

#### The `id` argument
Narrows the query results based on the elements’ IDs.

#### The `uid` argument
Narrows the query results based on the elements’ UIDs.

#### The `status` argument
Narrows the query results based on the elements’ statuses.

#### The `archived` argument
Narrows the query results to only elements that have been archived.

#### The `trashed` argument
Narrows the query results to only elements that have been soft-deleted.

#### The `site` argument
Determines which site(s) the elements should be queried in. Defaults to the primary site.

#### The `siteId` argument
Determines which site(s) the elements should be queried in. Defaults to the primary site.

#### The `unique` argument
Determines whether only elements with unique IDs should be returned by the query.

#### The `enabledForSite` argument
Narrows the query results based on whether the elements are enabled in the site they’re being queried in, per the `site` argument.

#### The `search` argument
Narrows the query results to only elements that match a search query.

#### The `relatedTo` argument
Narrows the query results to elements that relate to *any* of the provided element IDs. This argument is ignored, if `relatedToAll` is also used.

#### The `relatedToAll` argument
Narrows the query results to elements that relate to *all* of the provided element IDs. Using this argument will cause `relatedTo` argument to be ignored.

#### The `fixedOrder` argument
Causes the query results to be returned in the order specified by the `id` argument.

#### The `inReverse` argument
Causes the query results to be returned in reverse order.

#### The `dateCreated` argument
Narrows the query results based on the elements’ creation dates.

#### The `dateUpdated` argument
Narrows the query results based on the elements’ last-updated dates.

#### The `offset` argument
Sets the offset for paginated results.

#### The `limit` argument
Sets the limit for paginated results.

#### The `orderBy` argument
Sets the field the returned elements should be ordered by

#### The `withStructure` argument
Explicitly determines whether the query should join in the structure data.

#### The `structureId` argument
Determines which structure data should be joined into the query.

#### The `level` argument
Narrows the query results based on the elements’ level within the structure.

#### The `hasDescendants` argument
Narrows the query results based on whether the elements have any descendants.

#### The `ancestorOf` argument
Narrows the query results to only elements that are ancestors of another element.

#### The `ancestorDist` argument
Narrows the query results to only elements that are up to a certain distance away from the element specified by `ancestorOf`.

#### The `descendantOf` argument
Narrows the query results to only elements that are descendants of another element.

#### The `descendantDist` argument
Narrows the query results to only elements that are up to a certain distance away from the element specified by `descendantOf`.

#### The `leaves` argument
Narrows the query results based on whether the elements are “leaves” (element with no descendants).

#### The `editable` argument
Whether to only return comments that the user has permission to edit.

#### The `ownerId` argument
Narrows the query results based on the owner element the comment was made on, per the owners’ IDs.

#### The `commentDate` argument
Narrows the query results based on the comments’ commented date.

#### The `name` argument
Narrows the query results based on the comments’ full name.

#### The `email` argument
Narrows the query results based on the comments’ email.

#### The `comment` argument
Narrows the query results based on the comments’ actual comment text.

### Custom Fields

If you’ve added custom fields to your Comments Form (**Comments** → **Settings** → **Comments Form** → **Form Layout**), you can query them by handle within a GraphQL fragment. If you added a `summary` Plain Text field, for example, your query might look like this:

```graphql
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        name
        email
        comment
        ... on Comment {
            summary
        }
    }
}
```

## Mutations

### createComment

Saves a new nested visitor comment.

```graphql
mutation NewComment($newParentId: ID, $ownerId: ID, $name: String, $email: String, $comment: String) {
  saveComment(newParentId: $newParentId, ownerId: $ownerId, name: $name, email: $email, comment: $comment) {
    id
    ownerId
    name
    email
    comment
  }
}
```

Query variables:

```json
{
  "ownerId": 7,
  "newParentId": 30,
  "name": "Matt",
  "email": "matt@pixelandtonic.com",
  "comment": "Here’s a nested comment."
}
```

### saveComment

Edit an existing comment.

```graphql
mutation UpdateComment($id: ID, $comment: String) {
  saveComment(id: $id, comment: $comment) {
    id
    ownerId
    name
    email
    comment
  }
}
```

Query variables:

```json
{
  "id": 34,
  "comment": "I’m totally changing what I said."
}
```

### voteComment

Upvote a comment:

```graphql
mutation UpvoteComment($id: ID, $comment: String) {
  voteComment(id: $id, comment: $comment) {
    id
    ownerId
    name
    email
    comment
  }
}
```

Query variables:

```json
{
  "id": 34,
  "upvote": true
}
```

Downvote a comment:

```graphql
mutation DownvoteComment($id: ID, $comment: String) {
  voteComment(id: $id, comment: $comment) {
    id
    ownerId
    name
    email
    comment
  }
}
```

Query variables:

```json
{
  "id": 34,
  "downvote": true
}
```

### flagComment

Flag a comment for moderation:

```graphql
mutation FlagComment($id: ID!) {
    flagComment(id: $id) {
        id
        comment {
            flags
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

### subscribeComment

Subscribe to an element’s comment notifications:

```graphql
mutation SubscribeComment($ownerId: ID!) {
    subscribeComment(ownerId: $ownerId)
}
```

Repeat to toggle on/off.

Query variables:

```json
{
  "ownerId": 34
}
```

Subscribe to a specific comment thread, where `commentId` is the beginning of that thread:

```graphql
mutation SubscribeThread($ownerId: ID!, $commentId: ID) {
    subscribeComment(ownerId: $ownerId, commentId: $commentId)
}
```

Repeat to toggle on/off.

Query variables:

```json
{
  "ownerId": 34,
  "commentId": 95
}
```

### deleteComment

Delete a comment:

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