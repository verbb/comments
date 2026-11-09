# Vote
Visitors can vote on a comment according to your site's [Interactions](docs:feature-tour/interactions) settings. **Interaction Mode** decides what they can do. With **Likes (upvote only)**, which is the default, they can only upvote, shown as a Like button. With **Upvotes and downvotes**, they can do either, and with **None**, voting is off.

Signed-in visitors can vote while voting is on, and guests can too if **Allow Guest Interactions** is on. Each person has one vote per comment, and only approved comments can be voted on. Each person can vote 60 times a minute, counted per user for signed-in visitors and per IP address for guests.

To see a comment's votes in a template, use `comment.getVotes()`, `comment.getUpvotes()` and `comment.getDownvotes()` on the [Comment](docs:developers/comment), and `comment.canVote()` to check whether the visitor can vote. For a list of someone's votes, see [Finding User Votes](docs:template-guides/finding-user-votes).

<span id="up-vote-a-comment"></span>
<span id="down-vote-a-comment"></span>

## Voting on a Comment

The default templates handle this. In your own template, send a POST request with the `commentId` and `upvote`. Set `upvote` to `1` for an upvote, and leave it out or use `0` for a downvote, which is refused unless **Interaction Mode** is **Upvotes and downvotes**.

Voting the same way again takes the vote back, and voting the other way changes it.

```twig
{% if comment.canVote() %}
    <form method="post" accept-charset="UTF-8">
        {{ actionInput('comments/comments/vote') }}
        {{ csrfInput() }}
        {{ hiddenInput('commentId', comment.id) }}
        {{ hiddenInput('upvote', 1) }}

        <button type="submit">{{ 'Upvote' | t('comments') }} ({{ comment.getUpvotes() }})</button>
    </form>

    <form method="post" accept-charset="UTF-8">
        {{ actionInput('comments/comments/vote') }}
        {{ csrfInput() }}
        {{ hiddenInput('commentId', comment.id) }}
        {{ hiddenInput('upvote', 0) }}

        <button type="submit">{{ 'Downvote' | t('comments') }} ({{ comment.getDownvotes() }})</button>
    </form>
{% endif %}
```

Leave out the second form if your site only uses likes. The visitor returns to the page they were on. If the vote wasn't accepted, [`craft.comments.render()`](docs:template-guides/rendering-comments) on that page says why. A request that accepts JSON gets `success` and `type` back instead, where `type` is `upvote`, `downvote` or null once the vote has been taken back.

## In GraphQL

The [`voteComment`](docs:developers/graphql#votecomment) mutation returns a Vote model.

<span id="attributes"></span>

::: reference
### `commentId`

**Type:** `int|null`

The ID of the comment this vote was made on.
:::

::: reference
### `comment`

**Type:** `verbb\comments\elements\Comment|null`

The [Comment](docs:developers/comment) this vote was made on.
:::

::: reference
### `userId`

**Type:** `int|null`

The ID of the user who made this vote, if they're signed in.
:::

::: reference
### `user`

**Type:** `craft\elements\User|null`

The [User](https://docs.craftcms.com/api/v5/craft-elements-user.html) who made this vote, or null for a guest.
:::

::: reference
### `upvote`

**Type:** `int|null`

`1` if the vote is now an upvote.
:::

::: reference
### `downvote`

**Type:** `int|null`

`1` if the vote is now a downvote. If both are empty, the vote was taken back.
:::
