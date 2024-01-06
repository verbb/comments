# Changelog

## 2.0.10 - 2024-01-06

### Added
- Add “Exclude Moderator Comments” plugin setting to allow moderator comments to be excluded from moderation.
- Add “Moderator Edited Notifications” plugin setting to notify moderators when a comment is edited.

### Fixed
- Fix element index not dislaying some characters correctly for comments.
- Fix manually rendering JS not firing correctly in some instances.
- Fix max comments for user check for multi-site installs.
- Fix JS errors when no comment form is available (due to permissions).

## 2.0.9 - 2023-12-03

### Added
- Add action for Comments to determine state of saving or deleting.
- Add `inline` option for reCAPTCHA JS loading.

### Fixed
- Fix an error where trashed comments weren’t validated correctly.
- Fix custom email templates not working for notifications.
- Fix an error with `ownerSiteId` for multi-site installs.
- Fix lack of typecast for `siteId` when submitting comments.

## 2.0.8 - 2023-10-25

### Added
- Add support for Votes to Feed Me itegration. (thanks @JeroenOnstuimig).
- Add extra error-handling for some email notifications.

### Fixed
- Implement `Element::trackChanges()` for Blitz compatibility.
- Fix returning Vote user when both querying users and comments is allowed for GraphQL. (thanks @JeroenOnstuimig).

## 2.0.7 - 2023-03-09

### Changed
- Update system message headings for some email notifications.

### Fixed
- Fix an issue when installing the plugin, and automatically disabled.
- Fix an incompatibility with [Emails](https://github.com/ryssbowh/craft-emails) plugin.
- Fix incorrect return types for voting and flagging comments with GraphQL mutations.

## 2.0.6 - 2023-02-22

### Added
- Add `name` and `email` to available index columns for the control panel.

### Changed
- Update English translations.
- Only admins are now allowed to access plugin settings.
- Improve dutch translations. (thanks @jeroenlammerts).

### Fixed
- Fix a 404 error when trying to edit a non-primary site comment for a multi-site install.
- Fix HTML encoding for name, email and comment columns in the control panel element index.
- Fix compatibility with Craft 4 and guest names showing `User`.
- Fix an error when importing nested commerce with Feed Me.
- Fix email notifications not sending via the queue when comments are made from a non-primary site
- Fix custom sources not filtering by `$ownerSectionId`. (thanks @jeroenlammerts).

## 2.0.5 - 2022-12-30

### Fixed
- Fix an error when sending email notifications.

## 2.0.4 - 2022-12-25

### Changed
- Improve dutch translations. (thanks @jeroenlammerts).

### Fixed
- Fixed PHP errors that could occur when executing GraphQL queries.

## 2.0.3 - 2022-10-12

### Fixed
- Fix an error when upvoting immediately after downvoting.
- Fix avatars not working for logged-in users.

## 2.0.2.1 - 2022-09-17

### Fixed
- Fix a fatal PHP error introduced in `2.0.2`.

## 2.0.2 - 2022-09-17

### Added
- Add support for setting ReCAPTCHA API keys via .env variables.

### Fixed
- Fix template path not resetting when calling `commentsInclude()`.
- Fix an error when trying to edit a comment from the element index.
- Fix element index arrow for comment elements.
- Fix element action for setting status.
- Fix lack of permission support for managing other user’s comments.
- Fix being unable to set `id`, `uid` or `userId` for GraphQL mutations.
- Fix an error running `resave` console commands.

## 2.0.1 - 2022-08-27

### Added
- Added missing English Translations.

### Fixed
- Fix an error when viewing comments with an avatar image.
- Fix formatting for comments element index.
- Fix lack of proper error when trying to edit an invalid comment in the control panel.
- Fix the CP edit URL for comments to now no longer include the site ID.
- Fix an error when importing comments via Feed Me in some instances.
- Fix an error when uninstalling.
- Fix being unable to view comments in the control panel.

## 2.0.0 - 2022-06-02

### Added
- Add resave console command for elements.
- Add checks for registering events for performance.
- Add `archiveTableIfExists()` to install migration.

### Changed
- Now requires PHP `8.0.2+`.
- Now requires Craft `4.0.0+`.
- Now requires Comments `1.9.2` in order to update from Craft 3.
- Rename service classes.
- Rename base plugin methods.

### Fixed
- Fix an error with GraphQL.
- Fix typing for `placeholderAvatar` plugin setting.
- Un-bold title for comments in comments index.
- Fix an error when permissions exist for a no-longer-available element group.
- Fix setting a parent comment not working for Feed Me, due to `setParent()` changes.

### Removed
- Removed `craft.comments.all()` and `craft.comments.form()`.
- Removed `Comment::trashUrl`, `Comment::flagUrl`, `Comment::downvoteUrl`, `Comment::upvoteUrl`.

## 1.9.6 - 2023-03-09

### Changed
- Update system message headings for some email notifications.

### Fixed
- Fix an issue when installing the plugin, and automatically disabled.
- Fix an incompatibility with [Emails](https://github.com/ryssbowh/craft-emails) plugin.

## 1.9.5 - 2023-02-22

### Changed
- Improve dutch translations. (thanks @jeroenlammerts).

### Fixed
- Fix email notifications not sending via the queue when comments are made from a non-primary site
- Fix custom sources not filtering by `$ownerSectionId`. (thanks @jeroenlammerts).

## 1.9.4 - 2022-09-17

### Added
- Add support for setting ReCAPTCHA API keys via .env variables.

### Fixed
- Fix template path not resetting when calling `commentsInclude()`.

## 1.9.3 - 2022-06-02

### Added
- Add `Minimum Score` plugin setting for reCAPTCHA.

### Fixed
- Fix JS render variables being way too large.
- Fix an error saving a comment when the current site can’t be determined.
- Fix replies not showing in the correct order (latest first).

## 1.9.2 - 2022-02-27

### Added
- Add “Use Queue for Notifications” plugin setting (disabled by default).

### Fixed
- Fix SQL errors when ordering comments via "Votes" or "Flagged" in the control panel.
- Fix an error when sorting comments by their "flagged" column in the control panel.
- Fix an error when including "flagged" as a column when viewing comments in the control panel.

## 1.9.1 - 2021-12-31

### Fixed
- Fix Feed Me importing not taking into account trashed owners.

## 1.9.0 - 2021-12-04

### Changed
- Now requires Craft 3.7.24+.

### Fixed
- Fix an incompatibility error with Craft 3.7.24+.

## 1.8.12 - 2021-12-04

### Added
- Add `settings.commentingAvailable()`, which will provide more detailed information on why commenting is not available on an element. (thanks @adrienne).
- Add support for importing nested child comments for Feed Me.

### Fixed
- Fix an error when "Enable Admin Notifications" was enabled, but no "Comment Administrators" were defined.

## 1.8.11 - 2021-10-30

### Changed
- Now requires Craft 3.6.0+

### Fixed
- Fix not fetching the correct site-specific element when passing in the `siteId` param to the `render()` function.
- Fix `siteId` param for comment form not respecting overridden value when setting via Twig.

## 1.8.10 - 2021-10-06

### Added
- Add some missing NL translations. (thanks @arifje).
- Add `comments/comments/get-js-variables` controller endpoint to fetch necessary variables for rendering JS. Useful for headless setups. (thanks @arifje).

### Fixed
- Fix an error when using GraphQL mutations and "Auto-close Comments" is enabled.

## 1.8.9 - 2021-09-27

### Added
- Add support for "GraphQL Authentication" for all authenticated user functions when using GraphQL.
- Add `emailKey` as a variable to the `comments_subscriber_notification_comment` and `comments_subscriber_notification_element` email template.
- Update subscriber notification email defaults for replies.

## 1.8.8 - 2021-09-18

### Added
- Add `./craft comments/base/set-structure` console command for fixing comments that have lost their original structure.

### Changed
- Update status colours to Craft standard.
- Ensure reply and edit forms generate unique IDs, when an `id` attribute is set on the DOM element for a comment. (thanks @adrienne).

### Fixed
- Fix `userAgent` column in comments not having their default set to `null`.
- Fix incompatibility with Blitz and CSRF token refreshing.
- Fix CSRF token being added to form submissions twice.
- Fix Craft 2 migration and `commentDate` error.
- Fix error when trying to send author notification emails for comments made on assets.
- Fix `showCustomFields` deprecation warning.

## 1.8.7 - 2021-05-30

### Added
- Add support for GraphQL mutations. (thanks @mattstein).

### Changed
- Update reply notifications disabled warning message.
- Remove `showCustomFields` config setting.

### Fixed
- Fix reply email notifications not working correctly, when comment moderation was enabled.
- Fix subscriber notification emails not being sent after a moderated comment was approved.
- Fix subscriber notification emails being sent for comments that require moderation.
- Fix admin notifications not sending when new comments are made.

## 1.8.6 - 2021-04-11

### Added
- Add `getUserVotes()` variable functions.

### Fixed
- Fix an error when editing a comment, for a comment from a deleted user.
- Fix “under review” text not being translated properly.
- Fix an error in `hasDownVoted()` and `hasUpVoted()`.

## 1.8.5 - 2021-03-20

### Fixed
- Fix all email notifications not being supplied with a `user` variable.
- Fix an error being thrown for subscriber email notifications for guests.
- Fix widget throwing an error when a commented-on element was deleted.

## 1.8.4 - 2021-02-19

### Fixed
- Fix an error when flagging a comment, “Enable Flagged Notifications” is checked and no admin emails are defined.

## 1.8.3 - 2021-02-13

### Fixed
- Fix JS error thrown when flags encountered an error.
- Remove flags and votes memoization due to ongoing issues.

## 1.8.2 - 2021-02-03

### Changed
- Update JS to not rely on `.cc-i-body` for message.
- Improve Flag and Vote queries for large amount of comments/votes/flags.

### Fixed
- Fix a recursion error when trying to send subscribe email notifications.
- Fix error with `comment.parent` not returning the correct element.
- Fix some unsupported emoji’s included in comment text throwing errors.

## 1.8.1 - 2021-01-19

### Fixed
- Fix comments’ field layout not being deleted when uninstalling the plugin.
- Fix field layout for comments form being deleted incorrectly.

## 1.8.0 - 2021-01-18

### Added
- Add Gravatar support to automatically fetch commenter avatars.
- Add comments widget to show summary of comments in the Dashboard.
- Add `votes` param to `orderBy` comment queries, allowing you to order comments by their total number of votes.
- Add `isFlagged` comment query param.
- Add `maxUserComments` plugin setting to control the maximum number of comments each user can add, for each owner element.
- Add `securityMatchExact` plugin setting to allow security keywords to be exact word matches.
- Add `notificationAdmins` plugin setting, allowing you to add emails for administrators to get email notifications on every comment.
- Add `templateEmail` plugin setting to provide your own template for email notifications.
- Add `maxReplyDepth` plugin setting to control how many levels of replies a comment can have - even disable replies altogether.
- Add `notificationAdmin` to provide a collection of emails for certainly admin-centric notifications.
- Add `notificationAdminEnabled` to notify nominated admins whenever a comment is made.
- Add `notificationFlaggedEnabled` to notify nominated admins whenever a comment is flagged.
- Add “Votes” column to comment index in control panel.
- Add “Flagged” column to comment index in control panel.
- Add native Feed Me support.
- Add custom field support for Feed Me importing.
- Add `commentsSiteInclude()` Twig function to allow template include resolution to site templates.
- Add `jsSettings` optional parameter to `craft.comments.render()` to supply JS with extra settings.
- Add `trashAction` and `trashActionMessage` to JS render variables.
- Add comment caching for parent comments.
- Add caching for avatars, to prevent fetching the same avatar multiple times.

### Changed
- Improved database query performance, lowering average queries by 30%.
- Replace class selectors with attributes for javascript. (thanks @jsunsawyer).
- Comments now requires Craft 3.5.8+.
- Memoize flags and votes for performance.

### Fixed
- Fix eager-loading comments more than 2 levels deep.
- Fix comment replies not appearing in the control panel when editing a comment, if the replies were trashed.
- Fix guest avatars not caching correctly for comment elements.
- Fix comment owner not being cached properly for comment elements.
- Fix missing `<p>` tags when editing a comment and closing a comment on the front-end.

### Removed
- Removed `showCustomFields` config setting.

## 1.7.5 - 2020-12-12

### Fixed
- Fix comments form layout not saving custom fields.

## 1.7.4 - 2020-12-11

### Fixed
- Ensure a field layout for comments is created when plugin settings are save in the control panel.
- Fix error when trying to check avatar existence on non-local volumes.
- Fix pending status icon when “Use shapes to represent statuses” was set to true.

## 1.7.3 - 2020-12-07

### Changed
- Comment queries via GraphQL are now no longer automatically included in the public schema.
- Update comments to only support being saved in a single site.

### Fixed
- Fix potential error thrown during migrations run via console commands.
- Fix error when a reply failed on a multi-site.

## 1.7.2.1 - 2020-11-24

### Fixed
- **Really** fix an issue where the field layout may not exist.

## 1.7.2 - 2020-11-24

### Fixed
- Fix an issue where the field layout may not exist.

## 1.7.1 - 2020-11-23

### Fixed
- Fix field layout for comments not getting created correctly for new installs.

## 1.7.0 - 2020-11-22

> {warning} If you are using any custom fields, you will need to re-order your fields. The comment textarea field is now content-managed, but added to the top of your form, instead of the bottom as is currently the case. Simply re-order your form fields in Settings > Comments Form after the update.

> {tip} If you are using custom templates, consider reading the new [Custom Templates](https://verbb.io/craft-plugins/comments/docs/template-guides/custom-templates) documentation. There are no breaking changes in this release, but you may find the new method of overriding template partials much less heavy-handed and modular to implement. You might be able to clean up your templates a little!

### Added
- The comment form now makes use of Craft's 3.5+ field layout designer.
- The comment textarea can now be moved freely in the comment form, and optionally un-required.
- Add `commentsInclude` to front-end templates, allowing for easier resolution to default or custom templates.
- Add ability to override only a single included template partial for custom templates, rather than overriding all. For example, you can override _just_ the comment form, or even just a custom field.
- Add `user` to GraphQL comment interface. (thanks @jaydensmith).
- Add `guestNotice` setting to define a notice when guest commenting is not allowed, and the user is a guest.
- Add control panel scenario for Comment elements, to allow front-end and control panel differentiation for validation.

### Changed
- Now requires Craft 3.5+.
- Improve query performance when using a placeholder avatar.
- Improve reliability of getting author’s avatars, adding checks if it exists.
- The reply and comments form are no longer shown when guest commenting is off, and the user is a guest.
- Update example templates.
- “Custom Fields” settings are now replaced by “Comments Form”.
- Provide better handling when custom templates cannot be found. Comments will now fallback to default templates if the custom template cannot be found.
- Front-end templates are now much more modular, allowing for greater flexibility with custom override templates. Hopefully this will help customising templates without having to main the entire set of front-end templates.

### Fixed
- Fix minor alignment issue with field layout designer in plugin settings.
- Fix asset fields and other multi-value fields not showing errors correctly on the front-end.
- Fix custom fields for comments not showing correctly for non-English sites.
- Fix minor sidebar layout issue when editing a comment.
- Fix comments not validating correctly when editing in the control panel.
- Ensure required custom fields are validated from the control panel and front-end for a comment.
- Ensure comments form is properly reset after submission (JS).

### Deprecated
- The `showCustomFields` is deprecated, as it no longer had any effect.

## 1.6.6 - 2020-08-14

### Added
- Add `craft.comments.getJsVariables()`, to allow manual initialisation of front-end JS.
- Add `loadInline` option to `craft.comments.renderJs()`, to disable immediate initialisation.
- Add notice in settings for Structure info regeneration.

## 1.6.5 - 2020-08-10

### Fixed
- Fix visual bug for Craft 3.5 in comments index.

## 1.6.4 - 2020-06-22

### Fixed
- Fix JS error when using `craft.comments.renderJs`. (thanks @john-henry).

## 1.6.3 - 2020-06-22

> {warning} If you are using `securityBlacklist` in your config files, please rename it to `securitySpamlist`.

### Added
- Add `craft.comments.renderCss` and `craft.comments.renderJs`.

### Changed
- Change 'securityBlacklist' to 'securitySpamlist'.

## 1.6.2 - 2020-06-15

### Fixed
- Fix JS error when deleting a comment from the front-end.

## 1.6.1 - 2020-06-04

### Fixed
- Fix error trying to comment when using recaptcha.

## 1.6.0 - 2020-05-30

> {warning} If you are using the default JS, but are using your own custom templates (or vice-versa), please update your custom templates to use POST forms for flagging, voting and deleting a comment. If you do not have custom templates, this does not apply to you.

### Changed
- Flagging, voting and deleting a comment is now triggered through POST forms instead of links. Please ensure you update any custom templates to reflect this change.
- Flagging, voting and deleting a comment can now only be done via POST requests.

### Fixed
- Fix CSRF issue with delete, flag and vote URLs.

### Deprecated
- `comment.trashUrl` is now deprecated. Use a POST form instead. See [docs](https://verbb.io/craft-plugins/comments/docs/developers/comment).
- `comment.flagUrl` is now deprecated. Use a POST form instead. See [docs](https://verbb.io/craft-plugins/comments/docs/developers/flag).
- `comment.upvoteUrl` is now deprecated. Use a POST form instead. See [docs](https://verbb.io/craft-plugins/comments/docs/developers/vote).
- `comment.downvoteUrl` is now deprecated. Use a POST form instead. See [docs](https://verbb.io/craft-plugins/comments/docs/developers/vote).

## 1.5.6 - 2020-05-29 [CRITICAL]

### Fixed
- Properly fix XSS vulnerability.
- Fix an error for PHP 7.4.

## 1.5.5 - 2020-05-28 [CRITICAL]

### Fixed
- Fix XSS issue for guest names containing potentially malicious values. Credit to Paweł Hałdrzyński [Limpid Security](https://limpidsecurity.pl).
- Fix XSS issue for asset volume names containing potentially malicious values. Credit to Paweł Hałdrzyński [Limpid Security](https://limpidsecurity.pl).
- Fix CSRF issue for trashing a comment. Credit to Paweł Hałdrzyński [Limpid Security](https://limpidsecurity.pl).

## 1.5.4 - 2020-05-19

### Fixed
- Improve `comments/base/resave-structure` by trying to find an existing comments structure

## 1.5.3 - 2020-05-18

### Fixed
- Fix some Ajax actions (vote, flag) not working in some instances.
- Fix `allowAnonymous` error, thrown when trying to comment.

## 1.5.2 - 2020-05-18

### Added
- Add `comments/base/resave-structure` console command.

## 1.5.1 - 2020-05-18

### Added
- Add read-only structure info to settings. Useful for debugging.

## 1.5.0 - 2020-05-15

### Added
- Add `showCustomFields` config settings. This is default to off, so as not to cause any breaking changes.
- Add support for basic Craft fields, automatically rendering HTML for some fields. see [custom fields](https://verbb.io/craft-plugins/comments/docs/template-guides/custom-fields).
- Add docs with regards to [custom fields](https://verbb.io/craft-plugins/comments/docs/template-guides/custom-fields).
- Fix uploading assets from the front-end. They now work.

### Changed
- Use `FormData` JS to serialise comment form data. Check your site's [browser compatibility](https://caniuse.com/#search=formdata). We think you'll be fine, unless you need to support IE9.

### Fixed
- Fix error when a commenter has no name.

## 1.4.1.1 - 2020-05-13

### Fixed
- Fix name/email fields appearing when `allowGuest` is off.

## 1.4.1 - 2020-05-13

### Fixed
- Fix name/email fields not appearing on templates when being required, but not shown.

## 1.4.0 - 2020-05-11

### Added
- Add `guestShowEmailName` config option.
- Add performance improvements, with an 70% reduction in the number of database queries.
- Add eager-loading for owner element, author element (and photo).
- Add eager-loading for comment structure. Only applicable for default templates.

### Changed
- Clarify Anonymous commenting to be Guest commenting.

### Deprecated
- Deprecated plugin setting `allowAnonymous`. Use `allowGuest`.
- Deprecated plugin setting `allowAnonymousVoting`. Use `allowGuestVoting`.
- Deprecated plugin setting `allowAnonymousFlagging`. Use `allowGuestFlagging`.

## 1.3.12 - 2020-04-16

### Fixed
- Fix logging error `Call to undefined method setFileLogging()`.

## 1.3.11 - 2020-04-15

### Changed
- File logging now checks if the overall Craft app uses file logging.
- Log files now only include `GET` and `POST` additional variables.

## 1.3.10 - 2020-04-06

### Fixed
- Fix error when sending subscribe notifications.
- Fix error when sending reply notifications.
- Fix errors with missing structure when moving environments with a project.yaml file.
- Add structure checks in project config rebuild.

## 1.3.9 - 2020-04-05

### Added
- Add `beforeSendSubscribeEmail` event.

### Changed
- Allow all notification events to have `isValid` set to prevent sending.

### Fixed
- Ensure plugin project config is removed when uninstalling.

## 1.3.8 - 2020-03-30

### Added
- Add `notificationSubscribeAuto` to allow user to auto-subscribe to all comments on an element.

### Fixed
- Fix wrong plural in german translation. (thanks @FabianWildgrube).
- Fix incorrect logic and recipient for moderator approved emails.

## 1.3.7 - 2020-03-14

### Added
- Add `hideVotingForThreshold`.

### Fixed
- Fix custom fields not saving in settings.
- Fix custom fields no appearing for GQL queries.
- Fix incorrect `getVotes()` function. Change to `getAllVotes()`.

## 1.3.6 - 2020-02-29

### Added
- Add votes, upvotes, downvotes and flags to GQL queries.
- Add support for anonymous flag/voting.

### Fixed
- Fix layout and error of replies table in the CP.
- Fix notification checking for two guest users.
- Ensure we check for moderation when sending reply notifications.
- Ensure reply/author notifications are sent when moderation approved.
- Fix lack of IE support for `:scope`, throwing JS errors.
- Fix SVG avatar placeholder.

## 1.3.5 - 2020-02-14

### Fixed
- Fix SQL error thrown in Postgres when viewing the comments index.

## 1.3.4 - 2020-02-11

### Fixed
- PHP error when installing via console.

## 1.3.3 - 2020-02-03

### Fixed
- Fix error when deleting fields, in some circumstances.

## 1.3.2 - 2020-02-02

### Fixed
- Fix comments not saving on multi-sites, in some circumstances.
- Fix error when posting a new comment.

## 1.3.1 - 2020-01-30

### Added
- Add support for custom fields on comments.

### Fixed
- Fix structure not being setup correctly when installing plugin.

## 1.3.0 - 2020-01-29

### Added
- Craft 3.4 compatibility.

## 1.2.2 - 2020-01-09

### Fixed
- Fix subscribe to singular comment notifications. (thanks @frank-rocketpark).

## 1.2.1 - 2020-01-07

### Fixed
- Fixed issue causing new comments to not be submitted.

## 1.2.0 - 2020-01-06

### Added
- Add GraphQL support. See [docs](https://verbb.io/craft-plugins/comments/docs/developers/graphql).
- Add ability to subscribe to comment threads, or individual comments.
- Add ability to save additional custom fields content to comments (when using custom templates). Just add your fields with `fields[myHandle]`.
- Allow passing of custom comment url with a comment submission.
- Add name of user to comment heading, when replying.
- Add dutch translation for notifications messages. (thanks @skoften).
- Add notification for "someone made a comment on element x". (thanks @skoften).
- Add notification for "someone replied to your comment". (thanks @skoften).
- Add `ownerSection` and `ownerSectionId` comment query params. See [docs](https://verbb.io/craft-plugins/comments/docs/getting-elements/comment-queries).
- Add `indexSidebarGroup` and `indexSidebarIndividualElements` config settings. See [docs](https://verbb.io/craft-plugins/comments/docs/get-started/configuration).
- The comments element index sidebar now groups comments made on entries into their sections.
- Add `defaultQueryStatus` to control default query status for comment element queries.
- Add moderator notifications. Includes two new moderator notification settings, and a user group to define who your moderators are.
- Add `EVENT_BEFORE_SEND_AUTHOR_EMAIL` event.
- Add `EVENT_BEFORE_SEND_REPLY_EMAIL` event.
- Add `EVENT_BEFORE_SEND_MODERATOR_EMAIL` event.
- Add `EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL` event.

### Changed
- Notifications will skip sending to the currently logged in user. (thanks @skoften).

### Fixed
- Fix being unable to edit the content of a comment in the CP.
- Fix project config using non-uid’s for structure and permissions.
- Fix incorrect date comparison for `autoCloseDays`.

## 1.1.10 - 2019-09-18

### Fixed
- Fix incorrect variables being passed to JS.

## 1.1.9 - 2019-08-31

### Added
- Added `guestRequireEmailName`, to control whether guests email and name should be required. True by default.
- Add some example no-JS templates.

### Fixed
- Fix lack of returning comment/flag/vote models when performing actions on them.

## 1.1.8 - 2019-08-06

### Added
- Update missing translations. (thanks @skoften).

### Fixed
- Improve security keyword checking. (thanks @skoften).
- Ensure actual comment content was being checked against security keywords. (thanks @skoften).
- Fix error when anonymous commenting was enabled.
- Fix error when deleting comment from CP.

## 1.1.7.1 - 2019-05-25

### Fixed
- Add debugging to email notifications.

## 1.1.7 - 2019-05-23

### Fixed
- Fix SQL error for Postgres when viewing comments in the CP.
- Update link for email messages.

## 1.1.6 - 2019-04-27 [CRITICAL]

### Fixed
- Fix comment-saving always assuming comments are from the current user.
- Fix lack of validation for editing a comment from another user.
- Fix JS bug where a user could act on another users’ comment from the front-end in some cases.

## 1.1.5 - 2019-04-24

### Added
- Add support for max length. (thanks @ilicmarko).

### Changed
- Prevent whitespace being submitted as comments. (thanks @ilicmarko).
- Clear form if comment or reply was successful. (thanks @ilicmarko).
- Comments from a deleted user now show as from '[Delete User]'.

### Fixed
- Fix error when showing a comment from a deleted user.

## 1.1.4 - 2019-04-21

### Changed
- Use Craft's date utilities for getting translatable time diff.
- Remove Carbon dependancy.

### Fixed
- Fix error with `sql_mode=only_full_group_by`.
- Fix error with auto close days enabled.

## 1.1.3 - 2019-04-07

### Added
- Add Dutch language file. (thanks @skoften).

### Changed
- Improve comments sidebar (Don’t include deleted elements, Add config limiter, Use group-by query).

## 1.1.2 - 2019-03-19

### Added
- Add override notice for settings fields.

### Changed
- Allow `render()` to override comments query properly.

### Fixed
- Fix commentDate not being set in some cases.

## 1.1.1 - 2019-03-02

### Fixed
- Fixed more migration cases from Craft 2.

## 1.1.0 - 2019-03-01

### Added
- Add Google reCAPTCHA v3 for better spam protection.
- Add better support for multi-site and the owner element (the commented-upon element).

### Fixed
- Fixed some migration cases from Craft 2.

## 1.0.7.1 - 2019-02-25

### Fixed
- Add more checks to migrations.

## 1.0.7 - 2019-02-24

### Fixed
- Add limit to comments index for large amounts of comments.
- Fix forcing the current time on any new comment’s `commentDate`.
- Add more checks to migrations.

## 1.0.6.3 - 2019-02-21

### Fixed
- Fix validation being triggered when comments re-saved through queue

## 1.0.6.2 - 2019-02-20

### Fixed
- Fix migration from 1.0.6 (again).

## 1.0.6.1 - 2019-02-20

### Fixed
- Fix migration from 1.0.6.

## 1.0.6 - 2019-02-20

### Added
- Add email/name to sort in the CP.
- Add searching for comment, name and email in the CP.

### Changed
- Template now produces `#cc-w-{{ elementId }}` as opposed to a random number. (thanks @skoften).

### Fixed
- Fix only being able to vote on a comment once.
- Fix only being able to flag a comment once.
- Fix missing `owner` query function.
- Refactor CP routes and fix “save and continue route”, fixing an error.
- Fix error in the CP when trying to refer to an element has been deleted.

## 1.0.5 - 2019-01-23

### Added
- Added support for Emoji's in comments.

### Fixed
- Fix incorrect commentDate column in flags and votes tables.
- Fix error thrown when author emails couldn’t be sent.
- Fix comment not being cleared once submitted (thanks @skoften).
- Fix error in the CP when trying to refer to an element has been deleted.

## 1.0.4 - 2018-11-06

### Fixed
- Fix SQL error when trying to order by `commentDate`.
- Fix error with multisite saving edit form (thanks @colinmeinke).

## 1.0.3 - 2018-10-25

### Fixed
- Improve comments index to show each commented-on element.
- Fix SQL bug in comments index.
- Fix error on settings for Craft Solo.
- Properly minify in-built JS.
- Refactor and improve translations in front-end JS.
- Fix not passing in siteId for front-end requests, meaning multisite wasn't working.
- Fix author photo not working (thanks @stevenvandemoortele).
- Added try/catch block to prevent server error (thanks @skoften).
- Fix template bug (thanks @stevenvandemoortele).

## 1.0.2 - 2018-09-17

### Fixed
- Fix missing element methods
- Fix missing elementId in query for `render()`
- Fix `commentDate` not being set after new comment is created (causing an error to be thrown)
- Add flag user feedback
- Fix migration for Craft 2 not renaming `comments` table


## 1.0.1 - 2018-08-26

### Changed
- Switch from `dateCreated` to `commentDate`
- Remove on-delete cascades for owner users and elements. If a user or owner element is deleted, their respective comments won't also be deleted.

### Fixed
- Ensure structure is cleaned up after uninstall
- Removed craft 2 plugin that stuck around (wha?)


## 1.0.0 - 2018-08-25

### Added
- Craft 3 release, major refactor and rewrite from the ground up.
- Brand new, single-line include via `craft.comments.render()` which renders comments and form. Includes front-end CSS and Ajax-driven vanilla JS and is designed to be a drop-in solution to add comments to your site. You can of course still override or replace all aspects of the front end resources.

### Changed
- `craft.comments.all()` has been deprecated. Use `craft.comments.fetch()` instead.
- `craft.comments.form()` has been deprecated. Use `craft.comments.render()` instead.
- Simplified templates - find them in `comments/src/templates/_special`.
- Countless bug fixes, and improvements!

## 0.4.9 - 2017-11-04

### Fixed
- Minor fix for sidebar icon.

## 0.4.8 - 2017-10-17

### Added
- Verbb marketing (new plugin icon, readme, etc).
- Add plugin settings variable.

### Changed
- Improved `onTrashComment` and `onBeforeTrashComment` events.
- Swap comment output macro for native `{% nav %}` tag - better supports element queries such as level.

## 0.4.7 - 2016-10-09

### Fixed
- Fixed template error for permissions settings for Craft Personal/Client versions.
- Fixed `EntryModel.owner` issue where checking for non-entry elements being commented on.

## 0.4.6 - 2016-09-23

### Added
- Added ability to disable spam checks.

### Fixed
- Fixed support for adding comments to non-entry elements.
- Fixed typo in edit comment controller action.

## 0.4.5 - 2016-09-02

### Added
- Added support to permanently delete comment from within the CP.
- Added user permissions to allow edit or trash of comments for other users.
- Added `canEdit()` and `canTrash()` method to Comment Model.

### Changed
- Changed current 'delete' terminology to 'trashed', which is technically more correct.
- `onBeforeDeleteComment` event changed to `onBeforeTrashComment`.
- `onDeleteComment` event changed to `onTrashComment`.
- `deleteActionUrl` template action is now `trashActionUrl`.
- `comments/delete` controller action is now `comments/trash`. Raises a deprecation notice.
- Commenting permissions now only support native Craft elements.

### Fixed
- Fixed case where another logged in user could edit/save/delete comments from another user.
- Fixed permissions errors when checking for elements.

## 0.4.4 - 2016-06-24

### Fixed
- Fixed issue with comments index showing comment id, not user name.
- Move Save Comment button above content on edit comment page (for consistent Craft behaviour).

## 0.4.3 - 2016-06-23

### Added
- Permissions to control when elements are comment-able.
- New fieldtype to provide element-specific control on commenting.
- Added support for [craft.spamguard](https://github.com/selvinortiz/craft.spamguard).

### Fixed
- Fixed error in comments element index - introduced in 0.4.2.

## 0.4.2 - 2016-04-02

### Added
- Added `element` attribute to CommentModel.
- Added `parent` attribute to CommentModel.
- Added `comment` attribute to FlagModel.
- Added `user` attribute to FlagModel.
- Added `comment` attribute to VoteModel.
- Added `user` attribute to VoteModel.

### Changed
- Code formatting.

## 0.4.1 - 2016-04-02

### Added
- Added `author` attribute to CommentModel.
- Added `isGuest()` method to CommentModel.
- Added email notifications for element authors and for other commenters when replying.

### Changed
- Comments are now a little tidier in the element index table.
- Filter via Status in control panel.

## 0.4.0 - 2016-03-16

### Added
- Craft 2.5+ support, including release feed and icons.
- Comments can now be edited through the front-end.
- Added support for querying comments via votes
- Added `onBeforeSaveComment` hook.
- Added `onSaveComment` hook.
- Added `onBeforeDeleteComment` hook.
- Added `onDeleteComment` hook.
- Added `onBeforeFlagComment` hook.
- Added `onFlagComment` hook.
- Added `onBeforeVoteComment` hook.
- Added `onVoteComment` hook.

### Changed
- Reorganise settings pages - separate flag/voting.
- Flagging comments and voting can be enabled/disabled altogether.
- Comments now require moderation by default.
- Lots of refactoring and reorganising.
- Removed Comments fieldtype.
- Comments are shown in hierarchical structure when in control panel.
- Full ajax and non ajax support for endpoints.
- Renamed `comments/add` to `comments/save`.
- Simplified and improved templates for single template tag.
- Simplified example code for ajax and non ajax handling.
- Comment forms handle validation properly, actual error messages and remembers text on validation.

### Fixed
- Fixed `comments/edit` endpoint to include validation/security.

## 0.3.8 - 2015-12-31

- Fixes for field settings messing things up. **Notice** The Comments fieldtype will soon be removed in favour of centralized permissions.
- Other bugfixes and improvements.
- Ensure works well on Craft Client/Craft Personal.

## 0.3.7 - 2015-12-31

- Added the ability to get total comments per element id using `{{ craft.comments.total(elementId) }}`.

## 0.3.6 - 2015-12-31

- Added security measures. You can now provide values to check against _all_ attributes of a comment and action accordingly. Comments can be marked as pending, spam, or simply not allowed to be submitted. See [Security](https://github.com/verbb/comments/wiki/Security)
- Support sorting comments by votes.
- Added optional flood-control settings. Enforces minimum time to wait between posts. Works for anonymous and logged-in users.

## 0.3.5 - 2015-12-31

- Added Schema Tags to templates (see [#19](https://github.com/verbb/comments/issues/19)).

## 0.3.4 - 2015-12-31

- Added threshold for downvotes - if over specified limit, `isPoorlyRated` will be true.
- Comments can be set to auto-close after a defined amount of days since the elements creation.

## 0.3.3 - 2015-12-31

- Altered settings pane - now with multiple tabs.
- Updated permissions UI.
- Comments can be closed, disabling editing, deleting and replies. Accessible through `isClosed`.

## 0.3.2 - 2015-12-31

- Added basic sharing comment options. Provides permalink, Twitter and Facebook sharing.

## 0.3.1 - 2015-12-31

- Organise examples seperately. Better testing locally (symlinks).
- Added standard, non-Ajax example.
- Cleanup Ajax example - refactor.
- Checked all routes perform either via Ajax, or standard POST.

## 0.3.0 - 2015-12-31

- Added edit/delete (trashing) for users. Anonymous users can't do either.
- Added new set of templates for different comment statuses, allowing different templates for pending, approved, trashed and spam comments.
- Cleaned up comments UI to include dropdown options. Better user-handling.
- Alter Ajax example, ajax for voting, flagging, editing, deleting.
- Fixed, users could vote on their own comments.
- Fixed, remove voting arrows when unable to vote.
- Added `canVote`, `canUpVote`, `canDownVote` variables for better handling in templates. Checks are also done server-side.
- Some serious spelling mistakes for voting functions :)
- Added back `voteCount`.

## 0.2.1 - 2015-12-31

- Seperated plugin settings to its own 'real' tab. Bad UX being directed off to plugin settings, then being redirected to plugins screen.
- Added Permissions to control elements (and element types) comments are allowed to be made on. More convenient/centralised that custom field for each element.

## 0.2.0 - 2015-12-31

- Preserve comment formatting. Changed comment field to textarea in templates.
- Comments have access to objects for [flags](https://github.com/verbb/comments/wiki/Comment-ElementType#flags) and [votes](https://github.com/verbb/comments/wiki/Comment-ElementType#votes). Removed flagCount and votesCount in favour of using `length` Twig filter.

## 0.1.2 - 2015-12-31

- Added support for comments to be made on any element type (entries, users, assets, etc).

## 0.1.1 - 2015-12-31

- Added support for voting on comments.
- Added support for flagging inappropriate comments.
- After set amount of flags made against a comment, the 'isFlagged' property on the comment is true. Configurable through settings.

## 0.1.0 - 2015-12-31

- Initial beta release.
