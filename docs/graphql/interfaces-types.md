# Interfaces & Types

These are the GraphQL interfaces and types Comments adds. [Queries](docs:graphql/queries) and [Mutations](docs:graphql/mutations) show them in use.

## Comments

### The `CommentInterface` Interface

The interface implemented by every comment. Comments use a single `Comment` type, which also holds the custom fields from your Comment field layout, requested inside a `... on Comment` fragment as shown in [Custom Fields](docs:graphql/queries#custom-fields). The commenter’s IP address and browser aren’t available, and a guest’s email address is only available as `email` on a private schema with the **View commenters’ email addresses** permission.

Craft also adds `title`, `slug` and `uri` to every element type, but comments don’t have a title, slug or URI of their own, so these are always null. Use `ownerId` to find the element a comment belongs to, and that element’s `uri` for its page.

```graphql
{
    comments (ownerId: 2460, parentId: ":empty:") {
        id
        commentDate @formatDateTime (format: "j M Y")
        contentHtml
        comment
        votes
        author {
            name
            avatarUrl
        }
        viewer {
            canReply
            vote
        }
    }
}
```

::: reference
#### `id`

**Type:** `ID`

The ID of the comment.
:::

::: reference
#### `uid`

**Type:** `String`

The UID of the comment.
:::

::: reference
#### `status`

**Type:** `String`

The comment’s stored status, which is `approved`, `pending`, `spam` or `removed`.
:::

::: reference
#### `siteId`

**Type:** `Int`

The ID of the site the comment is on.
:::

::: reference
#### `siteHandle`

**Type:** `String`

The handle of the site the comment is on.
:::

::: reference
#### `dateCreated`

**Type:** `DateTime`

The date the comment’s element was created.
:::

::: reference
#### `dateUpdated`

**Type:** `DateTime`

The date the comment’s element was last updated.
:::

::: reference
#### `ownerId`

**Type:** `Int`

The ID of the element the comment was made on. Use that element’s own query for its title or URI.
:::

::: reference
#### `commentDate`

**Type:** `DateTime`

The date the comment was posted.
:::

::: reference
#### `comment`

**Type:** `String`

The comment’s plain text. A rich comment has its text here without formatting.
:::

::: reference
#### `contentFormat`

**Type:** `String`

The way the comment was written, which is `plain` or `rich`.
:::

::: reference
#### `contentHtml`

**Type:** `String`

The rendered HTML of a rich comment. It’s null for a plain text comment.
:::

::: reference
#### `contentDocument`

**Type:** `String`

The Editor JSON of a rich comment, for editing it. It’s null for a plain text comment.
:::

::: reference
#### `editedAt`

**Type:** `DateTime`

The date the comment’s content last changed after it was posted, or null if it hasn’t been edited.
:::

::: reference
#### `moderationState`

**Type:** `String`

The comment’s moderation state, such as `approved` or `pending`. Public schemas only ever return `approved` comments.
:::

::: reference
#### `rootId`

**Type:** `Int`

The ID of the comment at the top of this comment’s thread.
:::

::: reference
#### `depth`

**Type:** `Int`

The number of replies deep the comment is. Top-level comments are at 0.
:::

::: reference
#### `gifUrl`

**Type:** `String`

The URL of the GIF shown with the comment, or null when it doesn’t have one.
:::

::: reference
#### `name`

**Type:** `String`

The name a guest gave. It’s null for comments by signed-in users, so use `author` for the name the thread shows.
:::

::: reference
#### `email`

**Type:** `String`

The email address a guest gave. It’s null for comments by signed-in users. Only on a private schema with the **View commenters’ email addresses** permission.
:::

::: reference
#### `author`

**Type:** `CommentAuthor!`

The person who wrote the comment, as the thread shows them. See [the `CommentAuthor` type](#the-commentAuthor-type).
:::

::: reference
#### `viewer`

**Type:** `CommentViewer!`

A summary of what the person making the request can do with the comment, and how they voted. See [the `CommentViewer` type](#the-commentViewer-type).
:::

::: reference
#### `url`

**Type:** `String`

The URL of the page the comment was posted from.
:::

::: reference
#### `commentsData`

**Type:** `CommentsArrayType`

The comment as the data API describes it to the person asking, including what they may do with it in `viewer`. For a moderator on a schema that isn’t public, it also has `moderation`.
:::

::: reference
#### `children`

**Type:** `[CommentInterface]`

The comment’s direct replies. It accepts the same arguments as the `comments` query.
:::

::: reference
#### `parent`

**Type:** `CommentInterface`

The comment this one replies to, or null for a top-level comment.
:::

::: reference
#### `votes`

**Type:** `Int`

The comment’s net score, which is its upvotes minus its downvotes.
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
#### `userId`

**Type:** `Int`

The ID of the user who wrote the comment, or null for a guest. It’s only on schemas that can view users.
:::

::: reference
#### `user`

**Type:** `UserInterface`

The user who wrote the comment. It’s only on schemas that can view users.
:::

::: reference
#### `flags`

**Type:** `Int`

The number of open reports on the comment. It’s only on schemas with **Moderate comments**, and is null unless the request is from someone who can moderate comments, on a schema that isn’t public.
:::

### The `CommentAuthor` Type

Every comment has an `author` with the name, initials, avatar and badges the thread shows next to it. It’s available on any schema that can view comments, including a public one, so a headless front end can show “Alex Morgan” without giving the schema access to users, which would also expose your users’ details. The `user` field still needs a schema that can view users, for when you want the account itself.

```graphql
{
    comments (ownerId: 2460, parentId: ":empty:") {
        id
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

::: reference
#### `name`

**Type:** `String!`

The name shown on the comment. A guest who didn’t give a name is shown as “Guest”, and a member’s name never falls back to their email address.
:::

::: reference
#### `initials`

**Type:** `String!`

The first one or two initials of the name, for an avatar without a photo.
:::

::: reference
#### `avatarUrl`

**Type:** `String`

The URL of the author’s photo, or of your placeholder avatar. It’s null when **Show Avatar** is off, or there’s neither.
:::

::: reference
#### `isGuest`

**Type:** `Boolean!`

Whether the comment was posted without an account.
:::

::: reference
#### `badges`

**Type:** `[CommentBadge!]!`

The comment’s badges, each with a `type` and a `label`. Comments adds `owner` and `staff` badges with the labels from your badge settings, and a site can add its own through the [`defineBadges`](docs:developers/events#the-defineBadges-event) event.
:::

### The `CommentViewer` Type

Every comment also has a `viewer`, which says what the person making the request can do with it, so a headless front end can show the same controls the default templates would and leave out ones that would fail. These follow the same rules as the default templates and the mutations. The person is whoever is signed in for the request, through a session or a [GraphQL Authentication](https://plugins.craftcms.com/graphql-authentication) token, or a guest otherwise.

`viewer` is worked out for all the comments in a response together, so asking for it on a page of comments doesn’t add a query per comment. Because the answer depends on who’s asking, Craft’s GraphQL cache isn’t used for any query that includes a `viewer` field.

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

::: reference
#### `canReply`

**Type:** `Boolean!`

Whether they can reply to the comment.
:::

::: reference
#### `canEdit`

**Type:** `Boolean!`

Whether they can change the comment with `saveComment`, as its author within the **Author Edit Window**.
:::

::: reference
#### `canDelete`

**Type:** `Boolean!`

Whether they can delete the comment with `deleteComment`, as its author.
:::

::: reference
#### `canVote`

**Type:** `Boolean!`

Whether they can upvote or like the comment.
:::

::: reference
#### `canDownvote`

**Type:** `Boolean!`

Whether they can downvote the comment, which needs the **Upvotes and downvotes** interaction mode.
:::

::: reference
#### `canReport`

**Type:** `Boolean!`

Whether they can report the comment.
:::

::: reference
#### `vote`

**Type:** `String`

The way they voted on the comment, which is `upvote` or `downvote`, or null if they haven’t.
:::

## Conversations

### The `CommentConversation` Type

The type the [`commentConversation`](docs:graphql/queries#the-commentConversation-query) query returns, describing the discussion on an element.

```graphql
{
    commentConversation (ownerId: 2460) {
        commentCount
        open
        guestPolicy { allowed nameAndEmail }
        viewer { canComment }
    }
}
```

::: reference
#### `ownerId`

**Type:** `Int!`

The ID of the element the conversation is on.
:::

::: reference
#### `siteId`

**Type:** `Int!`

The ID of the site the conversation is on.
:::

::: reference
#### `commentCount`

**Type:** `Int!`

The number of published comments.
:::

::: reference
#### `lastActivityAt`

**Type:** `DateTime`

The date the latest published comment was posted, or null when there are none.
:::

::: reference
#### `open`

**Type:** `Boolean!`

Whether new comments can be posted.
:::

::: reference
#### `interactionMode`

**Type:** `String!`

The way people can vote, which is `like`, `updown` for upvotes and downvotes, or `none`.
:::

::: reference
#### `maxLength`

**Type:** `Int`

The most characters a comment can have, from **Maximum Comment Length**, or null for no limit.
:::

::: reference
#### `guestPolicy`

**Type:** `CommentGuestPolicy!`

A summary of what guests can do here. `allowed` is whether they can comment, `nameAndEmail` is whether they give their name and email (`required`, `optional` or `hidden`), and `canVote` and `canReport` are whether they can vote and report.
:::

::: reference
#### `reportReasons`

**Type:** `[CommentReportReason!]!`

The list of your **Report Reasons**, each with the `value` to send as `reason` to `flagComment`, its translated `label` and its `description`. It’s empty while reporting is off.
:::

::: reference
#### `viewer`

**Type:** `CommentConversationViewer!`

A summary of what the person making the request can do here. `isGuest` is whether they’re a guest, and `canComment`, `canVote` and `canReport` are whether they can post a new comment, vote and report comments. `canComment` takes everything into account, including closing dates, whether guests are allowed and **Maximum Comments per User**.
:::

### The `CommentsApiData` Type

The type the [data API queries](docs:graphql/queries#reading-comments-as-the-data-api-gives-them) return.

```graphql
{
    commentsThread(ownerId: 2460) {
        apiVersion
        data
    }
}
```

::: reference
#### `apiVersion`

**Type:** `Int!`

The version of the data API that built the response.
:::

::: reference
#### `data`

**Type:** `CommentsArrayType`

The data API’s response as a JSON value, without its `apiVersion` and `status`.
:::

## Votes and Reports

### The `VoteInterface` Interface

The interface of the `Vote` type, which [`voteComment`](docs:graphql/mutations#the-voteComment-mutation) returns.

```graphql
mutation VoteComment($id: ID!) {
    voteComment(id: $id, upvote: true) {
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

The ID of the vote. It’s always null, as votes aren’t exposed by ID.
:::

::: reference
#### `upvote`

**Type:** `Boolean`

Whether the vote is now an upvote.
:::

::: reference
#### `downvote`

**Type:** `Boolean`

Whether the vote is now a downvote. When neither `upvote` nor `downvote` is true, the vote was taken back.
:::

::: reference
#### `commentId`

**Type:** `ID`

The ID of the comment the vote was made on. It’s only on schemas that can view comments.
:::

::: reference
#### `comment`

**Type:** `CommentInterface`

The comment the vote was made on. It’s only on schemas that can view comments.
:::

::: reference
#### `userId`

**Type:** `Int`

The ID of the user who voted. It’s only on schemas that can view users.
:::

::: reference
#### `user`

**Type:** `UserInterface`

The user who voted. It’s only on schemas that can view users.
:::

### The `FlagInterface` Interface

The interface of the `Flag` type, which [`flagComment`](docs:graphql/mutations#the-flagComment-mutation) returns. In code, a report is called a flag.

```graphql
mutation FlagComment($id: ID!) {
    flagComment(id: $id, withdraw: false) {
        commentId
    }
}
```

::: reference
#### `id`

**Type:** `ID`

The ID of the report. It’s always null, as reports aren’t exposed by ID.
:::

::: reference
#### `commentId`

**Type:** `ID`

The ID of the reported comment. It’s only on schemas that can view comments.
:::

::: reference
#### `comment`

**Type:** `CommentInterface`

The reported comment. It’s only on schemas that can view comments.
:::

::: reference
#### `userId`

**Type:** `Int`

The ID of the user who made the report. It’s only on schemas that can view users.
:::

::: reference
#### `user`

**Type:** `UserInterface`

The user who made the report. It’s only on schemas that can view users.
:::

## Inputs

### The `CommentsProtectionEvidence` Input

The input `createComment` and `saveComment` take as `protection`, described under [Protection](docs:graphql/mutations#protection).

```json
{
    "version": 1,
    "preparation": "SIGNED_PREPARATION",
    "challenges": {
        "turnstile": "CLIENT_PROVIDER_TOKEN"
    }
}
```

::: reference
#### `version`

**Type:** `Int!`

The version of the preparation, from `protection.version`.
:::

::: reference
#### `preparation`

**Type:** `String!`

The signed preparation, from `protection.manifest.preparation`.
:::

::: reference
#### `challenges`

**Type:** `CommentsProtectionChallenges`

The tokens your front end collected for the challenges in `manifest.challenges`, as `turnstile` or `recaptchaV3`. Leave it out when no provider is enabled.
:::
