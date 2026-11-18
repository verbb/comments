# Console Commands

Comments includes console commands you can run from your Craft project’s directory.

## Diagnostics

::: reference
### `comments/diagnostics/scan`

Checks comment threads and the elements comments belong to, and lists any warnings from the upgrade, which the [upgrade guide](docs:get-started/upgrading-from-comments-3#what-gets-checked) explains. It changes nothing. A reply whose parent comment is missing is reported as an error, while a comment or conversation whose element no longer exists is a warning.

The command exits with an error code if it finds an error, so you can use it in a deployment check. If there are no errors or warnings about your comments, it prints “No problems found.”

```shell
php craft comments/diagnostics/scan
```
:::

::: reference
### `comments/diagnostics/rebuild-counts`

Recalculates the comment count and latest activity for every conversation, counting only published comments, then prints how many conversations it rebuilt. Use it if the counts in your comment headings look wrong, such as after changing comments directly in the database. It’s safe to run at any time.

```shell
php craft comments/diagnostics/rebuild-counts
```
:::

## Member Tokens

A front end on a separate site, listed under **Allowed Origins** with **Member Tokens** in **Comments** → **Settings** → **Other Sites**, signs its members in to Comments with short-lived member tokens that its own server requests using the **Member Token Key**.

::: reference
### `comments/member-tokens/revoke`

Revokes a member’s tokens, which signs them out of every such front end straight away, such as after their account was compromised. Name the member by username, email address, ID or UID. The command prints how many tokens it revoked.

| Option | Description | Default |
| --- | --- | --- |
| `--all` | Revokes every member’s tokens instead of one member’s. | `false` |

```shell
php craft comments/member-tokens/revoke alex@example.com
```

If you think the **Member Token Key** itself has leaked, change it without putting the old key in **Previous Member Token Key**, which would keep accepting it, then revoke every member’s tokens.

```shell
php craft comments/member-tokens/revoke --all
```
:::

::: reference
### `comments/member-tokens/purge`

Removes member tokens that ran out or were revoked more than a day ago, along with the one-time values that stop a signed request being replayed, then prints how many it removed. Comments already does this whenever Craft runs garbage collection, so run it only when you want them gone straight away.

```shell
php craft comments/member-tokens/purge
```
:::

## Resaving Comments

::: reference
### `resave/comments-comments`

Resaves every comment. Comments adds this action to Craft’s own `resave` command, so it accepts the general options of Craft’s `resave` commands, such as `--site`, `--status`, `--limit` and `--queue`, along with `--set` and `--to` for changing a value as it saves. Options for particular element types, such as `--section`, don’t apply to comments. See [Craft’s console commands](https://craftcms.com/docs/5.x/reference/cli.html#resave) for the full list.

```shell
php craft resave/comments-comments
```
:::
