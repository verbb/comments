# GraphQL
Comments supports accessing [Comment](docs:developers/comment) objects via GraphQL. Be sure to read about [Craft's GraphQL support](https://craftcms.com/docs/5.x/development/graphql.html).

## Schema Permissions
Choose what a schema can do under the **Comments** heading when editing it.

| Permission | What It Allows |
| --- | --- |
| View comments (`comments:read`) | The `comments`, `comment` and `commentConversation` queries. |
| Create comments (`comments:edit`) | The `createComment` mutation. |
| Save comments (`comments:save`) | The `saveComment`, `voteComment`, `flagComment` and `subscribeComment` mutations. |
| Delete comments (`comments:delete`) | The `deleteComment` mutation. |
| Moderate comments (`comments:moderate`) | The `moderateComment` mutation. |

Some mutations are also left out of the schema while their feature is off. `voteComment` needs an **Interaction Mode** other than None, `flagComment` needs **Allow Reporting**, and `subscribeComment` needs at least one of the **Available Subscriptions**. The `comment` and `commentId` fields of a vote or report need **View comments**, and fields about users need the schema to be able to view users.

On a public schema, queries only ever return approved comments that haven't been deleted by their author or trashed, whatever arguments are supplied. This also applies to the `children` and `parent` fields.

Commenters' email addresses, IP addresses and browsers aren't available through GraphQL. `orderBy` can't use the columns that hold them either, or what spam protection recorded, unless the request comes from a signed-in admin, as sorting by a value can reveal it.

A guest's identity is the same on every comment they make, so a public schema can't filter or sort comments by `identityId`, which would gather everything one guest has written across your site. Whether a comment has been reported is for moderators, so the `flags` count is only on schemas with the **Moderate comments** permission, and only answered for someone who can moderate comments, on a schema that isn't public, as for [`moderateComment`](#moderatecomment).

## Comments

### Example

:::code
```graphql GraphQL
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        name
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
                "comment": "Hey there, this is a great article, love your work!"
            },
            {
                "commentDate": "2019-01-23",
                "name": "John Smith",
                "comment": "Thanks, appreciate your comments! 😀"
            }
        ]
    }
}
```
:::

### The `comments` Query
This query is used to query for [Comment](docs:developers/comment) objects. You can also use the singular `comment` to fetch a single node.

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
| `adjacencyParentId`| `[QueryArgument]` | Narrows the query results to direct replies of a comment. Pass `":empty:"` for top-level comments.

The table covers the arguments most useful for comments. Craft's other general element arguments, such as `ref` and `relatedToAll`, are also accepted, and your GraphQL explorer lists the full set for your schema.

### The `CommentInterface` Interface
This is the interface implemented by all comments.

| Field | Type | Description
| - | - | -
| `id`| `ID` | The ID of the entity.
| `uid`| `String` | The UID of the entity.
| `_count`| `Int` | Return a number of related elements for a field.
| `enabled`| `Boolean` | Whether the element is enabled or not.
| `archived`| `Boolean` | Whether the element is archived or not.
| `siteHandle`| `String` | The handle of the site the element is associated with.
| `siteId`| `Int` | The ID of the site the element is associated with.
| `siteSettingsId`| `ID` | The unique identifier for an element-site relation.
| `language`| `String` | The language of the site element is associated with.
| `searchScore`| `String` | The element’s search score, if the `search` parameter was used when querying for the element.
| `trashed`| `Boolean` | Whether the element has been soft-deleted or not.
| `status`| `String` | The comment’s stored status, which is `approved`, `pending`, `spam` or `removed`.
| `dateCreated`| `DateTime` | The date the element was created.
| `dateUpdated`| `DateTime` | The date the element was last updated.
| `ownerId`| `Int` | The ID of the element that owns the comment.
| `userId`| `Int` | The ID of the author of this comment, on schemas that can read users.
| `user`| `UserInterface` | The comment’s author, on schemas that can read users.
| `commentDate`| `DateTime` | The comment's post date.
| `comment`| `String` | The comment’s plain text. A rich comment has its text here without formatting.
| `contentFormat`| `String` | How the comment was written: `plain` or `editor`.
| `contentHtml`| `String` | The rendered HTML of a rich comment. Null for plain text comments.
| `editorContent`| `String` | The Editor JSON of a rich comment, to edit it. Null for plain text comments.
| `editedAt`| `DateTime` | When the comment’s content last changed after it was posted.
| `moderationState`| `String` | The comment’s moderation state. Public schemas only ever return `approved` comments.
| `rootId`| `Int` | The ID of the comment at the top of this comment’s thread.
| `depth`| `Int` | How many replies deep the comment is. Top-level comments are 0.
| `gifUrl`| `String` | The URL of a GIF shown with the comment, if it has one.
| `name`| `String` | The guest’s name. Null for comments by signed-in users. Use `author` for the name the thread shows.
| `author`| `CommentAuthor!` | Who wrote the comment, [as the thread shows them](#showing-who-wrote-a-comment).
| `viewer`| `CommentViewer!` | What the person making the request can do with the comment, and how they voted. See [What the Viewer Can Do](#what-the-viewer-can-do).
| `url`| `String` | The URL of the page the comment was made from.
| `children`| `[CommentInterface]` | The comment’s direct replies. Accepts the same arguments as the `comments` query.
| `parent`| `CommentInterface` | The comment this is a reply to.
| `votes`| `Int` | The comment’s net score, which is its upvotes minus its downvotes.
| `upvotes`| `Int` | The number of upvotes for this comment.
| `downvotes`| `Int` | The number of downvotes for this comment.
| `flags`| `Int` | The number of open reports for this comment. Only on schemas with **Moderate comments**, and null unless the request is from someone who can moderate comments, on a schema that isn't public.

The commenter’s email address isn’t available through GraphQL.

### Showing Who Wrote a Comment

Every comment has an `author` with the name, initials, avatar and badges the thread shows next to it. It's available on any schema that can view comments, including a public one, so a headless front end can show “Alex Morgan” without giving the schema access to users, which would also expose your users' details. A guest's name is the one they gave, and a member's never falls back to their email address.

```graphql
{
    comments (ownerId: 2460, adjacencyParentId: ":empty:") {
        id
        contentHtml
        comment
        author {
            name
            initials
            avatarUrl
            isGuest
            badges { type label }
        }
    }
}
```

| Field | Type | Description
| - | - | -
| `name`| `String!` | The name shown on the comment. A guest without a name is “Guest”.
| `initials`| `String!` | Up to two initials from the name, for an avatar without a photo.
| `avatarUrl`| `String` | The author's photo, or your placeholder avatar. Null when **Show Avatar** is off, or there's neither.
| `isGuest`| `Boolean!` | Whether the comment was made without an account.
| `badges`| `[CommentBadge!]!` | The comment's badges, each with a `type` of `owner` or `staff` and the `label` from your badge settings.

The `user` field still needs a schema that can view users, for when you want the account itself.

### What the Viewer Can Do

Every comment also has a `viewer`, which says what the person making the request can do with it, so a headless front end can show the same controls the default templates would and leave out ones that would fail. These follow the same rules as the default templates and the mutations. The person is whoever is signed in for the request, through a session or a [GraphQL Authentication](https://plugins.craftcms.com/graphql-authentication) token, or a guest otherwise.

```graphql
{
    comments (ownerId: 2460) {
        id
        viewer {
            canReply
            canEdit
            canDelete
            canVote
            canDownvote
            canReport
            vote
        }
    }
}
```

| Field | Type | Description
| - | - | -
| `canReply`| `Boolean!` | Whether they can reply to it.
| `canEdit`| `Boolean!` | Whether they can change it with `saveComment`, as its author within the **Author Edit Window**.
| `canDelete`| `Boolean!` | Whether they can delete it with `deleteComment`, as its author.
| `canVote`| `Boolean!` | Whether they can upvote or like it.
| `canDownvote`| `Boolean!` | Whether they can downvote it, which needs the **Upvotes and downvotes** interaction mode.
| `canReport`| `Boolean!` | Whether they can report it.
| `vote`| `String` | How they voted on it, which is `upvote`, `downvote`, or null if they haven't.

`viewer` is worked out for all the comments in a response together, so asking for it on a page of comments doesn't add a query per comment. Because the answer depends on who's asking, Craft's GraphQL cache isn't used for any query that includes a `viewer` field.

Craft also adds `title`, `slug` and `uri` to every element type, but comments don't have a title, slug or URI of their own, so these are always null. Use `ownerId` to find the element a comment belongs to, and that element's `uri` for its page.

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
| `guestPolicy`| `CommentGuestPolicy!` | What guests can do here: whether they're `allowed` to comment, whether they give their name and email (`nameAndEmail`, which is `required`, `optional` or `none`), and whether they `canVote` and `canReport`.
| `reportReasons`| `[CommentReportReason!]!` | Your **Report Reasons**, each with the `value` to send as `reason` to `flagComment`, its translated `label` and its `description`. Empty while reporting is off.
| `viewer`| `CommentConversationViewer!` | What the person making the request can do here: whether they're a guest (`isGuest`), and whether they can post a new comment (`canComment`), vote (`canVote`) and report comments (`canReport`). Each comment's own [`viewer`](#what-the-viewer-can-do) says what they can do with that comment.

`canComment` takes everything into account, including closing dates, whether guests are allowed and **Maximum Comments per User**.

### The `VoteInterface` Interface
This is the interface returned by `voteComment`.

| Field | Type | Description
| - | - | -
| `commentId`| `ID` | The ID of the comment the vote is applied to, on schemas that can read comments.
| `comment`| `CommentInterface` | The comment the vote is applied to, on schemas that can read comments.
| `userId`| `Int` | The ID of the submitter of this vote, on schemas that can read users.
| `user`| `UserInterface` | The vote’s submitter, on schemas that can read users.
| `upvote`| `Boolean` | True if the vote is now an upvote.
| `downvote`| `Boolean` | True if the vote is now a downvote. If neither is true, the vote was taken back.

`id` is also on this interface, but it's always null.

### The `FlagInterface` Interface
This is the interface returned by `flagComment`, describing a report.

| Field | Type | Description
| - | - | -
| `commentId`| `ID` | The ID of the reported comment, on schemas that can read comments.
| `comment`| `CommentInterface` | The reported comment, on schemas that can read comments.
| `userId`| `Int` | The ID of the user who sent the report, on schemas that can read users.

`id` is also on this interface, but it's always null.

### Custom Fields
If you’ve added custom fields to the Comment element (**Comments** → **Settings** → **Comment Fields** → **Comment Field Layout**), you can query them by handle within a GraphQL fragment. If you added a `summary` Plain Text field, for example, your query might look like this:

```graphql
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        name
        comment
        ... on Comment {
            summary
        }
    }
}
```

Mutations only accept custom fields selected under **Public Fields** in the Comments settings.

## Mutations

Mutations act as whoever is making the request, which is the signed-in user for a session or a guest otherwise. Guests can only comment, vote or report comments if your settings allow it. The [GraphQL Authentication](https://plugins.craftcms.com/graphql-authentication) plugin's tokens are also recognised.

### Protection

While **Enable Protection** is on, `createComment` and `saveComment` run Comments' [spam checks](docs:feature-tour/anti-spam) before saving. First send a GET request to the `comments/frontend-api/protection` action on your site, such as `/actions/comments/frontend-api/protection?ownerId=…&siteId=…&channel=headless`. `siteId` defaults to the current site, and you include `commentId` when preparing an edit. Pass the signed preparation from the response, and any required provider token, as `protection`:

```json
{
    "version": 1,
    "preparation": "SIGNED_PREPARATION_FROM_RESPONSE",
    "challenges": {
        "turnstile": "CLIENT_PROVIDER_TOKEN"
    }
}
```

The input type is `CommentsProtectionEvidence`. For reCAPTCHA v3, use the `recaptchaV3` challenge field. Omit `challenges` when no provider is enabled. Honeypot, minimum form time and JavaScript checks aren't applied to these requests, but replay protection, rate limits, challenges and Akismet are. Get fresh preparation after every attempted submission. Preparation doesn't grant authorisation, so schema permissions, author permissions and validation still apply.

A front end on another domain can fetch the preparation from the browser. The `protection` action answers cross-origin requests the same way Craft's GraphQL API does, following Craft's `allowedGraphqlOrigins` setting. By default any origin can read it, without cookies, so the preparation is a guest's. List your front end's origins in `allowedGraphqlOrigins` to allow only those, with cookies, so a signed-in visitor's preparation matches their session. Set it to `false`, or turn GraphQL off, and the action sends no cross-origin headers. None of the other frontend API actions answer cross-origin requests, and the preparation only carries spam-check evidence, which a script on a server could fetch anyway, not any permission.

### createComment
Saves a new comment on the element given as `ownerId`, or a reply when given a `parentId`. To post a rich comment while **Authoring Mode** is **Rich (Editor)**, send the Editor's JSON as `editorContent`. Custom fields selected under **Public Fields** are accepted as arguments too.

```graphql
mutation NewComment($parentId: ID, $ownerId: ID, $siteId: ID, $name: String, $email: String, $comment: String, $protection: CommentsProtectionEvidence) {
    createComment(parentId: $parentId, ownerId: $ownerId, siteId: $siteId, name: $name, email: $email, comment: $comment, protection: $protection) {
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
    "siteId": 1,
    "parentId": 30,
    "name": "Matt",
    "email": "matt@pixelandtonic.com",
    "comment": "Here’s a nested comment."
}
```

`siteId` defaults to the primary site. `name` and `email` are for guests, so leave them out for a signed-in user. The new comment is moderated like any other, so check its `moderationState` before showing it as published.

### saveComment
Edit the content of an existing comment, identified by `id` or `uid`. Only the comment's author can use this, within the **Author Edit Window**, and only while the comment is approved or pending. The author, owner element, parent and dates can't be changed. A rich comment needs `editorContent`, as it can't be replaced with plain text. `siteId` defaults to the primary site. An edit goes through moderation the same way a new comment does, so it can return to pending.

```graphql
mutation UpdateComment($id: ID, $comment: String, $protection: CommentsProtectionEvidence) {
    saveComment(id: $id, comment: $comment, protection: $protection) {
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
    "id": 34,
    "comment": "I’m totally changing what I said."
}
```

### voteComment
Upvote a comment with `upvote: true`. Anything else counts as a downvote, which is refused unless **Interaction Mode** is **Upvotes and downvotes**. Repeat the same vote to take it back, or send the other one to change it. The `downvote` and `siteId` arguments are accepted but have no effect. Only approved comments can be voted on, and each person can vote 60 times a minute.

```graphql
mutation VoteComment($id: ID!, $upvote: Boolean) {
    voteComment(id: $id, upvote: $upvote) {
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
    "id": 34,
    "upvote": true
}
```

### flagComment
Report a comment to the moderators. `reason` is only kept if it matches the value of one of your **Report Reasons**, and both `reason` and `details` are optional. Repeat to withdraw the report. The `siteId` argument is accepted but has no effect. Only approved comments can be reported, and each person can make 3 reports every 10 minutes.

```graphql
mutation FlagComment($id: ID!, $reason: String, $details: String) {
    flagComment(id: $id, reason: $reason, details: $details) {
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
    "id": 34,
    "reason": "spam",
    "details": "This is the third time they've posted this link."
}
```

### subscribeComment
Subscribe to an element’s comment notifications. This needs a signed-in user, an element with comments turned on, and **Whole discussion** among the **Available Subscriptions**. It returns a message saying what happened. Repeat to toggle on and off. `siteId` is optional and defaults to the primary site.

```graphql
mutation SubscribeComment($ownerId: ID!, $siteId: ID!) {
    subscribeComment(ownerId: $ownerId, siteId: $siteId)
}
```

Query variables:

```json
{
    "ownerId": 34,
    "siteId": 1
}
```

To subscribe to the replies to a specific comment, add its `commentId`. This needs **Replies to individual comments** among the **Available Subscriptions**.

```graphql
mutation SubscribeThread($ownerId: ID!, $siteId: ID!, $commentId: ID) {
    subscribeComment(ownerId: $ownerId, siteId: $siteId, commentId: $commentId)
}
```

Query variables:

```json
{
    "ownerId": 34,
    "siteId": 1,
    "commentId": 95
}
```

### deleteComment
Delete your own comment, within the **Author Edit Window**. This hides it, and any approved replies stay in place under a placeholder. Users with the **Delete comments** user permission can still only delete their own comments this way. `siteId` is optional, as the comment is found on whichever site it was made on. The mutation returns `true`, including when no comment has that ID.

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

### moderateComment
Moderate a comment, with an `action` of `approve`, `remove`, `restore`, `spam` or `notSpam`, and an optional `reason` for its history. This needs the **Moderate comments** schema permission and a signed-in user with the **Moderate comments** user permission (`comments-moderate`). Removing also needs **Trash other users’ comments** (`comments-trash`). It can't be used on a public schema, or on your own comment, and returns the moderated comment.

```graphql
mutation ModerateComment($id: ID!, $action: String!, $reason: String) {
    moderateComment(id: $id, action: $action, reason: $reason) {
        id
        moderationState
    }
}
```

Query variables:

```json
{
    "id": 34,
    "action": "approve",
    "reason": "Reviewed by the community team."
}
```
