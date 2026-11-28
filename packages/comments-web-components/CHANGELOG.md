# Changelog

## Unreleased

## 4.0.1 - 2026-11-28

### Changed
- Released alongside Comments 4.0.1 with no changes to this package.

## 4.0.0 - 2026-11-25

### Added
- Add `@verbb/comments-web-components`, with `registerCommentsWebComponents()` and `<verbb-comments>`, which draws a Comments thread in the light DOM on any page through `@verbb/comments-browser`. It's released alongside the Comments plugin at the same version.
- Add the `owner-id`, `owner-uri`, `site-id`, `endpoint`, `load-mode`, `sort` and `theme` attributes, and the matching properties. Threads by URI get element IDs of their own. Changing which thread starts it again once, and `sort` and `theme` change the drawn thread in place.
- Add the `controller`, `themeConfig`, `parts`, `fields`, `labels`, `loadEditor` and `auth` properties, which redraw the thread when set, including properties set before the element is defined and an object changed in place and set again.
- Add Editor from npm by default, imported only when someone starts writing a rich comment.
- Add the thread's browser events from the element itself.
- Add starting and stopping with the page: a thread stops when its element is taken away and keeps going when it's moved, stops before Turbo Drive caches the page, keeps its controller through a morphing page swap, and is left alone by the plugin's own script, `Comments.mount()` included.
