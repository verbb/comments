# Console Commands

Comments includes console commands you can run from your Craft project’s directory.

## Front-End Assets

::: reference
### `comments/base/publish-assets`

Copies the plugin’s default CSS and JavaScript from `resources/dist` to the folder set by the [assetBasePath](docs:get-started/configuration#assetbasepath) setting. Run it after updating Comments when you serve those files yourself through [assetBaseUrl](docs:get-started/configuration#assetbaseurl), such as from a CDN. When `assetBasePath` isn’t set, the command does nothing and exits successfully, so it’s safe to add to a Composer `post-update-cmd` script on every environment.

```shell
php craft comments/base/publish-assets
```
:::

## Comment Structure

Comments stores replies in a Craft structure, and these commands repair it if it’s missing or comments end up attached to the wrong one. Back up your database before running them.

::: reference
### `comments/base/resave-structure`

Stores the comments structure’s UID in the plugin settings, then prints its ID and UID. It reuses the structure your existing comments belong to, and creates a new one when there are no comments or that structure is missing. Use it when the plugin settings have lost track of the structure. To move comments into a different structure, use [comments/base/set-structure](#comments-base-set-structure).

```shell
php craft comments/base/resave-structure
```
:::

::: reference
### `comments/base/set-structure`

Moves every comment into the structure with the UID you pass, by updating each comment’s structure record directly. It asks for confirmation first and can’t be undone. Find the UID in the plugin settings in your project config, or use the one printed by `comments/base/resave-structure`.

```shell
php craft comments/base/set-structure 3fa85f64-5717-4562-b3fc-2c963f66afa6
```
:::

## Resaving Comments

::: reference
### `resave/comments-comments`

Resaves every comment. Comments adds this action to Craft’s own `resave` command, so it accepts the same options as `resave/entries`, such as `--element-id`, `--site` and `--queue`. See [Craft’s console commands](https://craftcms.com/docs/5.x/reference/cli.html#resave) for the full list.

```shell
php craft resave/comments-comments
```

```shell
php craft resave/comments-comments --queue
```
:::
