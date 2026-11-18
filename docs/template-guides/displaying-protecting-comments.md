# Displaying & Protecting Comments

`craft.comments.render()` gives you the complete comments interface, including [spam protection](docs:feature-tour/anti-spam), so most sites never need this page. It's for the times you want something the standard thread doesn't draw, such as a list of recent comments in a sidebar or a custom layout of a discussion, and for posting comments from a form or app of your own, which needs the same spam protection the thread handles for you. To change how the standard thread looks rather than replace it, start with [Theming](docs:template-guides/theming).

## Displaying Comments Yourself

Fetch comments with a [comment query](docs:getting-elements/comment-queries), and each one is a [Comment](docs:reference/comment) element. Queries return approved comments by default. Use `parentId(':empty:')` to start from the top-level comments, and each comment's `getReplies()` for the replies beneath it. Put this in the entry's template where the comments should appear:

```twig
{% set comments = craft.comments.fetch()
    .owner(entry)
    .parentId(':empty:')
    .orderBy('commentDate desc')
    .all() %}

{% macro comment(comment) %}
    <article id="comment-{{ comment.id }}">
        <p>{{ comment.getAuthorName() }}, {{ comment.commentDate | date('short') }}</p>

        {% if comment.isEditorContent() %}
            {{ comment.getRenderedBodyHtml() }}
        {% else %}
            <p>{{ comment.getComment() | nl2br }}</p>
        {% endif %}

        {% for field in comment.shownFields %}
            <p>{{ field.label }}: {{ field.text }}</p>
        {% endfor %}
    </article>

    {% for reply in comment.getReplies().all() %}
        {{ _self.comment(reply) }}
    {% endfor %}
{% endmacro %}

{% for comment in comments %}
    {{ _self.comment(comment) }}
{% endfor %}
```

Each approved comment shows its author, date, text and any custom field values it shows publicly, followed by its approved replies. `getRenderedBodyHtml()` is a rich comment's HTML, which Editor has already sanitised, and everything else is escaped by Twig. Use `comment.shownFields` for custom field values, rather than reading a field by its handle, so a field that's **Not shown** stays private, as [Custom Fields](docs:template-guides/custom-fields#showing-values-in-your-own-code) explains.

`getReplies()` returns approved replies unless you ask for another status, as a comment query does. A comment that isn't approved, including one its author has deleted, is left out along with everything beneath it, where the thread from `craft.comments.render()` would keep its replies under a placeholder. This also makes a query for each comment, so limit how many you show on a busy discussion.

A list like this is the same for everyone, so it can be cached with `{% cache %}`. It holds nothing about the visitor, and nothing for them to do, so for posting, voting and following, keep `craft.comments.render()` on the page as well, or build your own form as described below.

## Posting from Your Own Code

Comments are posted through the data API's [`create`](docs:headless/data-api#writing-comments) action, which runs the same checks as the thread, from your site's permissions and moderation to custom field rules and spam protection. Spam protection needs a little work from your code on each attempt:

1. Fetch a **preparation** from the `protection` read. It's a signed, single-use permission to post one comment, which says which checks are turned on, such as the honeypot field or a Turnstile challenge.
2. Mount it on your form with `@verbb/protect`, Comments' spam protection package, which adds the hidden fields and runs any challenge.
3. When the visitor posts, ask Protect for its **evidence**, the result of those checks, and send it with the comment as `protection`.
4. Fetch a fresh preparation before the next attempt, whether the post succeeded or not.

The simplest way to do all of this is `@verbb/comments-core`, which handles preparations, evidence, retries and signing in for you, and keeps a preparation fresh in a page left open all day. The [package docs](https://docs.verbb.io/comments/core/protection) explain it, and it's what the React and Vue packages use.

If you'd rather call the API directly, this example posts a plain-text comment from a signed-in member on Craft's own site. Install `@verbb/protect` from npm at the version that matches your `verbb/protect` package, which `composer show verbb/protect` shows. The form goes in the entry's template:

```twig
<form id="comment-form" data-owner-id="{{ entry.id }}" data-site-id="{{ entry.siteId }}">
    <label for="comment-text">Your comment</label>
    <textarea id="comment-text" name="comment" required></textarea>
    <p class="form-error" role="alert" hidden></p>
    <button type="submit">Post comment</button>
</form>
```

And this goes in a script module your build includes on the page:

```js
import { mountProtection, parseManifest } from '@verbb/protect';
import '@verbb/protect/styles.css';

const endpoint = '/actions/comments/api';
const headers = { Accept: 'application/json', 'X-Comments-Api-Version': '1' };
const form = document.querySelector('#comment-form');
const error = form.querySelector('.form-error');
const ownerId = Number(form.dataset.ownerId);
const siteId = Number(form.dataset.siteId);

let csrf = null;
let controller = null;

// Each preparation works once, so this runs before every attempt.
async function prepare() {
    const response = await fetch(`${endpoint}/protection?ownerId=${ownerId}&siteId=${siteId}`, { headers });
    const data = await response.json();

    controller?.destroy();
    controller = mountProtection(form, parseManifest(data.protection.manifest));
    csrf = data.csrf;
}

await prepare();

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    error.hidden = true;

    await controller.ready;
    const evidence = await controller.prepare();

    const response = await fetch(`${endpoint}/create`, {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json', 'X-CSRF-Token': csrf.value },
        body: JSON.stringify({
            operationId: crypto.randomUUID(),
            ownerId,
            siteId,
            content: { format: 'plain', text: form.elements.comment.value },
            protection: evidence,
        }),
    });
    const result = await response.json();

    if (result.status === 'ok') {
        form.reset();
    } else {
        error.textContent = result.errors[0].message;
        error.hidden = false;
    }

    await prepare();
});
```

The comment is posted, or waits for a moderator, and the form is cleared. If it's refused, such as for being too long, the server's message appears above the button and what the visitor typed stays in the box. The stylesheet keeps Protect's honeypot field hidden from visitors, which matters because a person who fills it in is treated as a bot.

For a guest, add `guest: { name, email }` to the body from fields of your own. A reply adds `parentUid` with the UID of the comment being replied to. A form on another site signs in differently and has no CSRF token, as [Signing In Across Sites](docs:headless/signing-in-across-sites) explains. [Data API](docs:headless/data-api) lists everything `create` accepts and the errors it can return.

### Retrying After a Refusal

A preparation that's been used, has expired, or was made for a different element or comment can't be used again, so always fetch a new one after an attempt, as the example does. Keep what the visitor typed when a post is refused, and respect `Retry-After` when the answer is `rate_limited`. When the error is `protection_expired` or `protection_required`, posting again with a fresh preparation usually works without the visitor doing anything. Don't retry `challenge_failed` or `possible_spam` automatically, because they mean the checks didn't pass. Never send on a preparation or evidence cached from another visitor.

## Clients That Aren't Browsers

A server, a mobile app or another client that isn't a browser can't run Protect's browser checks. It asks for its preparation with `channel=headless`, such as `/actions/comments/api/protection?ownerId=109&siteId=1&channel=headless`, which leaves out the honeypot, the minimum form time and the JavaScript check. Replay protection, rate limits, challenges and Akismet still apply, so a client that has to pass a Turnstile or reCAPTCHA challenge needs to get the provider's token through its own trusted flow.

GraphQL's `createComment` and `saveComment` take the evidence as their `protection` argument, and the [GraphQL mutations](docs:graphql/mutations#protection) page shows how to get it.
