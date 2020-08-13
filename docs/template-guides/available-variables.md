# Available Variables

The following are common methods you will want to call in your front end templates:

### `craft.comments.fetch(params)`

See [Comment Queries](docs:getting-elements/comment-queries)

### `craft.comments.render(elementId, params)`

See [Template Reference](docs:template-guides/comments-form)

### `craft.comments.protect()`

Returns HTML used with regards to spam checks. If you're not using the `render()` function above, you'll need to call this in your templates.

### `craft.comments.renderCss(elementId, params)`

If you'd like to render the CSS for comments in a specific way, you can use this function. A `<link>` HTML node will be outputted at the location you add this tag to your templates. You should add this in your `<head>` to prevent a flash of unstyled content.

Be sure to disable `Output default CSS` else this will be rendered twice.

See also [Template Reference](docs:template-guides/comments-form)

### `craft.comments.renderJs(elementId, params)`

If you'd like to render the JS for comments in a specific way, you can use this function. This can be useful for injecting the JS at a specific point in your templates, as opposed to at the end of the page. This will output an external `<script>` tag, along with an inline `<script>` element to initialise the comments behaviour.

Be sure to disable `Output default JS` else this will be rendered twice.

See also [Template Reference](docs:template-guides/comments-form)
