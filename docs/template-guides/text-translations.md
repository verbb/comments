# Text & Translations

Every word Comments shows on your site goes through Craft's translations in the `comments` category. That covers the thread's buttons, headings and placeholders, the messages it shows after someone posts, votes or reports, and the errors the server sends back. The thread is drawn in the browser, but its wording still comes from your translations, because the thread's data carries every label already translated into the site's language. So you change the wording the same way you would for any Craft plugin, per site language and without any JavaScript.

## Changing the Wording

Suppose you want “Comment submitted for moderation.” to say something friendlier, and the empty thread to say something other than “Be the first to comment.” Create `translations/en/comments.php` in your project's root folder, next to `templates/`, and return the messages you want to change, keyed by the original English text:

```php
<?php

return [
    'Comment submitted for moderation.' => 'Thanks! A moderator will check your comment soon.',
    'Be the first to comment.' => 'Nobody has said anything yet.',
];
```

The next comment posted that waits for a moderator shows “Thanks! A moderator will check your comment soon.”, and a thread with no comments shows “Nobody has said anything yet.” Everything you leave out keeps its usual wording.

Copy each key exactly as it appears in `vendor/verbb/comments/src/translations/en/comments.php`, including its punctuation and curly apostrophes, such as `Delete this comment? This can’t be undone.` Keep any placeholder in braces, such as `{name}` or `{count}`, because the thread fills it in.

The folder is named after the site's language. `en` covers every English site, such as `en-US` and `en-GB`, and a file in a more specific folder, such as `translations/en-GB/comments.php`, wins for that language. For a site in another language, use its folder, such as `translations/de/comments.php`, and your messages replace Comments' own translations for that language. On a multi-site install, each site's thread uses its own site's language, so a German site gets the German wording on the same page template.

## What the Translations Reach

The thread's own wording is the largest part, listed in [The Thread's Labels](#the-threads-labels) below. Alongside it, the same file changes:

- What `craft.comments.render()` puts in the page before the thread is drawn. The heading is `{count, plural, =1{# comment} other{# comments}}`, an ICU plural message, and visitors without JavaScript see `Turn on JavaScript to read and post comments.`
- The messages the thread shows after an action, such as `Comment posted.`, `Comment deleted.` and `Report withdrawn.`
- The errors the server sends back, such as `This field can’t be filled in here.` beside a custom field, which also reach a [headless front end](docs:headless/data-api#errors) as each error's `message`.

A few things have their own place instead:

- **Custom field labels** come from the Comment field layout, and go through the `site` category, so translate them in `translations/<language>/site.php` as you would any field label.
- **Editor's own controls**, with [rich authoring](docs:feature-tour/rich-authoring) on, such as its toolbar, link and mention search and its GIF search, go through Editor's `editor` category, so translate them in `translations/<language>/editor.php`, keyed by the English text in `vendor/verbb/editor/src/translations/en/editor.php`.
- **Badge labels** and **Report Reasons** are settings, under **Comments** → **Settings** → **Appearance** and **Interactions**.
- **Emails** are edited under **Utilities** → **System Messages**, as described in [Customising Emails](docs:feature-tour/notifications-subscriptions#customising-emails).

## Checking a Translation

After saving the file, open a page with comments in a private window. The heading above the thread and the **Join the conversation** card both come from your translations, so a change to either shows straight away. A message that only appears after an action, such as the moderation one above, needs that action, so post a test comment on an entry with pre-moderation on.

If nothing changes, check the key against Comments' own file character by character. A straight apostrophe where Comments uses a curly one (`’`) is the most common reason a translation doesn't match.

The heading `render()` outputs is the one search engines and visitors without JavaScript see, so it's worth checking in the page's source as well. This changes it to count replies:

```php
<?php

return [
    '{count, plural, =1{# comment} other{# comments}}' => '{count, plural, =1{# reply} other{# replies}}',
];
```

For an entry with three comments, the mount point `{{ craft.comments.render(entry) }}` outputs then reads like this:

```html
<section id="comments-109-1" class="comments comments--reserve-2" aria-labelledby="comments-109-1-heading" data-comments-root …>
    <h2 id="comments-109-1-heading" class="comments__heading">3 replies</h2>
    <noscript><p class="comments__noscript">Turn on JavaScript to read and post comments.</p></noscript>
</section>
```

## Wording in a JavaScript App

A front end that draws the thread with Comments' JavaScript packages can also pass its own `labels`, keyed by the names in the table below, such as `{ beFirst: 'Nobody has said anything yet.' }`. Those win over the translated labels the thread's data carries, which is useful for a single page or an app that manages its own text. The [package docs](https://docs.verbb.io/comments/browser/configuring-threads) explain where `labels` goes for the bundled script, the web component and the React and Vue components. For anything that should change on every page, prefer a translation file, because it also reaches the server's messages and the page's heading.

## The Thread's Labels

These are the labels the thread's data carries, with the English text that's also their key in your translation file. The name is what JavaScript `labels` use, and the [package docs](https://docs.verbb.io/comments/browser/configuring-threads#labels) list a few more that only the renderer uses, which can only be changed that way.

| Name | English Text |
| --- | --- |
| `commentCountOne` | 1 comment |
| `commentCountOther` | {count} comments |
| `showComments` | Show comments |
| `showCommentsCount` | Show comments ({count}) |
| `loading` | Loading comments… |
| `loadMore` | Load more comments |
| `empty` | No comments yet. |
| `beFirst` | Be the first to comment. |
| `closed` | Comments are closed. |
| `sortLabel` | Sort comments |
| `sortNewest` | Newest |
| `sortOldest` | Oldest |
| `sortTop` | Top |
| `reply` | Reply |
| `replyTo` | Reply to {name}… |
| `replyingTo` | Replying to {name} |
| `cancelReply` | Cancel reply |
| `cancel` | Cancel |
| `post` | Post |
| `posting` | Posting… |
| `save` | Save comment |
| `edit` | Edit |
| `edited` | Edited |
| `delete` | Delete |
| `deleteConfirm` | Delete this comment? This can’t be undone. |
| `like` | Like |
| `likes` | Likes |
| `upvote` | Upvote |
| `downvote` | Downvote |
| `score` | Score |
| `report` | Report… |
| `reportTitle` | Report this comment |
| `reportReason` | What’s wrong with it? |
| `reportDetails` | Anything else? |
| `reportDetailsHint` | Optional. Only moderators see this. |
| `sendReport` | Send report |
| `reportSent` | Thanks for reporting this. A moderator will take a look. |
| `reportWithdrawn` | Report withdrawn. |
| `close` | Close |
| `moreActions` | More actions |
| `copyLink` | Copy link |
| `linkCopied` | Link copied. |
| `showReplies` | Show replies |
| `hideReplies` | Hide replies |
| `loadMoreReplies` | Load more replies |
| `guest` | Guest |
| `commentBy` | Comment by {name} |
| `unavailable` | Comment unavailable. |
| `awaitingApproval` | Waiting for approval. Only you can see this until a moderator approves it. |
| `editorPlaceholder` | Add to the conversation… |
| `editPlaceholder` | Edit your comment… |
| `yourComment` | Your comment |
| `writeSomethingFirst` | Write something first. |
| `tooLong` | Too long by {count} characters |
| `charactersLeft` | {count} characters left |
| `characterCount` | {count} of {limit} characters |
| `draftSaved` | Draft saved |
| `draftRestored` | Restored your draft from {time}. |
| `discardConfirm` | Discard what you’ve written? |
| `discard` | Discard |
| `uploadInProgress` | Please wait for your upload to finish. |
| `joinTitle` | Join the conversation |
| `signIn` | Sign in |
| `signInToComment` | Sign in to comment. |
| `signInOrGuest` | Sign in to comment and hear about replies, or post as a guest. |
| `commentAsGuest` | Comment as a guest |
| `postingAs` | Posting as {name} |
| `yourDetails` | Your details |
| `yourName` | Your name |
| `yourEmail` | Your email |
| `notShownPublicly` | Not shown publicly |
| `replyEmails` | Email me when someone replies. We’ll ask you to confirm your address first. |
| `follow` | Follow |
| `following` | Following |
| `followByEmail` | Follow by email |
| `error` | Something went wrong. Please try again. |
| `tryAgain` | Try again |
| `moderator` | Moderator |
| `moderatorView` | Moderator view. |
| `reviewInComments` | Review in Comments |
| `heldCount` | {count} held |
| `reportedCount` | {count} reported |
| `showHeld` | Show held |
| `hideHeld` | Hide held |
| `approve` | Approve |
| `markSpam` | Mark as spam |
| `notSpam` | Not spam |
| `remove` | Remove |
| `restore` | Restore |
| `keep` | Keep |
| `spam` | Spam |
| `gif` | GIF |
| `gifAdd` | Add a GIF |
| `gifSearch` | Search GIPHY |
| `gifResults` | GIFs |
| `gifRemove` | Remove GIF |
| `gifEmpty` | No GIFs found. |
| `gifPoweredBy` | Powered by GIPHY |
| `gifUnavailable` | GIFs can’t be loaded right now. |
| `foldedReports` | Comment marked as inappropriate |
| `foldedDownvotes` | Comment hidden due to low rating |
| `showFolded` | Show comment |
| `authorScore` | Total votes across this user’s comments |
| `comments` | Comments |
| `moderation` | Moderation |
| `onThisPage` | on this page |
| `hold` | Hold |
| `removedNote` | Removed · only moderators see this |
| `chooseOption` | Choose… |
| `addFiles` | Add files |
| `addFile` | Add a file |
| `removeFile` | Remove {name} |
| `retryUpload` | Try {name} again |
| `uploading` | Uploading… |
| `uploaded` | {name} uploaded. |
| `uploadFailed` | {name} didn’t upload. |
| `fileRemoved` | {name} removed. |
| `uploadLimits` | Up to {count} files, {size} each. |
| `uploadLimit` | Up to {size}. |
| `fileTypeNotAllowed` | {name} can’t be added here. Choose {types}. |
| `fileTooLarge` | {name} is larger than {size}. |
| `tooManyFiles` | You can add up to {count} files here. |

When a request for the thread fails before any of these arrive, the thread falls back to its own English for the few labels it needs to say so, such as **Try again**.
