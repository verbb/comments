# Rendering Comments

By far the easiest and quickest way to setup commenting on your site. With this template tag, you can output a list of comments on a particular element, along with a form for your users to fill out to comment. It also includes an individual form for each comment, to allow direct response to another users comment.

This by default comes with lightweight CSS and vanilla, dependancy-free Javascript, and is Ajax-driven. As such, its designed to be a drop-in solution when you don't want to worry about writing all the required Twig, CSS and JS. Its a good alternative to Disqus for instance.

While the CSS and JS are an additional 7.3kb minified (2.3kb gzip), and 10.5kb minified (2.6kb gzip) respectively, you can opt out of these being output and utilise your own styles and scripts.

```twig
{{ craft.comments.render(entry.id) }}
```

### Parameters

- `elementId` _(int)_ - The ID of the element to fetch comments on. Required.
- `query` _(object)_ - Modify the [Comment Query](docs:getting-elements/comment-queries) used by the template. Optional

The above would produce a form similar to the below.

![Comments Default Templating](/docs/screenshots/comments-default-templating.png)

You can roll-your-own templates, or even override template partials using [Custom Templates](docs:template-guides/custom-templates).

## Default Templates

There are a few templating options available via the Settings page of the Comments plugin, or by settings in the [Configuration](docs:get-started/configuration).

## CSS/JS Resources

The `render()` function is designed to be a single-line implementation, complete with CSS and JS. You can opt-out of using these resources, while still using the `render()` tag to generate the required Twig. As such, you'll be required to style the components yourself and any required Javascript. Consult the [resources](https://github.com/verbb/comments/tree/craft-3/src/resources/src) folder for a start.

For a complete build-your-own solution, create [Custom Templates](docs:template-guides/custom-templates).
