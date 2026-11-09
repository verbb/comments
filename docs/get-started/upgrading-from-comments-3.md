# Upgrading from Comments 3
Comments 4 replaces the way comments are stored, the public templates and the browser script. Your comments, threads, votes, flags and subscriptions move to the new storage in place when you run Craft's migrations, and each comment keeps its element ID, custom field values and dates.

Everything under [Breaking Changes](#breaking-changes) needs checking before you go live. A site using `craft.comments.render()` with the default templates and no custom forms has little to do beyond the requirements and settings. A site with custom templates, its own JavaScript, GraphQL or PHP integrations has more.

## Breaking Changes

### Requirements
Comments 4 requires Craft 5.10 or greater, up from Craft 5.0. It still requires PHP 8.2. Composer installs `verbb/editor` and `verbb/protect` with the plugin, so there's nothing else to add. Update Craft first if you need to.

### Template Overrides
If you've set **Template Folder Override**, the files in that folder stop being used, because they follow the old structure. Nothing fails: your comments render with the default templates until you move your changes across. Copy the template you want to change into a `frontend/` subfolder of the same override folder, then reapply your changes to the copy.

| Comments 3 | Comments 4 |
| --- | --- |
| `comments` | `frontend/conversation` |
| `_includes/header` | `frontend/_conversation/header` |
| `_includes/comment` | `frontend/_comment/comment`, with `_comment/author`, `_comment/content`, `_comment/actions` and `_comment/replies` |
| `_includes/avatar` | `frontend/_comment/author` |
| `_includes/form` | `frontend/_composer/form` |
| `_includes/footer` | None. Pagination is in `frontend/_shared/pagination` |
| `form-fields/index`, `form-fields/field`, `form-fields/elements/comment`, `form-fields/fields/*` | `frontend/_composer/custom-fields`, a single file for the comment box and every custom field |

The variables are different too. Templates receive prepared `conversation`, `comment`, `composer` and `viewer` objects rather than a `commentsQuery` and the plugin's `settings`, and `comment` isn't a Comment element. [Custom Templates](docs:template-guides/custom-templates) lists what each template gets, and the attributes the JavaScript needs.

```twig
{# Comments 3: includes needed a special function #}
{{ commentsInclude('_includes/comment') }}

{# Comments 4: an ordinary include finds your copy first #}
{% include '_comment/comment' %}
```

Check the result by viewing a page with comments and confirming your changes show.

### Twig Functions and Variables
These were removed, and calling them is an error. Remove them from your templates. `craft.comments.render()` registers everything itself, and you can turn off the bundled assets under **Comments** → **Settings** → **Templates & Resources**.

| Comments 3 | Comments 4 |
| --- | --- |
| `commentsInclude()`, `commentsSiteInclude()` | `{% include %}` |
| `craft.comments.renderCss()` | The **Load Bundled CSS** setting |
| `craft.comments.renderJs()`, `craft.comments.getJsVariables()` | The **Load Bundled JavaScript** setting |
| `craft.comments.protect()` | `craft.comments.protect(entry.id, entry.siteId)` |
| `comment.isPoorlyRated()` | None |
| `comment.getAuthorScore()` | None |
| `comment.can()`, `comment.canTrash()` | `comment.canEdit()`, `comment.canReply()`, `comment.canVote()`, `comment.canFlag()` |

The second argument of `craft.comments.render()` changes meaning as well. It used to set properties on the comment query. It's now a short list of rendering options, and anything else in it is ignored.

::: code-group
```twig [Comments 3]
{{ craft.comments.render(entry.id, { limit: 10, orderBy: 'commentDate asc' }) }}
```

```twig [Comments 4]
{{ craft.comments.render(entry.id, { limit: 10, sort: 'oldest' }) }}
```
:::

### Custom Forms
Your own comment forms need the new protection fields, or they'll be rejected. Remove the old `__UAHASH`, `__JSCHK`, `__HPP` and `__TIME` fields and any reCAPTCHA script of your own, and give `protect()` the element and site. Clear any cached form HTML when you deploy this, so visitors get the new fields.

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

[Displaying and Protecting Comments](docs:template-guides/displaying-and-protecting-comments) covers what these fields need when challenges are turned on. These controller actions changed as well:

| Comments 3 | Comments 4 |
| --- | --- |
| `comments/comments/trash` | `comments/frontend-api/remove`, which a signed-in author can use for their own comment, and which hides it rather than changing its status |
| `comments/comments/get-js-variables` | None |
| `comments/comments/giphy-search` | None |
| `html` in the JSON response from `comments/comments/save` | None. The response has `id`, `comment` and `notice`. Use the `comments/api/v1/*` endpoints for rendered markup |

### Custom Fields on Public Forms
Visitors can only submit custom fields you've selected. Until you've done this, values for your custom fields are ignored on public forms, GraphQL and the API, so check this if your forms have any. Go to **Comments** → **Settings** → **Comment Fields** and tick them under **Public Fields**, or set them in your config file.

```php
'publicFields' => ['myFieldHandle'],
```

A required field that isn't selected no longer stops a visitor from posting. Submit a test comment with a value for the field, and confirm it's saved on the comment.

### Your Own JavaScript and CSS
The browser script is new, so `new Comments.Instance()`, `Comments.Comment` and the rest of the Comments 3 script are gone. So are the `cc-*` class names and `data-role` attributes in the markup, and the Comments 3 stylesheet. Any styles you've written against them, or scripts that hook into them, need redoing. The [JavaScript API](docs:template-guides/javascript-api) page documents the `Comments` global and the browser events to use instead, and public comments use `comments__*` class names.

### Removed Settings
These settings no longer exist. **Comments** → **Settings** → **Upgrade Report** lists any your site was using, apart from the control panel sidebar settings. Remove them from `config/comments.php` if they're there, as values left in that file are ignored.

| Setting | What To Do |
| --- | --- |
| `giphyEnabled`, `giphyApiKey`, `giphyRating`, `giphyLimit` | GIF search has been removed. Comments that already have a GIF keep showing it, but new ones can't be added |
| `assetBaseUrl`, `assetBasePath` | Craft publishes the bundled assets itself, including on Craft Cloud. The `comments/base/publish-assets` command has gone with them |
| `defaultQueryStatus` | Comment queries return approved comments by default. Call `.status()` on a query for anything else |
| `downvoteCommentLimit`, `hideVotingForThreshold` | A comment can no longer be marked as poorly rated |
| `flaggedCommentLimit` | A comment counts as reported as soon as it has an open report |
| `showAuthorScore` | Author scores have been removed |
| `enableGravatar` | Guests show the **Placeholder Avatar** |
| `showCustomFieldNames`, `showCustomFieldInstructions` | Public forms always show field labels and instructions |
| `indexSidebarLimit`, `indexSidebarGroup`, `indexSidebarIndividualElements` | The Comments index has fixed sources for each element type, and a condition builder for anything narrower |
| `notificationAdmins` | Staff aren't emailed. Choose a **Moderator User Group** and deliver its notifications from the `afterNotify` event, as described under [Notifications for People With an Account](#notifications-for-people-with-an-account) |

These settings are translated for you, and their old names are removed the next time settings are saved. If they're in `config/comments.php`, they keep working until you move them to their new names.

| Comments 3 | Comments 4 |
| --- | --- |
| `allowVoting` | `interactionMode`, where `true` becomes `updown` and `false` becomes `none` |
| `notificationAuthorEnabled`, `notificationReplyEnabled`, `notificationModeratorApprovedEnabled` | `participantNotifications` |
| `notificationModeratorEnabled`, `notificationModeratorEditEnabled`, `notificationAdminEnabled`, `notificationFlaggedEnabled` | `staffNotifications` |
| `notificationSubscribeEnabled`, `notificationSubscribeCommentEnabled`, `notificationSubscribeAuto`, `notificationSubscribeDefault` | `subscriptionTypes` and `subscriptionDefault` |

### Comment Statuses
Comments with the `trashed` status now have the `removed` status, which is a moderation decision and separate from Craft's own trash. Update any templates or queries that use `trashed`.

::: code-group
```twig [Comments 3]
{% set hidden = craft.comments.fetch().status('trashed').all() %}
```

```twig [Comments 4]
{% set hidden = craft.comments.fetch().status('removed').all() %}
```
:::

### Closing and Turning Off Comments
**Close Comments Globally** now keeps existing comments visible and stops new ones, as does closing comments on an element. A Comments field set to off hides an element's comments altogether. If you relied on closing to hide comments, use the Comments field or [Permissions](docs:feature-tour/permissions) instead.

### Guest Emails
Guests only get emails once they've confirmed their address, including replies to their own comments. Comments 3 emailed any address it was given. Guests who gave an address before upgrading stop getting emails until they confirm one, which they're offered when they comment, or can do by subscribing to a discussion. See [Notifications and Subscriptions](docs:feature-tour/notifications-and-subscriptions#guests).

### Notifications for People With an Account
Comments only emails guests. Element authors, commenters with an account, mentioned people and staff aren't emailed by Comments, whether they're told about a reply, a mention, an approval, a discussion they follow, or a comment waiting for review. Instead, Comments fires `Notifications::EVENT_AFTER_NOTIFY` naming who should hear about each comment, and your site or a notifications plugin delivers it. Until something listens for that event, people with an account aren't told about comments at all.

The settings that chose who is told carry over, as described under [Removed Settings](#removed-settings). Staff notifications name the members of the **Moderator User Group**, so choose one if you haven't. The **Additional Notification Recipients** list (`notificationAdmins`) has no replacement, because those addresses had no account to name. If people outside your moderator group need to hear about new comments or reports, add them to that group, or send to them from your own event listener.

These system messages are removed, along with any customised wording in **Utilities** → **System Messages**: the author, mention, moderator, moderator edit, admin and flag notifications. The reply, subscription and approval messages remain, and are only sent to guests.

These PHP events are removed with them, and the remaining `beforeSend…` events only fire for guest emails:

| Comments 3 | Comments 4 |
| --- | --- |
| `beforeSendAuthorEmail` | `afterNotify` with the `author` type |
| `beforeSendModeratorEmail` | `afterNotify` with the `moderator` or `moderator-edit` type |
| `beforeSendAdminEmail` | `afterNotify` with the `admin` type |
| `beforeSendFlagEmail` | `afterNotify` with the `flag` type |

A listener that emails moderators looks like this. Put it in a module's or plugin's `init()` method:

```php
use Craft;
use verbb\comments\events\NotifyEvent;
use verbb\comments\services\Notifications;
use yii\base\Event;

Event::on(Notifications::class, Notifications::EVENT_AFTER_NOTIFY, function(NotifyEvent $event) {
    if ($event->type !== Notifications::TYPE_MODERATOR) {
        return;
    }

    foreach ($event->recipients as $user) {
        Craft::$app->getMailer()->compose()
            ->setTo($user)
            ->setSubject('A comment is waiting for review')
            ->setTextBody($event->comment->getCpEditUrl())
            ->send();
    }
});
```

[Events](docs:developers/events#the-afternotify-event) lists every type and who it names. Check it works by posting a comment that waits for review and confirming your listener runs.

### GraphQL
Check your queries and mutations against these changes, then see [GraphQL](docs:developers/graphql) for the full schema.

| Comments 3 | Comments 4 |
| --- | --- |
| `email` field on `CommentInterface` | Removed. Use `user` for registered commenters on a schema that can read users |
| `email` argument on the `comments` query | Removed |
| `userId` and `commentDate` arguments on mutations | Removed. A comment's author is whoever is making the request, and its date is when it's posted |
| `newParentId` argument on mutations | `parentId`, for `createComment`. A comment can't be moved through `saveComment` |
| Structure arguments such as `level`, `ancestorOf`, `descendantOf`, `leaves` and `hasDescendants` on the `comments` query | `rootId` and `adjacencyParentId` |
| Structure fields on `CommentInterface`, such as `level`, `lft`, `rgt` and `root` | `depth` and `rootId` |

Public schemas only ever return published comments, whatever arguments they're given, including through the `children` and `parent` fields. `saveComment` can only be used by a comment's author, and can only change its content. `createComment` and `saveComment` run the same spam checks as public forms, so they take a [`protection` argument](docs:developers/graphql#mutations) while those checks are on.

### PHP Integrations
Modules and plugins calling Comments directly need updating. Comment queries keep working, but threads no longer use a Craft structure.

| Comments 3 | Comments 4 |
| --- | --- |
| `Comments::$plugin->getVotes()` | `Comments::$plugin->getInteractions()` |
| `Comments::$plugin->getFlags()` | `Comments::$plugin->getReports()` |
| `Comments::$plugin->getSubscribe()` | `Comments::$plugin->getSubscriptions()` |
| `Comments::$plugin->getProtect()` | `Comments::$plugin->getProtection()` |
| `Comments::$plugin->getGiphy()` | None |
| `FlagEvent`, `VoteEvent`, `SubscribeEvent` | None. Nothing fired them |
| `FlagNotFoundException`, `VoteNotFoundException`, `SubscribeNotFoundException` | None |
| `Comment::STATUS_TRASHED` | `Comment::STATUS_REMOVED` |
| `comment.level`, `comment.getAncestors()` and other structure properties | `comment.depth`, `comment.rootId`, `comment.parentId`, `comment.getReplies()` |

Moderators changing a comment's status don't save its element, so `Comment::EVENT_BEFORE_SAVE` and `Comment::EVENT_AFTER_SAVE` no longer fire when a comment is approved or marked as spam from the index or the site. Use `Moderation::EVENT_AFTER_CHANGE_STATE` instead, as described in [Events](docs:developers/events).

The `comments/base/resave-structure`, `comments/base/set-structure` and `comments/base/publish-assets` console commands are gone, along with the `comments/comments/edit-comment`, `save-comment`, `edit-comment-slideout` and `delete-comment` control panel actions and the `comments/new` routes. Comments are edited through Craft's own element editor.

## Behaviour Changes
These don't need anything from you, but are worth reviewing.

- **Voting:** sites with voting enabled keep upvotes and downvotes. Votes are limited to one per comment for each person, and a vote that was taken back isn't carried over. New installs default to `like`, which is upvotes only.
- **Guests:** guest comments aren't merged by name or email. Guest votes and reports are grouped by their original session.
- **Moderation history:** comments that are pending, spam or removed get one "Import" entry in their history.
- **Reports:** flags are called reports throughout, including the **Allow Reporting**, **Allow Guest Reports** and **Report Reasons** settings, which are under **Interactions**. A comment counts as reported as soon as it has an open report, and reported comments are listed under the index's **Reported** source. Existing flags read “Legacy flag” as their reason.
- **Control panel access:** `comments-view` is granted to anyone who could reach comments in Comments 3, and `comments-moderate` to anyone with `comments-edit`.
- **Default control panel sort:** a site that used the structure sort now opens at the newest comment first.
- **Notifications through the queue:** `useQueueForNotifications` defaults to on, for sites that never saved the old default, so make sure your queue runs. A failed email now fails its job, so it shows up in the queue manager.
- **Comments fields:** a Comments field's values carry over. An element set to enabled follows its source setting, as it did, and one set to disabled stays off. The field also has a **Closed** option, and shows a summary of what's waiting on the element. Owner elements no longer get a Comments sidebar of their own, so add the field to any layout where editors need it, ideally on its own tab. See [Comments Field](docs:feature-tour/control-panel#comments-field).
- **Moderating:** moderation happens in the Comments index, under the **Needs review**, **Reported** and **Spam** sources, and on each comment's page, where the decisions sit in the sidebar and its history and notes are on an **Activity** tab. A comment layout's **Comment Form** tab is renamed **Content**. Removing a comment asks for a reason, and marking a comment as not spam returns it to **Needs review** rather than publishing it. See [Moderation](docs:feature-tour/moderation).
- **Rate limits:** votes, reports and guest subscriptions now have their own limits. A site behind a proxy or CDN needs [`trustedHosts`](docs:feature-tour/security#client-ip-addresses) set, or everyone will share a limit.

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
| `owner.missing`, `ownerSite.missing` | The comment's element no longer exists. The comment is kept, but won't appear on the front end. |
| `ownerSite.unsupported` | The element doesn't exist on the comment's site. The comment is kept and its conversation is created. |
| `hierarchy.structureUnresolved`, `hierarchy.multipleStructures` | Threads are read from whichever structures hold your comments, rather than from the configured one. |
| `hierarchy.structureMissing`, `hierarchy.nodeMissing` | There's no record of where these comments sit in a thread, so they become top-level comments. |
| `hierarchy.parentMissing` | A reply's parent no longer exists, so it becomes a top-level comment. |
| `hierarchy.crossOwnerParent` | A reply's parent belongs to a different element, so it starts a new thread. |
| `hierarchy.invalidBounds`, `hierarchy.duplicateBoundary` | The comment structure is damaged, so the placement of some replies may be wrong. |
| `status.unknown` | The comment had no recognised status, so it's held for moderation. |

Warnings are printed at the end of the upgrade and kept in the `comments_v3_log` table. **Comments** → **Settings** → **Upgrade Report** shows them when there's anything to report, and so does the `comments/diagnostics/scan` [console command](docs:developers/console-commands).

## After Upgrading
Your Comments 3 votes, flags, subscriptions and comment structure are kept in the `comments_v3_votes`, `comments_v3_flags`, `comments_v3_subscribe` and `comments_v3_structure` tables, in case you need to refer back to them. IP addresses aren't kept in these copies.

Comments 4 doesn't use these tables. Once you're happy with the upgrade you can drop them, and they're removed if you uninstall the plugin.
