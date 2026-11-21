# Changelog

## Unreleased

Comments 4 is a major redevelopment with breaking changes to templates, settings, GraphQL and PHP APIs, and it draws every thread with JavaScript. Read the [upgrade guide](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3) before updating.

### Added
- Add rich authoring through Editor, with an **Authoring Mode** setting that's rich on new installs. Sites upgrading from Comments 3 keep plain authoring, which writes in Comments' own lightweight editor, laid out the same, and saves plain text. Editor only loads once a visitor starts writing, opens a reply or edits a comment, and plain authoring never loads it. In both modes the editor keeps an unsent draft in the browser and shows **Maximum Comment Length** as a character limit. With rich authoring, commenters can format what they write, and signed-in commenters can mention people and attach images and files, with **Allow Mentions**, **Allow Attachments**, **Attachment Volume**, **Attachment Subfolder**, **Allowed File Types** and **Maximum File Size** settings. **Attachment Subfolder** can use the comment's properties in Twig, such as `comments/{owner.section.handle}`. Moderators edit comments in the control panel in the same editor, and a plain comment becomes rich there, as it does for its author, once its text is changed while rich authoring is on.
- Add new spam protection through Protect: a randomised honeypot, a minimum form time, single-use forms, rate limits, a choice of Cloudflare Turnstile, reCAPTCHA v3, hCaptcha or Friendly Captcha challenges, and a choice of Akismet, CleanTalk or OOPSpam to classify spam, alongside the keyword filters. It applies to the site's comment form, the data API, the Comments 3 form actions and GraphQL. Akismet can also learn from moderators' corrections.
- Add **Maximum Links**, which holds a comment with more links than you allow for review, and **Blocked Email Domains**, which refuses guests' comments from email addresses at the domains you list and their subdomains. ([Anti-Spam](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/anti-spam#content-rules))
- Add rules to the keyword filters: `[match: …]` combines words and phrases with `AND`, `OR` and parentheses, such as `[match: (viagra OR casino) AND cheap]`, and `[ip: …]` matches the visitor's IP address against addresses, ranges and CIDR blocks, such as `[ip: 203.0.113.0/24]`. Plain words keep working as before. A line starting with `# ` is now a note, and saving a filter with a rule that can't be read shows which line is wrong. ([Security](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/security#keyword-filters))
- Add **Delete Spam After**, which permanently deletes spam that hasn't changed for a number of days when Craft collects garbage. ([Anti-Spam](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/anti-spam#clearing-out-spam))
- Add **Hold First Comments**, which holds each person's comments for review until a moderator has approved one of them, for sites that don't moderate every comment. ([Moderation](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/moderation#reviewing-comments-before-they-re-public))
- Add a **Spam Protection** page to the Comments section, and a **Manage spam settings** permission, so people other than admins can change spam settings.
- Moderate in the Comments index, with **Needs review**, **Reported** and **Spam** sources, a **Reason** column saying why each comment needs someone, and a count of what's waiting in the navigation. Bulk actions approve, keep, remove, mark as spam or mark as not spam, and never delete permanently.
- Add decisions to the comment editor's sidebar while a comment waits: **Approve**, **Spam** and **Remove**, or **Keep** and **Remove** for a reported comment, each saving any edits in one step. Removing asks for a reason from a short fixed list, with an optional private note. A notice at the top of the comment says why it's waiting or what it was reported for, and **Preview** shows it in its thread on the site.
- Add a **Commenters** area for moderators, listing everyone who has commented with their counts, and a page for each person with their comments, an **Activity** tab of moderators' notes about them and what moderators have done, and their **Review setting**: Trusted, Normal, Held or Blocked. Trusted commenters skip pre-moderation, while spam checks and keyword filters still apply to them.
- Keep what spam protection found on each comment it checks, so moderators can see which checks passed and what a provider said.
- Add moderation on the public page, where moderators read held and reported comments in full, decide them in place, and see how many wait on that page.
- Add an **Activity** tab to each comment in the editor, after its own fields: everything that's happened to it, newest first, with moderators' notes, which post straight away.
- Add a Comments panel to owner elements' Comments field, with the comments waiting there to approve, mark as spam, keep or remove in place, the latest comments, and how many people follow the discussion.
- Add **Reply** for moderators in the control panel, on a comment's page, in its slideout and in an element's Comments panel. The reply is posted as the moderator and published straight away, with the same notifications as a reply on the site. ([Moderation](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/moderation#replying-to-a-comment)) ([#69](https://github.com/verbb/comments/issues/69))
- Add a **Start Closed** setting to the Comments field, which closes elements without a setting of their own until an editor opens them, for sites where comments are opt-in. A Comments 3 field whose **Default Value** was off has it turned on.
- Add guest subscriptions. Guests can subscribe to a discussion, or ask to be emailed about replies when they comment, after confirming their address.
- Add an unsubscribe link and one-click `List-Unsubscribe` headers to every email about a discussion, for guests and people with an account alike. A link can stop that discussion's emails or all comment emails, and works without signing in or a cookie.
- Add mention emails, with their own system message and a `beforeSendMentionEmail` event, sent once the comment is approved.
- Add a `Notifications::EVENT_AFTER_NOTIFY` event, fired after a notification's emails and naming everyone it was for, for telling people through another channel as well, such as an in-app inbox. Cancelling an email through its `beforeSend…Email` event and listening here replaces the email with your own delivery.
- Add deep links that reach a comment wherever it is: past the first page, below a collapsed reply or deeper than replies are shown. A link to `#comment-123` loads its thread straight away and scrolls to it, whatever the load mode, and `Comment::getPermalink()` gives one.
- Add a **Load Comments** setting (`loadMode`) and a `loadMode` option on `craft.comments.render()`, choosing whether a thread's comments load as a visitor scrolls near them, which is the default, when they press **Show comments**, or with the page. A link to a comment always loads its thread straight away. ([Rendering Comments](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/rendering-comments#choosing-when-comments-load))
- Add a **CSS Layer** setting (`useCssLayers`), which wraps the bundled stylesheet in `@layer comments` so a site whose own CSS uses cascade layers, such as Tailwind's, can place Comments below its utilities. ([Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming#sites-that-use-cascade-layers))
- Add a global `Comments` object to the browser, with `mount()` and `mountThread()`, which return promises because the renderer may still be loading, `destroy()`, `get()`, `configure()` and `setTheme()`, and `comments:*` browser events such as `comments:comment-created`, and `comments:editor-opened` once a reply or edit form is ready to use. ([#320](https://github.com/verbb/comments/issues/320))
- Add a cancelable `comments:before-submit` browser event for checking a comment before it's posted, and `showFormErrors()` and `clearFormErrors()` on each thread's controller to show errors the same way as the server's. ([Validating Before Posting](https://docs.verbb.io/comments/browser/validating-before-posting))
- Add support for bundling the comments script into a site's own build through `@verbb/comments-browser` and its TypeScript types, a `data-comments-auto-mount="false"` attribute for starting threads yourself, and a guard so a page that loads the script twice only runs one copy. ([Loading Comment Assets](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/loading-comment-assets#bundling-the-script-into-your-own-build))
- Add a JSON data API under `comments/api/` for the comments script and a site's own clients. Its public reads (`thread`, `replies`, `comment` and `structured-data`) never look at who's asking, start a session or set a cookie, so a CDN, Blitz or Craft's data cache can hold them. Everything personal, such as each visitor's permissions and votes, an author's own comment while it waits for approval and a moderator's held and reported comments, comes from a private `viewer` read that's never cached, and a guest with no session gets a light answer from it that starts nothing. Pages continue from signed cursors, page sizes and depth are limited the same for everyone, writes take an operation ID so a repeated request never posts twice, and every response carries its API version, with errors given stable codes. Threads can be asked for by an element's URI as well as its ID.
- Add front ends on other domains to the data API, listed under **Allowed Origins** (`allowedOrigins`) on a new **Other Sites** settings page. Any site can read public comments. A sibling subdomain listed with **Shared Cookies** signs members in through Craft's own session, guests on a listed site post through a short-lived guest session rather than a cookie, and a site listed with **Member Tokens** signs its members in with 15-minute tokens that its own server requests, signed with a **Member Token Key** (`memberTokenKey`, with `memberTokenPreviousKey` for changing it without downtime). A token only works from the site it was requested for, can't moderate unless that site has **Moderation** turned on, and stops working when it's revoked through `comments/api/revoke-member-tokens` or `craft comments/member-tokens/revoke`, or when its member is suspended or changes their password. A listed site can also have its own Turnstile or reCAPTCHA keys. In `config/comments.php`, an origin given as a plain string, as Formie's `allowedOrigins` takes it, is read as that origin with every switch off.
- Add a `session` read to the data API, which says who's signed in and gives the CSRF token, so a guest's first vote, report or follow works before they've opened the form. The data API's `comment` read and `commentsComment` in GraphQL also find a comment by the number in its link (`#comment-123`), and say which element and site it's on, and `viewer` takes those numbers alongside UIDs.
- Add `comments/api/mentions` and `comments/api/upload`, which Editor's mention search and uploads now go through, so they work under the same sign-in as the rest of the data API, including for members on other domains.
- Refuse SVG, HTML, XML and script uploads whatever **Allowed File Types** lists, along with any file whose contents don't match its extension, and keep only what draws an image, removing its metadata, including where a photo was taken, before it's stored. **Attachment Volume** warns when uploads would be served from the site's own address.
- Accept HEIC and AVIF images when the server has PHP's Imagick extension, which removes their metadata, including where a photo was taken, before they're stored. Without it they're refused with a message saying why, and TIFF images are always refused. The **Allowed File Types** setting says which applies on your server.
- Add `commentsThread`, `commentsReplies`, `commentsComment` and `commentsViewer` GraphQL queries returning the same data as the data API, and a `commentsData` field on comments. GraphQL mutations gain an `operationId` argument for safe retries, `uid` as well as `id`, `parentUid` and `notifyReplies` on `createComment`, `vote` on `voteComment` and `withdraw` on `flagComment` to set a vote or report rather than toggle it, `following` and `email` on `subscribeComment`, and `note`, `keep` and `hold` on `moderateComment`. Their errors carry the data API's error code in `extensions`.
- Add `craft.comments.structuredData()`, which outputs schema.org JSON-LD for an element's approved comments so search engines can read them without running scripts. Commenters' names are left out unless `includeAuthors` is set, and an `id` option attaches the comments to the node an SEO plugin already outputs.
- Add `Api::EVENT_DEFINE_COMMENT_DATA` and `Api::EVENT_DEFINE_VIEWER_DATA` events for adding a site's own public data to each comment and private data to the viewer's, and a `Comments::EVENT_DEFINE_BADGES` event for adding badges such as “Verified buyer” beside a commenter's name. ([#155](https://github.com/verbb/comments/issues/155))
- Add a new default thread design. The editor comes first, as a line beside your avatar that opens when you click into it and says who you're posting as, and replies open in place under their comment with who they're to, Cancel and **Reply**. Guests can sign in or comment as a guest, giving their name and email at the top of the editor. Actions are quiet, with counts hidden at zero and the rest in a menu, people without a photo get their initials on a colour, short public field values such as a location sit after a comment's time, and tapping the time shows the full date.
- Add **The Comment Form** and **Answers on Comments** settings (`formFields`) under **Comment Fields**, each with a live preview, for the custom fields people fill in on the site's comment form and where their answers show. Each field can be asked for or not, and an answer shows beside the date, under the comment's text, or not at all, which keeps it private to moderators in the control panel and to the comment's author when they edit it. Fields take the widths set in the field layout. Fields placed before the **Comment** element in the field layout, such as a rating, are asked for above the comment box, and the rest below it, with the box and its toolbar outlined together. Fields can be left off replies, field layout conditions decide who's asked for each one, and each comment keeps the fields it was posted with for later edits. A value is only shown if it was entered while its field was set to show, so showing a field that wasn't shown never publishes what was entered privately. Plain text, number, email, link, date, dropdown, multi-select, radio buttons, checkboxes, lightswitch and Assets fields are supported, and the settings page says why any other field can't be used.
- Add `comment.shownFields` for templates, which gives the values a comment shows, with their labels and formatted text, and never one that isn't shown.
- Add `FormFields::EVENT_REGISTER_FORM_FIELD_TYPES`, for a plugin or module to describe how its own field type is filled in on the site's comment form and shown on a comment.
- Add uploads for Assets fields on the comment form through `comments/api/upload` with the field's handle, honouring the field's file kinds and limits and **Maximum Attachment Size** under the same rules as attachments. Guests are only offered Assets fields with the new **Guests Can Upload Files** setting (`allowGuestFieldUploads`), which is off by default, and then each upload is checked like a comment and limited per address. Guests only attach their own uploads, and uploads that are never posted are removed after a day, or two hours for a guest's. The settings page warns when a collected field's files are stored where anyone can reach them.
- Add an **Interaction Mode** setting with likes, upvotes and downvotes, or neither, and a Top sort ranked by score.
- Add rate limits for votes, reports and guest subscriptions, per user for signed-in visitors and per network for guests.
- Add an **Author Edit Window** setting that limits how long authors can edit or delete their own comments, and an “Edited” label on comments changed after posting.
- Add configurable **Author** and **Staff** badges, with staff chosen by user group.
- Add **Report this comment**, with a configurable list of **Report Reasons** and their explanations. The dialog quotes the comment, has no default reason and an optional box for anything else.
- Add a button to collapse a comment's replies, a confirmation before authors delete a comment, and remembering a returning guest's name and email.
- Add light, dark and automatic themes with a **Theme** setting, where “None” loads only the base styles the thread needs to work. Each theme is drawn from documented `--comments-*` CSS custom properties that a site's own stylesheet overrides in every theme, and icons keep their size without the bundled CSS. ([Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming))
- Add a Closed option to the Comments field, which stops new comments on an element while leaving existing ones visible.
- Add a `compatibilityMode` setting, on by default, which keeps Comments 3's Twig helpers, comment methods, structure and `trashed` query parameters, GraphQL arguments and fields, `comments/comments/trash` action, console commands, vote, flag and subscribe events and browser event names working after an upgrade, each with a deprecation warning. Comments 3's GraphQL clients can still post without spam protection evidence, checked by everything except replay protection, and their `userId` and `commentDate` arguments are accepted and ignored. ([Upgrading from Comments 3](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#before-disabling-compatibility-mode))
- Add a **View commenters’ email addresses** GraphQL schema permission, which gives a private schema the `email` field and argument. Upgrading gives it to every private schema that could view comments. Public schemas never have them.
- Add `Interactions::EVENT_BEFORE_VOTE` and `EVENT_AFTER_VOTE`, `Reports::EVENT_BEFORE_REPORT`, `EVENT_AFTER_REPORT` and `EVENT_AFTER_WITHDRAW_REPORT`, and `Subscriptions::EVENT_AFTER_CHANGE_SUBSCRIPTION`, which replace Comments 3's vote, flag and subscribe events, and can stop a vote or a report. ([Events](https://verbb.io/craft-plugins/comments/docs/v4/developers/events#vote-report-and-subscription-events))
- Add a guest confirmation system message.
- Add `contentFormat`, `contentHtml`, `contentDocument`, `editedAt`, `moderationState`, `rootId` and `depth` to GraphQL comments, a `commentConversation` query, `reason` and `details` on `flagComment`, a `protection` argument on `createComment` and `saveComment`, and a `moderateComment` mutation behind a new schema permission.
- Add `author` and `viewer` to GraphQL comments, giving the name, avatar and badges the thread shows without needing access to users, and what the person asking can do with each comment and how they voted. `commentConversation` also gives the interaction mode, character limit, guest policy, report reasons and what the person asking can do there. ([GraphQL](https://verbb.io/craft-plugins/comments/docs/v4/graphql/queries))
- Add `hasVoted()`, `hasUpvoted()` and `hasDownvoted()` to comments, saying whether the current visitor has voted on one and which way. ([#250](https://github.com/verbb/comments/issues/250))
- Add `craft.comments.getSummary()` and `craft.comments.getConfig()` Twig functions, and `conversationId`, `identityId`, `moderationState`, `rootId`, `parentId` and `hasOpenReports` query params.
- Add `comments/diagnostics/scan` and `comments/diagnostics/rebuild-counts` console commands.
- Add an Upgrade Report settings page, shown after upgrading if there's anything to report, including settings that were in use and no longer exist.
- Add a Restore action to the Comments index, for users who can trash comments, as trashed comments couldn't be restored.

### Changed
- The spam settings, including the keyword filters and provider keys, move from **Settings** to their own **Spam Protection** page and are now saved in each environment's database rather than project config, so they can be changed on production with admin changes turned off. The upgrade copies each environment's values from its project config, and `config/comments.php` still overrides them. ([Upgrading from Comments 3](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#spam-settings))
- Replace the `recaptchaEnabled` setting with `challengeProvider`. A `recaptchaEnabled` in `config/comments.php` keeps working, with a deprecation warning.
- **Hide After Reports** (`flaggedCommentLimit`) and **Hide After Downvotes** (`downvoteCommentLimit`) now fold a comment away with a **Show comment** button, rather than hiding its text, and leave it published, so reported comments still wait for a moderator. With **Show Author Score**, they're off on new installs, and sites upgrading from Comments 3 keep their values, or Comments 3's defaults if they never changed them.
- Guests' addresses from Comments 3 count as confirmed when upgrading, so their reply emails carry on, unless the address belongs to a user. New guests confirm theirs before they're emailed.
- GIF search now runs in each visitor's browser, as GIPHY's API terms require, rather than through the site, so the **GIPHY API Key** is included in the page for anyone who can comment. The picker works with plain and rich authoring, where it's a GIF button in Editor's toolbar, shows “Powered by GIPHY”, keeps each GIF's size so the thread doesn't jump, and loads only when opened. Posted GIFs fit a square set by the theme's `--comments-gif-max-size` token, 15rem by default. Commenters can change or remove a GIF when editing, the data API reads and sets GIFs, and moderators can remove one from the control panel. See [GIFs from GIPHY](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/rich-authoring#gifs-from-giphy).
- **Use Gravatar** now only looks up signed-in users and guests who've confirmed their email, after a user's own photo, and the site fetches each image in the queue and serves it itself, so visitors never contact Gravatar and an address's hash is never in the page. Images are G-rated and SHA-256 hashed. See [Gravatar](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/security#gravatar).
- `Comment::getParent()` (`comment.parent`) now returns the parent only when it's approved, and `getReplies()`, `children` and `getDescendants()` only approved replies, as Comments 3's `children` did, so a template walking a thread from approved comments never shows a held one. Use `getParentComment()`, or `status(null)` on `getReplies()`, for every status. ([Upgrading from Comments 3](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#php-integrations))
- Comments now requires Craft 5.10 or greater, and Verbb Base 3.0.20 or greater. It also requires `verbb/editor` and `verbb/protect`, which Composer installs with it. PHP 8.2 is unchanged.
- Comments are now drawn in the browser by the comments script, from the data API, rather than by Twig templates. `craft.comments.render()` outputs only a light mount point with the comment count, so every page with comments can be cached by anyone, and the script fetches the thread as a visitor nears it. Visitors need JavaScript to read and post comments, and `craft.comments.structuredData()` puts approved comments in the page for search engines. Customise the thread with themes and CSS custom properties, the stable `comments__*` class names, `themeConfig` for buttons and inputs, browser and PHP events, and part overrides. ([Theming](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/theming))
- Replace the public CSS and JavaScript, including their class names and script API. Anything written against the `cc-*` classes, `data-role` attributes or `Comments.Instance` needs updating.
- The site's comment form, the data API and GraphQL now only accept custom fields the site's form asks for, under **The Comment Form**, and only from people the field layout's conditions show them to, so set any your forms use. Required fields that aren't offered to someone no longer block their comment. Sites upgrading from Comments 3 start with no field asked for, and the Upgrade Report says so.
- GraphQL schemas that can't moderate comments, and requests by someone who can't, now only read the custom fields a comment shows publicly, and get null for the rest, including relation fields. They can't search comments by custom fields, order them by anything but their own columns, filter them by related elements while the Comment layout has a relation field, or find elements related to a comment.
- The data API and the Comments 3 form actions now only answer site requests, not requests through the control panel's URL.
- The Comments 3 form actions `comments/comments/save`, `vote`, `flag` and `subscribe` now follow the data API's rules. Comments can only be posted on a live element that isn't a comment, with comments turned on, replies only to an approved comment on the same element, and edits only by the comment's author within the edit window. Blocked commenters can't follow a discussion or a comment's replies, and only approved comments' replies can be followed. A refused save answers JSON with `success: false` and `errors`, or renders the page again with `errors` keyed `comment` and by field and a `comment` of plain values holding only what was posted, no longer a comment element, rather than failing with an error page. ([Upgrading from Comments 3](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#custom-forms))
- The Comments 3 form actions' JSON answers no longer include the vote, flag and subscribe models, and redirects after a plain form post only follow the referring page when it's on the site's own address, going to the page posted to or the home page otherwise.
- Mention suggestions now only offer people with an approved comment on the thread and the element's author, rather than everyone who has commented there and the moderator group, so moderators are only offered once they've commented publicly. Someone a comment already mentions stays mentionable when it's edited.
- Custom fields on the Comment field layout can no longer use handles Comments uses itself, such as `comment`, `email`, `name`, `ownerId`, `parentUid`, `notifyReplies` and `operationId`. A field that already has one can't be collected on the site until its handle changes.
- Custom forms need updating for the new protection fields: pass the element and site to `craft.comments.protect()`, and remove any old `__UAHASH`, `__JSCHK`, `__HPP` and `__TIME` fields.
- `craft.comments.render()` now takes the element or its ID, and the options `siteId`, `sort`, `limit`, `loadMode`, `themeConfig`, `fields` and `nonce` as its second argument, ignoring anything else. A draft or revision shows the comments of the element it belongs to. It outputs nothing for an element that isn't live. It no longer sets properties on a comment query, and its third argument is ignored.
- Comments now load in the browser as a visitor scrolls near them, rather than with the page, so they aren't in its initial HTML for search engines that don't run scripts. Set **Load Comments** to **With the page** to keep them there. The Upgrade Report mentions this after upgrading.
- Guests are now only emailed once they've confirmed their address, including replies to their comments.
- The Trashed comment status is now **Removed**, separate from Craft's trash. `Comment::STATUS_TRASHED` is now `Comment::STATUS_REMOVED`, so update any templates or queries using `trashed`.
- **Close Comments Globally** now keeps existing comments visible, and turning comments off for an element now hides them.
- Blocked commenters can no longer vote, report or follow a discussion, as well as not posting in it, and closed conversations no longer take votes, though comments in them can still be reported and followed. This applies to the site, the data API, GraphQL and the Comments 3 form actions.
- GraphQL comment mutations are now refused over GET, and on a public schema need the page's CSRF token when the request carries a Craft session or a guest's cookie, so another site can't make a visitor's browser post, vote, report, follow or delete for them. Holding or approving a removed comment through `moderateComment` now needs the same permission as restoring it.
- GraphQL public schemas now only return published comments, whatever arguments are supplied, including through the `children` and `parent` fields. `saveComment` can now only change a comment's content, and only for its author. `createComment` and `saveComment` now need protection evidence while spam checks are on.
- Upgrading now runs in small batches that can resume after a timeout or failure, and verifies the migrated data before removing anything, stopping if it doesn't match. Comments 3 votes, flags, subscriptions and structure are kept in `comments_v3_*` tables, without IP addresses, and can be dropped afterwards.
- Threads no longer use a Craft structure. Use `depth`, `rootId`, `parentId` and `getReplies()` in place of `level`, `getAncestors()` and similar.
- Replace `allowVoting` with `interactionMode`. Upgraded sites with voting on keep upvotes and downvotes, and new installs default to likes.
- Staff notifications leave out whoever caused them, so moderators aren't emailed about their own comments, edits or reports.
- `beforeSendAdminEmail` and `beforeSendFlagEmail` now give the recipient as a user in `$event->user`, rather than an array. An address that doesn't belong to an account is a user without an ID.
- Consolidate the notification settings into `participantNotifications`, `staffNotifications`, `subscriptionTypes` and `subscriptionDefault`, and rename `notificationAdmins` (**Comment Administrators**) to `notificationRecipients` (**Notification Recipients**). Existing settings are translated when loaded, and removed the next time settings are saved.
- `useQueueForNotifications` now defaults to on, so make sure your site's queue is running.
- Replace `getVotes()`, `getFlags()` and `getSubscribe()` on the plugin class with `getInteractions()`, `getReports()` and `getSubscriptions()`, and `getProtect()` with `getProtection()`.
- Moderators changing a comment's status no longer saves its element, so `Comment::EVENT_AFTER_SAVE` doesn't fire for that. Use the new `Moderation::EVENT_AFTER_CHANGE_STATE` event instead.
- Call flags reports throughout. A comment now counts as reported as soon as it has an open report.
- Grant the new `comments-view` and `comments-moderate` permissions to users and user groups who held the matching Comments 3 permissions. Plugin access no longer reaches comments without `comments-view`.
- Client IP addresses now come from Protect, which only trusts forwarded headers from proxies listed in Craft's `trustedHosts`. A site behind a proxy or CDN needs this set, or every visitor shares one address. ([Security](https://verbb.io/craft-plugins/comments/docs/v4/feature-tour/security))
- Manage comments through Craft's native element index and editor, including slideouts, with moderation and element type sources, and date, report and vote columns. The editor's sidebar lets you move a comment and its replies to another element, change its author, and links its commenter's page. Moderators without `comments-edit` see a comment read-only.
- Commenters' email addresses are now only shown to admins. Other users no longer see the Email column in the Comments index, or can search, filter or sort comments by address. GraphQL's `orderBy` can't sort by address, IP address, browser or protection record either, except for admins and private schemas allowed to view addresses.
- Replace the sidebar on owner elements with a Comments field you add to a field layout, with options to follow the source setting, open, close or turn off comments. It also lists the element's waiting, reported and latest comments, which its **Show Comments Summary** setting can turn off.
- A Comments field switched off in Comments 3 is now read as Closed, which is what Comments 3 did with it, so the element's comments stay visible and new ones are stopped.
- Reorganise settings into General Settings, Authoring, Comment Fields, Commenting, Interactions, Notifications, Moderation, Spam, Appearance, and CSS & JavaScript. **Commenting** holds where comments are allowed, guest settings and limits, and reporting settings are now under **Interactions**.
- Bundled CSS and JavaScript are now published by Craft as asset bundles, including on Craft Cloud. Settings are now labelled **Load Bundled CSS** and **Load Bundled JavaScript**, and turning off **Load Bundled JavaScript** keeps rich authoring working for a site that bundles the script itself.
- Errors are now shown next to the field they're about, a new comment now appears at the top unless sorted oldest first, and comment headings now count the whole discussion.
- Marking a comment as not spam now returns it to Pending, so publishing it is a separate decision.

### Fixed
- Comments deleted by their authors are no longer returned as approved. They have a new `author-removed` status.
- Fix comments, guest names, report details and moderators' notes and reasons with emoji failing to save on databases that haven't been converted to `utf8mb4`. Comments checks each of its own tables, which can differ from Craft's, and stores emoji as shortcodes in a table that can't hold them. They're shown as emoji everywhere, as are emoji from Comments 2. ([Upgrading from Comments 3](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#running-the-upgrade))
- Fix keyword filters also matching a comment's status and format, ignoring words with accented characters, and matching case-sensitively in exact mode.
- Fix Maximum Comment Length counting bytes and refusing a comment of exactly that length.
- Fix the time since a comment was posted, such as “3 hours ago”, always being in English, whatever the site's language. ([#330](https://github.com/verbb/comments/issues/330))
- Fix vote and flag buttons being shown to guests when they aren't allowed to use them.
- Fix comments on auto-closed elements being accepted through the API.
- Fix the dashboard widget showing nothing until its settings were saved, and hiding flagged comments unless asked to show only those.
- Fixed a high-severity broken access control vulnerability.
- Fixed a high-severity mass assignment vulnerability.
- Fixed an information disclosure vulnerability.
- Fixed a moderate-severity information disclosure vulnerability.
- Fixed a moderation bypass vulnerability.
- Fixed an improper input validation vulnerability.
- Fix approving, holding and re-approving a comment resending every notification, notifications being sent for comments removed before the queue job ran, and people receiving more than one email for the same comment. Each person now gets one email per comment.
- Fix failed notification emails leaving the queue job marked as successful. All recipients are still tried, then the job fails so it shows up in the queue manager.
- Fix notification emails having no plain text part.
- Fix the Dutch moderator and approval emails not using their translations.
- Fix a removed moderator group exempting every signed-in user from moderation.
- Fix votes and flags being accepted on unpublished comments.
- Fix authors with no full name showing a blank name.
- Fix an error when commenting on a user while specific user groups are selected in the permissions settings.
- Fix GraphQL `createComment` being registered under the name `saveComment`.
- Fix GraphQL `voteComment` ignoring its `downvote` and `siteId` arguments, `flagComment` ignoring `siteId`, the `user` field on flags, and `createComment` keeping a name and email for signed-in users.
- Fix GraphQL schemas with only the save or delete comment scopes having no mutations available.
- Fix finding flagged or reported comments erroring on PostgreSQL, which also broke the Comments field on entry pages.
- Fix comments failing to post from browsers that send a long user agent, such as Instagram's in-app browser, and very long guest names or comments failing with an error instead of a message.

### Removed
- Remove the thread's Twig templates and the **Template Folder Override** setting (`templateFolderOverride`), as threads are drawn with JavaScript. A `templateFolderOverride` left in `config/comments.php` is ignored with a deprecation warning. The Upgrade Report lists any thread template files a site overrode, from the folder the setting named or `templates/_comments`, with a link to the upgrade guide. ([Upgrading from Comments 3](https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#template-overrides))
- Remove reading and posting comments without JavaScript in the thread `craft.comments.render()` outputs, which visitors without it see as the comment count and a line asking them to turn it on. A site's own forms can still post to the Comments 3 form actions without JavaScript.
- Remove the `defaultQueryStatus`, `hideVotingForThreshold`, `showCustomFieldNames`, `showCustomFieldInstructions`, `assetBaseUrl`, `assetBasePath`, `indexSidebarLimit`, `indexSidebarGroup` and `indexSidebarIndividualElements` settings. Public forms always show field labels.
- Remove `Comment::can()` and `Comment::canTrash()`, which compatibility mode keeps.
- Remove the `_special` templates, the `commentsInclude()` and `commentsSiteInclude()` Twig functions, and `craft.comments.renderCss()`, `renderJs()` and `getJsVariables()`.
- Remove the `comments/comments/trash`, `get-js-variables`, `giphy-search` (GIPHY's terms require visitors' browsers to search GIPHY directly), `edit-comment`, `save-comment`, `edit-comment-slideout` and `delete-comment` controller actions, and the `html` key from JSON responses to `comments/comments/save`. Use the data API's `comments/api/delete` and the other `comments/api/*` actions instead. `comments/comments/save`, `vote`, `flag` and `subscribe` stay for a site's own forms, answering JSON when asked and otherwise redirecting back with Craft's own notice and error messages.
- Remove `email` from the GraphQL `CommentInterface` and the `email` argument from the `comments` query, and `sessionId` and `lastIp` from GraphQL votes and reports. A comment's `flags` count is now only on schemas that can moderate, for someone who can moderate, and public schemas no longer accept `identityId` as an argument or sort.
- Remove the `userId`, `commentDate` and `newParentId` arguments from GraphQL comment mutations, and structure arguments and fields from GraphQL comments, in favour of `parentId`, `rootId` and `depth`, with `parentId` also narrowing the `comments` query to the direct replies of a comment.
- Remove the `comments/base/publish-assets`, `comments/base/resave-structure` and `comments/base/set-structure` console commands, and the **Publish now** settings button.
- Remove the `FlagEvent`, `VoteEvent` and `SubscribeEvent` classes and the `FlagNotFoundException`, `VoteNotFoundException` and `SubscribeNotFoundException` classes, which nothing used.
- Remove `RenderCache::getCommentIds()` and `setCommentIds()`, which nothing used.
- Remove `renderComment()`, `getRenderVariables()`, `getRenderJsVariables()`, `publishFrontEndAssets()` and `getComponentTemplatePath()` from the comments service, as threads are drawn in the browser.
- Remove the `comments/new` control panel routes, which never opened a form. Moderators reply from a comment's page instead, and new discussions start on the site.

## 3.0.23 - 2026-11-01

### Fixed
- Fixed a high-severity missing authorization vulnerability.
- Fix an error when saving or trashing a comment on the front-end while “Reply with GIFs” (GIPHY) support is enabled.
- Fix an error when rendering comments on assets, categories or users while “Auto-close Comments” is enabled.
- Fix an error when commenting on an asset while specific asset volumes are selected in the permissions settings.
- Fix a database error when filtering comments by more than one of owner type, owner section and owner entry type.
- Fix the Entry Section, Entry Type and Owner Type condition rules checking the comment instead of its owner when matching a comment.

## 3.0.22 - 2026-10-15

### Changed
- Updated Craft API usage to avoid deprecated calls.

## 3.0.21 - 2026-10-07

### Changed
- Require Verbb Base 3.0.20 or later so shared control-panel layouts use the current asset bundle namespace. ([verbb-base#3](https://github.com/verbb/verbb-base/issues/3))

## 3.0.20 - 2026-10-02

### Changed
- Updated the required version of `verbb/base` to 3.0.19.

## 3.0.19 - 2026-09-30

### Changed
- Route plugin settings through the plugin’s authorized settings controller.

### Fixed
- Fix template settings pages returning 404 errors and the default comment textarea not rendering when the field layout was not initialized. ([#329](https://github.com/verbb/comments/issues/329))

## 3.0.18 - 2026-09-23

### Fixed
- Render comment notification subjects and bodies in Twig sandbox.
- Fix saving one settings page or generating the comment structure resetting unrelated plugin settings.

## 3.0.17 - 2026-09-14

### Fixed
- Fix the front-end asset publishing button after settings normalization.

## 3.0.16 - 2026-09-13

### Changed
- Normalize plugin settings.

## 3.0.15 - 2026-06-20

### Added
- Add “Reply with GIFs” support, allowing commenters to search GIPHY and attach a GIF to a comment or reply.
- Add `giphyEnabled`, `giphyApiKey`, `giphyRating` and `giphyLimit` plugin settings.
- Add `gifUrl` field to the GraphQL Comment interface.
- Add per-user reputation score (net votes across a user’s approved comments) shown next to their name, with a `showAuthorScore` setting to toggle it.
- Add `assetBaseUrl` setting to serve the default front-end CSS/JS from a custom base URL (e.g. a CDN/file server) instead of publishing to `cpresources` — avoids per-server runtime publishing in multi-server setups.
- Add `assetBasePath` setting + a way to copy the front-end CSS/JS there: a `comments/base/publish-assets` console command, a “Publish now” button in the settings, and auto-publish on install.

### Changed
- Cache comment vote lookups per request (busting on vote save/delete) to avoid duplicate queries when rendering threads.

## 3.0.14 - 2026-04-29

### Fixed
- Fix an error when saving plugin settings and structure information not persisting.
- Fix comment permissions for entry types without a section (e.g. Matrix).

## 3.0.13 - 2026-03-03

### Fixed
- Fix an error when saving plugin settings and structure information not persisting.
- Fix an error when saving plugin setting in Craft 5.9+.

## 3.0.12 - 2026-02-07

### Fixed
- Fix return types for `hasDownVoted` and `hasUpVoted` functions.

## 3.0.11 - 2025-09-16

### Added
- Add Entry Type, Section and Element Type conditions for filtering comments in the control panel.
- Add the ability to query Comment elements via the `ownerEntryType` and `ownerEntryTypeId`.

### Fixed
- Fix Gravatar user check throwing an error for guests.

## 3.0.10 - 2025-09-02

### Changed
- Improve Gravatar handling.

## 3.0.9 - 2025-08-13

### Fixed
- Fix Feed Me lack of support for custom fields when resolving the owner element.
- Fix nested comment display for comments, when viewing in the control panel.

## 3.0.8 - 2025-07-18

### Changed
- Update English translations.

### Fixed
- Fix lack of handling for database column type for field.
- Fix an error when editing another users’ comment in some instances.

## 3.0.7 - 2025-04-17

### Added
- Add “Comments” sub-nav item in the control panel.

### Changed
- Improve performance of sidebar list of owner elements for large sites when viewing comments in the control panel.
- Custom fields now include a `data-role="comment-field"` attribute on their container. Consider including this in any custom templates of yours for the next major release.
- Validation errors for custom fields now target the `data-role="errors"` attribute of the custom field.

### Fixed
- Fix an error when eager loading owner and user for comments.
- Fix check of GraphQL Authentication plugin.
- Fix an error when eager loading owner and user for comments.
- Fix a JS error for guest comments (when guest comments are disabled).
- Fix deprecated `log()` function.
- Fix GraphQL resolvers to use `ElementCollection` correctly.
- Fix JS to handle whitespace in comment’s HTML.

## 3.0.6 - 2025-03-05

### Added
- Add support for `notificationAdmins` to be a Closure.

### Fixed
- Fix an issue creating comments for long URLs.
- Fix incorrect Unauthorized error caused by checking against random user.

## 3.0.5 - 2025-02-03

### Fixed
- Fix the `isFlagged` element query not working correctly.

## 3.0.4 - 2024-11-12

### Fixed
- Fix an XSS issue for comment excerpts.

## 3.0.3 - 2024-10-20

### Added
- Add French translation. (thanks @scandella).

### Changed
- Change JSON payload for `comment` when creating a comment from the front-end.

### Fixed
- Fix an error when calling `craft.comments.renderJs()`.
- Fix an error where it was possible to change the commenter email/name to an invalid value.

## 3.0.2 - 2024-09-07

### Fixed
- Fix installation bug. (thanks @timkelty).
- Fix translations for emails.

## 3.0.1 - 2024-07-21

### Added
- Add author email to sidebar when editing a comment in the control panel.
- Add “Comments Order By” plugin setting to control the direction of comments.
- Add Craft Teams support for permissions.
- Add JS events for front-end functionality.

### Changed
- Update English translations.
- Update non-English translations.
- Improve reply/edit form cloning.

### Fixed
- Fix an error when viewing comment replies in the control panel.
- Fix a type error for a comment’s string representation.

## 3.0.0 - 2024-05-18

### Added
- Add element conditions to allow comments to be filtered in the control panel by custom sources.

### Changed
- Now requires PHP `8.2.0+`.
- Now requires Craft `5.0.0+`.

### Fixed
- Fix an error when selecting a “Placeholder Avatar” asset.

## 2.0.22 - 2026-06-20

### Added
- Add “Reply with GIFs” support, allowing commenters to search GIPHY and attach a GIF to a comment or reply.
- Add `giphyEnabled`, `giphyApiKey`, `giphyRating` and `giphyLimit` plugin settings.
- Add `gifUrl` field to the GraphQL Comment interface.
- Add per-user reputation score (net votes across a user’s approved comments) shown next to their name, with a `showAuthorScore` setting to toggle it.
- Add `assetBaseUrl` setting to serve the default front-end CSS/JS from a custom base URL (e.g. a CDN/file server) instead of publishing to `cpresources` — avoids per-server runtime publishing in multi-server setups.
- Add `assetBasePath` setting + a way to copy the front-end CSS/JS there: a `comments/base/publish-assets` console command, a “Publish now” button in the settings, and auto-publish on install.

### Changed
- Cache comment vote lookups per request (busting on vote save/delete) to avoid duplicate queries when rendering threads.

## 2.0.21 - 2026-04-29

### Fixed
- Fix an error when saving plugin settings and structure information not persisting.

## 2.0.20 - 2026-02-07

### Fixed
- Fix return types for `hasDownVoted` and `hasUpVoted` functions.

## 2.0.19 - 2025-07-18

### Fixed
- Fix lack of handling for database column type for field.
- Fix an error when editing another users’ comment in some instances.

## 2.0.18 - 2025-04-17

### Changed
- Improve performance of sidebar list of owner elements for large sites when viewing comments in the control panel.

### Fixed
- Fix an error when eager loading owner and user for comments.

## 2.0.17 - 2025-03-05

### Added
- Add support for `notificationAdmins` to be a Closure.

### Fixed
- Fix an issue creating comments for long URLs.
- Fix incorrect Unauthorized error caused by checking against random user.

## 2.0.16 - 2025-02-03

### Fixed
- Fix the `isFlagged` element query not working correctly.

## 2.0.15 - 2024-10-20

### Fixed
- Fix an error when calling `craft.comments.renderJs()`.

## 2.0.14 - 2024-10-03

### Changed
- Change JSON payload for `comment` when creating a comment from the front-end.

### Fixed
- Fix an error where it was possible to change the commenter email/name to an invalid value.

## 2.0.13 - 2024-07-21

### Added
- Add author email to sidebar when editing a comment in the control panel.
- Add “Comments Order By” plugin setting to control the direction of comments.

### Changed
- Update English translations.
- Update non-English translations.

## 2.0.12 - 2024-04-10

### Fixed
- Fix an error when selecting a “Placeholder Avatar” asset.

## 2.0.11 - 2024-03-04

### Fixed
- Fix some email message translation keys.
- Fix order of operations when uninstalling the plugin.
- Fix Emoji support and stripping out some valid special characters for comments.

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
