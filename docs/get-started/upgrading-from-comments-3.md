# Upgrading from Comments 3
Comments 4 changes the way comments are stored, and replaces the Twig templates and browser script that showed them with a thread drawn in the browser from JSON. Your comments, threads, votes, flags and subscriptions move to the new storage in place when you run Craft's migrations, and each comment keeps its element ID, custom field values and dates.

Comments 4 has a compatibility mode, on by default, which keeps many of Comments 3's Twig helpers, comment methods, query parameters, GraphQL arguments and fields, console commands and browser events working, so your site doesn't break the moment it updates. Each one logs a deprecation warning under **Utilities** → **Deprecation Warnings** when it's used, and the changes below say which they are. Once the warnings stop, turn the mode off, as [Turning Off Compatibility Mode](#before-disabling-compatibility-mode) describes.

Comments 4 has a compatibility mode, on by default, so your site doesn't break the moment it updates. Work through this guide in three stages:

1. [Breaking Changes](#breaking-changes) need fixing before you go live, even with compatibility mode on, if your site uses what they describe.
2. Changes listed [Before Disabling Compatibility Mode](#before-disabling-compatibility-mode) keep working while the mode is on, each logging a deprecation warning under **Utilities** → **Deprecation Warnings**. Update them before you turn it off.
3. [Deprecated Changes](#deprecated-changes) keep working with compatibility mode off. Update them as you maintain your site.

[Behaviour Changes](#behaviour-changes) covers what changes by itself, which needs no edits but changes what visitors, editors and moderators see.

A site using `craft.comments.render()` with the default templates and no custom forms has little to do beyond the [Requirements](#requirements), [Notifications Through the Queue](#notifications-through-the-queue) and [Rate Limits Behind a Proxy](#rate-limits-behind-a-proxy). The rest depends on what your site does:

- If you overrode the thread's templates or styled its classes, read [Template Overrides](#template-overrides) and [Your Own JavaScript and CSS](#your-own-javaScript-and-css).
- If you post comments with your own forms, read [Custom Forms](#custom-forms) and [Custom Fields on Public Forms](#custom-fields-on-public-forms).
- If you use GraphQL, read [GraphQL](#graphQL).
- If a module or plugin calls Comments, read [PHP Integrations](#php-integrations).
- If your templates call Comments' Twig functions or pass options to `render()`, they keep working for now, as described under [Twig Functions and Variables](#twig-functions-and-variables).

When you're ready to update, [Running the Upgrade](#running-the-upgrade) covers the backup, the commands and deploying through project config.

## Breaking Changes
These are needed only if your site uses the feature or depends on the old behaviour. Compatibility mode doesn't bring it back, so make these changes before you go live.

### Requirements
Comments 4 requires Craft 5.10 or greater, up from Craft 5.0. It still requires PHP 8.2. Composer installs `verbb/editor` and `verbb/protect` with the plugin, so there's nothing else to add. Update Craft first if you need to.

### Notifications Through the Queue
`useQueueForNotifications` defaults to on, for sites that never saved Comments 3's default, which was off. Emails then wait for Craft's queue, so make sure your queue runs in every environment. A failed email fails its job, so it shows up in the queue manager.

### Rate Limits Behind a Proxy
Votes, reports and guest subscriptions now have their own rate limits, and guests are counted by IP address. A site behind a proxy or CDN needs [`trustedHosts`](docs:feature-tour/security#client-ip-addresses) set, or everyone shares one limit.

### Template Overrides
Comments 4 draws the thread in the browser from JSON, so there are no Twig templates to override. The plugin's front-end templates and the **Template Folder Override** setting (`templateFolderOverride`) have been removed, and a site can no longer change the thread's markup with Twig. If you'd set the folder, the files in it stop being used. Nothing fails, and your comments show the default thread until you move your changes across, so check every page with comments before you go live.

**Comments** → **Settings** → **Upgrade Report** lists the overridden thread templates it finds, in the folder your **Template Folder Override** named or in `templates/_comments`, with a link back to this section. A `templateFolderOverride` left in `config/comments.php` is ignored, and logs a deprecation warning until you remove it.

Every kind of change a template override made has a replacement, and most need far less than a copied template. Work through your changed files, decide what each change did, and move it to the lightest replacement that does the same job:

- Colours, fonts, sizes and spacing become [CSS custom properties](docs:template-guides/theming), and other styling moves from the `cc-*` classes to the stable `comments__*` [class names](docs:template-guides/theming#changing-individual-elements).
- Your design system's button and input classes go in [`themeConfig`](docs:template-guides/theming#using-your-design-systems-classes).
- A hidden avatar, vote button or report link becomes a setting, such as **Show Avatar**, **Interaction Mode** set to **None** or **Allow Reporting** turned off, which also stops the server offering it.
- Changed wording becomes a [static translation](docs:template-guides/text-translations).
- Extra information about the commenter becomes a badge from `Comments::EVENT_DEFINE_BADGES`, or your own data from `Api::EVENT_DEFINE_COMMENT_DATA`, as described in [Events](docs:developers/events#the-defineBadges-event).
- Your own form fields become custom fields the site's form asks for, shown on comments or not, as described under [Custom Fields on Public Forms](#custom-fields-on-public-forms).
- Rebuilt or reordered markup becomes a [part override](docs:template-guides/theming#changing-a-parts-markup) of one part, such as `comment`, `authorName` or `meta`.

[Upgrade Comments 3 Custom Templates](docs:user-guides/upgrade-comments-3-custom-templates) maps each Comments 3 template file to its replacement, with a worked example for each kind of change. The simplest change, styles written against the Comments 3 classes, looks like this, making commenters' names pink:

::: code-group
```css [Comments 3]
.cc-i-author {
    color: #c2185b;
}
```

```css [Comments 4]
.comments .comments__author-name {
    color: #c2185b;
}
```
:::

Check the result by viewing each page with comments, signed out and signed in, and confirming every change you'd made shows. A fully custom thread listed with `craft.comments.fetch()` and posted with your own forms can stay as it is, with the changes under [Custom Forms](#custom-forms), or move to your own interface on `@verbb/comments-core`, as described in [Headless Overview](docs:headless/headless-overview).

### Your Own JavaScript and CSS
The browser script is new, so `new Comments.Instance()`, `Comments.Comment` and the rest of the Comments 3 script are gone. So are the `cc-*` class names and `data-role` attributes in the markup, and the Comments 3 stylesheet. Any styles you've written against them need moving to the `comments__*` class names or the CSS variables, as shown under [Template Overrides](#template-overrides), and any scripts that hook into the thread need redoing with the `Comments` global and the browser events, which the [package docs](https://docs.verbb.io/comments/browser/browser-events) document.

Comments 3 dispatched its events on `document`, which Comments 4's events still reach, as they bubble from each thread. Their names and `detail` have changed:

| Comments 3 | Comments 4 |
| --- | --- |
| `comments:init` | `comments:mounted`, once for each thread |
| `comments:submit` | `comments:comment-created`, with the new comment's `commentUid` and its `moderationState` |
| `comments:reply-submit` | `comments:comment-created`, as for a new comment |
| `comments:edit-submit` | `comments:comment-updated` |
| `comments:reply-open`, `comments:edit-open` | `comments:editor-opened`, with the `mode` and the `commentUid` |
| `comments:reply-close`, `comments:edit-close` | `comments:editor-closed`, with the `reason` |
| `comments:delete` | `comments:comment-deleted` |
| `comments:flag` | `comments:report-changed`, with `reported` |
| `comments:upvote`, `comments:downvote` | `comments:vote-changed`, with the `vote`, which is null when a vote is taken back |
| `comments:subscribe` | `comments:follow-changed`, with `following` |

Comments 3's `detail` held the script's own objects, such as `detail.comment` or `detail.reply`. Comments 4's holds the comment's `commentUid` and, where there is one, the `commentId` from its `#comment-1446` anchor, along with the thread's `controller`.

The plugin's script is `js/comments.js`, which loads the rest of the thread's code as it's needed, and its stylesheet is `css/comments.css`. To include them in your own build rather than loading the plugin's copies, use the npm package `@verbb/comments-browser`, as described in [Bundling the Script Into Your Own Build](docs:template-guides/loading-comment-assets#bundling-the-script-into-your-own-build).

### Custom Fields on Public Forms
Comments 3 took every custom field in the Comment field layout from the site's form. Comments 4 only takes the ones you choose, so every field starts not asked for after upgrading, and the Upgrade Report says so if you have any. Go to **Comments** → **Settings** → **Comment Fields**, turn on the switch beside each one your form uses under **The Comment Form**, and under **Answers on Comments** choose where its answer shows, or **Not shown**. In `config/comments.php`, `formFields` sets the same thing, keyed by each field's UID in the field layout, with its `mode`, `placement` and `replies`, as described under [`formFields`](docs:get-started/configuration#formFields). [Custom Fields](docs:template-guides/custom-fields) explains each choice.

A required field you leave **Off** no longer stops anyone posting. If your forms posted files to an Assets field, they're now uploaded through `comments/api/upload` first, which the JavaScript thread does for you. Submit a test comment with a value for each field, and confirm it's saved on the comment.

### Custom Forms
Comment forms you built for Comments 3 keep working after the upgrade, because Comments' form actions still take plain form posts, without JavaScript. A form that outputs its spam protection with `craft.comments.protect()` keeps working in compatibility mode, as described under [Protecting Your Own Forms](#protecting-your-own-forms), and a reply form's `newParentId` field and error messages keep working whatever the mode, as described under [Reply Forms](#reply-forms). For a new form, post through the data API instead, as described in [Posting from Your Own Code](docs:template-guides/displaying-protecting-comments#posting-from-your-own-code), which also covers editing, deleting, rich comments and uploads.

These need changing now if your forms do them:

- **Writing out the old protection fields.** A form with Comments 3's `__UAHASH`, `__JSCHK`, `__HPP` or `__TIME` fields written into it, or a reCAPTCHA script of its own, is rejected. Remove them, and output the new fields with `craft.comments.protect()`. Clear any cached form HTML when you deploy this, so visitors get the new fields.
- **Reading the comment after a refused post.** When a comment can't be saved, the page is rendered again with a `comment` variable holding only what was posted. It's no longer a comment element, so a template that read its author, parent, replies or owner gets nothing there.
- **Reading JSON answers.** They're smaller than Comments 3's, because the vote, flag and subscribe models they returned no longer exist.

| Action | Comments 3 | Comments 4 |
| --- | --- | --- |
| `save` | `success`, `id`, `comment`, `html`, `notice`, or `success`, `comment` and `errors` from the element | `success`, `id`, `comment` and `notice`, or `success: false` with `errors` keyed `comment` and by field name |
| `vote` | `success`, `vote` | `success`, `type` (`upvote`, `downvote` or `null` when the vote was taken back) |
| `flag` | `success`, `flag`, `notice` | `success`, `notice` |
| `subscribe` | `success`, `subscribe`, `notice` | `success`, `subscribed`, `notice` |
| Any refusal from `vote`, `flag` or `subscribe` | `success: false`, the model and its `errors` | `success: false` with `errors.message` |

These controller actions have gone, along with Comments 3's script that called them:

| Comments 3 | Comments 4 |
| --- | --- |
| `comments/comments/get-js-variables` | None |
| `comments/comments/giphy-search` | None. GIPHY's terms require each visitor's browser to search GIPHY directly, as described under [GIFs from GIPHY](#gIFs-from-giphy) |

These actions now follow the same rules as the rest of Comments. A comment can only be posted on a live element that isn't a comment, with comments turned on, and a reply only to an approved comment on the same element. Only a comment's author can edit it, within **Author Edit Window**. A blocked commenter can't follow a discussion or a comment's replies, guests confirm their address before they're subscribed, and only approved comments can have their replies followed.

Deleting your own comment, editing a comment written with rich authoring, mentions, attachments and moderating on the page aren't available through these actions. They need the JavaScript thread from `craft.comments.render()`, or your own code on the [data API](docs:headless/data-api).

`craft.comments.protect()` takes the element's ID, its site ID (the current site if left out), and for an edit form the ID of the comment being edited, which the form also sends as `commentId`. It outputs a `div` holding the fields Comments' spam protection needs, with a `data-comments-protection` attribute holding its settings. If the element can't take comments, or protection isn't configured correctly, it outputs nothing rather than breaking the page, and posting says what's wrong.

The honeypot field it outputs has to stay hidden from visitors. It's inside an element with the `hidden` attribute and a `protect-honeypot` class, which browsers hide by default. If your site's CSS could show it, such as a `display` rule that overrides `hidden`, add the rule the bundled CSS uses inside a thread:

```css
.protect-honeypot {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip-path: inset(50%) !important;
    white-space: nowrap !important;
}
```

These forms work without any JavaScript until you turn on a challenge provider such as Turnstile, which can't be completed without a script, so leave it off while you rely on forms that post without JavaScript. See [Anti-Spam](docs:feature-tour/anti-spam).

`comments/comments/save`, `vote`, `flag` and `subscribe` still accept plain form posts and answer in JSON when the request asks for it. After a plain post, `save` redirects to the form's `redirect` input. The others return to the page the form was on when that page is on your site, and otherwise to the page posted to or the home page. Their messages are in Craft's standard flash messages, so show them with `craft.app.session.getFlash('notice')` and `craft.app.session.getFlash('error')`.

| Action | What It Does |
| --- | --- |
| `comments/comments/save` | Posts a comment, a reply with `parentId`, or an edit with `commentId` |
| `comments/comments/vote` | Votes on the comment in `commentId`, upwards when `upvote` is set |
| `comments/comments/flag` | Reports the comment in `commentId`, or withdraws the report when it's sent again |
| `comments/comments/subscribe` | Follows or stops following the discussion on the element in `ownerId` and `siteId`, or, for a signed-in visitor, the replies to the comment in `commentId`. A guest gives an `email`, and confirms it before anything is sent |

### GIFs from GIPHY
**Reply with GIFs** and its settings carry over, and comments keep their GIFs. GIF search now runs in each visitor's browser, because GIPHY's API terms forbid sending searches through your server. So your **GIPHY API Key** is included in the page for anyone who can comment, where Comments 3 kept it on the server, and the Upgrade Report says so if GIFs were on. Check it's a key for the web from your [GIPHY developer dashboard](https://developers.giphy.com/dashboard/), and that GIPHY's limit on it, 100 searches an hour until you apply for a production key, suits your site. The picker shows “Powered by GIPHY”, which GIPHY requires and Comments 3 didn't show. See [GIFs from GIPHY](docs:feature-tour/rich-authoring#gIFs-from-giphy).

A form of your own that posts `gifUrl` to `comments/comments/save` keeps working while GIFs are on, as long as the address is one of GIPHY's own. Script that searched through `comments/comments/giphy-search` needs to search GIPHY itself instead, with the key from the [data API's](docs:headless/data-api#gIFs) `viewer`.

### GraphQL
Check your queries and mutations against these changes, then see [GraphQL](docs:graphql/queries) for the full schema. Compatibility mode keeps the arguments and fields described under [GraphQL Arguments and Fields](#graphQL-arguments-and-fields), but not these:

| Comments 3 | Comments 4 |
| --- | --- |
| `email` field on `CommentInterface`, and `email` argument on the `comments` query | Only on a private schema with **View commenters’ email addresses**, which the upgrade gives every private schema that could view comments. A public schema never has them. For registered commenters, use `user` on a schema that can read users |
| `newParentId` argument on `saveComment` | None. A comment can't be moved to another parent |

Public schemas only ever return approved comments, whatever arguments they're given, including through the `children` and `parent` fields. `saveComment` can only be used by a comment's author, and can only change its content. `createComment` and `saveComment` run the same spam checks as public forms, so they take a [`protection` argument](docs:graphql/mutations) while those checks are on. Compatibility mode lets Comments 3's clients post without one, as described under [GraphQL Arguments and Fields](#graphQL-arguments-and-fields), except on a site with Turnstile or reCAPTCHA, whose challenge has to be completed either way.

`subscribeComment` still toggles a subscription and returns the same message as before, “Subscribed to discussion.” or “Unsubscribed from discussion.”, so an app that shows it keeps working. To set the subscription instead of toggling it and get a code back, pass `following`, as described under [the `subscribeComment` mutation](docs:graphql/mutations#the-subscribeComment-mutation).

### PHP Integrations
Modules and plugins calling Comments directly need updating. Comment queries keep working, though threads no longer use a Craft structure, as described under [Comment Structure](#comment-structure).

| Comments 3 | Comments 4 |
| --- | --- |
| `Comments::$plugin->getVotes()` | `Comments::$plugin->getInteractions()` |
| `Comments::$plugin->getFlags()` | `Comments::$plugin->getReports()` |
| `Comments::$plugin->getSubscribe()` | `Comments::$plugin->getSubscriptions()` |
| `Comments::$plugin->getProtect()` | `Comments::$plugin->getProtection()` |
| `Comments::$plugin->getGiphy()->search()` | None. Visitors' browsers search GIPHY directly. `getGiphy()` returns the service that checks GIFs and gives the browser GIPHY's key |
| `Comments::$plugin->getComments()->sendNotificationEmail()` and the other `send…NotificationEmail()` methods | None. Notifications are sent by Comments' queued notification job, and the [`afterNotify` event](docs:developers/events#the-afterNotify-event) names who each was for |
| `FlagNotFoundException`, `VoteNotFoundException`, `SubscribeNotFoundException` | None |
| `Comments::$plugin->getComments()->renderComment()`, `getRenderVariables()`, `getRenderJsVariables()`, `publishFrontEndAssets()` and `getComponentTemplatePath()` | None. Threads are drawn in the browser, and `render()` outputs only the mount point |

Moderators changing a comment's status don't save its element, so `Comment::EVENT_BEFORE_SAVE` and `Comment::EVENT_AFTER_SAVE` no longer fire when a comment is approved or marked as spam from the index or the site. Use `Moderation::EVENT_AFTER_CHANGE_STATE` instead, as described in [Events](docs:developers/events).

The `comments/comments/edit-comment`, `save-comment`, `edit-comment-slideout` and `delete-comment` control panel actions and the `comments/new` routes are gone. Comments are edited through Craft's own element editor.

## Before Disabling Compatibility Mode
The changes in this section can wait until after the upgrade. Compatibility mode, on by default, keeps their Comments 3 behaviour working, and each logs a deprecation warning under **Utilities** → **Deprecation Warnings** when it's used. Update them, and once no more warnings appear, turn the mode off in `config/comments.php`:

```php
return [
    'compatibilityMode' => false,
];
```

Try it on a staging site first. With the mode off, Comments 3's Twig functions and methods are errors, the structure parameters and the `trashed` status return no comments, the GraphQL schema loses its deprecated arguments and fields, `comments/comments/trash` and the `comments/base` commands no longer exist, and threads stop dispatching Comments 3's event names. Clear Craft's caches after changing it, including any cached page HTML, so pages and the GraphQL schema pick up the change.

Once these are done, the [Deprecated Changes](#deprecated-changes) are optional cleanup, and don't need compatibility mode.

### Twig Functions and Variables
These were removed or changed. In compatibility mode, calling them the Comments 3 way still works, with a deprecation warning, and with the mode off it's an error. Remove or update them in your templates. `craft.comments.render()` registers everything itself, and you can turn off the bundled assets under **Comments** → **Settings** → **CSS & JavaScript**.

| Comments 3 | Comments 4 |
| --- | --- |
| `commentsInclude()`, `commentsSiteInclude()` | None. The thread has no templates to include, as described under [Template Overrides](#template-overrides) |
| `craft.comments.renderCss()` | The **Load Bundled CSS** setting |
| `craft.comments.renderJs()`, `craft.comments.getJsVariables()` | The **Load Bundled JavaScript** setting |
| `craft.comments.protect()` | `craft.comments.protect(entry.id, entry.siteId)`, which takes the element's ID and site ID, as shown under [Custom Forms](#custom-forms) |
| `comment.can()`, `comment.canTrash()` | `comment.canEdit()`, `comment.canReply()`, `comment.canVote()`, `comment.canFlag()` |

In compatibility mode, `renderCss()`, `renderJs()` and `commentsInclude()` output nothing, `getJsVariables()` returns an empty list, and `commentsSiteInclude()` includes the site template as Twig's own `include()` does. `comment.can()` and `canTrash()` answer as Comments 3 did, and `craft.comments.protect()` with no element protects the page's own element.

The second argument of `craft.comments.render()` changes meaning as well. It used to set properties on the comment query. It's now a short list of rendering options, described in [`render()` Options](docs:template-guides/rendering-comments#render-options), and anything else in it is ignored. In compatibility mode, Comments 3's `orderBy: 'commentDate asc'` or `'commentDate desc'` is read as `sort: 'oldest'` or `'newest'`, and anything else is logged as ignored.

::: code-group
```twig [Comments 3]
{{ craft.comments.render(entry.id, { limit: 10, orderBy: 'commentDate asc' }) }}
```

```twig [Comments 4]
{{ craft.comments.render(entry, { limit: 10, sort: 'oldest' }) }}
```
:::

### Protecting Your Own Forms
`craft.comments.protect()` now takes the ID and site ID of the element the form comments on. In compatibility mode, `protect()` with no element still protects the page's own element, but give it the element when you update the form, because a form included on another page would otherwise protect that page's element. With the mode off, `protect()` with no element is an error.

::: code-group
```twig [Comments 3]
<form method="post">
    {{ actionInput('comments/comments/save') }}
    {{ csrfInput() }}
    {{ hiddenInput('elementId', entry.id) }}
    {{ hiddenInput('siteId', entry.siteId) }}
    {{ craft.comments.protect() }}
    <textarea name="fields[comment]"></textarea>
    <button type="submit">Post comment</button>
</form>
```

```twig [Comments 4]
<form method="post">
    {{ actionInput('comments/comments/save') }}
    {{ csrfInput() }}
    {{ hiddenInput('elementId', entry.id) }}
    {{ hiddenInput('siteId', entry.siteId) }}
    {{ craft.comments.protect(entry.id, entry.siteId) }}
    <textarea name="fields[comment]"></textarea>
    <button type="submit">Post comment</button>
</form>
```
:::

### Comment Statuses
Comments with the `trashed` status now have the `removed` status, which is a moderation decision and separate from Craft's own trash. In compatibility mode, `.status('trashed')` still finds removed comments, with a deprecation warning. With the mode off it finds nothing, so update any templates or queries that use `trashed`.

::: code-group
```twig [Comments 3]
{% set hidden = craft.comments.fetch().status('trashed').all() %}
```

```twig [Comments 4]
{% set hidden = craft.comments.fetch().status('removed').all() %}
```
:::

### Comment Structure
Threads no longer use a Craft structure. In compatibility mode, Craft's structure parameters on a comment query, such as `level`, `hasDescendants`, `ancestorOf` and `descendantOf`, are answered from each reply's parent and depth, and `comment.level` and `comment.getAncestors()` still work. With the mode off, the parameters return no comments. Use `comment.depth`, `comment.rootId`, `comment.parentId` and `comment.getReplies()` instead.

### GraphQL Arguments and Fields
With compatibility mode off, the GraphQL schema loses these, so update your queries and mutations first.

| Comments 3 | Comments 4 |
| --- | --- |
| `newParentId` argument on `createComment` | `parentId` |
| `userId` and `commentDate` arguments on mutations | None. A comment's author is whoever is making the request, and its date is when it's posted. Compatibility mode accepts and ignores them |
| Structure arguments such as `level`, `ancestorOf`, `descendantOf`, `leaves` and `hasDescendants` on the `comments` query | `rootId` and `parentId`. Compatibility mode answers them from each reply's parent and depth |
| Structure fields on `CommentInterface`, such as `level`, `lft`, `rgt` and `root` | `depth` and `rootId`. Compatibility mode keeps them, with `lft` and `rgt` always null |
| `flags` field on `CommentInterface` | Only on schemas that can moderate, for people who can. Compatibility mode keeps it on other schemas, where it's always null |

Comments 3's GraphQL ran no spam checks. In compatibility mode, a `createComment` or `saveComment` without a `protection` argument is still accepted, checked by everything except replay protection, which needs a preparation from `comments/api/protection`: rate limits, keyword filters and Akismet still apply. A site with Turnstile or reCAPTCHA needs its client to complete the challenge and send `protection` either way. With the mode off, a mutation without `protection` is refused while spam checks are on, so add it as described under [Protection](docs:graphql/mutations#protection).

### Deleting from Your Own Forms
`comments/comments/trash` has been replaced by `comments/api/delete`, which a signed-in author can use for their own comment from a script, and which hides it rather than changing its status. In compatibility mode, `trash` still does the same for the signed-in author's own comment. See [Deleting a Comment](docs:reference/comment#deleting-a-comment). With the mode off, `trash` no longer exists.

### Console Commands
The `comments/base/resave-structure`, `comments/base/set-structure` and `comments/base/publish-assets` console commands are gone. In compatibility mode they still run, doing nothing and saying so, so a deploy script that calls them carries on. Remove them from your deploy scripts.

### Vote, Flag and Subscribe Events
Comments 3's `Votes`, `Flags` and `Subscribe` services are gone, along with `getVotes()`, `getFlags()` and `getSubscribe()`, but their events still fire in compatibility mode, under the same class and event names and with the same `VoteEvent`, `FlagEvent` and `SubscribeEvent`, so a module listening on `Votes::class` keeps hearing about votes. They're notices, as they were in Comments 3, and setting anything on them has no effect. Move to Comments 4's own events, which can also stop a vote or a report:

| Comments 3 | Comments 4 |
| --- | --- |
| `Votes::EVENT_BEFORE_SAVE_VOTE`, `Votes::EVENT_BEFORE_DELETE_VOTE` | `Interactions::EVENT_BEFORE_VOTE`, with a `VoteChangeEvent` |
| `Votes::EVENT_AFTER_SAVE_VOTE`, `Votes::EVENT_AFTER_DELETE_VOTE` | `Interactions::EVENT_AFTER_VOTE`, whose `type` is null when a vote is taken back |
| `Flags::EVENT_BEFORE_SAVE_FLAG` | `Reports::EVENT_BEFORE_REPORT`, with a `ReportChangeEvent` |
| `Flags::EVENT_AFTER_SAVE_FLAG` | `Reports::EVENT_AFTER_REPORT` |
| `Flags::EVENT_BEFORE_DELETE_FLAG`, `Flags::EVENT_AFTER_DELETE_FLAG` | `Reports::EVENT_AFTER_WITHDRAW_REPORT` |
| `Subscribe::EVENT_AFTER_SAVE_SUBSCRIBE`, `Subscribe::EVENT_AFTER_DELETE_SUBSCRIBE` | `Subscriptions::EVENT_AFTER_CHANGE_SUBSCRIPTION`, with a `SubscriptionChangeEvent` |

See [Events](docs:developers/events#vote-report-and-subscription-events). With the mode off, Comments 3's events no longer fire.

### Browser Event Names
In compatibility mode, a thread drawn by `craft.comments.render()` also dispatches each Comments 3 name on `document`, with Comments 4's `detail`, so a listener that only reacts to the event, such as one that records it for analytics, keeps working. A listener that reads `detail.comment` or another of Comments 3's objects needs updating either way. With the mode off, threads only dispatch Comments 4's events, listed under [Your Own JavaScript and CSS](#your-own-javaScript-and-css).

## Deprecated Changes
These keep working with compatibility mode off. They don't hold up the upgrade or stop you turning the mode off, so update them as you maintain your site.

### Removed Settings
These settings no longer exist, and what they did has gone with them. **Comments** → **Settings** → **Upgrade Report** lists any your site was using, apart from the control panel sidebar settings. Values left in `config/comments.php` are ignored whatever the mode, with a deprecation warning, so remove them when you can.

| Setting | What To Do |
| --- | --- |
| `assetBaseUrl`, `assetBasePath` | Craft publishes the bundled assets itself, including on Craft Cloud. The `comments/base/publish-assets` command has gone with them |
| `templateFolderOverride` | The thread has no Twig templates to override. See [Template Overrides](#template-overrides) |
| `defaultQueryStatus` | Comment queries return approved comments by default. Call `.status()` on a query for anything else |
| `hideVotingForThreshold` | A comment folded away after downvotes keeps its vote buttons |
| `showCustomFieldNames`, `showCustomFieldInstructions` | Public forms always show field labels and instructions |
| `indexSidebarLimit`, `indexSidebarGroup`, `indexSidebarIndividualElements` | The Comments index has fixed sources for each element type, and a condition builder for anything narrower |

### Renamed Settings
These settings are translated for you, whatever the mode, and their old names are removed the next time settings are saved. If they're in `config/comments.php`, they keep working until you move them to their new names, and each logs a deprecation warning until you do.

| Comments 3 | Comments 4 |
| --- | --- |
| `allowVoting` | `interactionMode`, where `true` becomes `updown` and `false` becomes `none` |
| `notificationAuthorEnabled`, `notificationReplyEnabled`, `notificationModeratorApprovedEnabled` | `participantNotifications` |
| `notificationModeratorEnabled`, `notificationModeratorEditEnabled`, `notificationAdminEnabled`, `notificationFlaggedEnabled` | `staffNotifications` |
| `notificationSubscribeEnabled`, `notificationSubscribeCommentEnabled`, `notificationSubscribeAuto`, `notificationSubscribeDefault` | `subscriptionTypes` and `subscriptionDefault` |
| `notificationAdmins` | `notificationRecipients`, shown as **Notification Recipients** |
| `recaptchaEnabled` | `challengeProvider`, where `true` becomes `recaptcha` and `false` becomes an empty string |

### Reply Forms
A reply form names the comment it replies to with a `parentId` field. Comments 3's `newParentId` field is still accepted, so an existing reply form keeps working, but use `parentId` when you update the form.

After a refused post, the `comment` variable holds what was posted (`comment.comment`, `comment.name`, `comment.email`, `comment.ownerId`, `comment.parentId` and, for an edit, `comment.id`), and Comments 3's `comment.hasErrors()` and `comment.getFirstError('comment')` still work. The page also gets an `errors` variable keyed `comment` for the reason and by field name, such as `name`, for a field's own problem, and the reason is in the `error` flash message too. This reply form sits inside a loop where `parent` is the comment being replied to:

::: code-group
```twig [Comments 3]
<form method="post">
    {{ actionInput('comments/comments/save') }}
    {{ csrfInput() }}
    {{ hiddenInput('elementId', entry.id) }}
    {{ hiddenInput('siteId', entry.siteId) }}
    {{ hiddenInput('newParentId', parent.id) }}
    {{ craft.comments.protect() }}

    {% if comment is defined and comment.hasErrors() %}
        <p class="error">{{ comment.getFirstError('comment') }}</p>
    {% endif %}

    <textarea name="fields[comment]">{{ comment.comment ?? '' }}</textarea>
    <button type="submit">Reply</button>
</form>
```

```twig [Comments 4]
<form method="post">
    {{ actionInput('comments/comments/save') }}
    {{ csrfInput() }}
    {{ hiddenInput('elementId', entry.id) }}
    {{ hiddenInput('siteId', entry.siteId) }}
    {{ hiddenInput('parentId', parent.id) }}
    {{ craft.comments.protect(entry.id, entry.siteId) }}

    {% if errors.comment is defined %}
        <p class="error">{{ errors.comment | first }}</p>
    {% endif %}

    <textarea name="fields[comment]">{{ comment.comment ?? '' }}</textarea>
    <button type="submit">Reply</button>
</form>
```
:::

Post a reply that will be refused, such as an empty one, and check that the page shows the reason and keeps what was typed.

### Notification Recipients in Email Events
In the `beforeSendAdminEmail` and `beforeSendFlagEmail` events, `$event->user` is now a user rather than Comments 3's array. For an address that doesn't belong to an account, it's a user without an ID, holding the address. Reading it as Comments 3 did, such as `$event->user['email']`, still works whatever the mode, but `is_array($event->user)` is now false. Read `$event->user->email` when you update your code.

### Trashed Status Constant
`Comment::STATUS_TRASHED` is kept whatever the mode, and is `removed`, the same as `Comment::STATUS_REMOVED`. Use `STATUS_REMOVED` when you update your code.

## Behaviour Changes
These don't need changes to your templates, settings or code, but some change what editors, moderators and visitors see, so review them before you go live.

- Sites with voting enabled keep upvotes and downvotes. Votes are limited to one per comment for each person, and a vote that was taken back isn't carried over. New installs default to `like`, which is upvotes only.
- Guest comments aren't merged by name or email. Guest votes and reports are grouped by their original session.
- Guests imported from Comments 3 can't be recognised, so a review setting on one of their records won't follow them when they comment again.
- Comments that are pending, spam or removed get one **Imported from Comments 3** entry in their history.
- `comments-view` is granted to anyone who could reach comments in Comments 3, and `comments-moderate` to anyone with `comments-edit`.
- A site that used the structure sort in the control panel opens at the newest comment first.
- A page with comments holds nothing about the person viewing it, so it can be cached for everyone with Craft's `{% cache %}` tag, a full-page cache or a CDN. See [Caching Comments](docs:template-guides/rendering-comments#caching-comments).

### Guest Emails
Guests only get emails once they've confirmed their address, including replies to their own comments, where Comments 3 emailed any address it was given. The addresses guests gave Comments 3 count as confirmed when you upgrade, so their reply emails carry on, unless the address belongs to one of your users. New guests confirm theirs, which they're offered when they comment, or can do by subscribing to a discussion. See [Notifications & Subscriptions](docs:feature-tour/notifications-subscriptions#confirming-a-guests-address).

### Notification Emails
Comments emails the same people Comments 3 did, under the settings that carry over as described under [Removed Settings](#removed-settings). Comments waiting for review and edits to them go to the **Moderator User Group**, and new comments and reports go to the **Comment Administrators** addresses, now called **Notification Recipients**. Wording you customised in **Utilities** → **System Messages** carries over, and so do listeners for the `beforeSend…Email` events, which keep their names.

A few things are different:

- Every email about a discussion has an unsubscribe link, for people with an account as well as guests. See [Unsubscribe Links](docs:feature-tour/notifications-subscriptions#unsubscribe-links).
- Nobody is emailed about their own comment or report, so a moderator no longer hears about a comment they posted.
- Mentions, new in Comments 4, are emailed while `mention` is in **Participant Notifications**, which it is by default.
- The new [`afterNotify` event](docs:developers/events#the-afterNotify-event) names everyone each notification was for, so you can tell them through another channel as well.

### Visitors Without JavaScript
Comments 3 rendered the thread and its forms in Twig, so visitors could read and post comments without JavaScript. In Comments 4 the thread is drawn by its script, and visitors without JavaScript see the comment count and a line asking them to turn it on. The comments are no longer in the page's HTML either, so search engines that don't run scripts don't see them. Add [`craft.comments.structuredData()`](docs:template-guides/structured-data) to your entry template to give those search engines the approved comments as JSON-LD. If your site has its own forms that post without JavaScript, they keep working through the form actions described under [Custom Forms](#custom-forms).

### When Comments Load
Comments load in the browser as a visitor scrolls near them, rather than coming with the page, and the Upgrade Report mentions this. To load them as soon as the page does, or to wait for a **Show comments** button, choose **Load Comments** under **Comments** → **Settings** → **CSS & JavaScript**, or set [`loadMode`](docs:get-started/configuration#loadMode) to `eager` or `button`. See [Choosing When Comments Load](docs:template-guides/rendering-comments#choosing-when-comments-load).

### Closing and Turning Off Comments
**Close Comments Globally** now keeps existing comments visible and stops new ones, as does closing comments on an element. A Comments field set to **Off** hides an element's comments altogether. A Comments field that was switched off in Comments 3 is read as **Closed**, which matches what Comments 3 did with it, so the element's comments stay visible and new ones are stopped. If you relied on closing to hide comments, use the Comments field or [Permissions](docs:feature-tour/permissions) instead.

### Parent Comments and Replies
`comment.parent` (`getParent()`) is now only the approved parent, and null when the comment replies to one that's held, removed or spam. Comments 3 returned the parent whatever its status, so a template that walked up from approved comments could show a held comment's text or a guest's email. `comment.children`, `getDescendants()` and the new `getReplies()` return approved replies too, as Comments 3's `children` did. When your own code needs every status, such as a moderation page of your own, use `comment.getParentComment()`, or add `.status(null)` to `comment.getReplies()`.

### Author Scores and Folded Comments
**Show Author Score**, **Hide After Reports** (`flaggedCommentLimit`) and **Hide After Downvotes** (`downvoteCommentLimit`) carry over, under **Comments** → **Settings** → **Interactions**, and a site that never changed them keeps Comments 3's defaults: scores shown, and comments folded away after five reports or downvotes. A folded comment now shows a short note with a **Show comment** button in place of its text, rather than only the note, and stays published, so reported comments still wait for a moderator under **Reported**. New installs start with all three off. `comment.isPoorlyRated()` and `comment.getAuthorScore()` work as they did, and `comment.getFoldReason()` says why a comment is folded.

### Reports
Flags are called reports throughout, including the **Allow Reporting**, **Allow Guest Reports** and **Report Reasons** settings, which are under **Interactions**. A comment counts as reported as soon as it has an open report, and reported comments are listed under the index's **Reported** source. Existing flags read “Legacy report” as their reason.

### Comments Fields
A Comments field's values carry over. An element set to enabled follows its source setting, as it did, and one set to disabled is now **Closed**, which matches what Comments 3 did with it, so its comments stay visible and new ones are stopped. A field whose **Default Value** was off has the new **Start Closed** setting turned on, so elements that never had a value of their own, and new ones, stay closed until an editor opens them, as in Comments 3. The field also has a **Closed** option, and shows a summary of what's waiting on the element, so editors can see and decide comments from the entry itself. See [Per-Element Settings](docs:feature-tour/permissions#per-element-settings) and [Moderating from an Element](docs:feature-tour/moderation#moderating-from-an-element).

### Spam Settings
The settings that were under **Comments** → **Settings** → **Security & Spam**, including the keyword filters and reCAPTCHA keys, move to their own **Comments** → **Spam Protection** page, and out of project config into each environment's database. The upgrade copies each environment's values from its project config, so nothing changes on the day. After that, each environment keeps its own, and changing them on a development site no longer deploys them. Admins, and people with the new **Manage spam settings** permission, can change them on production with admin changes turned off. Values in `config/comments.php` still win over what's saved. See [Anti-Spam](docs:feature-tour/anti-spam).

### Moderating
Moderation happens in the Comments index, under the **Needs review**, **Reported** and **Spam** sources, and on each comment's page, where the decisions sit in the sidebar and its history and notes are on an **Activity** tab. A comment layout's **Comment Form** tab is renamed **Content**. Removing a comment asks for a reason, and marking a comment as not spam returns it to **Needs review** rather than publishing it. See [Moderation](docs:feature-tour/moderation).

### Authoring Mode
Upgraded sites keep plain-text comments, so commenters see no change in what they can write. New installs start with rich authoring, which adds formatting and optional mentions and attachments. To switch, choose **Rich (Editor)** for **Authoring Mode** under **Comments** → **Settings** → **Authoring**, or set `'authoringMode' => 'rich'` in `config/comments.php`. See [Rich Authoring](docs:feature-tour/rich-authoring).

### Gravatar
**Use Gravatar** carries over, and is tighter than in Comments 3. A user's own photo comes first, and Gravatar is only used for signed-in users and guests who've confirmed their email, so guests who haven't show the **Placeholder Avatar**. Your site fetches each image in the queue and serves it itself, so avatars appear once the queue has run, and visitors no longer load anything from Gravatar. The Upgrade Report mentions this if Gravatar was on. See [Gravatar](docs:feature-tour/security#gravatar).

## Running the Upgrade
Update to the latest Comments 3 release first, and take a database backup before you start.

```shell
composer require verbb/comments:^4.0 && php craft up
```

The upgrade commits its progress as it goes, so large sites don't need to finish in one attempt. If it's interrupted, or it stops with an error, run `php craft up` again and it carries on from where it was.

We recommend running it from the command line rather than the control panel, because a web request can time out on a site with a lot of comments.

If your database was never converted to `utf8mb4`, we recommend converting it with `php craft db/convert-charset`. Until then, Comments stores emoji as shortcodes such as `:tada:` and shows them as emoji, because older MySQL character sets can't store them.

If you use project config with `allowAdminChanges` disabled in production, run the upgrade in your development environment first and deploy the resulting config along with the code. User group permissions arrive through that config. Permissions granted directly to users are updated when the upgrade runs in each environment.

## What Gets Checked
Before removing any Comments 3 storage, the upgrade compares it with the new data: comment counts and content, threads, moderation states, votes, flags and subscriptions. If anything doesn't match, the upgrade stops with the failed check named and your Comments 3 data still in place.

The upgrade only stops for mismatches like these. Problems with your existing data are recorded as warnings and the upgrade carries on:

| Warning | What Happens |
| --- | --- |
| `owner.missing` | The comment's element no longer exists. The comment is kept, but won't appear on the front end. |
| `ownerSite.missing` | The comment's element is known, but no site was recorded for it. The comment is kept. |
| `ownerSite.unsupported` | The element doesn't exist on the comment's site. The comment is kept and its conversation is created. |
| `hierarchy.structureUnresolved`, `hierarchy.multipleStructures` | Threads are read from whichever structures hold your comments, rather than from the configured one. |
| `hierarchy.structureMissing`, `hierarchy.nodeMissing` | There's no record of where these comments sit in a thread, so they become top-level comments. |
| `hierarchy.parentMissing` | A reply's parent no longer exists, so it becomes a top-level comment. |
| `hierarchy.crossOwnerParent` | A reply's parent belongs to a different element, so it starts a new thread. |
| `hierarchy.invalidBounds`, `hierarchy.duplicateBoundary` | The comment structure is damaged, so the placement of some replies may be wrong. |
| `status.unknown` | The comment had no recognised status, so it's held for moderation. |
| `vote.contradictory` | A person's vote on a comment was recorded as both up and down, so it's kept as a downvote. |
| `permissions.groupFailed` | A user group couldn't be given the new comment permissions. Check the group's permissions in its settings. |

Warnings are printed at the end of the upgrade and kept in the `comments_v3_log` table. **Comments** → **Settings** → **Upgrade Report** shows them when there's anything to report, and so does the `comments/diagnostics/scan` [console command](docs:developers/console-commands).

## After Upgrading
Your Comments 3 votes, flags, subscriptions and comment structure are kept in the `comments_v3_votes`, `comments_v3_flags`, `comments_v3_subscribe` and `comments_v3_structure` tables, in case you need to refer back to them. IP addresses aren't kept in these copies.

Comments 4 doesn't use these tables. Once you're happy with the upgrade you can drop them, and they're removed if you uninstall the plugin.
