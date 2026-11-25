# Mutations

Comments adds mutations for posting, editing, voting on, reporting, following and moderating comments. [Enable Comments in Your Schema](docs:graphql/queries#enable-comments-in-your-schema) lists the permission each one needs.

## How Mutations Work

Mutations act as whoever is making the request, which is the signed-in user for a session or a guest otherwise. Guests can only comment, vote or report comments if your settings allow it. The [GraphQL Authentication](https://plugins.craftcms.com/graphql-authentication) plugin's tokens are also recognised.

Send mutations as POST requests, as Comments refuses them over GET. On a public schema, which needs no token, a request that carries a Craft session cookie or a guest's cookie also needs the page's CSRF token, so another site can't make a visitor's browser post, vote or delete for them. Send it in an `X-CSRF-Token` header. A request from a server without those cookies doesn't need one.

Every mutation also takes an optional `operationId`, a unique ID for that change of 8 to 128 letters, digits, hyphens or underscores. A repeat with the same ID within ten minutes returns the first result rather than acting again, which stops a retried request from toggling a vote or report back.

## Protection

While **Enable Protection** is on, `createComment` and `saveComment` run Comments' [spam checks](docs:feature-tour/anti-spam) before saving, so each one needs fresh preparation from Comments' data API first. Send a GET request to the `comments/api/protection` action on your site, such as `/actions/comments/api/protection?ownerId=…&siteId=…&channel=headless`. `siteId` defaults to the current site, and you add `commentUid`, the comment's UID, when preparing an edit. The response looks like this:

```json
{
    "apiVersion": 1,
    "status": "ok",
    "protection": {
        "version": 1,
        "manifest": {
            "version": 1,
            "instanceId": "…",
            "preparation": "SIGNED_PREPARATION",
            "issuedAt": 1762850700,
            "expiresAt": 1762851600,
            "honeypot": null,
            "javascript": false,
            "challenges": [
                { "provider": "turnstile", "siteKey": "…", "action": "comments_create" }
            ]
        },
        "expiresAt": 1762851600
    },
    "csrf": null
}
```

Pass `protection.version`, `protection.manifest.preparation` and any provider token your front end collected for the challenges in `manifest.challenges` as the mutation's `protection` argument:

```json
{
    "version": 1,
    "preparation": "SIGNED_PREPARATION",
    "challenges": {
        "turnstile": "CLIENT_PROVIDER_TOKEN"
    }
}
```

The input type is `CommentsProtectionEvidence`. Give a challenge's token in the field for its provider: `turnstile`, `recaptchaV3`, `hcaptcha` or `friendlyCaptcha`. Omit `challenges` when no provider is enabled. Honeypot, minimum form time and JavaScript checks aren't applied to these requests, but replay protection, rate limits, challenges and spam classifiers are. Get fresh preparation after every attempted submission, and before `expiresAt`, which is a Unix timestamp. Preparation doesn't grant authorisation, so schema permissions, author permissions and validation still apply. `csrf` holds the CSRF token's `name` and `value`, for a request that will submit with cookies. It's null for a front end on another site that signs in with a member token or a guest session token, as in the example, because those requests don't use a CSRF token.

A request from a server, or from a page on your Craft site itself, needs nothing more. A front end on another domain can fetch the preparation from the browser once its origin is listed under **Allowed Origins** in **Comments** → **Settings** → **Other Sites**, and it names how it's signed in with an `X-Comments-Profile` header. An origin listed with **Shared Cookies**, such as another subdomain of your site, sends `same-site-credentialed` with the browser's cookies, so a signed-in visitor's preparation matches the session they submit with. An origin listed with **Member Tokens**, a separate site whose own server signs its members in, sends `cross-origin-member` with the member's token, so the preparation is for that member. Member tokens only work with the data API, though, so a site that signs its members in that way posts, votes and reports through the [data API](docs:headless/data-api) rather than these mutations, as [Signing In Across Sites](docs:headless/signing-in-across-sites) explains. Any other listed origin sends `cross-origin-public` without cookies and gets a guest's preparation. Origins that aren't listed get no cross-origin headers, so the browser refuses the response, and Craft's `allowedGraphqlOrigins` setting doesn't apply to this action. The preparation only carries spam-check evidence, which a script on a server could fetch anyway, not any permission. Use the same signed-in identity or session for preparing and submitting.

## Posting and Editing Comments

<span id="createcomment"></span>

### The `createComment` Mutation

Saves a new comment on the element given as `ownerId`, or a reply when given a `parentId`, or the parent's UID as `parentUid`, as the data API names it. A parent given by `parentUid` must be an approved comment on the same element. To post a rich comment while **Authoring Mode** is **Rich (Editor)**, send the Editor's JSON as `contentDocument`, which makes the comment rich without `contentFormat`. You can also send `contentFormat` as `plain` or `rich`. While rich authoring is off, the comment is saved as plain text from `comment`. Custom fields the site's form asks for, under **The Comment Form**, are accepted as arguments too, as described in [Custom Fields](docs:graphql/queries#custom-fields).

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `ID` | The ID of the element the comment is on. |
| `siteId` | `ID` | The ID of the element's site. Defaults to the primary site. |
| `parentId` | `ID` | The ID of the comment this one replies to. |
| `parentUid` | `String` | The UID of the comment this one replies to, as an alternative to `parentId`. |
| `name` | `String` | The guest's name. |
| `email` | `String` | The guest's email address. |
| `comment` | `String` | The comment's text. |
| `contentFormat` | `String` | `plain` or `rich`. |
| `contentDocument` | `String` | The Editor JSON of a rich comment. |
| `notifyReplies` | `Boolean` | Whether a guest is emailed about replies. |
| `protection` | `CommentsProtectionEvidence` | The spam protection evidence described under [Protection](#protection). |
| `operationId` | `String` | A unique ID that stops a retried request from acting twice. |

```graphql
mutation NewComment($parentId: ID, $ownerId: ID, $siteId: ID, $name: String, $email: String, $comment: String, $protection: CommentsProtectionEvidence) {
    createComment(parentId: $parentId, ownerId: $ownerId, siteId: $siteId, name: $name, email: $email, comment: $comment, protection: $protection) {
        id
        moderationState
        author {
            name
        }
        comment
    }
}
```

Query variables:

```json
{
    "ownerId": 2460,
    "siteId": 1,
    "parentId": 30,
    "name": "Alex Morgan",
    "email": "alex@example.com",
    "comment": "Here’s a reply to the first comment."
}
```

`siteId` defaults to the primary site. `name` and `email` are for guests, so leave them out for a signed-in user. A guest can also send `notifyReplies` as `true` to be emailed about replies, which starts by sending them an email to confirm their address if they haven't confirmed it already. It's ignored for a signed-in user. The new comment is moderated like any other, so check its `moderationState` before showing it as published.

```json
{
    "data": {
        "createComment": {
            "id": "2480",
            "moderationState": "approved",
            "author": {
                "name": "Alex Morgan"
            },
            "comment": "Here’s a reply to the first comment."
        }
    }
}
```

<span id="savecomment"></span>

### The `saveComment` Mutation

Edit the content of an existing comment, identified by `id` or `uid`. Only the comment's author can use this, within the **Author Edit Window**, and only while the comment is approved or pending. The author, owner element, parent and dates can't be changed. A rich comment needs `contentDocument`, as it can't be replaced with plain text. `siteId` defaults to the primary site. An edit goes through moderation the same way a new comment does, so it can return to pending.

It takes the same arguments as `createComment`, plus `id` or `uid` to identify the comment.

```graphql
mutation UpdateComment($id: ID, $comment: String, $protection: CommentsProtectionEvidence) {
    saveComment(id: $id, comment: $comment, protection: $protection) {
        id
        moderationState
        comment
        editedAt
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

```json
{
    "data": {
        "saveComment": {
            "id": "34",
            "moderationState": "approved",
            "comment": "I’m totally changing what I said.",
            "editedAt": "2026-10-14T10:05:00+10:00"
        }
    }
}
```

<span id="deletecomment"></span>

### The `deleteComment` Mutation

Delete your own comment, given as `id` or `uid`, within the **Author Edit Window**. This hides it, and any approved replies stay in place under a placeholder. Users with the **Delete comments** user permission can still only delete their own comments this way, though they, and users with **Edit other users’ comments**, aren't held to the window. `siteId` is optional, as the comment is found on whichever site it was made on. The mutation returns `true`, including when no comment has that ID.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID` | The ID of the comment. |
| `uid` | `String` | The UID of the comment, as an alternative to `id`. |
| `siteId` | `ID` | The ID of the comment's site. |
| `operationId` | `String` | A unique ID that stops a retried request from acting twice. |

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

## Voting, Reporting and Following

<span id="votecomment"></span>

### The `voteComment` Mutation

Upvote a comment with `upvote: true`, or downvote it with `downvote: true`, which is refused unless **Interaction Mode** is **Upvotes and downvotes**. Send one or the other, because a vote with neither or both is refused with “Choose to upvote or downvote.” Repeating the same vote takes it back, and sending the other one changes it. To set the vote instead of toggling it, pass `vote` as `upvote`, `downvote` or `null` to remove it, which is safer for a client that might send the same request twice.

Give the comment as `id`, or as `uid`, as the data API names it. With `siteId`, the comment must be on that site. Only approved comments can be voted on, voting stops once a discussion is closed, blocked commenters can't vote, and each person can vote 60 times a minute.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID` | The ID of the comment. |
| `uid` | `String` | The UID of the comment, as an alternative to `id`. |
| `siteId` | `ID` | The ID of the comment's site. |
| `upvote` | `Boolean` | Toggles an upvote. |
| `downvote` | `Boolean` | Toggles a downvote. |
| `vote` | `String` | Sets the vote to `upvote`, `downvote` or null, rather than toggling it. |
| `operationId` | `String` | A unique ID that stops a retried request from acting twice. |

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

```json
{
    "data": {
        "voteComment": {
            "upvote": true,
            "downvote": null,
            "comment": {
                "upvotes": 5,
                "downvotes": 1
            }
        }
    }
}
```

<span id="flagcomment"></span>

### The `flagComment` Mutation

Report a comment to the moderators. `reason` is only kept if it matches the value of one of your **Report Reasons**, and both `reason` and `details` are optional, with `details` up to 2,000 characters. Repeating the mutation withdraws the report. To set it instead of toggling it, pass `withdraw` as `false` to make a report or `true` to take yours back.

Give the comment as `id` or `uid`, and with `siteId`, the comment must be on that site. Nobody can report their own comment. Only approved comments can be reported, reporting stays open in a closed discussion so harmful content can still be reported, blocked commenters can't report, and each person can make 3 reports every 10 minutes.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID` | The ID of the comment. |
| `uid` | `String` | The UID of the comment, as an alternative to `id`. |
| `siteId` | `ID` | The ID of the comment's site. |
| `reason` | `String` | The value of one of your **Report Reasons**. |
| `details` | `String` | More detail for the moderators, up to 2,000 characters. |
| `withdraw` | `Boolean` | Sets the report rather than toggling it. `false` makes a report and `true` withdraws yours. |
| `operationId` | `String` | A unique ID that stops a retried request from acting twice. |

```graphql
mutation FlagComment($id: ID!, $reason: String, $details: String) {
    flagComment(id: $id, reason: $reason, details: $details, withdraw: false) {
        commentId
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

```json
{
    "data": {
        "flagComment": {
            "commentId": "34"
        }
    }
}
```

Passing `withdraw: false` makes the report even if the request is sent twice, so the result is always an open report. The returned report doesn't say whether it's open, so use `withdraw` whenever your front end needs to know.

<span id="subscribecomment"></span>

### The `subscribeComment` Mutation

Subscribe to an element’s comment notifications. This needs an element with comments turned on and **Whole discussion** among the **Available Subscriptions**. Without `following`, described below, it also needs a signed-in user, toggles the subscription on and off each time, and returns a message saying what happened, which is “Subscribed to discussion.” or “Unsubscribed from discussion.”, including for a single comment's replies. `siteId` is optional and defaults to the primary site.

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `ID!` | The ID of the element to follow. |
| `siteId` | `ID` | The ID of the element's site. Defaults to the primary site. |
| `commentId` | `ID` | The ID of a comment, to follow its replies instead. |
| `following` | `Boolean` | Sets whether to follow the whole discussion, rather than toggling it. |
| `email` | `String` | A guest's email address, with `following`. |
| `operationId` | `String` | A unique ID that stops a retried request from acting twice. |

```graphql
mutation SubscribeComment($ownerId: ID!, $siteId: ID) {
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

```json
{
    "data": {
        "subscribeComment": "Subscribed to discussion."
    }
}
```

To subscribe to the replies to a specific comment, add its `commentId`. This needs **Replies to individual comments** among the **Available Subscriptions**.

```graphql
mutation SubscribeThread($ownerId: ID!, $siteId: ID, $commentId: ID) {
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

To set whether someone follows the whole discussion, rather than toggling it, pass `following` as `true` or `false`. This works for guests too, who give their `email`, and it answers with a code rather than a message, the same as the data API's `follow` action. The code is `followed`, `unfollowed`, or `confirming` when a guest needs to confirm their address first.

```graphql
mutation FollowThread($ownerId: ID!, $following: Boolean, $email: String) {
    subscribeComment(ownerId: $ownerId, following: $following, email: $email)
}
```

```json
{
    "data": {
        "subscribeComment": "followed"
    }
}
```

## Moderating Comments

<span id="moderatecomment"></span>

### The `moderateComment` Mutation

Moderate a comment, given as `id` or `uid`, with an `action` of `approve`, `remove`, `restore`, `spam`, `not-spam`, `keep` or `hold`. `reason` is kept in the comment's history, and `note` adds a note for other moderators, kept with the comment. This needs the **Moderate comments** schema permission and a signed-in admin or user with the **Moderate comments** user permission (`comments-moderate`). Removing or restoring a comment, or any action on a comment that's already removed, also needs **Trash other users’ comments** (`comments-trash`). `hold` puts an approved comment back to waiting for review, and is refused with a conflict if the comment isn't approved, such as when another moderator has acted on it first. It can't be used on a public schema, or on your own comment, and returns the moderated comment.

| Argument | Type | Description |
| --- | --- | --- |
| `id` | `ID` | The ID of the comment. |
| `uid` | `String` | The UID of the comment, as an alternative to `id`. |
| `action` | `String!` | One of `approve`, `remove`, `restore`, `spam`, `not-spam`, `keep` or `hold`. |
| `reason` | `String` | A reason, kept in the comment's history. |
| `note` | `String` | A note for other moderators, kept with the comment. |
| `operationId` | `String` | A unique ID that stops a retried request from acting twice. |

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

```json
{
    "data": {
        "moderateComment": {
            "id": "34",
            "moderationState": "approved"
        }
    }
}
```
