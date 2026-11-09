# Console Commands
Comments has commands for checking and repairing its data. Run them from your Craft project's root.

## Checking for Problems

```shell
php craft comments/diagnostics/scan
```

This checks comment threads and the elements comments belong to, and lists anything the upgrade from Comments 3 found that's worth knowing. It changes nothing. A reply whose parent comment is missing is reported as an error, while a comment or conversation whose element no longer exists is a warning.

The command exits with an error code if it finds an error, so you can use it in a deployment check. If there are no errors or warnings about your comments, it prints “No problems found.”

## Rebuilding Comment Counts

```shell
php craft comments/diagnostics/rebuild-counts
```

This recalculates the comment count and latest activity for every conversation, counting only published comments, then prints how many conversations it rebuilt. Use it if the counts in your comment headings look wrong, such as after changing comments directly in the database. It's safe to run at any time.

## Resaving Comments

Craft's `resave` command has an action for comments.

```shell
php craft resave/comments-comments
```

It accepts the general options of Craft's `resave` commands, such as `--site`, `--status`, `--limit` and `--queue`, along with `--set` and `--to` for changing a value as it saves. Options for particular element types, such as `--section`, don't apply to comments.
