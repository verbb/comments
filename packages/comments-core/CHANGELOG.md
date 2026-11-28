# Changelog

## Unreleased

## 4.0.1 - 2026-11-28

### Changed
- Released alongside Comments 4.0.1 with no changes to this package.

## 4.0.0 - 2026-11-25

### Added
- Add `@verbb/comments-core`, the data layer for Comments front ends, with no DOM, so it runs in browsers, Node, workers and server components. It's released alongside the Comments plugin at the same version.
- Add `createCommentsClient()` for the data API (`comments/api/*`), over REST or, through `@verbb/comments-core/graphql`, GraphQL, with the API version check, typed errors whose message is always safe to show a visitor, an `operationId` on every write that's resent once with the same ID after a dropped connection, and `cookieSession()` sign-in for Craft's own domain and for another subdomain of the same site.
- Add `memberToken({ getToken })` and `guestSession()` for front ends on a different site from Craft. A member's short-lived token goes in `Authorization: Bearer` and is fetched again before it expires or once the server refuses it, and a guest's session token goes in `X-Comments-Session`, kept in memory or, if you choose, `sessionStorage`.
- Add `createThreadStore()`, which loads a thread and the visitor's own data together, pages roots and replies by cursor without duplicates, places a visitor's pending comments and a moderator's held ones, clears them when the person signs out, fetches a thread again when a cache served one older than the visitor's own data, follows links to comments only the visitor may see, and handles posting, editing, deleting, voting, reporting, following and moderating, running actions on the same comment in the order they were made.
- Add links to a comment by the number in its permalink: `client.comment({ id })`, `ids` on `viewer` reads, and `store.loadComment()` taking a UID, a number or an answer already fetched. The store asks `viewer` about at most 100 comments a read.
- Add `createProtectionFlow()`, which keeps a Protect preparation fresh in a page left open, uses each one once, and fetches a new one and retries once when the server says it expired. It imports `@verbb/protect` the first time a preparation arrives, rather than with the thread store, so a front end whose visitors only read never downloads it.
- Add the `signature_invalid` and `user_not_eligible` error codes, which only a site's own server sees when it asks for member tokens.
- Add the `blocked` error code for a commenter blocked from a discussion, handled like `forbidden`: never retried, and the thread store shows the refused action, or for `blocked` every action, as unavailable.
- Add TypeScript types for every response and request, and the JSON Schema generated from them at `@verbb/comments-core/schema/data.json`.
- Add custom field uploads: `client.upload()` takes an Assets field's `field` with its thread (`ownerId` or `ownerUri`, `siteId`, `parentUid`) or `commentUid`, and a guest's `protection` evidence, answered as `FieldUploadResponse`. Guests' sign-in no longer lists `upload` as unavailable, since guests can upload to the fields they're offered.
- Add stage M's shapes to the types and schema: `placement`, `accept`, `maxFiles`, `maxSize` and `meta` on field descriptions, `edit`'s `editor.fields` and file values (`EditFileData`), a shown file's `url` being null without public URLs, and the `comment` read's `ownerId` and `siteId`.
- Add `upload` as a protection flow action (`EvidenceContext`), for a guest's custom field uploads, which each use a preparation of their own.
- Add `jsonLdScript()`, which writes structured data as text that's safe inside `<script type="application/ld+json">`, with `<`, `>`, `&`, U+2028 and U+2029 escaped, so a comment containing `</script>` can't end the script.
- Add `ThreadStoreEventMap`, which types each event a thread store emits. `store.on()` takes only those events, and gives each every `detail` key the browser event of the same name has, such as `commentUid`, `commentId` and `vote`, with a few of its own, such as `cursor` and `count`.
- Add `COMMENTS_SESSION_HEADER`, the name of the header a guest's session token travels in on another site.
- Add `meta` to a custom field's description, holding anything else its field type gives for a site's own field override.
- Add `badges` to the viewer's own data, in the same shape as a comment author's, for the "Posting as" line.
- Add GIFs from GIPHY to the types and schema: `gifWidth` and `gifHeight` beside a comment's `gifUrl`, `editor.gifs` (`GifsData`) on `viewer` and `edit` reads, the `edit` read's `content.gif`, and an optional `gif` on a write's `content`, where an update leaves the comment's GIF alone without it and takes it off with `null`.
- Add Comments 3's folded comments and author scores to the types and schema: `folded` on every comment (`reports`, `downvotes` or `null`, as `FoldReason`) and `score` on its author.
