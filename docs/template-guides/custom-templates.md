# Custom Templates

If you'd prefer to make your own templates for listing comments - go right ahead! You can either use the `craft.comments.fetch()` tag to completely write your own, or override the default templates for the single-line `craft.comments.render()` tag.

To override the `craft.comments.render()` tag templates, first create a folder in your Craft templates folder. For example, let's say you create the folder `templates/_comments`. Go to Settings → Comments → Custom Templates, and enter `_comments` in the **Template folder override** field provided, and hit Save.

If you navigate to the front-end of your site, to the template that outputs `craft.comments.render()`, you'll notice no immediate difference or errors. This is because Comments will look for template overrides in your provided folder, but if no template files are provided, it'll fallback to the default templates. This is particularly useful, as you aren't reqiured to override **all** templates.

To get started, it's worth taking the time to understand the structure of how Comments' templates go together.

:::tip
We're using the `.html` extension here for clarity. You can use `.twig` or whatever you have set in your [defaultTemplateExtensions](https://docs.craftcms.com/v3/config/config-settings.html#defaulttemplateextensions) for the actual files.
:::

- `comments.html`
- `form-fields/`
    - `field.html`
    - `index.html`
    - `fields/`
        - `assets.html`
        - `checkboxes.html`
        - `dropdown.html`
        - `...`
    - `elements/`
        - `comment.html`
- `_includes/`
    - `avatar.html`
    - `comment.html`
    - `form.html`
    - `footer.html`
    - `header.html`

Let's start with the top-level `comments.html` template.

:::tip
Check out the raw templates on [Comment's Github](https://github.com/verbb/comments/tree/craft-3/src/templates/_special) - they'll be the most up to date.
:::

## Comments Templates
The main template loaded when `craft.comments.render()` is called, is the `comments.html` template. To override the comments template, provide a file named `comments.html`. This template in turn includes many partials, which you can override individually. See [Overriding Partials](#overriding-partials)

### Available Template Variables
Comments templates have access to the following variables:

Variable | Description
--- | ---
`id` | The unique identifier for rendering. Mostly used to connect JavaScript functionality with the comments form.
`element` | The owner element these comments have been made on. Most commonly an Entry element.
`commentsQuery` | A [Comment](docs:developers/comment) query used to output the comments on the page.
`settings` | The plugin settings.

## Comment Form Templates
The template that handles the user-input when making a comment, and caters for both logged-in users, and guest users. There are some required fields and settings that you must include however. Below is the bare-minimum form implementation with all required fields:

```twig
<form method="post" role="form" method="post" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/comments/save">
    <input type="hidden" name="elementId" value="{{ element.id }}">
    <input type="hidden" name="siteId" value="{{ element.siteId }}">
    {{ craft.comments.protect() }}
    {{ csrfInput() }}

    {% if not currentUser %}
        <input name="fields[name]" type="text" />

        <input name="fields[email]" type="text" />
    {% endif %}

    <textarea name="fields[comment]"></textarea>

    <input type="submit" value="Add Comment" />
</form>
```

The crucial parts are the `name` attributes for form elements, along with the `elementId`, `siteId` and `action` hidden fields.

The `craft.comments.protect()` call includes additional fields that are used to prevent spam comments being submitted. Read the [Anti-Spam](docs:feature-tour/anti-spam) page for more.

## Comment Form Field Templates
You'll notice the above structure includes the `form-fields/` directory. Inside this directory are a mixture of folder and individual files, each representing a template that you're able to override. The `fields` templates are for custom fields, allowing you to handle different custom fields added to the comments form. The `elements/comment.html` file is the template for the actual comment text, which is an in-built form "element" (rather than a custom field). You can use this to change how the comment `<textarea>` appears.

### Using Custom Fields

You can also save custom fields to comments, but you'll be required to do your own custom templating, as per the above. Add fields to your comment form as required, being careful to note the handle of the custom fields in the `name` attribute of inputs.

```twig
<form method="post" role="form" method="post" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/comments/save">
    <input type="hidden" name="elementId" value="{{ element.id }}">
    <input type="hidden" name="siteId" value="{{ element.siteId }}">
    {{ craft.comments.protect() }}
    {{ csrfInput() }}

    <input type="text" name="fields[myTextField]" value="Some Value">

    <input type="submit" value="Add Comment" />
</form>
```

## Overriding Partials
You'll have noticed in our preview of the templates directory, the inclusion of an `_includes` and `form-fields` directory. This houses partial templates that are used throughout the templates. This helps not only with re-use, but keeps things modular, which has a flow-on effect when you want to override _just_ a partial.

The `comments.html` file sets up the comments and comments form, but also includes other partials like `_includes/comment.html` and `_includes/form.html`. Rather than overriding the `comments.html` file just to alter any one of these partials, you can override just the partial.

For example, let's say we want to override just the comment form, not the list of comments. We could create a file `_includes/form.html` and add our content to this template.

What this means in practice is a much more easily maintainable collection of custom template overrides for your project, where you don't need to duplicate all of Comment's own templates and keep them up to date.

### How it Works
Comments' templates use a custom Twig function like `{{ commentsInclude('_includes/comment') }}`. This is in contrast to what you might be used to in your own templates, something like `{% include '_includes/comment' %}`. The drawback with this latter approach is how Comments resolves the template partial. Using `{% include %}` it will expect to find the template partial relative to the template file you're including it from. Instead, `commentsInclude()` will resolve the template partial to either your overrides folder, or Comments' default templates.

### Examples
Let's take a look at some example use-cases for overriding partial templates. For all these examples, we'll assume you have a folder in your templates directory setup as `templates/_comments`.

#### Override comments
You might want to add a wrapper `<div>` element, change element classes, or roll your own complete templates. In this instance create a `comments.html` file in your `_comments` folder and build your template(s). You can continue to use any of the default template partials using the `formieInclude` Twig function, or implement your own.

#### Override comments form
In this instance, you might just want to alter the form where users input their comment. Create a `form.html` file in your `_comments/_includes` folder and build your template. The default templates will render, but include just your custom template for the comment form.

#### Override comment textarea
You might like to modify _just_ the `<textarea>` element for the comment form, rather than the entire form. Create a `comment.html` file in your `_comments/form-fields/elements` folder and build your template. The default templates will render, but include just your custom template for the comment `<textarea>` element.

#### Override an asset custom field
You might like to modify _just_ the template used for an Assets custom field for the comment form. Create a `assets.html` file in your `_comments/form-fields/fields` folder and build your template. The default templates will render, but include just your custom template for the Asset field.

## Non-JavaScript Examples
We've put together an example using no JavaScript, for a more traditional approach. You'll also likely want to turn off `Output default JS` in the plugin settings.

View these example templates via [Github](https://github.com/verbb/comments/tree/craft-3/examples).
