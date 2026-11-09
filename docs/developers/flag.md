# Report
Visitors can report a comment for a moderator to review, choosing one of your **Report Reasons** and adding an optional note. Reported comments wait for a moderator under the **Reported** source on the Comments screen, and stay on the site until a moderator removes them.

Signed-in visitors can report comments while **Allow Reporting** is on, and guests can too if **Allow Guest Reports** is on. Each person can make 3 reports every 10 minutes, counted per user for signed-in visitors and per IP address for guests. Withdrawing a report counts towards the same limit. Only approved comments can be reported.

In code, a report is called a flag. That's why the methods for working with them in a template are `comment.isFlagged()`, `comment.getFlags()` and `comment.hasFlagged()` on the [Comment](docs:developers/comment), the related settings are `allowFlagging` and `allowGuestFlagging`, and the GraphQL mutation is `flagComment`. To find comments with open reports, use the `isFlagged` parameter of a [comment query](docs:getting-elements/comment-queries#isflagged).

<span id="flagging-a-comment"></span>

## Reporting a Comment

The default templates show **Report this comment** for you. In your own template, send a POST request with the `commentId` of the comment. `reason` is optional, and is only kept if it matches the value of one of your **Report Reasons**. `details` is optional text for the moderator.

Reporting a comment again withdraws that person's open report.

```twig
{% set reportReasons = craft.app.plugins.getPlugin('comments').getSettings().reportReasons %}

{% if comment.canFlag() %}
    <form method="post" accept-charset="UTF-8">
        {{ actionInput('comments/frontend-api/report') }}
        {{ csrfInput() }}
        {{ hiddenInput('commentId', comment.id) }}

        <select name="reason">
            {% for reason in reportReasons %}
                <option value="{{ reason.value }}">{{ reason.label | t('comments') }}</option>
            {% endfor %}
        </select>

        <textarea name="details"></textarea>

        <button type="submit">
            {{ comment.hasFlagged() ? 'Withdraw report' | t('comments') : 'Report this comment' | t('comments') }}
        </button>
    </form>
{% endif %}
```

The visitor returns to the page they were on, where [`craft.comments.render()`](docs:template-guides/rendering-comments) thanks them for the report or says why it wasn't accepted. `comments/comments/flag` also works for a form with only a `commentId`, and records the report without a reason.

## In GraphQL

The [`flagComment`](docs:developers/graphql#flagcomment) mutation returns a `Flag` model for the report.

<span id="attributes"></span>

::: reference
### `commentId`

**Type:** `int|null`

The ID of the comment that was reported.
:::

::: reference
### `comment`

**Type:** `verbb\comments\elements\Comment|null`

The [Comment](docs:developers/comment) that was reported.
:::

::: reference
### `userId`

**Type:** `int|null`

The ID of the user who sent the report, if they're signed in.
:::
