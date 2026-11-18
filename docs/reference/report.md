# Report
Visitors can report a comment for a moderator to review, choosing one of your **Report Reasons** and adding an optional note. Reported comments wait for a moderator under the **Reported** source on the Comments screen, and stay on the site until a moderator removes them.

Signed-in visitors can report comments while **Allow Reporting** is on, and guests can too if **Allow Guest Reports** is on. Each person can make 3 reports every 10 minutes, counted per user for signed-in visitors and per IP address for guests. Withdrawing a report counts towards the same limit. Only approved comments can be reported.

In code, a report is called a flag. That's why the methods for working with them in a template are `comment.isFlagged()`, `comment.getFlags()` and `comment.hasFlagged()` on the [Comment](docs:reference/comment), the related settings are `allowFlagging` and `allowGuestFlagging`, and the GraphQL mutation is `flagComment`. To find comments with open reports, use the `isFlagged` parameter of a [comment query](docs:getting-elements/comment-queries#isFlagged).

<span id="flagging-a-comment"></span>

## Reporting a Comment

The thread from `craft.comments.render()` offers **Report…** in each comment's **···** menu. In your own template, post a form to `comments/comments/flag` with the `commentId` of the comment. Sending it again withdraws that person's open report.

```twig
{% if comment.canFlag() %}
    <form method="post" accept-charset="UTF-8">
        {{ actionInput('comments/comments/flag') }}
        {{ csrfInput() }}
        {{ hiddenInput('commentId', comment.id) }}

        <button type="submit">
            {{ comment.hasFlagged() ? 'Withdraw report' | t('comments') : 'Report this comment' | t('comments') }}
        </button>
    </form>
{% endif %}
```

The visitor returns to the page they were on. If the report wasn't accepted, such as for a comment that's no longer public, the reason is in Craft's `error` flash message, which your template can show with `craft.app.session.getFlash('error')`. A report made this way has no reason.

To include one of your **Report Reasons** and a note for the moderator, send a POST request from a script to the data API's `comments/api/report` action instead, with the comment's `uid`, an `operationId`, a `reason` matching the value of one of your **Report Reasons**, and optional `details` of up to 2,000 characters. Set `withdraw` to `true` to take the report back. Nobody can report their own comment, and a closed discussion still accepts reports, so harmful content can be reported after commenting ends. It answers in JSON, and needs Craft's CSRF token in an `X-CSRF-Token` header, as shown for [deleting a comment](docs:reference/comment#deleting-a-comment), which also explains the `operationId`.

## In GraphQL

The [`flagComment`](docs:graphql/mutations#the-flagComment-mutation) mutation returns the report as a `FlagInterface`, with the fields below. `commentId` and `comment` are only on schemas that can view comments, and `userId` and `user` only on schemas that can view users. The report doesn't say whether it's now open or withdrawn, so pass `withdraw` when your front end needs to know.

<span id="attributes"></span>

::: reference
### `commentId`

**Type:** `ID`

The ID of the comment that was reported.
:::

::: reference
### `comment`

**Type:** `CommentInterface`

The [Comment](docs:reference/comment) that was reported.
:::

::: reference
### `userId`

**Type:** `Int`

The ID of the user who sent the report, or null for a guest.
:::

::: reference
### `user`

**Type:** `UserInterface`

The user who sent the report, or null for a guest.
:::
