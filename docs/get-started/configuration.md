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
        'allowGuest' => false,
        'guestNotice' => '',
        'guestRequireEmailName' => true,
        'guestShowEmailName' => true,
        'requireModeration' => true,
        'moderatorUserGroup',
        'autoCloseDays' => '',
        'maxReplyDepth' => '',
        'maxUserComments' => '',

        // Voting
        'allowVoting' => true,
        'allowGuestVoting' => false,
        'downvoteCommentLimit' => 5,
        'hideVotingForThreshold' => false,

        // Flagging
        'allowFlagging' => true,
        'allowGuestFlagging' => false,
        'flaggedCommentLimit' => 5,

        // Templates - Default
        'showAvatar' => true,
        'placeholderAvatar' => '',
        'showTimeAgo' => true,
        'outputDefaultCss' => true,
        'outputDefaultJs' => true,

        // Templates - Custom
        'templateFolderOverride' => '',
        'templateEmail' => '',

        // Security
        'enableSpamChecks' => true,
        'securityMaxLength' => '',
        'securityFlooding' => '',
        'securityModeration' => '',
        'securitySpamlist' => '',
        'securityBanned' => '',
        'securityMatchExact' => false,
        'recaptchaEnabled' => false,
        'recaptchaKey' => '',
        'recaptchaSecret' => '',

        // Notifications
        'notificationAuthorEnabled' => true,
        'notificationReplyEnabled' => true,
        'notificationSubscribeAuto' => false,
        'notificationSubscribeDefault' => true,
        'notificationSubscribeEnabled' => false,
        'notificationSubscribeCommentEnabled' => false,
        'notificationModeratorEnabled' => false,
        'notificationModeratorApprovedEnabled' => false,
        'notificationAdmins' => [],
        'notificationAdminEnabled' => false,
        'notificationFlaggedEnabled' => false,

        // Permissions
        'permissions' => [],

        // Custom Fields
        'showCustomFieldNames' => false,
        'showCustomFieldInstructions' => false,
    ]
];
```

### Configuration options

- `indexSidebarLimit` - Set a limit for the number of elements in the comments index sidebar in the control panel.
- `indexSidebarGroup` - Whether to group elements in the comments index sidebar in the control panel.
- `indexSidebarIndividualElements` - Whether to show individual elements in the comments index sidebar in the control panel.
- `defaultQueryStatus` - Set the default status for element queries to return.

- `allowGuest` - Whether to allow guest commenting.
- `guestNotice` - When guest commenting is not allowed, and the user is a guest, display this message in place of the form.
- `guestRequireEmailName` - Whether guests should be required to enter their name and email.
- `guestShowEmailName` - Whether guests should be shown fields to enter their name and email.
- `requireModeration` - Whether comments should be moderated before being public.
- `moderatorUserGroup` - The UID of the User Group that should moderate comments and receive notifications.
- `autoCloseDays` - Number of days until commenting is automatically closed. 0 to disable.
- `maxReplyDepth` - Set the number of levels (depth) replies to comments can have. Leave empty for no restrictions, 0 to disable replies, or any number to limit how many levels of replies can be made.
- `maxUserComments` - Set the number of comments each user is allowed for each owner element. Leave empty for no restrictions.

- `allowVoting` - Whether to allow voting.
- `allowGuestVoting` - Whether to allow guest voting.
- `downvoteCommentLimit` - Number of down votes required for comment to be marked as `isPoorlyRated`.
- `hideVotingForThreshold` - Whether to hide voting altogether when `isPoorlyRated` is true.

- `allowFlagging` - Whether to allow flagging.
- `allowGuestFlagging` - Whether to allow guest flagging.
- `flaggedCommentLimit` - Number of flags required for comment to be marked as `isFlagged`.

- `showAvatar` - Whether to show an avatar for comments
- `placeholderAvatar` - When "Show avatar" is enabled, and a guest is making a comment, show this as their avatar.
- `showTimeAgo` - Whether to show how long ago a comment was made.
- `outputDefaultCss` - Whether to output the default CSS for the form.
- `outputDefaultJs` - Whether to output the default JS for the form.

- `templateFolderOverride` - Provide a path to your own templates in the below folder.
- `templateEmail` - The template Comments will use for HTML emails.

- `enableSpamChecks` - Whether to enable spam checks for comments.
- `securityMaxLength` - The maximum number of characters a single comment can have. 
- `securityFlooding` - The number of seconds between commenting.
- `securityModeration` - A collection of words that if entered require comments to be moderated.
- `securitySpamlist` - A collection of words that if entered mark comments as spam.
- `securityBanned` - A collection of words that if entered mark comments as trashed.
- `securityMatchExact` - Whether to enable exact keyword matching. With this turned on, it will no longer match words within other words (eg. ‘craft’ will not match ‘crafty’).
- `recaptchaKey` - The required key for ReCAPTCHA.
- `recaptchaSecret` - The required secret for ReCAPTCHA.

- `notificationAuthorEnabled` - Whether to notify element authors when a comment is made.
- `notificationReplyEnabled` - Whether to notify comment authors when a reply is made.
- `notificationSubscribeAuto` - Whether to automatically subscribe to notifications on any comments on the same element, after your first reply.
- `notificationSubscribeDefault` - Whether to automatically subscribe to notifications on comments that the user owns.
- `notificationSubscribeEnabled` - Whether to allow subscriber notification altogether.
- `notificationSubscribeCommentEnabled` - Whether to notify comment authors when a reply is made.
- `notificationModeratorEnabled` - Users can subscribe to a specific thread of comments made on an element.
- `notificationModeratorApprovedEnabled` - Whether to notify comment authors when their comment has been approved via moderation.
- `notificationAdmins` - Enter the email address of any administrators, used for the below settings.
- `notificationAdminEnabled` - Admins will receive an email whenever someone makes a comment.
- `notificationFlaggedEnabled` - Users receive an email when someone replies to their comment.

- `showCustomFieldNames` - Whether custom fields should show their field names as labels.
- `showCustomFieldInstructions` - Whether custom fields should show their instruction text underneath labels.

## Control Panel

You can also manage configuration settings through the Control Panel by visiting Settings → Comments.


### `notificationAdmins`
Provide a nested array of emails, each with an `enabled` item.

```php
'notificationAdmins' => [
    [
        'email' => 'admin@site.com',
        'enabled' => true,
    ],
    [
        'email' => 'admin-alt@site.com',
        'enabled' => true,
    ],
],
```

### `permissions`
Provide a nested array of element permissions. You'll need to use the UID for your "groups" of elements - Sections for Entries, Category Groups for Categories, etc.

```php
'permissions' => [
    'craft\elements\Asset' => [
        // Volume UIDs
        '33974e79-b3b6-47ec-af91-4519fe4985be',
        'c194716f-aa74-40b6-8426-c835599cbe93',
    ],

    'craft\elements\Category' => [
        // Category Group UIDs
        'a27827c8-4810-433c-acb9-261b53d46281',
        '346194f0-6da8-4f46-a20b-795631ee9a5f',
    ],

    'craft\elements\Entry' => [
        // Section UIDs
        'a8d4bdf1-164e-4ddc-aaad-640026b8d3bf',
    ],

    'craft\elements\User' => [
        // User Group UIDs
        'c23a4d8d-47f0-4e71-927c-d5897ec9c9f8',
    ],
],
```