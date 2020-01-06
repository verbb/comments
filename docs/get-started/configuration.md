# Configuration

Create an `comments.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

```php
<?php

return [
    '*' => [
        'indexSidebarLimit' => 25,
        'indexSidebarGroup' => true,
        'indexSidebarIndividualElements' => false,
        'defaultQueryStatus' => ['approved'],

        // General
        'allowAnonymous' => false,
        'guestRequireEmailName' => true,
        'requireModeration' => true,
        'moderatorUserGroup',
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
        'recaptchaKey' => '',
        'recaptchaSecret' => '',

        // Notifications
        'notificationAuthorEnabled' => true,
        'notificationReplyEnabled' => true,
        'notificationSubscribeDefault' => true,
        'notificationSubscribeEnabled' => false,
        'notificationSubscribeCommentEnabled' => false,
        'notificationModeratorEnabled' => false,
        'notificationModeratorApprovedEnabled' => false,

        // Permissions
        'permissions' => [],
    ]
];
```

### Configuration options

- `indexSidebarLimit` - Set a limit for the number of elements in the comments index sidebar in the control panel.
- `indexSidebarGroup` - Whether to group elements in the comments index sidebar in the control panel.
- `indexSidebarIndividualElements` - Whether to show individual elements in the comments index sidebar in the control panel.
- `defaultQueryStatus` - Set the default status for element queries to return.

- `allowAnonymous` - Whether to allow anonymous commenting.
- `guestRequireEmailName` - Whether guests should be required to enter their name and email.
- `requireModeration` - Whether comments should be moderated before being public.
- `moderatorUserGroup` - The UID of the User Group that should moderate comments and receive notifications.
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
- `recaptchaKey` - The required key for ReCAPTCHA.
- `recaptchaSecret` - The required secret for ReCAPTCHA.

- `notificationAuthorEnabled` - Whether to notify element authors when a comment is made.
- `notificationReplyEnabled` - Whether to notify comment authors when a reply is made.
- `notificationSubscribeDefault` - Whether to automatically subscribe to notifications on comments that the user owns.
- `notificationSubscribeEnabled` - Whether to allow subscriber notification altogether.
- `notificationSubscribeCommentEnabled` - Whether to notify comment authors when a reply is made.
- `notificationModeratorEnabled` - Users can subscribe to a specific thread of comments made on an element.
- `notificationModeratorApprovedEnabled` - Whether to notify comment authors when their comment has been approved via moderation.

## Control Panel

You can also manage configuration settings through the Control Panel by visiting Settings → Comments.

