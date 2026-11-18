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

### General Settings

::: reference
#### `pluginName`

**Type:** `string` · **Default:** `'Comments'`

The label used for Comments in the control panel.
:::

::: reference
#### `closed`

**Type:** `bool` · **Default:** `false`

Stops new comments everywhere, while existing comments stay visible. A Comments field can't reopen a discussion while this is on. To hide a discussion completely, turn comments off for the element instead. See [Permissions](docs:feature-tour/permissions#per-element-settings).
:::

::: reference
#### `compatibilityMode`

**Type:** `bool` · **Default:** `true`

Keeps Comments 3's Twig helpers, comment methods, query parameters, GraphQL arguments and fields, console commands and browser events working on a site that has upgraded. Turn it off once your templates and code are updated, as [Turning Off Compatibility Mode](docs:get-started/upgrading-from-comments-3#before-disabling-compatibility-mode) describes.
:::

::: reference
#### `autoCloseDays`

**Type:** `int|null` · **Default:** `null`

Closes comments on an element this many days after its post date, leaving existing comments visible. Leave it empty to keep them open. Elements with no post date, such as assets and users, never close this way.
:::

::: reference
#### `sortDefaultKey`

**Type:** `string` · **Default:** `'commentDate'`

The sort used when opening the Comments index in the control panel. Supported values are `commentDate`, `dateUpdated`, `thread`, and `status`. This doesn't affect the public site, which uses [`orderBy`](#orderBy).
:::

::: reference
#### `sortDefaultDirection`

**Type:** `string` · **Default:** `'desc'`

The direction of [`sortDefaultKey`](#sortDefaultKey), either `asc` or `desc`.
:::

### Authoring

These are explained in [Rich Authoring](docs:feature-tour/rich-authoring).

::: reference
#### `authoringMode`

**Type:** `string` · **Default:** `'rich'`

How visitors write comments, shown as **Authoring Mode**. Supported values are `rich` (**Rich (Editor)**), which adds formatting and optional mentions and attachments, and `plain` (**Plain text**), which saves comments as plain text.
:::

::: reference
#### `securityMaxLength`

**Type:** `int|null` · **Default:** `null`

The most characters a comment can have. Leave it empty for no limit.
:::

::: reference
#### `authorEditWindow`

**Type:** `int|null` · **Default:** `null`

How many minutes authors can edit or delete their own comment for. Leave it empty for no limit. People who can edit or delete anyone's comments aren't limited by it.
:::

::: reference
#### `allowMentions`

**Type:** `bool` · **Default:** `true`

Whether signed-in commenters can mention people when [`authoringMode`](#authoringMode) is `rich`.
:::

::: reference
#### `allowAttachments`

**Type:** `bool` · **Default:** `false`

Whether signed-in commenters can add images and files when [`authoringMode`](#authoringMode) is `rich`. This needs [`attachmentVolumeId`](#attachmentVolumeId).
:::

::: reference
#### `attachmentVolumeId`

**Type:** `int|null` · **Default:** `null`

The ID of the asset volume attachments are stored in. Required when [`allowAttachments`](#allowAttachments) is on.
:::

::: reference
#### `attachmentSubpath`

**Type:** `string|null` · **Default:** `null`

The folder in [`attachmentVolumeId`](#attachmentVolumeId) each comment's files go to, such as `'comments/{owner.section.handle}'`. It can use the comment's properties in Twig, as an Assets field's upload location can. Blank, or a folder that can't be worked out for a comment, is the volume's root.
:::

::: reference
#### `attachmentExtensions`

**Type:** `array` · **Default:** `['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']`

The file extensions commenters can attach.
:::

::: reference
#### `attachmentMaxSize`

**Type:** `int` · **Default:** `10`

The largest file commenters can attach, in megabytes.
:::

::: reference
#### `giphyEnabled`

**Type:** `bool` · **Default:** `false`

Whether commenters can search [GIPHY](https://giphy.com) and add a GIF to a comment or reply, with plain or rich authoring. This needs [`giphyApiKey`](#giphyApiKey). See [GIFs from GIPHY](docs:feature-tour/rich-authoring#gIFs-from-giphy).
:::

::: reference
#### `giphyApiKey`

**Type:** `string|null` · **Default:** `null`

An API key for the web from your [GIPHY developer dashboard](https://developers.giphy.com/dashboard/), which can be an environment variable such as `'$GIPHY_API_KEY'`. GIPHY's terms require searches to come from each visitor's browser, so the key is included in the page for anyone who can comment.
:::

::: reference
#### `giphyRating`

**Type:** `string` · **Default:** `'g'`

The highest GIPHY content rating commenters can search for: `g`, `pg`, `pg-13` or `r`.
:::

::: reference
#### `giphyLimit`

**Type:** `int` · **Default:** `24`

The most GIFs to show for each search, up to 50.
:::

### Comment Fields

These are under **Comment Fields** in the control panel, and explained in [Custom Fields](docs:template-guides/custom-fields).

::: reference
#### `formFields`

**Type:** `array` · **Default:** `[]`

Which custom fields on the Comment field layout visitors fill in on the site's form, shown under **The Comment Form** and **Answers on Comments**. Each field is keyed by its field layout element's UID, which identifies that field's place in the Comment field layout. Its value is an array with a `mode` of `collect`, which keeps the value private (**Not shown** on the settings page), or `collect-and-show`, which also shows it on the comment (**Beside the date** or **Under the text**). `placement` says where a shown value goes, either `details` for a list under the text or `inline` for after the date, and defaults to `details`. `replies` says whether replies ask for the field too, and defaults to `false`. Fields that aren't listed aren't asked for, and are only edited in the control panel.

The control panel doesn't show these UIDs, so the practical route is to choose each field under **Comments** → **Settings** → **Comment Fields**. That saves the same array to project config, under `plugins.comments.settings.formFields`, where you can copy the keys if you'd rather keep the setting in `config/comments.php`. For example, this collects a rating and shows it after the date on top-level comments, and collects an order number that stays private:

```php
'formFields' => [
    '6f1c2a0e-8d4b-4c57-9a3e-1b2d3c4e5f60' => [
        'mode' => 'collect-and-show',
        'placement' => 'inline',
        'replies' => false,
    ],
    '0a9b8c7d-6e5f-4a3b-8c2d-1e0f9a8b7c6d' => [
        'mode' => 'collect',
        'placement' => 'details',
        'replies' => false,
    ],
],
```
:::

::: reference
#### `allowGuestFieldUploads`

**Type:** `bool` · **Default:** `false`

Whether guests are offered Assets fields on the comment form, and can upload files to them. A guest's uploads are anonymous and can only be limited by their address, so this is off by default.
:::

### Commenting

These are under **Commenting** in the control panel, and explained in [Permissions](docs:feature-tour/permissions).

::: reference
#### `permissions`

**Type:** `array` · **Default:** `[]`

Which elements can have comments by default. An element type that isn't listed can have comments everywhere. For a type that is listed, use `'*'` to allow all of them, or an array of UIDs to allow only those. These are section UIDs for entries (or entry type UIDs for nested entries, which have no section), category group UIDs for categories, volume UIDs for assets and user group UIDs for users.

The practical route is to choose the elements under **Comments** → **Settings** → **Commenting**, which saves this array with the right UIDs to project config, under `plugins.comments.settings.permissions`. Copy it from there if you'd rather keep the setting in `config/comments.php`.

```php
'permissions' => [
    'craft\elements\Asset' => [
        '33974e79-b3b6-47ec-af91-4519fe4985be',
    ],
    'craft\elements\Category' => [
        'a27827c8-4810-433c-acb9-261b53d46281',
    ],
    'craft\elements\Entry' => [
        'a8d4bdf1-164e-4ddc-aaad-640026b8d3bf',
    ],
    'craft\elements\User' => [
        'c23a4d8d-47f0-4e71-927c-d5897ec9c9f8',
    ],
],
```

[Permissions](docs:feature-tour/permissions) explains how this works with the Comments field.
:::

::: reference
#### `allowGuest`

**Type:** `bool` · **Default:** `false`

Whether visitors who aren't signed in can comment.
:::

::: reference
#### `guestNotice`

**Type:** `string|null` · **Default:** `null`

The error a signed-out visitor gets when they post through GraphQL or Comments' form actions while guests can't comment. Without one, it's “Must be logged in to comment.” The thread itself doesn't use this message. It shows signed-out visitors a **Join the conversation** card with “Sign in to comment.”, and a **Sign in** button when Craft's `loginPath` is set. You can reword the card with [static translations](docs:template-guides/text-translations).
:::

::: reference
#### `guestRequireEmailName`

**Type:** `bool` · **Default:** `true`

Whether guests have to provide both a name and an email address.
:::

::: reference
#### `guestShowEmailName`

**Type:** `bool` · **Default:** `true`

Whether guests see name and email fields. They're shown regardless when [`guestRequireEmailName`](#guestRequireEmailName) is on, because they're needed.
:::

::: reference
#### `maxUserComments`

**Type:** `int|null` · **Default:** `null`

The number of comments a signed-in user can have on one element, counting approved and pending ones. Leave it empty for no limit. Guests aren't counted.
:::

::: reference
#### `maxReplyDepth`

**Type:** `int|null` · **Default:** `null`

How many levels of replies comments can have. Leave it empty for no limit, or use `0` to turn replies off.
:::

### Interactions

These are explained in [Interactions](docs:feature-tour/interactions).

::: reference
#### `interactionMode`

**Type:** `string` · **Default:** `'like'`

How visitors can react to a comment. Supported values are `none`, `like` for upvotes only, and `updown` for upvotes and downvotes.
:::

::: reference
#### `allowGuestVoting`

**Type:** `bool` · **Default:** `false`

Whether visitors who aren't signed in can vote, shown as **Allow Guest Interactions**.
:::

::: reference
#### `showAuthorScore`

**Type:** `bool` · **Default:** `false`

Whether to show each signed-in author's total votes across their approved comments beside their name: upvotes or likes, less downvotes.
:::

::: reference
#### `downvoteCommentLimit`

**Type:** `int|null` · **Default:** `null`

How many downvotes fold a comment away, shown as **Hide After Downvotes**. A folded comment shows a short note and a **Show comment** button in place of its text, and stays published. Only applies when [`interactionMode`](#interactionMode) is `updown`. Leave it empty to never fold comments for their votes.
:::

::: reference
#### `allowFlagging`

**Type:** `bool` · **Default:** `true`

Whether signed-in visitors can report comments, shown as **Allow Reporting**.
:::

::: reference
#### `allowGuestFlagging`

**Type:** `bool` · **Default:** `false`

Whether visitors who aren't signed in can report comments, shown as **Allow Guest Reports**.
:::

::: reference
#### `flaggedCommentLimit`

**Type:** `int|null` · **Default:** `null`

How many open reports fold a comment away, shown as **Hide After Reports**, until a moderator settles them. A folded comment shows a short note and a **Show comment** button in place of its text, stays published, and still waits for a moderator under **Reported**. Leave it empty to never fold comments for their reports.
:::

::: reference
#### `reportReasons`

**Type:** `array` · **Default:** see below

The reasons visitors choose from when reporting a comment. Each has a `label` and a short `description`, shown in the report dialog, and a `value`, which is what's stored with each report, so changing the `label` or `description` is safe.

```php
'reportReasons' => [
    ['value' => 'spam', 'label' => 'Spam', 'description' => 'Ads, scams or links that don’t belong'],
    ['value' => 'harassment', 'label' => 'Harassment or abuse', 'description' => 'Attacks or insults aimed at a person'],
    ['value' => 'off-topic', 'label' => 'Off-topic', 'description' => 'Not about this post'],
    ['value' => 'other', 'label' => 'Other', 'description' => 'Tell the moderators below'],
],
```
:::

### Notifications

These are explained in [Notifications & Subscriptions](docs:feature-tour/notifications-subscriptions).

::: reference
#### `useQueueForNotifications`

**Type:** `bool` · **Default:** `true`

Whether emails and the [`afterNotify` event](docs:developers/events#the-afterNotify-event) are sent through Craft's queue rather than during the visitor's request, which keeps commenting fast. Turning this off suits a local environment with no queue runner. Emails asking a guest to confirm their address always use the queue.
:::

::: reference
#### `participantNotifications`

**Type:** `array` · **Default:** `['owner', 'reply', 'mention']`

The events that tell people taking part in a discussion. Supported values are `owner` for a new comment on an element someone authored, `reply` for a reply to someone's comment, `mention` for being mentioned in an approved comment, and `approved` for someone's pending comment being approved. People with an account are emailed at their account's address, and guests once they've confirmed theirs.
:::

::: reference
#### `subscriptionTypes`

**Type:** `array` · **Default:** `[]`

What people can subscribe to, shown as **Available Subscriptions**. Supported values are `conversation` for a whole discussion and `replies` for the replies to a comment. On the site, subscribing is called following. When `conversation` is included, the thread shows a **Follow** button, and when guests can comment, signed-out visitors get **Follow by email** instead, which asks them to confirm their address. Guests can only follow a whole discussion.
:::

::: reference
#### `subscriptionDefault`

**Type:** `string` · **Default:** `'none'`

What a signed-in user is subscribed to after commenting. Supported values are `none`, `replies` and `conversation`. The value has to be included in [`subscriptionTypes`](#subscriptionTypes), and someone who has unsubscribed isn't subscribed again.
:::

::: reference
#### `staffNotifications`

**Type:** `array` · **Default:** `[]`

The events that email staff. Supported values are `moderator-pending` for a comment awaiting approval and `moderator-edited` for a pending comment edited by its author, which email the members of [`moderatorUserGroup`](#moderatorUserGroup), and `staff-new` for every new comment and `staff-reported` for every new report, which email the [`notificationRecipients`](#notificationRecipients).
:::

::: reference
#### `notificationRecipients`

**Type:** `array|Closure` · **Default:** `[]`

The email addresses told about new comments and reports, shown as **Notification Recipients**. Each row is an array such as `['email' => 'editor@example.com', 'enabled' => true]`, and a plain address counts as an enabled row. A closure that returns the rows works too, for a list you look up when it's needed.

```php
'notificationRecipients' => [
    ['email' => 'editor@example.com', 'enabled' => true],
    'moderation@example.com',
],
```
:::

::: reference
#### `templateEmail`

**Type:** `string|null` · **Default:** `null`

A site template that wraps the emails Comments sends, in place of Craft's own. It receives the rendered message as `body`, along with `comment`, `element`, `user` and `language`.
:::

### Moderation

These are explained in [Moderation](docs:feature-tour/moderation).

::: reference
#### `requireModeration`

**Type:** `bool` · **Default:** `true`

Whether new comments have to be approved before they're public.
:::

::: reference
#### `moderatorUserGroup`

**Type:** `string|null` · **Default:** `null`

The UID of the user group whose members are your moderators. They're emailed about comments waiting for review and edits to them when those [`staffNotifications`](#staffNotifications) are on, their comments can skip moderation with [`moderatorExcluded`](#moderatorExcluded), and they're among the people who can be mentioned.
:::

::: reference
#### `moderatorExcluded`

**Type:** `bool` · **Default:** `true`

Whether comments from [`moderatorUserGroup`](#moderatorUserGroup) skip moderation.
:::

::: reference
#### `holdFirstComments`

**Type:** `bool` · **Default:** `false`

Holds each person's comments for review until a moderator has approved one of them. It only matters while [`requireModeration`](#requireModeration) is off.
:::

### Spam Protection

These are explained in [Anti-Spam](docs:feature-tour/anti-spam). Turning off [`enableSpamChecks`](#enableSpamChecks) turns off all of them, but not [`securityFlooding`](#securityFlooding), [`maxLinks`](#maxLinks), [`blockedEmailDomains`](#blockedEmailDomains) or the keyword filters. These and the keyword filters are saved in each environment's database rather than project config, and a value in this file wins over what's saved.

::: reference
#### `enableSpamChecks`

**Type:** `bool` · **Default:** `true`

Whether public comments are checked before saving, including those from the API and GraphQL.
:::

::: reference
#### `honeypotEnabled`

**Type:** `bool` · **Default:** `true`

Adds a hidden field to forms, which people leave empty and bots tend to fill in.
:::

::: reference
#### `minimumSubmitTime`

**Type:** `int` · **Default:** `3`

How many seconds a form must be open before it's posted, from `0` to `300`. Use `0` to turn this off.
:::

::: reference
#### `replayProtectionEnabled`

**Type:** `bool` · **Default:** `true`

Allows each prepared form to be submitted once.
:::

::: reference
#### `rateLimitEnabled`

**Type:** `bool` · **Default:** `true`

Limits how many times each person can try to post in a period, successfully or not. Signed-in visitors are counted by user and guests by IP address, which depends on [Client IP Addresses](docs:feature-tour/security#client-ip-addresses) being right for your site.
:::

::: reference
#### `rateLimitCount`

**Type:** `int` · **Default:** `5`

The attempts allowed in each window.
:::

::: reference
#### `rateLimitWindow`

**Type:** `int` · **Default:** `60`

The length of a window, in seconds.
:::

::: reference
#### `securityFlooding`

**Type:** `int|null` · **Default:** `null`

How many seconds each person has to wait between comments. Leave it empty to allow bursts.
:::

::: reference
#### `challengeProvider`

**Type:** `string` · **Default:** `''`

The service that checks each visitor is a person: `turnstile` for Cloudflare Turnstile, `recaptcha` for reCAPTCHA v3, `hcaptcha` for hCaptcha or `friendly-captcha` for Friendly Captcha. Leave it empty for no challenge.
:::

::: reference
#### `turnstileKey`

**Type:** `string|null` · **Default:** `null`

The Turnstile site key. Environment variables are supported.
:::

::: reference
#### `turnstileSecret`

**Type:** `string|null` · **Default:** `null`

The Turnstile secret key. Environment variables are supported.
:::

::: reference
#### `recaptchaKey`

**Type:** `string|null` · **Default:** `null`

The reCAPTCHA site key. Environment variables are supported.
:::

::: reference
#### `recaptchaSecret`

**Type:** `string|null` · **Default:** `null`

The reCAPTCHA secret key. Environment variables are supported.
:::

::: reference
#### `recaptchaMinScore`

**Type:** `float` · **Default:** `0.5`

The score a visitor needs to pass, from `0` (likely a bot) to `1` (likely a person).
:::

::: reference
#### `hcaptchaKey`

**Type:** `string|null` · **Default:** `null`

The hCaptcha site key. Environment variables are supported.
:::

::: reference
#### `hcaptchaSecret`

**Type:** `string|null` · **Default:** `null`

The hCaptcha secret key. Environment variables are supported.
:::

::: reference
#### `friendlyCaptchaKey`

**Type:** `string|null` · **Default:** `null`

The Friendly Captcha site key. Environment variables are supported.
:::

::: reference
#### `friendlyCaptchaSecret`

**Type:** `string|null` · **Default:** `null`

The Friendly Captcha API key. Environment variables are supported.
:::

::: reference
#### `friendlyCaptchaEndpoint`

**Type:** `string` · **Default:** `'global'`

Which Friendly Captcha service verifies visitors, `global` or `eu`.
:::

::: reference
#### `challengeAuthenticated`

**Type:** `bool` · **Default:** `true`

Whether signed-in visitors get challenges as well as guests.
:::

::: reference
#### `challengeSendIp`

**Type:** `bool` · **Default:** `false`

Whether the visitor's IP address is shared with the challenge provider.
:::

::: reference
#### `protectionHostnames`

**Type:** `array` · **Default:** `[]`

The hostnames a challenge is accepted from, when comments are posted from somewhere other than the site's own hostname. Empty uses the hostname of the site's base URL. This can only be set in the config file.
:::

::: reference
#### `spamClassifier`

**Type:** `string` · **Default:** `''`

The service that classifies comments as spam: `akismet`, `cleantalk` or `oopspam`. Leave it empty for none.
:::

::: reference
#### `akismetApiKey`

**Type:** `string|null` · **Default:** `null`

The Akismet API key. Environment variables are supported.
:::

::: reference
#### `akismetBlogUrl`

**Type:** `string|null` · **Default:** `null`

The site URL given to Akismet. Empty uses the site's base URL.
:::

::: reference
#### `akismetTestMode`

**Type:** `bool` · **Default:** `false`

Tells Akismet these are test requests, so they don't affect its results for your site.
:::

::: reference
#### `cleantalkApiKey`

**Type:** `string|null` · **Default:** `null`

The CleanTalk access key. Environment variables are supported.
:::

::: reference
#### `oopspamApiKey`

**Type:** `string|null` · **Default:** `null`

The OOPSpam API key. Environment variables are supported.
:::

::: reference
#### `oopspamSpamThreshold`

**Type:** `int` · **Default:** `3`

The OOPSpam score, from 1 to 6, at which a comment counts as spam. Lower catches more.
:::

::: reference
#### `spamFeedbackEnabled`

**Type:** `bool` · **Default:** `false`

Tells Akismet or OOPSpam when a moderator marks a comment as spam or not spam, as long as the comment has an IP address stored.
:::

::: reference
#### `saveSpam`

**Type:** `bool` · **Default:** `true`

Saves comments found to be spam with the Spam status for review. When off, they're rejected.
:::

::: reference
#### `spamRetentionDays`

**Type:** `int|null` · **Default:** `null`

Deletes spam permanently once it hasn't changed for this many days, when Craft collects garbage. Leave it empty to keep spam until someone deletes it.
:::

::: reference
#### `maxLinks`

**Type:** `int|null` · **Default:** `null`

Holds a comment for review when it has more links than this. Leave it empty for no limit.
:::

::: reference
#### `blockedEmailDomains`

**Type:** `string|null` · **Default:** `null`

Email domains, one per line, whose guests can't comment. Each also covers its subdomains.
:::

::: reference
#### `protectionFailClosed`

**Type:** `bool` · **Default:** `false`

Rejects comments when a server-side provider can't be reached, rather than logging the error and carrying on with the other checks. This doesn't cover checks made in the browser.
:::

### Keyword Filters

These are under **Spam Protection** in the control panel, and explained in [Security](docs:feature-tour/security).

::: reference
#### `securityModeration`

**Type:** `string|null` · **Default:** `null`

Rules, one per line, that hold a comment for moderation. See [Keyword Filters](docs:feature-tour/security#keyword-filters) for what a rule can be.
:::

::: reference
#### `securitySpamlist`

**Type:** `string|null` · **Default:** `null`

Rules, one per line, that mark a comment as spam.
:::

::: reference
#### `securityBanned`

**Type:** `string|null` · **Default:** `null`

Rules, one per line, that stop a comment being submitted.
:::

::: reference
#### `securityMatchExact`

**Type:** `bool` · **Default:** `false`

Matches whole words only, so `craft` doesn't match `crafty`. It applies to plain lines and `[match: …]` rules.
:::

### Appearance

::: reference
#### `orderBy`

**Type:** `string` · **Default:** `'desc'`

The order comments are shown in on the public site, unless a visitor chooses another. Supported values are `desc` for newest first and `asc` for oldest first.
:::

::: reference
#### `theme`

**Type:** `string` · **Default:** `'light'`

The colour theme for public comments and the Editor. Supported values are `light`, `dark`, `auto`, which follows the visitor's colour-scheme preference, and `none`, which loads only the base styles the thread needs to work, for a site providing its own look. To adjust a theme's colours and spacing, see [Theming](docs:template-guides/theming).
:::

::: reference
#### `showAvatar`

**Type:** `bool` · **Default:** `true`

Whether to show an avatar with each comment. Users show their photo, or their Gravatar when [`enableGravatar`](#enableGravatar) is on, and anyone else shows [`placeholderAvatar`](#placeholderAvatar).
:::

::: reference
#### `placeholderAvatar`

**Type:** `array|null` · **Default:** `null`

The asset to show as an avatar when someone has no photo, as an array holding its ID.
:::

::: reference
#### `enableGravatar`

**Type:** `bool` · **Default:** `false`

Whether to show a commenter's [Gravatar](https://gravatar.com) when they don't have a user photo. Only addresses known to be the commenter's are looked up: signed-in users, and guests who've confirmed their email. Your server fetches each image in the queue and serves it from your site, so visitors never contact Gravatar and their email's hash is never in the page. See [Gravatar](docs:feature-tour/security#gravatar).
:::

::: reference
#### `showTimeAgo`

**Type:** `bool` · **Default:** `true`

Whether to show how long ago a comment was made, rather than its date.
:::

::: reference
#### `ownerBadgeLabel`

**Type:** `string|null` · **Default:** `'Author'`

The badge next to a comment made by the author of the element it's on. Leave it empty for no badge.
:::

::: reference
#### `staffBadgeLabel`

**Type:** `string|null` · **Default:** `null`

The badge next to comments from [`staffBadgeGroups`](#staffBadgeGroups). Leave it empty for no badge.
:::

::: reference
#### `staffBadgeGroups`

**Type:** `array` · **Default:** `[]`

The UIDs of the user groups that show [`staffBadgeLabel`](#staffBadgeLabel). Being able to moderate doesn't show a badge by itself.
:::

### CSS & JavaScript

::: reference
#### `outputDefaultCss`

**Type:** `bool` · **Default:** `true`

Whether the bundled stylesheet is loaded when comments are rendered. For a look of your own, set [`theme`](#theme) to `none` instead, which keeps the base styles the thread needs to work. Turn this off only when your own build includes Comments' stylesheet or provides those base styles itself. See [Loading Comment Assets](docs:template-guides/loading-comment-assets#choosing-what-the-bundled-css-includes).
:::

::: reference
#### `outputDefaultJs`

**Type:** `bool` · **Default:** `true`

Whether the bundled JavaScript, which draws each thread, is loaded when comments are rendered. Turn this off when your site bundles Comments' script into its own build, or draws comments with its own interface. The thread's data still says where to load the Editor from, so rich authoring keeps working once your site loads the script. See [Loading Comment Assets](docs:template-guides/loading-comment-assets#bundling-the-script-into-your-own-build).
:::

::: reference
#### `useCssLayers`

**Type:** `bool` · **Default:** `false`

Whether the bundled stylesheet is wrapped in `@layer comments`. Turn this on when your site's own CSS is in cascade layers, as Tailwind CSS 4's is, so you can place Comments' styles below your utilities with a layer order such as `@layer theme, base, comments, components, utilities;`. See [Theming](docs:template-guides/theming#sites-that-use-cascade-layers).
:::

::: reference
#### `loadMode`

**Type:** `string` · **Default:** `'visible'`

When a thread's comments load in the browser. `visible` loads them just before a visitor scrolls to them, `button` waits for the visitor to press **Show comments**, and `eager` loads them as soon as the page does. A `loadMode` option on `craft.comments.render()` overrides this for one page. See [Rendering Comments](docs:template-guides/rendering-comments#choosing-when-comments-load) for what each means for search engines.
:::

::: reference
#### `themeConfig`

**Type:** `array` · **Default:** `[]`

Your own classes and attributes for the thread's buttons, form controls, links and avatars on every page, so they can use your design system's classes. It takes the same shape as the `themeConfig` option on `craft.comments.render()`, keyed by control: `primaryButton`, `secondaryButton`, `quietButton`, `input`, `textarea`, `select`, `checkbox`, `radio`, `link` and `avatar`. Each control can set `class`, `title`, `aria-label` and your own `data-*` attributes, and `resetClass` drops Comments' own styling classes from it. This can only be set in the config file. It's merged first, so a `themeConfig` passed to `render()` adds to it and wins for any attribute both set.

```php
'themeConfig' => [
    'primaryButton' => [
        'resetClass' => true,
        'attributes' => ['class' => 'btn btn-primary btn-sm'],
    ],
    'input' => [
        'attributes' => ['class' => 'form-control'],
    ],
],
```

[Using Your Design System's Classes](docs:template-guides/theming#using-your-design-systems-classes) explains how to use it, and the [package docs](https://docs.verbb.io/comments/browser/theme-config) list where each control appears.
:::

### Other Sites

::: reference
#### `allowedOrigins`

**Type:** `array` · **Default:** `[]`

Front ends on another domain or subdomain that may load and post comments through the data API, shown as **Allowed Origins**. Each is an array with the `origin`, as a scheme and host such as `https://www.example.com`, and switches for `credentials` (**Shared Cookies**), `memberTokens` (**Member Tokens**) and `moderation` (**Moderation**), which are all off unless you turn them on. A plain string, such as `'https://www.example.com'`, is read as that origin with every switch off, so a list written for Formie's `allowedOrigins` works as it is. Pages on your Craft site itself don't need to be listed.

An origin with its own Turnstile or reCAPTCHA account can also have `protectionSiteKey` (**Challenge Site Key**) and `protectionSecretKey` (**Challenge Secret Key**). Set both or neither, and give the secret key as an environment variable, such as `$SHOP_TURNSTILE_SECRET`, so it isn't saved to project config. Without them, the origin uses the site's own challenge keys, so add the origin's hostname to those keys at the provider.

```php
'allowedOrigins' => [
    [
        'origin' => 'https://community.example.com',
        'credentials' => true,
    ],
    [
        'origin' => 'https://shop.example.net',
        'memberTokens' => true,
        'protectionSiteKey' => '$SHOP_TURNSTILE_KEY',
        'protectionSecretKey' => '$SHOP_TURNSTILE_SECRET',
    ],
],
```

Here, `community.example.com` is another subdomain of the site that shares Craft's session cookie, and `shop.example.net` is a separate site whose own server signs its members in with [`memberTokenKey`](#memberTokenKey). [Signing In Across Sites](docs:headless/signing-in-across-sites) explains when to use each.
:::

::: reference
#### `memberTokenKey`

**Type:** `string|null` · **Default:** `null`

The secret a separate site's own server signs its member token requests with, shown as **Member Token Key**. Give it as an environment variable, such as `$COMMENTS_MEMBER_TOKEN_KEY`, holding at least 32 random characters, and never send it to a browser. Leave it empty to turn member tokens off.
:::

::: reference
#### `memberTokenPreviousKey`

**Type:** `string|null` · **Default:** `null`

The old key while you change [`memberTokenKey`](#memberTokenKey), shown as **Previous Member Token Key**. Requests signed with either key are accepted until every server has the new one, so clear this once they have. Give it as an environment variable too.
:::
