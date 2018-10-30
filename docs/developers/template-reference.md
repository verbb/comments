# Template Reference

## Single-line comment form

By far the easiest and quickest way to setup commenting on your site. With this template tag, you can output a list of comments on a particular element, along with a form for your users to fill out to comment. It also includes an individual form for each comment, to allow direct response to another users comment.

```twig
{% set params = {
    order: 'elements.dateCreated asc',
} %}

{{ craft.comments.form(entry.id, params) }}
```

### Parameters

- `element.id` _(int)_ - The ID of the element to fetch comments on. Required.
- `params` _(object)_ - Available options are any attribute of the [Comment Element](/craft-plugins/comments/docs/developers/comment-element). Optional

Consult the [Example CSS / JS](/craft-plugins/comments/docs/feature-tour/example-css-js) wiki article to get get started styling these elements.

## Fetching comments

This tag allows you to fetch comments according to the criteria you provide. This is useful for retrieving all comments for user (for use in their account), number of comments for an entry, and lots more.

```twig
{% set params = {
    userId: currentUser.id,
    limit: 10,
    status: 'pending'
} %}

{{ craft.comments.all(params) }}

or

{% for entry in craft.entries.section('news') %}
    Number of comments: {{ craft.comments.all({ elementId: entry.id }) | length }}
{% endfor %}
```

### Parameters

- `params` _(object)_ - Options to filter comments by. Available options are any attribute of the [Comment Element](/craft-plugins/comments/docs/developers/comment-element).

## Template Overrides

Of course, you can roll-your-own templates using [Template Overrides](/craft-plugins/comments/docs/feature-tour/template-overrides).