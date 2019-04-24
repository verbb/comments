# Configuration

Create an `comments.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

```php
<?php

return [
    '*' => [
        'allowAnonymous' => false,
        'requireModeration' => true,
        'autoCloseDays' => '',

        // Voting
        'allowVoting' => true,
        'flaggedCommentLimit' => 5,

        // Flagging
        'allowFlagging' => true,
        'downvoteCommentLimit' => 5,

        // Templates - Default
        'showAvatar' => true,
        'placeholderAvatar' => '',
        'showTimeAgo' => true,
        'outputDefaultCss' => true,
        'outputDefaultJs' => true,

        // Templates - Custom
        'templateFolderOverride' => '',

        // Security
        'enableSpamChecks' => true,
        'securityMaxLength' => '',
        'securityFlooding' => '',
        'securityModeration' => '',
        'securityBlacklist' => '',
        'securityBanned' => '',

        // Notifications
        'notificationAuthorEnabled' => true,
        'notificationReplyEnabled' => true,

        // Permissions
        'permissions' => [],
    ]
];
```

### Configuration options

- `allowAnonymous` - Whether to allow anonymous commenting.
- `requireModeration` - Whether comments should be moderated before being public.
- `autoCloseDays` - Number of days until commenting is automatically closed. 0 to disable.
- `allowVoting` - Whether to allow voting.
- `flaggedCommentLimit` - Number of flags required for comment to be marked as `isFlagged`.
- `allowFlagging` - Whether to allow flagging.
- `downvoteCommentLimit` - Number of down votes required for comment to be marked as `isPoorlyRated`.
- `showAvatar` - Whether to show an avatar for comments
- `placeholderAvatar` - When "Show avatar" is enabled, and a guest is making a comment, show this as their avatar.
- `showTimeAgo` - Whether to show how long ago a comment was made.
- `outputDefaultCss` - Whether to output the default CSS for the form.
- `outputDefaultJs` - Whether to output the default JS for the form.
- `templateFolderOverride` - Provide a path to your own templates in the below folder.
- `enableSpamChecks` - Whether to enable spam checks for comments.
- `securityMaxLength` - The maximum number of characters a single comment can have. 
- `securityFlooding` - The number of seconds between commenting.
- `securityModeration` - A collection of words that if entered require comments to be moderated.
- `securityBlacklist` - A collection of words that if entered mark comments as spam.
- `securityBanned` - A collection of words that if entered mark comments as trashed.
- `notificationAuthorEnabled` - Whether to notify element authors when a comment is made.
- `notificationReplyEnabled` - Whether to notify comment authors when a reply is made.

## Control Panel

You can also manage configuration settings through the Control Panel by visiting Settings → Comments.

