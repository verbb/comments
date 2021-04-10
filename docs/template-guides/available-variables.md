# Available Variables

The following are common methods you will want to call in your front end templates:

### `craft.comments.fetch(params)`

See [Comment Queries](docs:getting-elements/comment-queries)

### `craft.comments.render(elementId, params)`

See [Rendering Comments](docs:template-guides/rendering-comments)

### `craft.comments.protect()`

Returns HTML used with regards to spam checks. If you're not using the `render()` function above, you'll need to call this in your templates.

### `craft.comments.renderCss(elementId, attributes)`

If you'd like to render the CSS for comments in a specific way, you can use this function. A `<link>` HTML node will be outputted at the location you add this tag to your templates. You should add this in your `<head>` to prevent a flash of unstyled content.

You can also provide `attributes`, and object of attributes added to the `<link>` element.

Be sure to disable `Output default CSS` else this will be rendered twice.

See [Rendering Comments](docs:template-guides/rendering-comments)

### `craft.comments.renderJs(elementId, params, loadInline, attributes)`

If you'd like to render the JS for comments in a specific way, you can use this function. This can be useful for injecting the JS at a specific point in your templates, as opposed to at the end of the page. This will output an external `<script>` tag, along with an inline `<script>` element to initialise the comments behaviour. You can control this with the `loadInline` parameter.

You can also provide `attributes`, and object of attributes added to the `<script>` element.

Be sure to disable `Output default JS` else this will be rendered twice.

See [Rendering Comments](docs:template-guides/rendering-comments)

### `craft.comments.getJsVariables(elementId, params)`

This will return an array of variables required for the inline JavaScript for Comments to work. This function can be useful if you'd like to control the initialisation of the JavaScript.

```twig
{# Render the `comments.js` file - exclude the inline JS #}
{{ craft.comments.renderJs(elementId, {}, false) }}

{# Fetch the variables required for the comments JS #}
{% set jsVariables = craft.comments.getJsVariables(elementId) %}

{# Wait for the document to be ready, then initialise #}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Populate the ID and the settings
        new Comments.Instance('{{ jsVariables.id }}', {{ jsVariables.settings | json_encode | raw }});
    });
</script>
```

### `craft.comments.getUserVotes(userId)`
Returns a collection of [Vote](docs:developers/vote) objects for a provided user ID.

### `craft.comments.getUserDownvotes(userId)`
Returns a collection of downvoted [Vote](docs:developers/vote) objects for a provided user ID.

### `craft.comments.getUserUpvotes(userId)`
Returns a collection of upvoted [Vote](docs:developers/vote) objects for a provided user ID.
