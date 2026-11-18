# Data API

The data API is a set of Craft controller actions under `comments/api/` that read and write comments as JSON. The thread `craft.comments.render()` draws uses it, and so do the JavaScript packages, so a front end you build yourself gets exactly what the standard thread gets. This page is the reference for those actions, their responses and their errors, for when you're writing your own client or checking what a request returned.

Most front ends don't call the API by hand. `@verbb/comments-core` handles signing in, spam protection, retries and paging for you, as the [package docs](https://docs.verbb.io/comments/core/client-transports) explain, and [Headless Overview](docs:headless/headless-overview) helps you choose an approach.

## Making Requests

Each action's URL is the API's address followed by its name. In Twig, `actionUrl('comments/api')` gives the address, such as `https://example.com/actions/comments/api`, so the thread for an entry is at `https://example.com/actions/comments/api/thread?ownerId=109&siteId=1`. Action URLs work the same whatever routes or element URIs your site has, and a site without pretty URLs gets `index.php?p=actions/comments/api/thread` instead.

Send `X-Comments-Api-Version: 1` with every request. A request asking for a version the server doesn't serve is refused with `unsupported_api_version`, and every response carries the same header and an `apiVersion` in its body, so your client can check it's reading the format it expects. The version only changes when the format changes in a way an older client can't read, and new fields can appear in a response at any time, so ignore keys you don't recognise.

Reads are `GET` requests with their parameters in the query string. Writes are `POST` requests with a JSON body and `Content-Type: application/json`. A read sent as a `POST`, or a write as a `GET`, is refused with `method_not_allowed`.

Every response is JSON with an `apiVersion` and a `status` of `ok` or `error`:

```json
{
    "apiVersion": 1,
    "status": "ok",
    "comments": []
}
```

### Who's Asking

The public reads, `thread`, `replies`, `comment` and `structured-data`, never look at who's asking, so they work from anywhere without signing in. Everything else identifies the person according to where the request comes from, as described in [Signing In Across Sites](docs:headless/signing-in-across-sites):

- On Craft's own origin, or another subdomain listed with **Shared Cookies**, Craft's session cookie identifies them, and writes need Craft's CSRF token in an `X-CSRF-Token` header. Get the token from the `session` read.
- On a different site, a member sends `Authorization: Bearer` with a member token, and a guest sends `X-Comments-Session` with a guest session token. These requests don't send cookies or a CSRF token.

Requests from another origin also send an `X-Comments-Profile` header naming which of these they use: `same-origin-browser` (the default when it's left out), `same-site-credentialed`, `cross-origin-member` or `cross-origin-public`. A profile that doesn't fit the request's origin is refused with `origin_not_allowed`.

### Write Operation IDs

Every write needs an `operationId`, a value you generate for each action, of 8 to 128 letters, numbers, `-` or `_`, such as a UUID. Comments keeps each write's result for ten minutes against the person and the `operationId`, and a repeat of the same write returns that result with `replayed: true` instead of acting twice. That makes it safe to retry a write after a dropped connection or a double click. Generate a new one for each new action, and reuse it only to retry the same one. A failed write isn't kept, so retrying it is carried out again.

## Reading Comments

### `thread`

Returns a page of an element's approved top-level comments, each with a preview of its replies, along with the discussion's details, the editor's public settings and the thread's labels. It's a public read.

| Parameter | Description |
| --- | --- |
| `ownerId` | The ID of the element the comments belong to. |
| `ownerUri` | The element's URI instead of its ID, for front ends that route by URI. Leave it empty for the homepage. |
| `siteId` | The site, which defaults to the current site. |
| `sort` | `newest`, `oldest` or `top`. Anything else uses the discussion's default. `top` is only offered while voting is on. |
| `limit` | How many top-level comments to return, from 1 to 50, defaulting to 20. |
| `cursor` | The `page.next` value from the previous page, to get the next one. |

```json
{
    "apiVersion": 1,
    "status": "ok",
    "threadVersion": "…",
    "conversation": {
        "uid": "9f3a2c1e-…",
        "ownerId": 109,
        "siteId": 1,
        "status": "open",
        "closedNotice": null,
        "commentCount": 42,
        "rootCount": 18,
        "sort": "newest",
        "sorts": ["newest", "oldest", "top"],
        "interactionMode": "like",
        "maxLength": 5000,
        "guestPolicy": { "allowed": true, "nameAndEmail": "required", "canVote": false, "canReport": true },
        "followAvailable": true,
        "reportReasons": [{ "value": "spam", "label": "Spam", "description": null }]
    },
    "editor": { "authoring": "rich", "maxLength": 5000, "richEditor": { "context": "comments.reply", "module": "…", "css": "…" } },
    "comments": [],
    "page": { "next": "eyJ2IjoxLCJrIjoicm9vdHMi…", "limit": 20 },
    "labels": { "reply": "Reply", "post": "Post" },
    "locale": "en-GB"
}
```

`conversation.uid` is `null` until the element's first comment. `commentCount` counts every public comment, and `rootCount` only the top-level ones. `labels` holds the thread's wording in the site's language, as listed in [Text & Translations](docs:template-guides/text-translations#the-threads-labels). `richEditor` is `null` with plain authoring.

### `replies`

Returns a page of a comment's approved replies, for when its preview doesn't hold them all. It's a public read.

| Parameter | Description |
| --- | --- |
| `parentUid` | The UID of the comment whose replies you want. |
| `limit` | How many replies to return, from 1 to 100, defaulting to 20. |
| `cursor` | The `next` value from the comment's `replies`, or from the previous page. |

The response has `parentUid` and `replies`, which holds `count`, `items` and `next`. Replies are always in the order of the **Frontend Comment Order** setting, whatever the thread's sort.

### `comment`

Returns one approved comment in its place in the thread, for a link to a single comment. Give its `uid`, or its `id`, the number in a permalink such as `#comment-1445`. It's a public read. The response has `targetUid`, the comment's `ownerId` and `siteId`, and `comment`, which is the top-level comment it sits under, with the replies leading down to it.

### `structured-data`

Returns the discussion as schema.org JSON-LD, for a server rendering a page on another site, as [Structured Data](docs:template-guides/structured-data) describes. It's a public read, and takes `ownerId` or `ownerUri`, `siteId`, `limit` (1 to 50, defaulting to 20), `includeAuthors` and `id`. The response has `structuredData`, the same object `craft.comments.structuredData()` outputs.

### `viewer`

Returns everything personal to the person asking, for the same window of comments a `thread` request returned. Send it alongside `thread`, with the same `ownerId` or `ownerUri`, `siteId`, `sort`, `limit` and `cursor`. You can also ask about other comments by `uids[]` or `ids[]`, 200 at most between them, such as one a link points to.

```json
{
    "apiVersion": 1,
    "status": "ok",
    "threadVersion": "…",
    "viewer": {
        "isGuest": false,
        "hasSession": true,
        "name": "Alex Morgan",
        "initials": "AM",
        "avatarUrl": null,
        "avatarTone": 2,
        "badges": [{ "type": "staff", "label": "Staff" }],
        "canComment": true,
        "canVote": true,
        "canReport": true,
        "isModerator": true,
        "blocked": false,
        "loginUrl": "/login",
        "follow": { "available": true, "following": false, "needsEmail": false }
    },
    "editor": {
        "draftKey": "comments-109-1-12",
        "guest": { "show": false, "required": true, "name": null, "email": null, "offerReplyEmails": true },
        "fields": { "comment": [], "reply": [] },
        "protection": { "version": 1, "manifest": {}, "expiresAt": 1762812345 },
        "richEditor": { "context": "comments.reply", "options": {} },
        "gifs": { "apiKey": "…", "rating": "g", "limit": 24, "language": "en" }
    },
    "comments": {
        "6c1d…": { "canReply": true, "canEdit": false, "canDelete": false, "canVote": true, "canDownvote": false, "canReport": true, "vote": null, "reported": false, "isAuthor": false, "moderation": null, "custom": {} }
    },
    "extraComments": [],
    "moderation": { "heldCount": 2, "reportedCount": 1, "reviewUrl": "https://example.com/admin/comments?source=needs-review" },
    "csrf": { "name": "CRAFT_CSRF_TOKEN", "value": "…" },
    "custom": {}
}
```

`comments` holds what this person can do with each comment in the window, keyed by UID, and how they voted. `editor.fields` describes the [custom fields](docs:template-guides/custom-fields) this person is asked for on a new comment and on a reply, after the field layout's conditions are applied, which is why it's private. `editor.protection` is the spam protection preparation the form needs to post. `editor.gifs` is what a GIF picker needs to search GIPHY, as described under [GIFs](#gIFs), or `null`. `extraComments` holds comments the public thread leaves out but this person may see, such as their own comment waiting for a moderator, and for moderators the held and reported ones, each in the full comment shape with its `state`. `moderation` is `null` for anyone who isn't a moderator.

A guest who hasn't done anything yet, with no session, gets a shorter answer that starts nothing and sets no cookie, with no CSRF token or protection until they start writing.

### `session`

Returns who's signed in and Craft's CSRF token, as `viewer` and `csrf`. Call it before the first write from a page that hasn't had a CSRF token yet. Given an `ownerId` or `ownerUri` and `siteId`, its `viewer` is the one for that element's discussion. On a different site, `csrf` is `null`, and a guest gets their guest session token in the `X-Comments-Session` response header.

### `protection`

Returns a fresh spam protection preparation, for posting from your own form, as described in [Displaying & Protecting Comments](docs:template-guides/displaying-protecting-comments#posting-from-your-own-code). It takes `ownerId` or `ownerUri`, `siteId`, `commentUid` when the preparation is for editing that comment, and `channel=headless` for a client that isn't a browser. The response has `protection`, with its `version`, `manifest` and `expiresAt`, and `csrf` as `session` gives it.

### `edit`

Returns what an author needs to edit their own comment, given its `uid`. That's the comment's content, including a rich comment's Editor document and its GIF as `content.gif`, and the values of the custom fields it was posted with, including ones that are only collected. It needs the person to be signed in and allowed to edit the comment.

### `mentions` and `upload`

These search for people to mention and upload files for [rich authoring](docs:feature-tour/rich-authoring), in the format Editor expects, applying the same sign-in rules as everything else. `upload` also takes files for an Assets custom field, with the field's handle as `field`, and answers with the uploaded file's `id`, which the comment's `fields` then sends. `upload` only accepts `POST`.

## Writing Comments

Each write is a `POST` with a JSON body, and every body has an `operationId`. A successful response includes an `announcement`, a short message in the site's language for the visitor, such as “Comment posted.”, and `replayed`.

| Action | Body | Response |
| --- | --- | --- |
| `create` | `ownerId` or `ownerUri`, `siteId`, `parentUid` for a reply, `content`, `guest` with `name`, `email` and `notifyReplies` for a guest, `fields`, `protection` | `comment`, `moderationState` (`approved` or `pending`), `conversation.commentCount` |
| `update` | `uid`, `content`, `fields`, `protection` | `comment`, `moderationState` |
| `delete` | `uid` | `uid`, `conversation.commentCount` |
| `vote` | `uid`, `vote` as `upvote`, `downvote` or `null` to take a vote back | `comment` with its `interaction` and `viewer` |
| `report` | `uid`, `reason`, `details` (up to 2,000 characters), or `withdraw: true` | `comment` with its `viewer` |
| `follow` | `ownerId` or `ownerUri`, `siteId`, `following`, and `email` for a guest | `viewer.follow`, `outcome` |
| `moderate` | `uid`, `action` (`approve`, `spam`, `not-spam`, `remove`, `keep`, `restore` or `hold`), `reason`, `note` | `comment` with its `moderation` |

`content` is `{ "format": "plain", "text": "…" }` for plain text, or `{ "format": "rich", "document": … }` with Editor's document for a rich comment, either with an optional `gif`, as described under [GIFs](#gIFs). `fields` holds custom field values keyed by handle, for the fields this person is asked for. `vote` and `report` set a state rather than toggling, so sending the same one twice is harmless. `delete` is an author deleting their own comment, while a moderator's removal is `moderate` with `remove`.

This creates a comment from a signed-in member on Craft's own site, with a CSRF token from `session` and evidence from Protect, as [Displaying & Protecting Comments](docs:template-guides/displaying-protecting-comments#posting-from-your-own-code) shows how to get:

```http
POST /actions/comments/api/create HTTP/1.1
Content-Type: application/json
Accept: application/json
X-Comments-Api-Version: 1
X-CSRF-Token: …

{
    "operationId": "3b1f8a52-6a0e-4b3c-9d7e-1c2b3a4d5e6f",
    "ownerId": 109,
    "siteId": 1,
    "content": { "format": "plain", "text": "Ice and rest for a week, then build back up slowly." },
    "protection": { "version": 1, "preparation": "…", "honeypot": {}, "javascript": "…", "challenges": {} }
}
```

### GIFs

While [GIFs from GIPHY](docs:feature-tour/rich-authoring#gIFs-from-giphy) are on, `viewer` gives someone who can comment `editor.gifs`: the site's GIPHY `apiKey`, the highest `rating` to search for, the result `limit` and the site's two-letter `language`. `edit` gives the same as its `editor.gifs`. GIPHY's terms require your front end to search GIPHY from the visitor's browser with these, and to use what it returns unchanged, with “Powered by GIPHY” beside the results.

To post a GIF, add `gif` to `content` with the address GIPHY gave for it, and its size if you know it:

```json
{ "format": "plain", "text": "Same here", "gif": { "url": "https://media2.giphy.com/media/…/giphy.webp?cid=…", "width": 480, "height": 270 } }
```

A comment can be just a GIF, with empty `text`. The address must be one of GIPHY's own, over HTTPS, and a GIF is refused while GIPHY is off, both with a `validation_failed` error on `content`. On `update`, leaving `gif` out keeps the comment's GIF, so a client that doesn't know about GIFs can't drop one, and `"gif": null` takes it off, which is allowed even while GIPHY is off. Changing a comment's GIF marks it as edited.

### `member-token` and `revoke-member-tokens`

These are for your front end's server, not a browser. They're signed with the Member Token Key and issue or revoke member tokens, as [Signing In Across Sites](docs:headless/signing-in-across-sites#asking-for-a-member-token) explains.

## The Comment Shape

Every response that includes a comment uses the same shape:

```json
{
    "uid": "6c1d…",
    "parentUid": null,
    "depth": 0,
    "state": "approved",
    "content": { "format": "rich", "html": "<p>Ice and rest for a week…</p>", "text": "Ice and rest for a week…", "gifUrl": null, "gifWidth": null, "gifHeight": null },
    "author": { "name": "Alex Morgan", "initials": "AM", "avatarUrl": "https://example.com/uploads/alex.jpg", "avatarTone": 3, "isGuest": false, "badges": [{ "type": "owner", "label": "Author" }], "score": 12 },
    "folded": null,
    "createdAt": "2026-11-09T21:04:00+00:00",
    "editedAt": null,
    "display": { "timeAgo": "2 hours ago", "fullDate": "9 November 2026, 9:04 pm" },
    "permalink": "https://example.com/news/trail-running-knees#comment-1445",
    "fields": [{ "handle": "location", "label": "Where are you?", "type": "text", "placement": "inline", "value": "Rotorua", "text": "Rotorua" }],
    "interaction": { "upvotes": 3, "downvotes": 0 },
    "custom": {},
    "replies": { "count": 5, "items": [], "next": "eyJ2IjoxLCJrIjoicmVwbGllcyI…" }
}
```

| Key | Description |
| --- | --- |
| `state` | `approved`, or `placeholder` for a comment that's no longer shown but whose approved replies still are. A placeholder has no content, author or dates. In `viewer` and moderation responses, a comment can also be `pending`, `held`, `spam`, `reported` or `removed`. |
| `content` | The comment's `format`, its plain `text`, and for a rich comment its `html`, which Editor has already sanitised on the server. `html` is `null` for a plain comment. Escape `text` when you show it. `gifUrl` is the address of a GIF from GIPHY shown with the comment, with its `gifWidth` and `gifHeight` when they're known. All three are `null` for a comment without a GIF, and `gifUrl` is `null` for any address that isn't `http` or `https`. |
| `author` | The public name, initials, avatar and badges, the same for everyone. A guest's name falls back to “Guest”. There's never an email address, user ID or anything else that identifies the commenter. `score` is a signed-in author's total votes across their approved comments while [`showAuthorScore`](docs:get-started/configuration#showAuthorScore) is on, and `null` otherwise. |
| `folded` | `reports` or `downvotes` when the thread folds the comment away, after [`flaggedCommentLimit`](docs:get-started/configuration#flaggedCommentLimit) open reports or [`downvoteCommentLimit`](docs:get-started/configuration#downvoteCommentLimit) downvotes, and `null` otherwise. A folded comment is still published, so show a note and a way to read it in place of its content. |
| `display` | The date formatted in the site's language, as `timeAgo` and `fullDate`. `timeAgo` is `null` when **Show Time Ago** is off. Use `createdAt` to format it yourself. |
| `permalink` | A link to the comment on its element's page, or only the `#comment-…` anchor when the element has no URL. |
| `fields` | The comment's custom field values that are shown on comments, each with its `handle`, `label`, `type`, `placement` (`details` or `inline`), raw `value` and formatted `text`, as [Custom Fields](docs:template-guides/custom-fields#showing-values-in-your-own-code) describes. Values that aren't shown never appear here. |
| `interaction` | The comment's upvotes and downvotes, or `null` when voting is off. |
| `custom` | Your own data, added with the [`defineCommentData`](docs:developers/events#the-defineCommentData-event) event. |
| `replies` | The number of approved replies, a preview of up to three as `items`, and a cursor for the rest. Previews go four levels deep, and deeper replies come through `replies`. |

## Paging and Sorting

Pages are fetched with cursors rather than page numbers. Each page of a `thread` has `page.next`, and each comment's `replies` has `next`, which you send back as `cursor` to get the next page, or `null` when there are no more. Cursors are signed, so a changed one is refused with `invalid_cursor`, and a cursor only works with the sort and limit it was made with.

Newest and oldest sorts are stable from page to page, because ties are broken by each comment's ID. Under the `top` sort, a comment's votes can change between pages, so it can occasionally appear twice or be skipped. The thread de-duplicates comments by their UID, and so should your own client.

These limits apply to everyone, signed in or not:

| Limit | Value |
| --- | --- |
| Top-level comments per page | 1 to 50, defaulting to 20 |
| Replies per `replies` page | 1 to 100, defaulting to 20 |
| Reply preview per comment | 3 |
| Levels of replies in one response | 4 |
| Comments in one response, counting replies | 200 |

A `limit` outside its range is refused with `limit_exceeded`.

## Caching

The public reads are the same for every visitor, so they can be cached by a CDN. They answer with `Cache-Control: public, max-age=0, s-maxage=60, must-revalidate`, which lets a shared cache keep them for a minute while browsers check each time, and `CDN-Cache-Control: max-age=60, stale-while-revalidate=300`, which lets a CDN serve a copy up to five minutes older while it fetches a fresh one. They also carry an `ETag`, so a client that sends `If-None-Match` gets a `304 Not Modified` when nothing has changed. They send `Access-Control-Allow-Origin: *`, so any site can read them, never set a cookie, and only vary by `X-Comments-Api-Version`. Craft's data cache holds them on the server until something changes, or for an hour at most. Through a CDN, a new comment can take a minute to reach other visitors, or up to five more while the CDN fetches a fresh copy.

Everything else, including any error, answers with `Cache-Control: private, no-store`, so nothing personal is ever cached.

### Setting `@web` So Reads Can Be Cached

All of this depends on Craft knowing your site's address from its configuration. When Craft's `@web` alias isn't defined, which is the case in a new Craft project, Craft works it out from each request's own host, and the comment links in a response could then differ from one request to the next. Comments plays safe, and answers its public reads with `Cache-Control: private, no-cache` and no `CDN-Cache-Control`, so no CDN or shared cache keeps them. It also builds every read afresh rather than using Craft's data cache, so each one counts against the 120 built reads a minute described below.

Define `@web` in `config/general.php`, from the same environment variable your site's base URL uses:

```php
use craft\config\GeneralConfig;
use craft\helpers\App;

return GeneralConfig::create()
    ->aliases([
        '@web' => App::env('PRIMARY_SITE_URL'),
    ]);
```

Give each site under **Settings** → **Sites** an explicit base URL too, such as `$PRIMARY_SITE_URL`, because a base URL built from `@web` now always uses the address above, which is wrong for a site on another host. To check it's working, open a page with comments, find the request to `thread` in your browser's developer tools and look at its response headers. `Cache-Control: public, max-age=0, s-maxage=60, must-revalidate` means the reads can be cached, and `private, no-cache` means `@web` is still being worked out from the request.

### Rate Limits

Each visitor's address can make 1,200 public reads a minute, of which 120 can be reads Comments has to build rather than serve from its cache, before getting `rate_limited`. Without `@web` defined, every read is built, so the limit is 120. A CDN in front of your site is the way to serve a busy thread to many visitors. `viewer` allows 600 a minute, `session` and `protection` 120, mention searches 120 and uploads 30. Spam protection limits how often comments, votes and reports can be posted, as described in [Anti-Spam](docs:feature-tour/anti-spam#rate-limits). A refusal includes a `Retry-After` header with the seconds to wait.

## What the API Keeps Private

The data API never returns a commenter's email or IP address, user agent, session, user ID or the ID that links a guest's activity. Comments waiting for a moderator, held and reported comments, report counts and moderation controls only come through `viewer`, only to the people allowed to see them, and are never cached. Custom field values that aren't shown only appear in the `edit` read, for the comment's own author.

Data you add with the [`defineCommentData`](docs:developers/events#the-defineCommentData-event) event is public and cached with the thread, so it must never depend on who's asking. Data that's personal to the visitor belongs in [`defineViewerData`](docs:developers/events#the-defineViewerData-event), which only reaches the `viewer` response.

## Errors

An error answers with an HTTP status and one error in `errors`:

```json
{
    "apiVersion": 1,
    "status": "error",
    "errors": [
        {
            "category": "protection",
            "code": "protection_expired",
            "message": "Please refresh the form and complete verification before trying again.",
            "fieldErrors": {},
            "recoverable": true,
            "retryAfter": null
        }
    ]
}
```

`message` is written for the visitor, in the site's language. `fieldErrors` holds messages keyed by field, as `content`, `guest.name`, `guest.email` and `fields.<handle>` for a custom field. `recoverable` is `true` when the same action can succeed after the visitor fixes something or your client gets a fresh token or preparation, and `retryAfter` is the seconds to wait before trying again, when there is one.

Handle errors by their `code`, and fall back to the `category` for a code your client doesn't know, as new codes can be added.

| Code | Category | Status | When It Happens |
| --- | --- | --- | --- |
| `validation_failed` | `validation` | 422 | Something sent isn't valid, with the details in `fieldErrors`, including a custom field this person can't fill in |
| `file_type_not_allowed` | `validation` | 422 | An upload isn't one of the file types allowed |
| `file_too_large` | `validation` | 422 | An upload is over the size limit |
| `too_many_files` | `validation` | 422 | An upload is over a field's file limit, or the 20 files a person can have waiting |
| `protection_required` | `protection` | 422 | A write needs spam protection evidence and didn't send any |
| `protection_expired` | `protection` | 422 | The protection preparation was used, ran out or belongs to another form, so fetch a new one |
| `challenge_failed` | `protection` | 422 | The challenge provider's check failed |
| `possible_spam` | `protection` | 422 | Spam protection refused the comment |
| `protection_unavailable` | `protection` | 503 | Spam protection couldn't check the comment, and the site refuses rather than holds it |
| `rate_limited` | `rate` | 429 | Too many requests, with `Retry-After` |
| `sign_in_required` | `auth` | 401 | The action needs someone signed in |
| `token_expired` | `auth` | 401 | A member token has run out, so fetch a new one |
| `token_revoked` | `auth` | 401 | A member token was revoked or isn't known |
| `session_expired` | `auth` | 401 | A guest session token is missing, changed or has run out, so call `session` again |
| `signature_invalid` | `auth` | 401 | A `member-token` or `revoke-member-tokens` request isn't signed correctly |
| `origin_not_allowed` | `auth` | 403 | The request's origin isn't allowed the way it's signing in |
| `csrf_failed` | `auth` | 403 | A write identified by cookies didn't send a valid CSRF token |
| `user_not_eligible` | `auth` | 403 | A member token was asked for someone who doesn't exist or isn't active |
| `forbidden` | `policy` | 403 | This person isn't allowed to do this |
| `blocked` | `policy` | 403 | This person is blocked from the discussion |
| `closed` | `policy` | 403 | The discussion is closed |
| `edit_window_passed` | `policy` | 403 | The **Author Edit Window** has passed |
| `not_found` | `state` | 404 | The element or comment doesn't exist or can't be shown |
| `conflict` | `state` | 409 | The comment changed since it was loaded, such as another moderator acting on it first |
| `invalid_request` | `request` | 400 | The request can't be read, such as the wrong type for a value |
| `limit_exceeded` | `request` | 400 | A `limit`, or the number of comments asked about, is over its maximum |
| `invalid_cursor` | `request` | 400 | A cursor was changed, or is for another list, sort or limit |
| `method_not_allowed` | `request` | 405 | A read was sent as a `POST`, or a write as a `GET` |
| `unsupported_api_version` | `request` | 400 | The request asked for an API version the server doesn't serve |
| `server_error` | `server` | 500 | Something unexpected went wrong, with the details in Craft's log rather than the message |

## Reading Through GraphQL

The same data is available through Craft's GraphQL API, through the `commentsThread`, `commentsReplies`, `commentsComment` and `commentsViewer` queries, each returning the same JSON as the matching read under `data`. Writes go through Comments' GraphQL mutations. [GraphQL](docs:graphql/queries) covers the schema, and `@verbb/comments-core` can use GraphQL in place of these actions with its GraphQL transport, as the [package docs](https://docs.verbb.io/comments/core/client-transports) explain.
