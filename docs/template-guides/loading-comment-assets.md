# Loading Comment Assets

`craft.comments.render()` registers the bundled CSS and JavaScript by itself, so you don't need to add anything to your layout. Craft publishes them as asset bundles and generates their URLs, including on Craft Cloud. Craft outputs registered files where your layout closes its `<head>` and `<body>` tags, or where it calls `{{ head() }}` and `{{ endBody() }}`, so any template that renders a full page loads them. A layout like this one, which every entry template extends, is all a page with comments needs:

```twig
<!DOCTYPE html>
<html lang="{{ craft.app.language }}">
    <head>
        <meta charset="utf-8">
        <title>{{ entry.title ?? siteName }}</title>
    </head>
    <body>
        {% block content %}{% endblock %}
    </body>
</html>
```

When an entry template calls `craft.comments.render(entry)` inside its `content` block, Craft adds Comments' stylesheet before `</head>` and its script before `</body>`. A template that returns only a fragment of HTML, such as one fetched by your own JavaScript, has no `<head>` or `<body>` for Craft to add them to, so load Comments' files with the page that fetches it instead.

The bundled files are:

- `css/comments.css`, which holds the base styles the thread needs to work and the default theme. With the **None** theme, `css/comments-base.css` loads instead, with only the base styles.
- `js/comments.js`, a small module script that finds each thread on the page and waits until it's needed. It doesn't hold up the page.
- The code that draws and runs the thread, in the `js/chunks/` folder beside it. `comments.js` loads the part that draws threads as the first thread nears view, and the part that handles the comment form once someone heads for it, such as by clicking into the comment box or pressing **Reply**, or straight away for a signed-in visitor, so a guest who only reads never downloads the form. A form with an Assets custom field also loads the part that handles file uploads.
- The Editor's own script and styles, with [rich authoring](docs:feature-tour/rich-authoring) only. They load once a visitor starts writing, and the Editor takes over from the plain comment box. With plain authoring they never load, unless someone edits a comment that was written with rich authoring.

The settings that control these are under **Comments** → **Settings** → **CSS & JavaScript**, apart from the **Theme** under **Appearance**, which chooses the stylesheet's look and is explained in [Theming](docs:template-guides/theming).

## Choosing What the Bundled CSS Includes

The bundled stylesheet comes in two parts. The base styles keep the thread working, such as hiding the spam honeypot field and screen reader text, reserving [space for the thread](docs:template-guides/rendering-comments#reserving-space-for-the-thread) while it loads, and keeping hidden elements hidden. The theme is everything else, from colours and type to spacing and the layout of each comment. Your own CSS only adds to these rules, so restyling the thread doesn't stop anything working.

When your site provides the whole look, choose the **None** theme rather than turning the CSS off. The thread then loads only the base styles, and keeps its `comments__*` classes and `data-comments-*` attributes for your own CSS to style. [Changing Individual Elements](docs:template-guides/theming#changing-individual-elements) explains how to target them.

Turn off **Load Bundled CSS** only when your own build includes Comments' stylesheet, as described [below](#bundling-the-script-into-your-own-build), or provides those base styles itself. Without them, the honeypot field shows to visitors, and nothing reserves the thread's space.

If your site's CSS uses cascade layers, as Tailwind CSS 4's does, turn on **CSS Layer** to load a copy of the stylesheet wrapped in `@layer comments`, so your utilities can win over it. [Theming](docs:template-guides/theming#sites-that-use-cascade-layers) explains the layer order to use.

## Supplying Your Own JavaScript

Turn off **Load Bundled JavaScript** when your site [bundles Comments' script](#bundling-the-script-into-your-own-build) into its own build. The page still gets each thread's mount point, so a bundled copy of the script finds and starts it as the plugin's copy would. Without any script, visitors see only the heading and the line asking them to turn on JavaScript.

To hook into the bundled script rather than replace it, such as to track a posted comment in your analytics or check a comment before it's sent, use the `Comments` global and the browser events the script dispatches on each thread. The [package docs](https://docs.verbb.io/comments/browser/) cover these, and [Validate and Track Comments](docs:user-guides/forms-data/validate-and-track-comments) works through both. For a front end that draws comments with its own interface, see [Headless Overview](docs:headless/headless-overview).

## Bundling the Script into Your Own Build

A site with its own build, such as Vite or webpack, can include Comments' script and stylesheet in its bundle from the npm package `@verbb/comments-browser`, instead of loading the plugin's copies. Install the version that matches your Comments plugin, because the two are released together with the same version number. The [package docs](https://docs.verbb.io/comments/browser/bundling-in-your-own-build) explain installing it, starting it and everything else about bundling, and [Bundle Comments in Your Own Build](docs:user-guides/front-end-headless/bundle-comments-in-your-own-build) works through it step by step.

On the plugin's side, turn off **Load Bundled JavaScript** under **Comments** → **Settings** → **CSS & JavaScript**, or with [`outputDefaultJs`](docs:get-started/configuration#outputDefaultJs), so the page doesn't load a second copy of the script. If your bundle imports the stylesheet as well, turn off **Load Bundled CSS** too, or [`outputDefaultCss`](docs:get-started/configuration#outputDefaultCss). Each thread's mount point is still output by `render()`, and with [rich authoring](docs:feature-tour/rich-authoring) the thread's data still says where to load the Editor from, so your bundled copy starts threads and loads the Editor as the plugin's copy would.
