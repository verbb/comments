# Loading Comment Assets

`craft.comments.render()` loads the plugin's CSS and JavaScript for you. It registers `comments.css` and `comments.js` with Craft, which outputs them where your layout calls `{{ head() }}` and `{{ endBody() }}`, and starts the thread once the page has loaded. Most sites don't need anything else.

Load the files yourself when you need control over how or where they're output. Common reasons include:

- Adding attributes to the `<link>` or `<script>` tag, such as a `nonce` for a Content Security Policy or a `data-*` attribute a cookie consent tool looks for.
- Outputting the tags in a particular spot, such as a template that doesn't call `{{ head() }}` and `{{ endBody() }}`, or HTML your site fetches with Ajax, where registered files never reach the page.
- Starting the thread from your own JavaScript, such as after your own scripts have run.

Before you load either file yourself, turn off the matching setting under **Comments** → **Settings** → **Default Templates**. Turn off **Output Default CSS** before using `renderCss()`, and **Output Default JS** before using `renderJs()` or `getJsVariables()`, or set [`outputDefaultCss`](docs:get-started/configuration#outputdefaultcss) and [`outputDefaultJs`](docs:get-started/configuration#outputdefaultjs) in your config file. Otherwise the files load twice, and with **Output Default JS** on, `render()` also starts the thread a second time. With **Output Default JS** off, `render()` doesn't start the thread at all, so the page needs `renderJs()` or your own script to do it.

These functions always publish the files from the plugin to `cpresources`. They don't use the [`assetBaseUrl`](docs:get-started/configuration#assetbaseurl) setting, which only applies to the files `render()` loads.

## Loading the CSS and JavaScript in Your Layout

This example keeps `render()` for the thread's HTML and outputs the tags itself. The stylesheet goes in the `<head>`, and the script goes after the thread, because the script starts the thread as soon as it loads and needs the thread's HTML to exist by then.

```twig
<!DOCTYPE html>
<html lang="{{ craft.app.language }}">
<head>
    <meta charset="utf-8">
    <title>{{ entry.title }}</title>

    {{ craft.comments.renderCss(entry.id, { media: 'screen' }) }}
</head>
<body>
    <article>
        <h1>{{ entry.title }}</h1>
        {{ entry.body }}
    </article>

    {{ craft.comments.render(entry.id) }}

    {{ craft.comments.renderJs(entry.id, {}, true, { 'data-consent': 'functional' }) }}
</body>
</html>
```

The page then contains a `<link rel="stylesheet" media="screen">` tag for `comments.css` in the `<head>`. After the thread, it contains a small inline script, followed by a `<script data-consent="functional">` tag for `comments.js` that calls the inline script once the file has loaded.

If you'd rather output the script in the `<head>`, add `defer: true` to its attributes. The browser then runs the script after it has read the whole page, so the thread's HTML exists by the time the script starts it.

### `craft.comments.renderCss(elementId, attributes)`

Outputs a `<link rel="stylesheet">` tag for `comments.css` where you call it.

| Argument | Type | What It Does |
| --- | --- | --- |
| `elementId` | `int` | The ID of the element the comments belong to. It's required, but the stylesheet is the same for every element. |
| `attributes` | `object` | Attributes to add to the `<link>` tag. Defaults to `{}`. |

### `craft.comments.renderJs(elementId, params, loadInline, attributes)`

Outputs a `<script>` tag for `comments.js` where you call it. With `loadInline` on, it also outputs an inline script before it, which starts the thread for `elementId` once `comments.js` has loaded.

| Argument | Type | What It Does |
| --- | --- | --- |
| `elementId` | `int` | The ID of the element the comments belong to. Use the same ID you pass to `render()`, so the script finds the thread's HTML. |
| `params` | `object` | The same parameters you pass to `render()`. Only `siteId` changes the output, by setting the site the thread's subscribe button uses. Defaults to `{}`. |
| `loadInline` | `bool` | Whether to output the inline script that starts the thread. Defaults to `true`. Set it to `false` to load only `comments.js` and start the thread yourself. |
| `attributes` | `object` | Attributes to add to the external `<script>` tag. They aren't added to the inline script. Defaults to `{}`. |

## Starting the Thread With Your Own JavaScript

`craft.comments.getJsVariables()` gives you the settings `renderJs()` passes to the script, so you can start the thread from your own code. This is useful when the inline script `renderJs()` outputs doesn't suit, such as when your Content Security Policy requires a `nonce` on inline scripts, or when one page has more than one thread and should load `comments.js` once.

This example loads `comments.js` without the inline script, then starts the thread once the page is ready:

```twig
{{ craft.comments.render(entry.id) }}

{# Load comments.js only, without the inline script that starts the thread #}
{{ craft.comments.renderJs(entry.id, {}, false) }}

{% set jsVariables = craft.comments.getJsVariables(entry.id) %}

<script>
    document.addEventListener('DOMContentLoaded', function() {
        new Comments.Instance({{ jsVariables.id | json_encode | raw }}, {{ jsVariables.settings | json_encode | raw }});
    });
</script>
```

`Comments.Instance` is the class `comments.js` uses for each thread. It takes the thread's selector and its settings, and attaches the form, reply, edit, voting and other actions to the thread's HTML.

For a page with several threads, call `renderJs()` once with `loadInline` set to `false`, then call `getJsVariables()` and create a `Comments.Instance` for each element.

### `craft.comments.getJsVariables(elementId, params)`

Returns the selector and settings for a thread, which you pass to `new Comments.Instance()`.

| Argument | Type | What It Does |
| --- | --- | --- |
| `elementId` | `int` | The ID of the element the comments belong to. Use the same ID you pass to `render()`. |
| `params` | `object` | The same parameters you pass to `render()`. Only `siteId` changes the output, as it does for `renderJs()`. Defaults to `{}`. |

It returns an object with two keys.

| Key | What It Holds |
| --- | --- |
| `id` | The CSS selector for the thread's HTML, such as `#cc-w-123`. |
| `settings` | The thread's settings, including the action URL, the CSRF token name and value, the reCAPTCHA and GIPHY settings, the comment order, translated button labels, and the element's `id` and `siteId`. |

The settings include the current visitor's CSRF token, so don't cache them in a way that shares them between visitors.
