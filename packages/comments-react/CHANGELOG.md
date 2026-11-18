# Changelog

## Unreleased

### Added
- Add `@verbb/comments-react`, React 19 components and hooks for Comments threads, released alongside the Comments plugin at the same version.
- Add `<CommentsThread>`, which renders only the mount point on the server, marked so the plugin's own script leaves it alone, and draws the thread with `@verbb/comments-browser` in the browser, with props for the owner, site, endpoint, load mode, sort, page size, theme, `darkSelector`, `themeConfig`, part and field overrides, labels and the count, and an event prop for every browser event, such as `onCommentCreated`.
- Add `<CommentsProvider>` for the `endpoint`, sign-in (`auth`), `siteId`, `themeConfig`, `parts`, `fields`, `labels`, `theme`, `darkSelector` and `loadEditor` every thread shares, with Editor loaded from npm and registered by default. A new sign-in provider of the same kind is taken over without starting the thread again, and its `reset()` on sign-out clears the thread's private state.
- Add component part and field overrides, rendered through portals so they keep the app's context, state and router, and follow their comment as the thread redraws, each kept to its own part if it throws, with `renderDefaultInto()` for Comments' own markup and `templatePart()` for cheap renderer functions.
- Add `useCommentsThread()` for a thread's state and actions from `@verbb/comments-core`, `useCommentForm()` for posting from your own form with the site's spam protection and one operation ID across retries, and `useCommentsEvent()` for Comments' events.
- Add `fetchCommentsCount()` and `fetchStructuredData()` from `@verbb/comments-react/server`, which import nothing that touches the page, and `jsonLdScript()` to write the structured data safely into a `<script>`.
- Add `loadMode` to `<CommentsThread>`, read when the thread starts. A new `sort` re-sorts a loaded thread in place, keeping a half-written comment and the reader's place, as `<verbb-comments>` does, a thread whose `sort` is taken away starts again with the default, and a new `limit` starts the thread again.
- Add `autoLoad` to `useCommentsThread()`, which loads the thread as the component mounts unless it's false.
- Add `CommentsProviderSettings`, the type of the settings `<CommentsProvider>` shares, named apart from `@verbb/comments-browser`'s `CommentsSettings`.
- Add `ThreadStoreEventMap` types to `useCommentsEvent()` with `{ thread }`, so a store event's `detail` has the same keys as the browser event's.
