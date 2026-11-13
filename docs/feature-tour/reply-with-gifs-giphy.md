# Reply with GIFs (GIPHY)

Comments can let your visitors search [GIPHY](https://giphy.com) and attach a GIF to any comment or reply — handy for more casual, social discussions.

## Setup

1. Create a GIPHY API key from the [GIPHY Developers dashboard](https://developers.giphy.com/dashboard/).
2. In the control panel, go to **Comments → Settings → Comments Form** and find the **GIPHY** section.
3. Turn on **Reply with GIFs**.
4. Enter your **GIPHY API Key**. You can paste the key directly, or reference an environment variable (e.g. `$GIPHY_API_KEY`) — recommended, so the key stays out of your project config.
5. Optionally adjust the **GIF Content Rating** (`g`, `pg`, `pg-13`, `r`) and **GIF Results Limit**.

Once enabled, a **GIF** button appears on the comment form (and on every reply form). Visitors can search GIPHY, pick a GIF, and it’ll be shown alongside their comment. A comment can be GIF-only — no text is required when a GIF is attached.

## How it works

- GIF search is proxied through the plugin’s own controller action (`comments/comments/giphy-search`), so **your API key is never exposed to the browser**.
- The chosen GIF’s URL is validated to be a GIPHY-hosted URL before it’s stored against the comment, preventing arbitrary URLs/markup from being saved.
- The GIF URL is stored in the `gifUrl` attribute of the comment element, and is available in Twig as `comment.gifUrl` and via GraphQL as `gifUrl`.

## Settings reference

| Setting        | Handle         | Default | Notes                                                            |
| -------------- | -------------- | ------- | ---------------------------------------------------------------- |
| Reply with GIFs| `giphyEnabled` | `false` | Master toggle for the feature.                                   |
| GIPHY API Key  | `giphyApiKey`  | `null`  | Supports environment variables.                                  |
| GIF Rating     | `giphyRating`  | `g`     | One of `g`, `pg`, `pg-13`, `r`.                                  |
| GIF Limit      | `giphyLimit`   | `24`    | Maximum number of GIFs returned per search.                      |
