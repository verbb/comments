# Configuration

You can customise Comments’ settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `comments.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will let guests comment:

```php
<?php

return [
    'allowGuest' => true,
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults. You can also manage many of these settings in the control panel under **Comments → Settings**.

## Configuration Options

::: reference
### `pluginName`

**Type:** `string` · **Default:** `'Comments'`

Sets a custom name for the plugin in the control panel.
:::

### General

::: reference
#### `allowGuest`

**Type:** `bool` · **Default:** `false`

Whether guests (visitors who aren’t signed in) can post comments.
:::

::: reference
#### `guestNotice`

**Type:** `string|null` · **Default:** `null`

The message shown to guests in place of the comment form when `allowGuest` is `false`.
:::

::: reference
#### `guestRequireEmailName`

**Type:** `bool` · **Default:** `true`

Whether guests must enter their name and email to comment. Only applies to guest comments.
:::

::: reference
#### `guestShowEmailName`

**Type:** `bool` · **Default:** `true`

Whether guests are shown fields to enter their name and email. Only applies to guest comments.
:::

::: reference
#### `requireModeration`

**Type:** `bool` · **Default:** `true`

Whether new comments need to be approved by a moderator before they’re publicly visible.
:::

::: reference
#### `moderatorUserGroup`

**Type:** `string|null` · **Default:** `null`

The UID of the user group whose members moderate comments. This group also receives moderator notifications, and its members can skip moderation with `moderatorExcluded`.
:::

::: reference
#### `moderatorExcluded`

**Type:** `bool` · **Default:** `true`

Whether comments from members of the `moderatorUserGroup` group skip moderation themselves.
:::

::: reference
#### `autoCloseDays`

**Type:** `mixed` · **Default:** `null`

Closes commenting on elements older than this number of days, based on the element’s **Post Date**. Leave it empty (`null`) or set it to `0` to never close commenting automatically.
:::

::: reference
#### `maxReplyDepth`

**Type:** `mixed` · **Default:** `null`

The number of levels of replies comments can have. Use `null` for no limit, `0` to turn off replies, or any other number to limit how deeply replies can nest.
:::

::: reference
#### `maxUserComments`

**Type:** `mixed` · **Default:** `null`

The number of comments each user can make on a single element. Use `null` for no limit.
:::

::: reference
#### `defaultQueryStatus`

**Type:** `array` · **Default:** `['approved']`

The statuses comment queries return when you don’t set a `status` parameter yourself. Add `pending` or other statuses to show those comments by default.
:::

::: reference
#### `permissions`

**Type:** `array` · **Default:** `[]`

Which elements can be commented on, keyed by element type. Each value is either `'*'` to allow commenting on every element of that type, or an array of UIDs for the “groups” of elements that allow commenting. Use section UIDs for entries (or entry type UIDs for entries without a section, such as nested entries), volume UIDs for assets, category group UIDs for categories and user group UIDs for users. Element types that aren’t listed allow commenting everywhere.

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
:::

### Voting

::: reference
#### `allowVoting`

**Type:** `bool` · **Default:** `true`

Whether signed-in users can upvote or downvote other users’ comments.
:::

::: reference
#### `allowGuestVoting`

**Type:** `bool` · **Default:** `false`

Whether guests can upvote or downvote comments.
:::

::: reference
#### `downvoteCommentLimit`

**Type:** `int` · **Default:** `5`

The number of downvotes a comment needs before its `isPoorlyRated` property is `true`. You can then decide how to handle poorly rated comments in your templates.
:::

::: reference
#### `hideVotingForThreshold`

**Type:** `bool` · **Default:** `false`

Whether to hide the voting controls on a comment once it reaches the `downvoteCommentLimit` threshold.
:::

::: reference
#### `showAuthorScore`

**Type:** `bool` · **Default:** `true`

Whether to show each registered user’s total score (the net votes across all their approved comments) next to their name.
:::

### Flagging

::: reference
#### `allowFlagging`

**Type:** `bool` · **Default:** `true`

Whether signed-in users can flag a comment as inappropriate, spam or for any other reason.
:::

::: reference
#### `allowGuestFlagging`

**Type:** `bool` · **Default:** `false`

Whether guests can flag comments.
:::

::: reference
#### `flaggedCommentLimit`

**Type:** `int` · **Default:** `5`

The number of flags a comment needs before its `isFlagged` property is `true`. You can then decide how to handle flagged comments.
:::

### Comment Form

::: reference
#### `showCustomFieldNames`

**Type:** `bool` · **Default:** `false`

Whether custom fields in the comment form show their field names as labels.
:::

::: reference
#### `showCustomFieldInstructions`

**Type:** `bool` · **Default:** `false`

Whether custom fields in the comment form show their instructions underneath their labels.
:::

::: reference
#### `giphyEnabled`

**Type:** `bool` · **Default:** `false`

Whether commenters can search GIPHY and attach a GIF to their comment or reply. This also needs a `giphyApiKey`.
:::

::: reference
#### `giphyApiKey`

**Type:** `string|null` · **Default:** `null`

Your GIPHY API key, used for GIF search. It stays on the server and is never shown to visitors. This setting supports environment variables.
:::

::: reference
#### `giphyRating`

**Type:** `string` · **Default:** `'g'`

The highest GIPHY content rating to include in search results. Use `g`, `pg`, `pg-13` or `r`.
:::

::: reference
#### `giphyLimit`

**Type:** `int` · **Default:** `24`

The maximum number of GIFs shown for each search.
:::

### Default Templates

::: reference
#### `showAvatar`

**Type:** `bool` · **Default:** `true`

Whether to show an avatar for each comment. Users who comment have their user photo shown, while guests are shown the `placeholderAvatar`.
:::

::: reference
#### `placeholderAvatar`

**Type:** `mixed` · **Default:** `null`

The asset shown as the avatar for guests when `showAvatar` is enabled, set as an array holding the asset’s ID, such as `[123]`. Because asset IDs differ between environments, this is usually easier to set in the control panel.
:::

::: reference
#### `enableGravatar`

**Type:** `bool` · **Default:** `false`

Whether to show a commenter’s Gravatar, based on their email. If they don’t have one, their user photo or the placeholder avatar is used instead.
:::

::: reference
#### `showTimeAgo`

**Type:** `bool` · **Default:** `true`

Whether to show how long ago each comment was made.
:::

::: reference
#### `orderBy`

**Type:** `string` · **Default:** `'desc'`

The order comments are shown in on the front end. Use `desc` for newest first or `asc` for oldest first.
:::

::: reference
#### `outputDefaultCss`

**Type:** `bool` · **Default:** `true`

Whether to output the default CSS for the comment form.
:::

::: reference
#### `outputDefaultJs`

**Type:** `bool` · **Default:** `true`

Whether to output the default JavaScript for the comment form.
:::

::: reference
#### `assetBaseUrl`

**Type:** `string|null` · **Default:** `null`

Serves the default CSS and JavaScript from this base URL, such as a CDN or file server, instead of publishing them to `cpresources`. You’re responsible for deploying the plugin’s `resources/dist` files to this location, which `assetBasePath` can help with. This setting supports environment variables.
:::

::: reference
#### `assetBasePath`

**Type:** `string|null` · **Default:** `null`

The filesystem path the plugin’s `resources/dist` CSS and JavaScript are copied to when publishing. It should map to the location `assetBaseUrl` serves. Comments copies the files on install, from the button on the settings screen and with the `comments/base/publish-assets` console command. This setting supports environment variables.
:::

### Custom Templates

::: reference
#### `templateFolderOverride`

**Type:** `string|null` · **Default:** `null`

A folder in your site’s `templates` directory containing your own versions of the Comments templates. Any template Comments finds there is used instead of its default.
:::

::: reference
#### `templateEmail`

**Type:** `string|null` · **Default:** `null`

The site template Comments uses for HTML emails. When it isn’t set, Comments uses Craft’s default email template.
:::

### Security

::: reference
#### `enableSpamChecks`

**Type:** `bool` · **Default:** `true`

Whether to run spam checks on comments submitted through the front-end form, including the hidden form checks and reCAPTCHA. When this is disabled, comments that fail those checks are still accepted.
:::

::: reference
#### `securityMaxLength`

**Type:** `string|null` · **Default:** `null`

The maximum number of characters a single comment can have.
:::

::: reference
#### `securityFlooding`

**Type:** `string|null` · **Default:** `null`

The minimum number of seconds a commenter must wait between comments, to prevent comment flooding.
:::

::: reference
#### `securityModeration`

**Type:** `string|null` · **Default:** `null`

Words or IP addresses, one per line, that mark a comment as pending when they appear in its content, name, email or IP address. Matching is case-insensitive.
:::

::: reference
#### `securitySpamlist`

**Type:** `string|null` · **Default:** `null`

Words or IP addresses, one per line, that mark a comment as spam when they appear in its content, name, email or IP address. Matching is case-insensitive.
:::

::: reference
#### `securityBanned`

**Type:** `string|null` · **Default:** `null`

Words or IP addresses, one per line, that stop a comment from being submitted when they appear in its content, name, email or IP address. Matching is case-insensitive.
:::

::: reference
#### `securityMatchExact`

**Type:** `bool` · **Default:** `false`

Whether keywords only match whole words. When this is enabled, ‘craft’ no longer matches ‘crafty’.
:::

::: reference
#### `recaptchaEnabled`

**Type:** `bool` · **Default:** `false`

Whether to use reCAPTCHA to protect the comment form against spam. This also needs a `recaptchaKey` and `recaptchaSecret`.
:::

::: reference
#### `recaptchaKey`

**Type:** `string|null` · **Default:** `null`

Your reCAPTCHA site key. This setting supports environment variables.
:::

::: reference
#### `recaptchaSecret`

**Type:** `string|null` · **Default:** `null`

Your reCAPTCHA secret key. This setting supports environment variables.
:::

::: reference
#### `recaptchaMinScore`

**Type:** `float` · **Default:** `0.5`

The minimum reCAPTCHA score a comment needs to pass the check. Comments that score lower are rejected as spam.
:::

### Notifications

::: reference
#### `useQueueForNotifications`

**Type:** `bool` · **Default:** `false`

Whether to send notification emails through Craft’s queue. This is recommended, because it stops sending emails from slowing down comment submission.
:::

::: reference
#### `notificationAuthorEnabled`

**Type:** `bool` · **Default:** `true`

Whether the author of an element receives an email for every new comment on it.
:::

::: reference
#### `notificationReplyEnabled`

**Type:** `bool` · **Default:** `true`

Whether comment authors receive an email when someone replies to their comment.
:::

::: reference
#### `notificationSubscribeAuto`

**Type:** `bool` · **Default:** `false`

Whether users are automatically subscribed to an element after they comment on it, so they’re notified of any later comment on that element.
:::

::: reference
#### `notificationSubscribeDefault`

**Type:** `bool` · **Default:** `true`

Whether users are automatically subscribed to their own comments, so they’re notified when someone replies to them.
:::

::: reference
#### `notificationSubscribeEnabled`

**Type:** `bool` · **Default:** `false`

Whether signed-in users can subscribe to all comments on an element. This shows a **Subscribe** button at the top of the comments thread.
:::

::: reference
#### `notificationSubscribeCommentEnabled`

**Type:** `bool` · **Default:** `false`

Whether signed-in users can subscribe to a specific comment thread. This shows a **Subscribe** button on each comment.
:::

::: reference
#### `notificationModeratorEnabled`

**Type:** `bool` · **Default:** `false`

Whether members of the `moderatorUserGroup` group receive an email when a new comment needs moderation. When this is enabled, element authors aren’t notified until the comment is approved.
:::

::: reference
#### `notificationModeratorEditEnabled`

**Type:** `bool` · **Default:** `false`

Whether moderators receive an email when a user edits their own comment and it needs moderation again.
:::

::: reference
#### `notificationModeratorApprovedEnabled`

**Type:** `bool` · **Default:** `false`

Whether comment authors receive an email when a moderator approves their comment.
:::

::: reference
#### `notificationAdmins`

**Type:** `array|Closure` · **Default:** `[]`

The administrators who receive the admin and flagged comment notifications. Provide an array of email addresses, each with an `enabled` item. Only enabled addresses receive emails.

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

You can also provide a closure that returns this array, which is useful when the list comes from elsewhere in your project.
:::

::: reference
#### `notificationAdminEnabled`

**Type:** `bool` · **Default:** `false`

Whether the `notificationAdmins` addresses receive an email whenever someone comments on an element.
:::

::: reference
#### `notificationFlaggedEnabled`

**Type:** `bool` · **Default:** `false`

Whether the `notificationAdmins` addresses receive an email whenever someone flags a comment.
:::

### Comments Index

::: reference
#### `sortDefaultKey`

**Type:** `string` · **Default:** `'structure'`

The default sort attribute for the comments index in the control panel, such as `structure`, `commentDate` or `voteCount`. It doesn’t affect the front end.
:::

::: reference
#### `sortDefaultDirection`

**Type:** `string` · **Default:** `'asc'`

The default sort direction for the comments index in the control panel, either `asc` or `desc`. It doesn’t affect the front end.
:::
