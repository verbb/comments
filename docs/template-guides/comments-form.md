# Comments Form

By far the easiest and quickest way to setup commenting on your site. With this template tag, you can output a list of comments on a particular element, along with a form for your users to fill out to comment. It also includes an individual form for each comment, to allow direct response to another users comment.

This by default comes with lightweight CSS and vanilla, dependancy-free Javascript, and is Ajax-driven. As such, its designed to be a drop-in solution when you don't want to worry about writing all the required Twig, CSS and JS. Its a good alternative to Disqus for instance.

While the CSS and JS are an additional 7.3kb minified (2.3kb gzip), and 10.5kb minified (2.6kb gzip) respectively, you can opt out of these being output and utilise your own styles and scripts.

```twig
{{ craft.comments.render(entry.id) }}
```

### Parameters

- `elementId` _(int)_ - The ID of the element to fetch comments on. Required.
- `params` _(object)_ - Available options are any attribute of the [Comment](docs:developers/comment). Optional

The above would produce a form similar to the below.

![Comments Default Templating](/docs/screenshots/comments-default-templating.png)

You can roll-your-own templates using [Template Overrides](docs:template-guides/custom-form).