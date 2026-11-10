# Custom Templates

`craft.comments.render()` outputs the whole comments interface using templates that come with the plugin. When you want different markup, you can override any of them in your own templates folder and keep everything else as it is. You only copy the files you change, so an update to the rest can't break your copies.

To restyle comments without changing the markup, you don't need any of this. Use the `comments__*` classes described in [Loading Comment Assets](docs:template-guides/loading-comment-assets). To write the whole thing yourself from a [comment query](docs:getting-elements/comment-queries), see [Displaying and Protecting Comments](docs:template-guides/displaying-and-protecting-comments).

## Setting Up an Override Folder

Suppose you want to change how the author's name appears next to each comment.

1. Create a folder in your site's templates folder, such as `templates/_comments`.
2. Go to **Comments** → **Settings** → **Templates & Resources**, and enter `_comments` as the **Template Folder Override**, or set [`templateFolderOverride`](docs:get-started/configuration#templatefolderoverride) in your config file.
3. Inside it, create a `frontend` subfolder. This is the one Comments looks in.
4. Copy the template you want to change from `vendor/verbb/comments/src/templates/frontend/` to the same path inside your `frontend` folder, then edit your copy.

For the author's name, that's one file:

```
templates/
└── _comments/
    └── frontend/
        └── _comment/
            └── author.twig
```

Reload a page with comments and you'll see your version. Every template you haven't copied still comes from the plugin. Overrides can use `.twig`, `.html` or anything else in Craft's `defaultTemplateExtensions`.

Includes, imports and embeds in your overrides work the same way, so `{% include '_comment/author' %}` finds your copy first and the plugin's otherwise. Every template name resolves inside your `frontend` folder, not from the root of your site's templates, so anything your overrides include or import has to live there too. That keeps the comment templates self-contained, so they render the same when the browser fetches part of the page.

For example, to use a file of macros in an override, put it in your `frontend` folder:

```
templates/
└── _comments/
    └── frontend/
        ├── _comment/
        │   └── author.twig
        └── _macros/
            └── people.twig
```

Then import it by its path inside `frontend`, as you would any other template:

```twig
{# templates/_comments/frontend/_comment/author.twig #}
{% import '_macros/people' as people %}
```

A macro file elsewhere in your site's templates, such as `templates/_macros/people.twig`, can't be imported from an override, and Twig reports that it can't find the template.

## Changing the Wording

You don't need an override to change what a message says. The text Comments shows, on your site and in the control panel, goes through Craft's translations in the `comments` category, so you can replace it with a static translation file in your project. A few general control panel labels, such as **Status** and **Date Updated**, are Craft's own and use its `app` category, and relative times such as “3 hours ago” come from Craft's date formatting in the site's language.

Suppose you want “Comment submitted for moderation.” to say something friendlier. Create `translations/en/comments.php` in your project's root folder, next to `templates/`, and return the messages you want to change, keyed by the original English text:

```php
<?php

return [
    'Comment submitted for moderation.' => 'Thanks! A moderator will check your comment soon.',
    'Be the first to comment.' => 'Nobody has said anything yet.',
];
```

Copy each key exactly as it appears in `vendor/verbb/comments/src/translations/en/comments.php`, including its punctuation, and leave out anything you don't want to change. The folder is named after the site's language, and `en` covers every English site, such as `en-US` and `en-GB`. For a site in another language, use its folder, such as `translations/de/comments.php`, and your messages replace Comments' own translations for that language.

The emails Comments sends are edited under **Utilities** → **System Messages** instead, as described in [Customising Emails](docs:feature-tour/notifications-and-subscriptions#customising-emails).

## The Templates

Everything is included from `conversation`, so you can override at whichever level suits your change.

| Template | What It Outputs |
| --- | --- |
| `conversation` | The outer `<section>` and its regions, which include everything below. |
| `_conversation/header` | The heading, comment count, sort links and **Follow** button, and the moderator's summary of held and reported comments. |
| `_conversation/subscribe` | The **Follow** form, with an email field for a guest. |
| `_conversation/follow-by-email` | The **Follow by email** disclosure, for a guest who'd need to give an address. |
| `_conversation/list` | The list of top-level comments, the empty message, the placeholder for lazy loading and the link to load more. |
| `_conversation/empty` | The message when there are no comments yet. |
| `_conversation/notice` | The message in place of the form when comments are closed, or guests can't comment. |
| `_editor/region` | What goes at the top of the thread, which is the form for a signed-in visitor, the **Join the conversation** card for a guest, or the notice. |
| `_editor/form` | The form for a comment, reply or edit. |
| `_editor/guest-fields` | The guest's name and email fields, and the offer of reply emails. |
| `_editor/custom-fields` | The comment box, the Editor waiting to replace it with rich authoring, and any [Public Fields](docs:template-guides/custom-fields). |
| `_editor/empty` | An empty template used to clear a reply or edit form. |
| `_comment/comment` | One comment, including its replies. This also handles placeholders. |
| `_comment/author` | The avatar, name, badges, date, short public field values and “Edited” label. |
| `_comment/content` | The comment's text, or rich content and any GIF. |
| `_comment/actions` | The vote buttons, **Reply**, and the **···** menu with **Copy link**, **Edit**, **Report…**, **Delete** and moderation. |
| `_comment/review` | What a moderator sees on a held, spam or reported comment, with buttons to decide it. |
| `_comment/replies` | A comment's replies, the button to hide them and the link to load more. |
| `_report/form` | The **Report this comment** dialog. |
| `_shared/feedback` | The message shown after an action. |
| `_shared/pagination` | The links to load more comments or replies. |

The page at `frontend/manage.twig`, which handles [unsubscribe links and email confirmation](docs:feature-tour/notifications-and-subscriptions#unsubscribe-links), can't be overridden. It's rendered from the plugin in control panel template mode, because it has to work on its own for someone following a link in an email.

## Template Variables

Templates don't receive Comment elements or the plugin's settings. They receive prepared objects, which hold only what's safe and relevant for the person viewing the page.

The whole page, from `conversation` down, gets these variables. Parts of the page are also rendered by themselves when the browser fetches them, with only some of the variables, so check for anything that might be missing with `??` or `default` if your site has Twig's strict variables on. A comment always has `comment`, `conversation`, `viewer`, `editor` and `instance`, and a reply or edit form has `editor`, `conversation`, `viewer`, `instance` and `form`.

| Variable | What It Is |
| --- | --- |
| `conversation` | The discussion and its URLs. |
| `viewer` | Who's looking, and what they can do. |
| `editor` | The form for a comment. |
| `comment` | The comment being rendered, in `_comment/*`. |
| `form` | The form's mode in `_editor/form`, which is `create`, `reply` or `edit`. |
| `instance` | A unique ID for this set of comments, for building `id` attributes. |
| `sharedCache` | Whether this render has to be safe to share between visitors, which is also true while lazy loading. |
| `reportReasons` | The **Report Reasons**, each with a `value`, `label` and `description`. |
| `reportTargetId` | The comment being reported without JavaScript, if any. |
| `flash` | A message for this visitor, as `error` or `notice`. |
| `contractVersion`, `theme`, `apiBase`, `editorAsset` | Values for the attributes on `conversation`. |

### `conversation`

These are the properties of `conversation` that are most useful in a custom template.

| Property | What It Is |
| --- | --- |
| `ownerId`, `siteId`, `conversationUid` | Which element and site this is. |
| `roots`, `rootsNext`, `rootsNextHref` | The current page of top-level comments, a cursor for the next page, and its link. |
| `commentCount` | The number of public comments. |
| `sort`, `allowsTopSort`, `rootLimit` | The current sort, whether **Top** is available, and how many top-level comments make a page. |
| `lazy`, `showCommentsHref` | Whether the list is waiting to load, and a link to load it without JavaScript. |
| `canComment`, `closedNotice`, `guestNotice` | Whether the form is offered, and why not if it isn't. |
| `interactionMode` | The site's **Interaction Mode**, which is `like`, `updown` or `none`. |
| `isModerator`, `heldCount`, `reportedCount`, `reviewUrl` | Whether this visitor can moderate, how many comments here are held or reported, and a link to review them in the control panel. |
| `subscribeAction`, `voteAction`, `removeAction`, `moderateAction`, `reportAction` | The URLs the default forms post to. |
| `pageParams`, `cancelHref` | What else is in the page's URL, kept when moving around without JavaScript, and a link back to the page. |

### `viewer`

| Property | What It Is |
| --- | --- |
| `isLoggedIn`, `name`, `initials`, `avatarUrl`, `avatarTone` | Who's looking. `avatarTone` is a number from 0 to 7 for colouring their initials. |
| `badge` | The badge their comments carry here, such as **Author**. |
| `loginUrl`, `allowsGuests` | Where to sign in, and whether guests can comment at all. |
| `canComment`, `isModerator` | Whether they can post, and moderate. |
| `showSubscribe`, `subscribeNeedsEmail`, `isSubscribed` | The state of the **Follow** form. |

### `comment`

| Property | What It Is |
| --- | --- |
| `id`, `uid` | The comment's IDs. |
| `state` | `approved`, `pending` or `spam`, or `unavailable` for a placeholder. Only moderators see comments that aren't approved. |
| `placeholder` | Whether this is standing in for a hidden comment. See below. |
| `moderatorOnly`, `reported`, `reviewLabel` | Whether only a moderator can see this comment, whether it has open reports, and what a moderator reads about it. |
| `depth`, `visualDepth` | How deep the comment really is, and how far to indent it, which stops at four levels. |
| `content`, `contentHtml`, `contentFormat` | The plain text, the rendered HTML of a rich comment, and which of `plain` or `editor` it's in. |
| `gifUrl` | The address of a GIF on the comment, if it has one. |
| `authorName`, `authorAvatarUrl`, `authorInitials`, `avatarTone`, `badges` | The author, and any badges, as `type` and `label` pairs. |
| `commentDate`, `fullDate`, `timeAgo`, `editedAt` | When it was posted as an ISO 8601 date and in words, how long ago when **Show Time Ago** is on, and when it was edited. |
| `metaLine` | Short values from its [public fields](docs:template-guides/custom-fields#showing-fields-on-a-comment), shown after the date. |
| `permalink`, `hrefs` | The comment's anchor, and links for `reply`, `edit`, `flag` and `replies` without JavaScript. |
| `replies`, `replyCount`, `hasMoreReplies` | The replies loaded so far, which are also `comment` objects, the total, and whether there are more. |
| `actions` | What the visitor can do, from `reply`, `edit`, `remove`, `interact`, `interact-down` and `report`. |
| `moderationActions` | What they can do as a moderator, from `moderate-approve`, `moderate-keep`, `moderate-remove`, `moderate-restore`, `moderate-spam` and `moderate-not-spam`. |
| `interaction` | Vote details, which are `mode`, `allowsDownvote`, `usesScore`, `upvotes`, `downvotes`, `score` and the visitor's own `viewer` vote. It's null when interactions are off, or the comment isn't approved. |
| `contextualEditor` | A reply or edit form opened by the server for a visitor without JavaScript. |

`comment.commentDate` and `comment.editedAt` are strings, so use Twig's `date` filter on them. `timeAgo` is null in a shared-cache render, because it would go stale. `contentHtml` has already been made safe when it's rendered, so output it with `| raw` as the default template does. The `flag` link opens the **Report this comment** form.

### `editor`

| Property | What It Is |
| --- | --- |
| `mode`, `operationId` | `create`, `reply` or `edit`, and an ID for this submission. |
| `ownerId`, `siteId`, `parentCommentId`, `editCommentId`, `parentAuthorName` | What's being commented on, and who's being replied to. |
| `initialPlainContent`, `initialEditorContent` | The existing text when editing, or what was typed when a form comes back with an error. |
| `richAuthoring`, `contentFormat`, `getEditorOptions()`, `editorAsset`, `draftKey`, `maxLength` | Whether the form swaps in the Editor, where to load it from, and what it needs. |
| `showGuestFields`, `guestRequireEmailName`, `guestName`, `guestEmail`, `offerReplyEmails` | The guest fields, and a returning guest's details. |
| `publicFields`, `hasFileInputs` | The custom fields to show, and whether the form needs `enctype="multipart/form-data"`. |
| `redirectTemplate`, `cancelHref` | Where the form goes after posting without JavaScript, and a link to cancel. |

Each row in `publicFields` has `handle`, `label`, `instructions`, `required`, `type`, `multiple`, `options` and `values`. [Custom Fields](docs:template-guides/custom-fields) explains how they're used.

## Handling Placeholders

A comment that's been hidden can still have visible replies, such as one that was removed or deleted by its author. So those replies stay connected, the comment is replaced by a placeholder with `comment.placeholder` set to true. It has no author, content or actions, only its IDs and its replies, because telling visitors any more would reveal what's been hidden.

If you override `_comment/comment`, you must handle this, or visitors will see an empty comment with no explanation. Start from a copy of the default template, which keeps the check and always renders the replies. This trimmed version shows the parts that matter:

```twig
<li
    class="comments__comment"
    data-comments-comment
    data-comments-comment-uid="{{ comment.uid }}"
    data-comments-comment-id="{{ comment.id }}"
    data-comments-state="{{ comment.state }}"
>
    <article id="comment-{{ comment.id }}" tabindex="-1">
        {% if comment.placeholder %}
            <p class="comments__placeholder">{{ 'Comment unavailable.' | t('comments') }}</p>
        {% else %}
            {% include '_comment/author' %}
            {% include '_comment/content' %}
            {% include '_comment/actions' %}

            {% if comment.reviewLabel or comment.moderatorOnly %}
                {% include '_comment/review' %}
            {% endif %}

            <div class="comments__contextual-editor" data-comments-region="contextual-editor" data-comments-comment-uid="{{ comment.uid }}">
                {% if comment.contextualEditor %}
                    {% include '_editor/form' with { editor: comment.contextualEditor, form: comment.contextualEditor.mode } %}
                {% endif %}
            </div>
        {% endif %}

        {% include '_comment/replies' %}
    </article>
</li>
```

## Keeping the JavaScript Working

The bundled JavaScript finds things using `data-comments-*` attributes, and replaces pieces of the page with fragments rendered from these same templates. Your markup can change as much as you like, but keep the attributes, or that behaviour stops. The safest way to start is from a copy of the default template, changing what's around them.

### Root and Regions

`conversation` needs its attributes on the outer element, particularly `data-comments-root`, `data-comments-contract`, `data-comments-instance`, `data-comments-conversation-uid`, `data-comments-owner-id`, `data-comments-site-id`, `data-comments-api-base`, `data-comments-sort`, `data-comments-limit`, `data-comments-enhanced` and `data-comments-csrf-param`. Without the first two, the JavaScript doesn't start at all, and without `data-comments-enhanced` it leaves forms to post normally. The messages it shows come from `data-comments-discard-message`, `data-comments-copied-message`, `data-comments-failed-message`, `data-comments-retry-label` and `data-comments-uploading-message`, and the rest control the theme, shared caching, lazy loading and loading the Editor later.

Fragments go into these regions, which should stay in place:

| Attribute | Where | Filled With |
| --- | --- | --- |
| `data-comments-region="header"` | The outermost element of `_conversation/header` | Replaced by a fresh header, so the attribute has to be on that template's outermost element. |
| `data-comments-region="list"` | `conversation` | `_conversation/list`. |
| `data-comments-region="editor"` | `conversation` | `_editor/region`, which is the form, the **Join the conversation** card or a notice. |
| `data-comments-region="feedback"` | `conversation` | Must hold an element with the `comments__feedback` class, for messages. |
| `data-comments-region="live"` | `conversation` | Announcements for screen readers. |
| `data-comments-region="report-dialog"` | `conversation` | `_report/form`. |
| `data-comments-region="contextual-editor"` | `_comment/comment` | A reply or edit form. It also needs `data-comments-comment-uid`. |

### Comments and Replies

- The outermost element of `_comment/comment` needs `data-comments-comment`, `data-comments-comment-uid` and `data-comments-comment-id`. Its `article` must be a direct child with `id="comment-{{ comment.id }}"` and `tabindex="-1"`, which is how [links to a comment](docs:template-guides/rendering-comments#linking-to-a-comment) find it.
- The **Report this comment** dialog quotes the comment using the `comments__author-name` element inside that `article`, and the `comments__content` element that's a direct child of it.
- `_comment/replies` needs `data-comments-replies` and `data-comments-root-uid` on its outermost element, with replies in an `ol` as a direct child.
- `_conversation/list` needs the `comments__list` class on its `ol`, and `comments__load-more` around its link.

### Controls

Buttons and links are found through `data-comments-action`, most with `data-comments-comment-uid` as well:

| Action | Used On |
| --- | --- |
| `reply`, `edit`, `report` | Buttons on a comment. They should also have `data-comments-js-only`, and a `<noscript>` link to the matching `comment.hrefs` value (`flag` for `report`). |
| `remove` | The **Delete** button. `data-comments-confirm` holds its confirmation message. |
| `interact` | Vote buttons, with `data-comments-interaction-type` set to `upvote` or `downvote`. |
| `moderate` | Moderation buttons, with `data-comments-moderation-action`. |
| `subscribe` | The **Follow** button, whose `aria-pressed` says the current state. |
| `sort` | The sort links, each with `data-comments-sort` set to `newest`, `oldest` or `top`. A `select` with this action also works, using its value. |
| `load-roots` | The link to load more comments, with `data-comments-next`. |
| `load-replies` | The link to load more replies, with `data-comments-root-uid` and `data-comments-reply-limit`. |
| `toggle-replies` | The **Hide replies** button, with `data-comments-label-hide`, `data-comments-label-show` and `aria-controls`. |
| `toggle-held` | The moderator's **Hide held** button, with `data-comments-label-hide` and `data-comments-label-show`. |
| `copy-link` | The **Copy link** button, with the comment's anchor in `data-comments-link`. |
| `toggle-date` | The link around a comment's `time`, which switches to the date in `data-comments-full-date` when tapped. |
| `cancel-editor`, `cancel-report` | **Cancel** buttons. |

Anything with `data-comments-js-only` is hidden until the JavaScript has started, as long as you're using the bundled CSS or have kept that rule.

### Forms

These are intercepted when they're submitted, so keep their markers:

| Marker | Form |
| --- | --- |
| `data-comments-form="create"`, `"reply"` or `"edit"`, with `data-comments-operation-id` | `_editor/form`, whose value comes from `form`. |
| `data-comments-form="report"` | `_report/form`, which has to be inside a `<dialog data-comments-report-dialog>`. |
| `data-comments-form="subscribe"` | The form in `_conversation/subscribe`. |
| `comments__vote-form` class | Each vote form in `_comment/actions`. |
| `comments__moderate-form` class | Each moderation form in `_comment/actions` and `_comment/review`. |

`_editor/form` must keep its hidden fields and `{{ csrfInput() }}`, `{{ redirectInput(editor.redirectTemplate) }}` and `{{ craft.comments.protect(editor.ownerId, editor.siteId, editor.editCommentId) }}`, which are also what makes it work [without JavaScript](docs:template-guides/working-without-javascript). The comment box needs to be named `fields[comment]`, and guest fields `fields[name]` and `fields[email]`. A field error is placed after the input with the same name.

### Rich Authoring

Every form starts as the plugin's own editor, which is a `textarea` in a frame drawn to match the Editor's. It's what visitors without JavaScript post with, and all that plain authoring uses. The bundled script adds the character limit, a draft kept in the browser, ⌘↵ and a **Post** button that waits for some text. It looks for these parts of `_editor/form`, and leaves out whatever an override doesn't have:

| Part | What It's For |
| --- | --- |
| `comments__frame` | The frame around the box, which the script collapses to one line for a form with `data-comments-collapsible`. |
| `comments__frame-foot` | The row with **Cancel** and **Post**, where the draft note and character count go. |
| `comments__submit`, with a `comments__submit-label` inside | **Post**, which is greyed out until there's something to post. |
| `data-comments-draft-key`, `data-comments-limit` on the form | Where the draft is kept, and the character limit. |

When `editor.richAuthoring` is true, `_editor/custom-fields` also outputs the Editor as a `<verbb-editor>` element inside a `<template data-comments-editor>`, which keeps it inert until a visitor clicks into the box, opens a reply or edits a comment. Then the script loads the Editor from the form's `data-comments-editor-module` and `data-comments-editor-css` attributes, swaps it in for the box and brings across whatever was typed. Keep these if you override that template. This is a trimmed version of the default, which also sets the Editor's label, character limit, draft and submit button:

```twig
<input type="hidden" name="contentFormat" value="{{ editor.contentFormat }}">
<input type="hidden" name="fields[editorContent]" value="">
<template data-comments-editor>
    <verbb-editor
        data-editor-root
        data-options="{{ editor.getEditorOptions() | json_encode | e('html_attr') }}"
    ></verbb-editor>
</template>
<textarea class="comments__textarea comments__textarea-fallback" name="fields[comment]">{{ editor.initialPlainContent ?? '' }}</textarea>
```

`editor.getEditorOptions()` includes the comment being edited, and the bundled CSS hides the plain editor's parts once the Editor is running. Don't output `editor.getEditorOptions()` in a page that's cached for everyone, because it can hold a CSRF token for the signed-in visitor.

## Shared Caching

If you use [shared caching](docs:template-guides/rendering-comments#caching-comments), keep the `sharedCache` checks in `conversation`, `_conversation/header` and `_editor/region`. They leave out anything personal, such as the form and its CSRF token, which arrives once the page has loaded. Anything personal you add to an override should do the same.
