# Displaying and Protecting Comments

Use `{{ craft.comments.render(entry.id) }}` for the complete comments interface. It includes [spam protection](docs:feature-tour/anti-spam) and manages browser challenges, submissions, retries and replacement forms. This page is for when you need your own markup from the ground up, or a client that isn't a browser.

## Displaying Comments Yourself

Fetch comments with a [comment query](docs:getting-elements/comment-queries), and they'll be [Comment](docs:developers/comment) elements rather than the prepared objects [custom templates](docs:template-guides/custom-templates) get. Queries return approved comments by default. Use `adjacencyParentId(':empty:')` to start from the top-level comments, and each comment's `getReplies()` for the replies beneath it. Put this in the entry's template where the comments should appear:

```twig
{% set comments = craft.comments.fetch()
    .owner(entry)
    .adjacencyParentId(':empty:')
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
    </article>

    {% for reply in comment.getReplies().status('approved').all() %}
        {{ _self.comment(reply) }}
    {% endfor %}
{% endmacro %}

{% for comment in comments %}
    {{ _self.comment(comment) }}
{% endfor %}
```

Each approved comment shows its author, date and text, followed by its approved replies. `getReplies()` includes every status, which is why it's narrowed here. A comment that isn't approved, including one its author has deleted, is left out along with everything beneath it, where `craft.comments.render()` would keep its replies under a placeholder. This also makes a query for each comment, so limit how many you show on a busy discussion.

## Custom Server-Rendered Forms

Include `{{ craft.comments.protect(entry.id, entry.siteId) }}` inside the form alongside Craft's CSRF input. Its arguments are the element's ID, its site ID (the current site if left out), and the ID of a comment being edited. For an edit, pass the existing comment's ID as that third argument, along with a `commentId` field holding it. For a reply, add a `parentId` field with the ID of the comment being replied to.

```twig
<form method="post">
    {{ actionInput('comments/comments/save') }}
    {{ csrfInput() }}
    {{ redirectInput(entry.url) }}
    {{ hiddenInput('elementId', entry.id) }}
    {{ hiddenInput('siteId', entry.siteId) }}
    {{ craft.comments.protect(entry.id, entry.siteId) }}

    {% if not currentUser %}
        <input type="text" name="fields[name]">
        <input type="email" name="fields[email]">
    {% endif %}

    <textarea name="fields[comment]" required></textarea>
    <button type="submit">Post comment</button>
</form>
```

The helper outputs a `div` holding the fields Protect needs, with a `data-comments-protection` attribute holding its manifest. If the element can't take comments, or protection isn't configured correctly, it outputs nothing rather than breaking the page, and posting will say what's wrong. Your site's permission, moderation and field rules still apply. Custom fields also need to be selected as [Public Fields](docs:template-guides/custom-fields).

After posting, the visitor returns to the URL in `redirectInput()`. If posting fails, Craft renders the same page again for that request, with the error in the `comments.error` flash message. Your form can show the error and what was typed like this:

```twig
{% set error = craft.app.session.getFlash('comments.error') %}

{% if error %}
    <p role="alert">{{ error }}</p>
{% endif %}

<textarea name="fields[comment]" required>{{ craft.app.request.getBodyParam('fields.comment') }}</textarea>
```

After a successful post, the `comments.notice` flash message says whether the comment was posted or is waiting for moderation.

The honeypot field has to be hidden from visitors. The bundled CSS does this, or see [Loading Comment Assets](docs:template-guides/loading-comment-assets#supplying-your-own-assets) for the rule to use when you've turned it off.

This form works by itself without any JavaScript, until you turn on **Require JavaScript** or a challenge provider. Those need `@verbb/protect`, the module's browser runtime. Mount it using the manifest in the `data-comments-protection` attribute, and call `controller.prepare()` immediately before your own POST or fetch. Destroy the controller when removing the form, and fetch new preparation before trying again after an attempt. The runtime only handles evidence, so your code handles submitting and showing errors.

## Headless and GraphQL

Request `comments/api/v1/protection` with `ownerId`, `siteId`, optionally `commentId`, and `channel=headless`. Generate this URL with Craft's URL helper, such as `siteUrl('comments/api/v1/protection')`, so that it works with both query-string and pretty routing. The response has `manifest`, `hiddenInputs`, `expiresAt` and protocol `version`, and is marked as private and uncacheable. Use the same signed-in identity or session for preparing and submitting.

For a browser using your own front end, mount the manifest with `@verbb/protect` and submit the evidence it returns. A client that isn't a browser has to get any required provider token through its own trusted flow, as the headless channel doesn't turn off configured challenges. It does leave out the honeypot, minimum form time and JavaScript checks, while replay protection, rate limits, challenges and Akismet still apply. GraphQL uses the `protection` input documented under [GraphQL](docs:developers/graphql#mutations).

Preparation that's been used, has expired, or was made for a different action, element or plan can't be reused. Fetch new preparation after a failed attempt, keep what the person typed, and respect `Retry-After` on HTTP 429 responses. Never send on a token or proof that was cached from another visitor.
