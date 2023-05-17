# Upgrading from v1
While the [changelog](https://github.com/verbb/comments/blob/craft-4/CHANGELOG.md) is the most comprehensive list of changes, this guide provides high-level overview and organizes changes by category.

## Renamed Classes
The following classes have been renamed.

Old | What to do instead
--- | ---
| `verbb\comments\services\CommentsService` | `verbb\comments\services\Comments`
| `verbb\comments\services\FlagsService` | `verbb\comments\services\Flags`
| `verbb\comments\services\ProtectService` | `verbb\comments\services\Protect`
| `verbb\comments\services\RenderCacheService` | `verbb\comments\services\RenderCache`
| `verbb\comments\services\SecurityService` | `verbb\comments\services\Security`
| `verbb\comments\services\SubscribeService` | `verbb\comments\services\Subscribe`
| `verbb\comments\services\VotesService` | `verbb\comments\services\Votes`

## Removed Methods
The following methods have been removed.

Old | What to do instead
--- | ---
| `Comment::trashUrl` | Use [form](https://verbb.io/craft-plugins/comments/docs/developers/comment) instead
| `Comment::flagUrl` | Use [form](https://verbb.io/craft-plugins/comments/docs/developers/comment) instead
| `Comment::downvoteUrl` | Use [form](https://verbb.io/craft-plugins/comments/docs/developers/comment) instead
| `Comment::upvoteUrl` | Use [form](https://verbb.io/craft-plugins/comments/docs/developers/comment) instead
| `craft.comments.all()` | Use `craft.comments.fetch()` instead.
| `craft.comments.form()` | Use `craft.comments.render()` instead.

## Templates

### `getFields()`
Any references to `getFields()` should be changed to `getCustomFields()`. This is inline with Craft 4 element field layout changes.
