# Available Variables

The following are common methods you will want to call in your front end templates:

### `craft.comments.fetch(params)`

See [Comment Queries](docs:getting-elements/comment-queries)

### `craft.comments.render(elementId, params)`

See [Template Reference](docs:template-guides/comments-form)

### `craft.comments.protect()`

Returns HTML used with regards to spam checks. If you're not using the `render()` function above, you'll need to call this in your templates.
