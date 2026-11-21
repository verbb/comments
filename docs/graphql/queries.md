# Queries

Comments adds queries for [Comment](docs:reference/comment) elements and their discussions to Craft's GraphQL API, and mutations for posting, editing, voting, reporting, following and moderating, so a headless front end or another server can work with comments. If you haven't used GraphQL in Craft before, start with [Craft's GraphQL documentation](https://craftcms.com/docs/5.x/development/graphql.html), which explains schemas, tokens and the endpoint.

## Enable Comments in Your Schema

Choose what a schema can do under the **Comments** heading when editing it.

| Permission | What It Allows |
| --- | --- |
| View comments (`comments:read`) | The `comments`, `comment` and `commentConversation` queries, and the `commentsThread`, `commentsReplies`, `commentsComment` and `commentsViewer` queries described in [Reading Comments as the Data API Gives Them](docs:graphql/queries#reading-comments-as-the-data-api-gives-them). |
| View commenters’ email addresses (`commentEmails:read`) | The `email` field on comments and the `email` argument on the `comments` query, with guests’ addresses, on a private schema. A public schema never has them, whatever it's given. |
| Create comments (`comments:edit`) | The `createComment` mutation. |
| Save comments (`comments:save`) | The `saveComment`, `voteComment`, `flagComment` and `subscribeComment` mutations. |
| Delete comments (`comments:delete`) | The `deleteComment` mutation. |
| Moderate comments (`comments:moderate`) | The `moderateComment` mutation. |

Some mutations are also left out of the schema while their feature is off. `voteComment` needs an **Interaction Mode** other than None, `flagComment` needs **Allow Reporting**, and `subscribeComment` needs at least one of the **Available Subscriptions**. The `comment` and `commentId` fields of a vote or report need **View comments**, and fields about users need the schema to be able to view users.

On a public schema, queries only ever return approved comments that haven't been deleted by their author or trashed, whatever arguments are supplied. This also applies to the `children` and `parent` fields. The `comments` and `comment` queries don't take an element's own comment settings into account, so they also return approved comments on an element whose Comments field is **Off**, whose source doesn't allow comments, or that visitors can't see, such as a disabled entry. To get what visitors see on the site, use [`commentsThread`](#the-commentsThread-query).

Commenters' email addresses, IP addresses and browsers aren't available through GraphQL. `orderBy` can't use the columns that hold them either, or what spam protection recorded, unless the request comes from a signed-in admin, as sorting by a value can reveal it. A schema that can't read every custom field is limited further, to the `orderBy` columns listed under [Custom Fields](docs:graphql/queries#custom-fields).

A guest's identity is the same on every comment they make, so a public schema can't filter or sort comments by `identityId`, which would gather everything one guest has written across your site. Whether a comment has been reported is for moderators, so the `flags` count is only on schemas with the **Moderate comments** permission, and only answered for someone who can moderate comments, on a schema that isn't public, as for [`moderateComment`](docs:graphql/mutations#the-moderateComment-mutation).

## Querying Comments

### Fetching the Latest Comments on an Entry

This query fetches the two newest comments on the entry with the ID `2460`, with each commenter's name as the thread shows it. A public schema only returns approved comments.

::: code
```graphql GraphQL
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        author {
            name
        }
        comment
    }
}
```

```json JSON Response
{
    "data": {
        "comments": [
            {
                "commentDate": "2026-10-14",
                "author": {
                    "name": "Alex Morgan"
                },
                "comment": "The section on drainage saved our garden bed. Thanks for writing it up."
            },
            {
                "commentDate": "2026-10-12",
                "author": {
                    "name": "Sam Lee"
                },
                "comment": "Does this work in clay soil too? 😀"
            }
        ]
    }
}
```
:::

### The `comments` Query

The `comments` query returns a list of [Comment](docs:reference/comment) elements matching its arguments, and the singular `comment` query takes the same arguments and returns the first match.

| Argument | Type | Description
| - | - | -
| `id`| `[QueryArgument]` | Narrows the query results based on the elements’ IDs.
| `uid`| `[String]` | Narrows the query results based on the elements’ UIDs.
| `status`| `[String]` | Narrows the query results based on the elements’ statuses, such as `approved`, `pending` or `author-removed`. Only on schemas that can query inactive elements.
| `archived`| `Boolean` | Narrows the query results to only elements that have been archived. Only on schemas that can query inactive elements.
| `trashed`| `Boolean` | Narrows the query results to only elements that have been soft-deleted. Only on schemas that can query inactive elements.
| `site`| `[String]` | Determines which site(s) the elements should be queried in. Defaults to the current (requested) site.
| `siteId`| `[QueryArgument]` | Determines which site(s) the elements should be queried in. Defaults to the current (requested) site.
| `unique`| `Boolean` | Determines whether only elements with unique IDs should be returned by the query.
| `preferSites`| `[QueryArgument]` | Determines which site should be selected when querying multi-site elements.
| `search`| `String` | Narrows the query results to only elements that match a search query.
| `relatedTo`| `[QueryArgument]` | Narrows the query results to elements that relate to the provided element IDs.
| `notRelatedTo`| `[QueryArgument]` | Narrows the query results to elements that don’t relate to the provided element IDs.
| `relatedToAssets`| `[AssetCriteriaInput]` | Narrows the query results to elements that relate to an asset list defined with this argument.
| `relatedToEntries`| `[EntryCriteriaInput]` | Narrows the query results to elements that relate to an entry list defined with this argument.
| `relatedToUsers`| `[UserCriteriaInput]` | Narrows the query results to elements that relate to a use list defined with this argument.
| `relatedToCategories`| `[CategoryCriteriaInput]` | Narrows the query results to elements that relate to a category list defined with this argument.
| `relatedToTags`| `[TagCriteriaInput]` | Narrows the query results to elements that relate to a tag list defined with this argument.
| `fixedOrder`| `Boolean` | Causes the query results to be returned in the order specified by the `id` argument.
| `inReverse`| `Boolean` | Causes the query results to be returned in reverse order.
| `dateCreated`| `[String]` | Narrows the query results based on the elements’ creation dates.
| `dateUpdated`| `[String]` | Narrows the query results based on the elements’ last-updated dates.
| `offset`| `Int` | Sets the offset for paginated results.
| `limit`| `Int` | Sets the limit for paginated results.
| `orderBy`| `String` | Sets the field the returned elements should be ordered by.
| `siteSettingsId`| `[QueryArgument]` | Narrows the query results based on the unique identifier for an element-site relation.
| `ownerId`| `[QueryArgument]` | Narrows the query results based on the owner element the comment was made on, per the owners’ IDs.
| `userId`| `[QueryArgument]` | Narrows the query results based on the comment’s authors.
| `commentDate`| `[String]` | Narrows the query results based on the comments’ commented date.
| `name`| `[String]` | Narrows the query results based on the comments’ full name.
| `comment`| `[String]` | Narrows the query results based on the comments’ actual comment text.
| `conversationId`| `[QueryArgument]` | Narrows the query results to a conversation.
| `identityId`| `[QueryArgument]` | Narrows the query results based on commenter identities. Not on public schemas.
| `moderationState`| `[String]` | Narrows the query results by moderation state.
| `rootId`| `[QueryArgument]` | Narrows the query results to a thread, by the ID of the comment at the top of it.
| `parentId`| `[QueryArgument]` | Narrows the query results to direct replies of a comment. Pass `":empty:"` for top-level comments.

The table covers the arguments most useful for comments. Craft's other general element arguments, such as `ref` and `relatedToAll`, are also accepted, and your GraphQL explorer lists the full set for your schema.

The fields each comment has are listed under [the `CommentInterface` interface](docs:graphql/interfaces-types#the-commentInterface-interface).

### The `comment` Query

Returns a single comment, or null when nothing matches. It takes the same arguments as the `comments` query, and returns the first match.

```graphql
{
    comment (id: 2471) {
        id
        author {
            name
        }
        comment
        parent {
            id
        }
    }
}
```

```json
{
    "data": {
        "comment": {
            "id": "2471",
            "author": {
                "name": "Sam Lee"
            },
            "comment": "Does this work in clay soil too? 😀",
            "parent": null
        }
    }
}
```

### Custom Fields

If you’ve added custom fields to the Comment element (**Comments** → **Settings** → **Comment Fields** → **Comment Field Layout**), you can query them by handle within a GraphQL fragment. If you added a `summary` Plain Text field, for example, your query might look like this:

```graphql
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        author {
            name
        }
        comment
        ... on Comment {
            summary
        }
    }
}
```

Mutations only accept custom fields the site's form asks for, under **Comments** → **Settings** → **Comment Fields** → **The Comment Form**, and only the ones the form would offer that person on that comment, such as leaving out fields not shown on replies or held back by the field layout's conditions. An edit with `saveComment` only takes the fields the comment was posted with.

Field values that aren't shown stay private in GraphQL too. A schema can read every custom field only if it isn't public and has the **Moderate comments** permission, and, when the request is for a signed-in user, that person can moderate comments. Any other schema or request gets `null` for a field unless it's shown on comments and the value was entered while it was shown, and gets `null` for every generated field. Because placing comments in order or finding them by a value could reveal it, such a request also can't search or filter by any custom field, can only use `orderBy` with `id`, `uid`, `commentDate`, `dateCreated`, `dateUpdated`, `depth`, `rootId`, `parentId`, `ownerId`, `ownerSiteId`, `conversationId`, `voteCount`, `score` and `sortOrder` (plus `identityId` and `userId` on a schema that isn't public) unless a signed-in admin is making it, and can't filter comments by related elements while the Comment field layout has a relation field.

## Querying Conversations

### The `commentConversation` Query

This query returns the state, totals and rules of the discussion on an element, such as for showing a comment count without fetching the comments, or deciding which parts of a comment form to show. It needs the **View comments** permission, and returns null if the element doesn't exist, isn't public or has comments turned off. `siteId` defaults to the primary site.

```graphql
{
    commentConversation (ownerId: 2460) {
        commentCount
        lastActivityAt
        open
        interactionMode
        maxLength
        guestPolicy { allowed nameAndEmail canVote canReport }
        reportReasons { value label description }
        viewer { isGuest canComment canVote canReport }
    }
}
```

For a guest on a site that only lets members comment, `viewer.canComment` is false, and a front end can show a sign-in link in place of the form.

| Field | Type | Description
| - | - | -
| `ownerId`| `Int!` | The ID of the element the conversation is on.
| `siteId`| `Int!` | The ID of the site the conversation is on.
| `commentCount`| `Int!` | The number of published comments.
| `lastActivityAt`| `DateTime` | When the latest published comment was made.
| `open`| `Boolean!` | Whether new comments can be posted.
| `interactionMode`| `String!` | How people can vote, which is `like`, `updown` for upvotes and downvotes, or `none`.
| `maxLength`| `Int` | The most characters a comment can have, from **Maximum Comment Length**, or null for no limit.
| `guestPolicy`| `CommentGuestPolicy!` | What guests can do here: whether they're `allowed` to comment, whether they give their name and email (`nameAndEmail`, which is `required`, `optional` or `hidden`), and whether they `canVote` and `canReport`.
| `reportReasons`| `[CommentReportReason!]!` | Your **Report Reasons**, each with the `value` to send as `reason` to `flagComment`, its translated `label` and its `description`. Empty while reporting is off.
| `viewer`| `CommentConversationViewer!` | What the person making the request can do here: whether they're a guest (`isGuest`), and whether they can post a new comment (`canComment`), vote (`canVote`) and report comments (`canReport`). Each comment's own [`viewer`](docs:graphql/interfaces-types#the-commentViewer-type) says what they can do with that comment.

`canComment` takes everything into account, including closing dates, whether guests are allowed and **Maximum Comments per User**.

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `ID!` | The ID of the element the conversation is on. |
| `siteId` | `ID` | The ID of the site. Defaults to the primary site. |

```json
{
    "data": {
        "commentConversation": {
            "commentCount": 12,
            "lastActivityAt": "2026-10-14T09:32:00+10:00",
            "open": true,
            "interactionMode": "updown",
            "maxLength": 5000,
            "guestPolicy": {
                "allowed": true,
                "nameAndEmail": "required",
                "canVote": false,
                "canReport": true
            },
            "reportReasons": [
                {
                    "value": "spam",
                    "label": "Spam",
                    "description": "Advertising or repeated links."
                }
            ],
            "viewer": {
                "isGuest": true,
                "canComment": true,
                "canVote": false,
                "canReport": true
            }
        }
    }
}
```

## Reading Comments as the Data API Gives Them

Four queries return exactly what the data API's `comments/api/*` reads return, built by the same code, so a client that draws comments from the data API's JSON can fetch it through GraphQL instead. Each returns a `CommentsApiData` object, with the API version as `apiVersion` and the response itself as `data`, a JSON value without the data API's `apiVersion` and `status`. They need the **View comments** permission.

For `commentsThread`, `data` holds the discussion under `conversation`, its comments under `comments`, the thread's wording under `labels`, and under `page`, a `next` cursor while there are more comments, which you pass back as `cursor` to fetch the next page. A single comment from the `comments` query can also give its data API description through its `commentsData` field, including what the person asking may do with it.

### The `commentsThread` Query

Returns a page of an element's thread, as the data API's `thread` gives it.

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `ID` | The ID of the element the thread is on. |
| `ownerUri` | `String` | The URI of the element the thread is on, as an alternative to `ownerId`. |
| `siteId` | `ID` | The ID of the element's site. |
| `sort` | `String` | The order of the top-level comments, which is `newest`, `oldest` or `top`. |
| `limit` | `Int` | How many top-level comments to return. |
| `cursor` | `String` | The `page.next` value from a previous response, to fetch the next page. |

```graphql
{
    commentsThread(ownerId: 2460, sort: "newest", limit: 20) {
        apiVersion
        data
    }
}
```

The response below is shortened to its keys. The full response also has a `threadVersion`, and fills `conversation`, `comments` and `labels` the same way the data API does.

```json
{
    "data": {
        "commentsThread": {
            "apiVersion": 1,
            "data": {
                "conversation": {},
                "editor": null,
                "comments": [],
                "page": {
                    "next": null,
                    "limit": 20
                },
                "labels": {},
                "locale": "en-AU"
            }
        }
    }
}
```

### The `commentsReplies` Query

Returns a page of a comment's replies, as the data API's `replies` gives it. `data` holds the parent's UID as `parentUid`, and the replies under `replies`, with their total `count`, the comments as `items`, and a `next` cursor while there are more.

| Argument | Type | Description |
| --- | --- | --- |
| `parentUid` | `String!` | The UID of the comment whose replies to fetch. |
| `limit` | `Int` | How many replies to return. |
| `cursor` | `String` | The `replies.next` value from a previous response, to fetch the next page. |

```graphql
{
    commentsReplies(parentUid: "6f1c2c9e-8d2b-4f4a-9a57-0c3d1e2b7a10", limit: 10) {
        data
    }
}
```

```json
{
    "data": {
        "commentsReplies": {
            "data": {
                "parentUid": "6f1c2c9e-8d2b-4f4a-9a57-0c3d1e2b7a10",
                "replies": {
                    "count": 3,
                    "items": [],
                    "next": null
                }
            }
        }
    }
}
```

### The `commentsComment` Query

Returns one comment in its thread, as the data API's `comment` gives it, such as for a page that opens at a permalinked comment. `data` holds the comment as `comment`, with `targetUid`, `ownerId` and `siteId`.

| Argument | Type | Description |
| --- | --- | --- |
| `uid` | `String` | The comment's UID. |
| `id` | `Int` | The comment's ID, the number in its permalink, as an alternative to `uid`. |

```graphql
{
    commentsComment(id: 2471) {
        data
    }
}
```

```json
{
    "data": {
        "commentsComment": {
            "data": {
                "targetUid": "6f1c2c9e-8d2b-4f4a-9a57-0c3d1e2b7a10",
                "ownerId": 2460,
                "siteId": 1,
                "comment": {}
            }
        }
    }
}
```

### The `commentsViewer` Query

Returns what the person asking can do in a thread and with each of the comments you name, as the data API's `viewer` gives it. The response depends on who's asking, so it's never cached.

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `ID` | The ID of the element the thread is on. |
| `ownerUri` | `String` | The URI of the element, as an alternative to `ownerId`. |
| `siteId` | `ID` | The ID of the element's site. |
| `sort` | `String` | The thread's order, which is `newest`, `oldest` or `top`. |
| `limit` | `Int` | The size of the thread's page. |
| `cursor` | `String` | The cursor of the thread's page. |
| `uids` | `[String]` | The UIDs of the comments to describe. |
| `ids` | `[Int]` | The IDs of the comments to describe, as an alternative to `uids`. |

```graphql
{
    commentsViewer(ownerId: 2460, ids: [2471, 2468]) {
        data
    }
}
```
