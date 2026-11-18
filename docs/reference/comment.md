# Comment
Whenever you're dealing with a comment from a [comment query](docs:getting-elements/comment-queries), you're working with a `Comment` element. The thread that `craft.comments.render()` draws gets its comments from Comments' data API instead, so this page is for your own templates, such as a list of recent comments or a custom thread.

For example, this lists the latest comments on an entry, with each `comment` being a `Comment` element. A query returns approved comments unless you ask for another status.

```twig
{% set comments = craft.comments.fetch()
    .ownerId(entry.id)
    .orderBy('commentDate desc')
    .limit(5)
    .all() %}

{% for comment in comments %}
    <article>
        <p>{{ comment.getAuthorName() }}, {{ comment.getTimeAgo() }}</p>

        {% if comment.isEditorContent() %}
            {{ comment.getRenderedBodyHtml() }}
        {% else %}
            <p>{{ comment.getComment() }}</p>
        {% endif %}
    </article>
{% endfor %}
```

<span id="attributes"></span>

## Properties

::: reference
### `id`

**Type:** `int|null`

The ID of the comment.
:::

::: reference
### `ownerId`

**Type:** `int|null`

The element ID this comment was made on (Entry, Asset, etc).
:::

::: reference
### `ownerSiteId`

**Type:** `int|null`

The site ID of the element this comment was made on.
:::

::: reference
### `owner`

**Type:** `craft\base\ElementInterface|null`

The element this comment was made on (Entry, Asset, etc).
:::

::: reference
### `userId`

**Type:** `int|null`

The ID of the [user](https://craftcms.com/docs/5.x/reference/element-types/users.html) who wrote the comment. This is null for guests.
:::

::: reference
### `author`

**Type:** `craft\elements\User|bool`

The [user](https://craftcms.com/docs/5.x/reference/element-types/users.html) who wrote the comment. For a guest, this is a new User with their name and email populated. For a deleted user, this is a new User named “[Deleted User]”.
:::

::: reference
### `parent`

**Type:** `verbb\comments\elements\Comment|null`

The comment this is a reply to, when it's approved. For a top-level comment, or a reply to one that's held, removed, spam or deleted by its author, this is null. Use [`getParentComment()`](#getParentComment) for the parent whatever its status.
:::

::: reference
### `parentId`

**Type:** `int|null`

The ID of the comment this is a reply to.
:::

::: reference
### `rootId`

**Type:** `int|null`

The ID of the comment at the top of this comment's thread. On a top-level comment, this is its own ID.
:::

::: reference
### `depth`

**Type:** `int|null`

The number of replies deep this comment is. Top-level comments are `0`.
:::

::: reference
### `status`

**Type:** `string|null`

The comment's stored status, which is `approved`, `pending`, `spam` or `removed`. A comment its author deleted keeps the status it had, so use [`getStatus()`](#getStatus) when you need to tell those apart.
:::

::: reference
### `moderationState`

**Type:** `string|null`

The comment’s place in moderation, using the same four values as `status`, which is kept in step with it. Only `approved` comments are shown publicly.
:::

::: reference
### `heldReason`

**Type:** `string|null`

The reason a `pending` or `spam` comment was held, which moderators see on the Comments screen. It's one of `pre-moderation`, `commenter`, `protection`, `keyword`, `edited` or `moderator`, and null for an approved or removed comment. When you hold a comment from your own code, such as in the [`beforeSave`](docs:developers/events#the-beforeSave-event) event, set this along with `moderationState` and `status`.
:::

::: reference
### `name`

**Type:** `string|null`

The name the commenter gave. It’s only set for guests.
:::

::: reference
### `email`

**Type:** `string|null`

The email address the commenter gave. It’s only set for guests. Take care not to output this on public pages.
:::

::: reference
### `url`

**Type:** `string|null`

The URL that this comment was made from.
:::

::: reference
### `ipAddress`

**Type:** `string|null`

The commenter's IP address, recorded when posting through a public form. See [Client IP Addresses](docs:feature-tour/security#client-ip-addresses).
:::

::: reference
### `userAgent`

**Type:** `string|null`

The commenter's user agent, recorded when posting through a public form.
:::

::: reference
### `comment`

**Type:** `string|null`

The comment's plain text. A rich comment also has this, without its formatting. Use `getRenderedBodyHtml()` to show one.
:::

::: reference
### `contentFormat`

**Type:** `string|null`

The way the comment was written, which is `plain` or `rich`.
:::

::: reference
### `commentDate`

**Type:** `DateTime|null`

The date the comment was posted.
:::

::: reference
### `editedAt`

**Type:** `DateTime|null`

The date the comment's content last changed after it was posted. This is null if it hasn't been edited.
:::

::: reference
### `authorRemovedAt`

**Type:** `DateTime|null`

The date the author deleted the comment, or null if they haven’t. These comments are left out of a query for `approved` comments. Use `.status('author-removed')` to find them.
:::

::: reference
### `gifUrl`

**Type:** `string|null`

The URL of the GIF from GIPHY shown with the comment, if it has one. Commenters add GIFs while [GIFs from GIPHY](docs:feature-tour/rich-authoring#gIFs-from-giphy) are on.
:::

::: reference
### `gifWidth`, `gifHeight`

**Type:** `int|null`

The size of the comment's GIF in pixels, when it's known.
:::

::: reference
### `conversationId`

**Type:** `int|null`

The ID of the conversation, which holds an element's comments on one site.
:::

::: reference
### `identityId`

**Type:** `int|null`

The ID of the commenter's identity, which represents a registered user or a guest.
:::


## Methods

::: reference
### `getStatus()`

**Returns:** `string|null`

Returns the comment's status, the same as [`status`](#status), except that a comment its author deleted returns `author-removed`. These are the values the `status` parameter of a [comment query](docs:getting-elements/comment-queries#status) accepts.
:::

::: reference
### `isGuest()`

**Returns:** `bool`

Returns true if the comment was made by a guest.
:::

::: reference
### `getComment()`

**Returns:** `string|null`

Returns the comment's plain text, with any emoji shortcodes stored in it converted to emoji.
:::

::: reference
### `isEditorContent()`

**Returns:** `bool`

Returns true if this is a rich comment.
:::

::: reference
### `getRenderedBodyHtml()`

**Returns:** `Twig\Markup`

Returns the rendered HTML of a rich comment, or an empty value for a plain comment.
:::

::: reference
### `getTimeAgo()`

**Returns:** `string`

Returns a human-friendly string of how long ago a comment was made, such as `2 minutes ago`, in the current site's language.
:::

::: reference
### `getExcerpt(startPos, maxLength)`

**Returns:** `string|null`

Returns an excerpt of the comment. `startPos` defaults to `0` and `maxLength` to `100`.
:::

::: reference
### `getPermalink()`

**Returns:** `string|null`

Returns a URL for the comment on the page of its element, which works wherever it is in the thread. This is null if the element doesn't have a URL. See [Linking to a Comment](docs:template-guides/rendering-comments#linking-to-a-comment).
:::

::: reference
### `getShownFields()`

**Returns:** `array`

Returns the comment's custom field values that are shown publicly, in the field layout's order. It only includes fields that are shown on comments, and only values entered while the field was shown, so it's always safe to print on a public page. Each item has the field's `handle`, `label`, `type`, `placement` (`details` or `inline`), its raw `value` and its formatted `text`. Reading a field by its handle, such as `comment.orderNumber`, returns its value whatever its setting, so use this instead. See [Showing Values in Your Own Code](docs:template-guides/custom-fields#showing-values-in-your-own-code).

```twig
{% for field in comment.shownFields %}
    <p>{{ field.label }}: {{ field.text }}</p>
{% endfor %}
```
:::

::: reference
### `getReplies()`

**Returns:** `verbb\comments\elements\db\CommentQuery`

Returns a query for the comment's approved direct replies, the same default as any comment query. `comment.children` returns the same replies. To include other statuses, such as on a moderation page of your own, add `.status(null)` for every status, or the ones you want.

```twig
{% for reply in comment.getReplies().all() %}
    <p>{{ reply.getAuthorName() }}: {{ reply.getExcerpt() }}</p>
{% endfor %}

{# Every reply, whatever its status #}
{% set everyReply = comment.getReplies().status(null).all() %}
```
:::

::: reference
### `getParentComment()`

**Returns:** `verbb\comments\elements\Comment|null`

Returns the comment this is a reply to, whatever its status, or null for a top-level comment. Check its status before showing it publicly, or use [`parent`](#parent), which is only the approved parent.
:::

::: reference
### `hasFlagged()`

**Returns:** `bool`

Returns true if the current visitor has reported this comment and the report is still open, so you can show that they've already reported it.
:::

::: reference
### `isFlagged()`

**Returns:** `bool`

Returns true if the comment has at least one open report.
:::

::: reference
### `getFlags()`

**Returns:** `int`

Returns the number of open reports on this comment. Reports that a moderator has dealt with, or that their author withdrew, aren't counted.
:::

::: reference
### `isSubscribed()`

**Returns:** `bool`

Returns true if the current visitor is subscribed to replies to this comment.
:::

::: reference
### `getVotes()`

**Returns:** `int|float`

Returns the comment's net score, which is its upvotes minus its downvotes. When **Interaction Mode** only allows likes, use `getUpvotes()` for the number of likes.
:::

::: reference
### `getAllVotes()`

**Returns:** `int`

Returns the number of votes made on this comment, counting upvotes and downvotes.
:::

::: reference
### `getUpvotes()`

**Returns:** `int`

Returns the number of upvotes made on this comment.
:::

::: reference
### `getDownvotes()`

**Returns:** `int`

Returns the number of downvotes made on this comment.
:::

::: reference
### `isPoorlyRated()`

**Returns:** `bool`

Whether the comment has at least [`downvoteCommentLimit`](docs:get-started/configuration#downvoteCommentLimit) downvotes, while that's set and voting is up and down.
:::

::: reference
### `getFoldReason()`

**Returns:** `string|null`

Why the thread folds this comment away: `reports` after [`flaggedCommentLimit`](docs:get-started/configuration#flaggedCommentLimit) open reports, `downvotes` when it's [poorly rated](#isPoorlyRated), or `null`. A folded comment is still published.
:::

::: reference
### `getAuthorScore()`

**Returns:** `int`

The author's total votes across their approved comments, upvotes or likes less downvotes, or `0` for a guest.
:::

::: reference
### `hasVoted()`

**Returns:** `bool`

Returns true if the current visitor has voted on this comment, or liked it when **Interaction Mode** only allows likes. Signed-in users are recognised by their account, and guests by the browser they voted from. When **Interaction Mode** is None, this is always false.

```twig
{% if comment.hasVoted() %}
    <p>Thanks for your vote.</p>
{% endif %}
```
:::

::: reference
### `hasUpvoted()`

**Returns:** `bool`

Returns true if the current visitor has upvoted or liked this comment.
:::

::: reference
### `hasDownvoted()`

**Returns:** `bool`

Returns true if the current visitor has downvoted this comment. This is always false when **Interaction Mode** only allows likes, even for a downvote made before it changed.

```twig
<button type="button" {{ comment.hasUpvoted() ? 'aria-pressed="true"' }}>Upvote</button>
<button type="button" {{ comment.hasDownvoted() ? 'aria-pressed="true"' }}>Downvote</button>
```
:::

::: reference
### `getAuthor()`

**Returns:** `craft\elements\User|bool`

Returns a Craft User for the author of this comment. See [`author`](#author).
:::

::: reference
### `getUser()`

**Returns:** `craft\elements\User|null`

Returns the signed-in user who wrote this comment. Unlike [`getAuthor()`](#getAuthor), this is null for a guest or a deleted user.
:::

::: reference
### `getAuthorName()`

**Returns:** `string|null`

Returns the author's name, for a user or a guest.
:::

::: reference
### `getAuthorEmail()`

**Returns:** `string|null`

Returns the author's email address, for a user or a guest. Take care not to output this on public pages.
:::

::: reference
### `getAvatar()`

**Returns:** `craft\elements\Asset|string`

Returns the author's photo, or otherwise the **Placeholder Avatar**, or an empty string if there's neither. An image whose file is missing is skipped.
:::

::: reference
### `getOwner()`

**Returns:** `craft\base\ElementInterface|null`

Returns the element that owns this comment.
:::


## Permission Methods

These check what the current visitor can do, apart from `canDelete()`, which checks the user you pass to it.

::: reference
### `canReply()`

**Returns:** `bool`

Returns true if the visitor can comment on the element and a reply to this comment wouldn't go deeper than **Maximum Reply Depth**.
:::

::: reference
### `canEdit()`

**Returns:** `bool`

Returns true if the visitor is signed in and wrote this comment, it's within the **Author Edit Window**, and it's approved or pending rather than marked as spam, removed or deleted. Users with the **Edit other users’ comments** permission aren't held to the window for their own comments.
:::

::: reference
### `canDelete(user)`

**Returns:** `bool`

Returns true if `user` can delete this comment. That's anyone with the **Delete comments** permission, or the comment's author within the **Author Edit Window**. As for `canEdit()`, users with the **Edit other users’ comments** permission aren't held to the window for their own comments. Pass `currentUser`, and check it's set first, as guests can't delete comments.
:::

::: reference
### `canVote()`

**Returns:** `bool`

Returns true if **Interaction Mode** isn't None, and the visitor is signed in or **Allow Guest Interactions** is on. It only checks those settings, not the comment, so it can be true where a vote would still be refused. Only approved comments accept votes, votes can't change while the discussion is closed, and a visitor blocked from the discussion can't vote. When you show voting buttons for comments of any status, also check that `comment.getStatus()` is `approved`.
:::

::: reference
### `canFlag()`

**Returns:** `bool`

Returns true if **Allow Reporting** is on, and the visitor is signed in or **Allow Guest Reports** is on. Like `canVote()`, it only checks those settings. Only approved comments accept reports, and a visitor blocked from the discussion can't report, though a closed discussion still accepts reports. Check `comment.getStatus()` too when you list comments of any status.
:::


## Deleting a Comment

An author can delete their own comment within the **Author Edit Window**, using **Delete** in the comment's **···** menu in the thread. Deleting hides the comment from the site, and its approved replies stay in place under a placeholder. Someone with the **Delete comments** permission can delete anyone's comment from the control panel, and moderators can remove a comment from the thread or the Comments index, as described in [Moderation](docs:feature-tour/moderation).

To offer **Delete** in your own interface, send a POST request to the data API's `comments/api/delete` action with the comment's `uid`, as the signed-in author. Like every data API write, it also needs an `operationId`, a random string of 8 to 128 letters, digits, hyphens or underscores. If the same request is sent twice, such as after a double click or a retry on a dropped connection, the second one returns the first one's result rather than acting again, so create a new `operationId` for each delete and reuse it only when retrying that same delete. The action answers in JSON rather than redirecting, so send it from a script. For example, in a template that lists the signed-in visitor's own comments, give each button what the script needs:

```twig
{% if currentUser and comment.userId == currentUser.id and comment.canDelete(currentUser) %}
    <button type="button" data-delete-comment="{{ comment.uid }}">{{ 'Delete' | t('comments') }}</button>
{% endif %}

<script>
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-comment]');

        if (!button) {
            return;
        }

        const response = await fetch('{{ actionUrl('comments/api/delete') }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': '{{ craft.app.request.csrfToken }}',
            },
            body: JSON.stringify({
                uid: button.dataset.deleteComment,
                operationId: crypto.randomUUID(),
            }),
        });

        const result = await response.json();

        if (result.status === 'ok') {
            button.closest('article')?.remove();
        }
    });
</script>
```

A successful response has a `status` of `ok`, the comment's `uid`, the discussion's new `commentCount` under `conversation`, and an `announcement` such as “Comment deleted.” to show the visitor. When the comment can't be deleted, such as after the **Author Edit Window** has passed, the response has an error status code, a `status` of `error`, and an `errors` list whose first item has a `message` saying why.

A page holding a CSRF token, like this one, mustn't be cached for everyone. For a cached page, fetch a fresh token from the `csrf` in the data API's `comments/api/session` response before sending the request.
