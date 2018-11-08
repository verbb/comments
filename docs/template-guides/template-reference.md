# Template Reference

## Single-line comment form

By far the easiest and quickest way to setup commenting on your site. With this template tag, you can output a list of comments on a particular element, along with a form for your users to fill out to comment. It also includes an individual form for each comment, to allow direct response to another users comment.

This by default comes with lightweight CSS and vanilla, dependancy-free Javascript, and is Ajax-driven. As such, its designed to be a drop-in solution when you don't want to worry about writing all the required Twig, CSS and JS. Its a good alternative to Disqus for instance.

While the CSS and JS are an additional 7.3kb minified (2.3kb gzip), and 10.5kb minified (2.6kb gzip) respectively, you can opt out of these being output and utilise your own styles and scripts.

```twig
{{ craft.comments.render(entry.id) }}
```

### Parameters

- `elementId` _(int)_ - The ID of the element to fetch comments on. Required.
- `params` _(object)_ - Available options are any attribute of the [Comment](/craft-plugins/comments/docs/developers/comment). Optional

The above would produce a form similar to the below.

![Comments Default Templating](/uploads/plugins/comments/comments-default-templating.png)

## Fetching comments

This tag allows you to fetch comments elements according to the criteria you provide. The provides you full control over how comments are shown and organised.

Note that this will return a Element Query, so you'll need to call `.all()` or `.one()` to actually perform the query.

```twig
{% set params = {
    userId: currentUser.id,
    limit: 10,
    status: 'pending'
} %}

{{ craft.comments.fetch(params).all() }}

or

{% for entry in craft.entries.section('news').all() %}
    Number of comments: {{ craft.comments.fetch({ ownerId: entry.id }).count() }}
{% endfor %}
```

### Parameters

-   `params` _(object)_ - Options to filter comments by. Available options are any attribute of the [Comment](/craft-plugins/comments/docs/developers/comment).

## Template Overrides

Of course, you can roll-your-own templates using [Template Overrides](/craft-plugins/comments/docs/templating/template-overrides).