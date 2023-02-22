# GraphQL
Comments supports accessing [Comment](docs:developers/comment) objects via GraphQL. Be sure to read about [Craft's GraphQL support](https://craftcms.com/docs/4.x/graphql.html).

## Comments

### Example

:::code
```graphql GraphQL
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        name
        email
        comment
    }
}
```

```json JSON Response
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
:::

### The `comments` query
This query is used to query for [Comment](docs:developers/comment) objects. You can also use the singular `comment` to fetch a single node.

| Argument | Type | Description
| - | - | -
| `id`| `[QueryArgument]` | Narrows the query results based on the elements’ IDs.
| `uid`| `[String]` | Narrows the query results based on the elements’ UIDs.
| `status`| `[String]` | Narrows the query results based on the elements’ statuses.
| `archived`| `Boolean` | Narrows the query results to only elements that have been archived.
| `trashed`| `Boolean` | Narrows the query results to only elements that have been soft-deleted.
| `site`| `[String]` | Determines which site(s) the elements should be queried in. Defaults to the current (requested) site.
| `siteId`| `[QueryArgument]` | Determines which site(s) the elements should be queried in. Defaults to the current (requested) site.
| `unique`| `Boolean` | Determines whether only elements with unique IDs should be returned by the query.
| `preferSites`| `[QueryArgument]` | Determines which site should be selected when querying multi-site elements.
| `enabledForSite`| `Boolean` | Narrows the query results based on whether the elements are enabled in the site they’re being queried in, per the `site` argument.
| `search`| `String` | Narrows the query results to only elements that match a search query.
| `relatedTo`| `[QueryArgument]` | Narrows the query results to elements that relate to the provided element IDs. This argument is ignored, if `relatedToAll` is also used.
| `relatedToAssets`| `[AssetCriteriaInput]` | Narrows the query results to elements that relate to an asset list defined with this argument.
| `relatedToEntries`| `[EntryCriteriaInput]` | Narrows the query results to elements that relate to an entry list defined with this argument.
| `relatedToUsers`| `[UserCriteriaInput]` | Narrows the query results to elements that relate to a use list defined with this argument.
| `relatedToCategories`| `[CategoryCriteriaInput]` | Narrows the query results to elements that relate to a category list defined with this argument.
| `relatedToTags`| `[TagCriteriaInput]` | Narrows the query results to elements that relate to a tag list defined with this argument.
| `relatedToAll`| `[QueryArgument]` | Narrows the query results to elements that relate to *all* of the provided element IDs. Using this argument will cause `relatedTo` argument to be ignored. **This argument is deprecated.** `relatedTo: ["and", ...ids]` should be used instead.
| `fixedOrder`| `Boolean` | Causes the query results to be returned in the order specified by the `id` argument.
| `inReverse`| `Boolean` | Causes the query results to be returned in reverse order.
| `dateCreated`| `[String]` | Narrows the query results based on the elements’ creation dates.
| `dateUpdated`| `[String]` | Narrows the query results based on the elements’ last-updated dates.
| `offset`| `Int` | Sets the offset for paginated results.
| `limit`| `Int` | Sets the limit for paginated results.
| `orderBy`| `String` | Sets the field the returned elements should be ordered by.
| `siteSettingsId`| `[QueryArgument]` | Narrows the query results based on the unique identifier for an element-site relation.
| `withStructure`| `Boolean` | Explicitly determines whether the query should join in the structure data.
| `structureId`| `Int` | Determines which structure data should be joined into the query.
| `level`| `Int` | Narrows the query results based on the elements’ level within the structure.
| `hasDescendants`| `Boolean` | Narrows the query results based on whether the elements have any descendants in their structure.
| `ancestorOf`| `Int` | Narrows the query results to only elements that are ancestors of another element in its structure, provided by its ID.
| `ancestorDist`| `Int` | Narrows the query results to only elements that are up to a certain distance away from the element in its structure specified by `ancestorOf`.
| `descendantOf`| `Int` | Narrows the query results to only elements that are descendants of another element in its structure provided by its ID.
| `descendantDist`| `Int` | Narrows the query results to only elements that are up to a certain distance away from the element in its structure specified by `descendantOf`.
| `leaves`| `Boolean` | Narrows the query results based on whether the elements are “leaves” in their structure (element with no descendants).
| `nextSiblingOf`| `Int` | Narrows the query results to only the entry that comes immediately after another element in its structure, provided by its ID.
| `prevSiblingOf`| `Int` | Narrows the query results to only the entry that comes immediately before another element in its structure, provided by its ID.
| `positionedAfter`| `Int` | Narrows the query results to only entries that are positioned after another element in its structure, provided by its ID.
| `positionedBefore`| `Int` | Narrows the query results to only entries that are positioned before another element in its structure, provided by its ID.
| `ownerId`| `Int` | Narrows the query results based on the owner element the comment was made on, per the owners’ IDs.
| `commentDate`| `String` | Narrows the query results based on the comments’ commented date.
| `name`| `String` | Narrows the query results based on the comments’ full name.
| `email`| `String` | Narrows the query results based on the comments’ email.
| `comment`| `String` | Narrows the query results based on the comments’ actual comment text.

### The `CommentInterface` interface
This is the interface implemented by all comments.

| Field | Type | Description
| - | - | -
| `id`| `ID` | The ID of the entity.
| `uid`| `String` | The UID of the entity.
| `_count`| `Int` | Return a number of related elements for a field.
| `title`| `String` | The element’s title.
| `slug`| `String` | The element’s slug.
| `uri`| `String` | The element’s URI.
| `enabled`| `Boolean` | Whether the element is enabled or not.
| `archived`| `Boolean` | Whether the element is archived or not.
| `siteId`| `Int` | The ID of the site the element is associated with.
| `siteSettingsId`| `ID` | The unique identifier for an element-site relation.
| `language`| `String` | The language of the site element is associated with.
| `searchScore`| `String` | The element’s search score, if the `search` parameter was used when querying for the element.
| `trashed`| `Boolean` | Whether the element has been soft-deleted or not.
| `status`| `String` | The element’s status.
| `dateCreated`| `DateTime` | The date the element was created.
| `dateUpdated`| `DateTime` | The date the element was last updated.
| `lft`| `Int` | The element’s left position within its structure.
| `rgt`| `Int` | The element’s right position within its structure.
| `level`| `Int` | The element’s level within its structure
| `root`| `Int` | The element’s structure’s root ID.
| `structureId`| `Int` | The element’s structure ID.
| `children`| `[CommentInterface]` | The node’s children.
| `parent`| `CommentInterface` | The node’s parent.
| `prev`| `CommentInterface` | Returns the previous element relative to this one, from a given set of criteria.
| `next`| `CommentInterface` | Returns the next element relative to this one, from a given set of criteria.
| `ownerId`| `Int` | The ID of the element that owns the comment.
| `commentDate`| `DateTime` | The comment's post date.
| `comment`| `String` | The actual comment text.
| `name`| `String` | The full name for the comment’s author.
| `email`| `String` | The email for the comment’s author.
| `url`| `String` | The url the comment was made on.
| `votes`| `Int` | The number of total votes for this comment.
| `upvotes`| `Int` | The number of upvotes for this comment.
| `downvotes`| `Int` | The number of downvotes for this comment.
| `flags`| `Int` | The number of flags for this comment.

### The `FlagInterface` interface
This is the interface implemented by all flags.

| Field | Type | Description
| - | - | -
| `id`| `ID` | The ID of the entity.
| `sessionId`| `ID` | The session ID from which the vote was submitted.
| `lastIp`| `String` | The last known IP address of the voter.

### The `VoteInterface` interface
This is the interface implemented by all votes.

| Field | Type | Description
| - | - | -
| `id`| `ID` | The ID of the entity.
| `sessionId`| `ID` | The session ID from which the vote was submitted.
| `lastIp`| `String` | The last known IP address of the voter.
| `upvote`| `Boolean` | Whether the vote is positive.
| `downvote`| `Boolean` | Whether the vote is negative.


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
mutation UpvoteComment($id: ID!, $upvote: Boolean) {
    voteComment(id: $id, upvote: $upvote) {
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
    "id": 34,
    "upvote": true
}
```

Downvote a comment:

```graphql
mutation DownvoteComment($id: ID!, $downvote: Boolean) {
    voteComment(id: $id, downvote: $downvote) {
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