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
