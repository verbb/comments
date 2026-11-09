# Finding User Votes

Retrieve votes when an account page needs to show what a user has voted on. Each of the calls below takes a user ID and returns an array of that user's votes. Votes can say a lot about a person, so limit them to the person viewing the page, or to someone allowed to see them.

This example belongs in an account template that only signed-in users can reach, and lists the comments the current user has voted on:

```twig
{% for vote in craft.comments.getUserVotes(currentUser.id) %}
    {% set comment = craft.comments.fetch().id(vote.commentId).one() %}

    {% if comment %}
        <p>
            You {{ vote.type == 'upvote' ? 'upvoted' : 'downvoted' }}:
            {{ comment.getExcerpt() }}
        </p>
    {% endif %}
{% endfor %}
```

Each vote becomes a line with the start of the comment's text. The query only finds approved comments, so votes on comments that have since been held or removed are skipped. It also runs once for each vote, so limit how many you show for an active user.

A vote is a record with these properties:

| Property | What It Is |
| --- | --- |
| `id`, `uid` | The vote's IDs. |
| `commentId` | The ID of the comment voted on. |
| `identityId` | The ID of the commenter identity who voted. |
| `type` | `upvote` or `downvote`. A like is an `upvote`. |
| `dateCreated`, `dateUpdated` | When it was made, and last changed. |

Guests' votes aren't connected to a user, so they aren't included. A vote that's been taken back doesn't appear.

## Calls Used in This Task

### `craft.comments.getUserVotes(userId)`

Returns all of a user's votes.

### `craft.comments.getUserUpvotes(userId)`

Returns a user's upvotes, which are their likes when **Interaction Mode** is set to **Likes (upvote only)**.

### `craft.comments.getUserDownvotes(userId)`

Returns a user's downvotes.
