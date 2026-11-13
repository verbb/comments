# Finding User Votes

The default templates show each comment's vote count, but not which way the person viewing the page voted. To show that, or to list a user's votes on an account page, fetch the user's votes with the calls below. Each takes a user ID and returns an array of [Vote](docs:reference/vote) objects.

Votes can say a lot about a person, so only show them to the person who made them, or to someone allowed to see them.

## Highlighting Comments the Current User Voted On

This example lists an entry's comments in your own template and marks the ones the signed-in user upvoted or downvoted. It fetches the user's votes once, before the loop, and collects the IDs of the comments they voted on, so each comment only needs a check against those lists.

```twig
{% set upvotedIds = [] %}
{% set downvotedIds = [] %}

{% if currentUser %}
    {% set upvotedIds = craft.comments.getUserUpvotes(currentUser.id) | map(vote => vote.commentId) %}
    {% set downvotedIds = craft.comments.getUserDownvotes(currentUser.id) | map(vote => vote.commentId) %}
{% endif %}

{% set comments = craft.comments.fetch()
    .ownerId(entry.id)
    .all() %}

{% for comment in comments %}
    {% set voteClass = comment.id in upvotedIds ? 'is-upvoted' : (comment.id in downvotedIds ? 'is-downvoted' : '') %}

    <div class="comment {{ voteClass }}">
        <p>{{ comment.comment }}</p>
        <p>{{ comment.votes }} votes</p>

        {% if voteClass == 'is-upvoted' %}
            <p>You upvoted this.</p>
        {% elseif voteClass == 'is-downvoted' %}
            <p>You downvoted this.</p>
        {% endif %}
    </div>
{% endfor %}
```

Each comment the user upvoted gets an `is-upvoted` class and a line saying so, which you can style to show the vote as already pressed. Downvoted comments get `is-downvoted` instead. Guests see the comments without either.

Only call these with a real user ID. Passing a null ID, such as `currentUser.id` when nobody is signed in, returns the votes guests have made, which is why the example checks `currentUser` first. Guests' votes aren't connected to a user, so they're never included for a user ID.

## Listing a User's Votes on an Account Page

This example belongs in an account template that only signed-in users can reach, and lists the comments the current user has upvoted:

```twig
{% for vote in craft.comments.getUserUpvotes(currentUser.id) %}
    {% set comment = vote.getComment() %}

    {% if comment %}
        <p>
            <a href="{{ comment.owner.url }}">{{ comment.owner.title }}</a>:
            {{ comment.comment }}
        </p>
    {% endif %}
{% endfor %}
```

`vote.getComment()` runs a comment query, so it only finds comments with a status in [`defaultQueryStatus`](docs:get-started/configuration#defaultquerystatus), which is approved comments by default. Votes on comments that have since been held or removed are skipped. It also runs once for each vote, so limit how many you show for an active user.

## Choosing Which Votes to Fetch

Each of the three calls takes a user ID and returns an array of [Vote](docs:reference/vote) objects, or an empty array when the user hasn't voted. They differ in which votes they include.

| Call | What It Returns |
| --- | --- |
| `craft.comments.getUserUpvotes(userId)` | The user's upvotes. |
| `craft.comments.getUserDownvotes(userId)` | The user's downvotes. |
| `craft.comments.getUserVotes(userId)` | Every vote the user has made, including cancelled ones. |

A vote holds the voter's `userId`, the `commentId` it was made on, and an `upvote` or `downvote` value of `1`. The [Vote](docs:reference/vote) reference lists every property.

Each user has one vote per comment. Voting the opposite way on a comment cancels the existing vote rather than replacing it, and the vote is kept with neither `upvote` nor `downvote` set. `getUserVotes()` returns these cancelled votes too, so check `vote.upvote` and `vote.downvote` before treating a vote as counted. This example does that to show a user how many votes they've cast.

```twig
{% set upvoteCount = 0 %}
{% set downvoteCount = 0 %}

{% for vote in craft.comments.getUserVotes(currentUser.id) %}
    {% if vote.upvote %}
        {% set upvoteCount = upvoteCount + 1 %}
    {% elseif vote.downvote %}
        {% set downvoteCount = downvoteCount + 1 %}
    {% endif %}
{% endfor %}

<p>You've upvoted {{ upvoteCount }} comments and downvoted {{ downvoteCount }}.</p>
```

Like the account page example, this belongs in a template only signed-in users can reach.
