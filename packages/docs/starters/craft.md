# Craft Starter

The `craft/` folder is the Craft site every other starter reads from, and the place to see Comments on Craft's own pages. Locally it runs at `https://comments-starters.ddev.site`, and its hosted copy is [comments-starters.verbb.io](https://comments-starters.verbb.io). Its home page links to four Craft-rendered examples and to every other starter.

## The Default

`templates/blog/_entry.twig` is the drop-in default. `craft.comments.render(entry)` is the only thing the thread needs. It outputs the mount point and brings Comments' stylesheet and script, which load the thread as it nears view.

```twig
<div class="mt-12">
    {{ craft.comments.render(entry) }}
</div>
```

Everything else about the thread, such as voting, guests, the custom fields and the theme, comes from the settings in the control panel and `config/comments.php`.

## Sprig

`templates/sprig/` swaps the post and its thread with Sprig as you choose another post. The thread sits in an element with `s-preserve` and an `id` that names the post, so reloading the same post keeps the thread exactly as it is, while choosing another post swaps in that post's thread. [Turbo, Sprig and Datastar](/browser/turbo-sprig-datastar) explains why.

## Turbo

`templates/turbo/` moves between posts with Turbo Drive, and its **Refresh with morph** button uses Turbo 8's morphing page refresh. The thread is in an element with `data-turbo-permanent` and an `id` that names the post, so the morph leaves it alone.

## Datastar

`templates/datastar/` patches the post from the server with Datastar. The thread is in an element with `data-ignore-morph` and an `id` that names the post, so a patch for the same post keeps it.

## The Site's Configuration

`config/comments.php` holds the settings the other starters depend on. In the `dev` environment it allows the starters' local origins, which you'd otherwise add under **Settings** → **Other Sites**, with the web components starter for guests, the Next.js starter with **Member Tokens** and the Nuxt starter with **Shared Cookies**. Elsewhere it reads them from `COMMENTS_ALLOWED_ORIGINS`, as the file's comments describe. It also uses Cloudflare's Turnstile test keys unless the environment gives real ones.

The starters' module, in `modules/starters/`, seeds the site with `craft starters/seed`, and adds a `verified` badge to Priya's comments with the plugin's `Comments::EVENT_DEFINE_BADGES` event, which the React, Vue, Next.js and Nuxt starters draw with their own component.
