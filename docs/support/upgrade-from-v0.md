# Upgrading from v0.x
The below items are things to be aware of, when upgrading from Craft 2 (v0.x) to Craft 3 (v1.x).

## Variables

Old | New
--- | ---
`craft.comments.elements(elementType, criteria)` | Removed.
`craft.comments.all(criteria)` | `craft.comments.fetch(criteria).all()`
`craft.comments.total(elementId)` | Use `craft.comments.fetch().elementId(elementId).count()` instead.
`craft.comments.form(elementId, criteria)` | `craft.comments.render(elementId, criteria)`
`craft.comments.isClosed(elementId)` | Removed.
`craft.comments.getActiveComment()` | Removed.
`craft.comments.getSettings()` | Removed.

## Structure UID
If you need to re-attach the structure, for whatever reason, you can use the following console command to re-assign the structure's UID to Comments' settings.

```
./craft comments/base/resave-structure
```

## Templates
There's no longer separate templates for each status. Where you might have had:

- `comment-approved.html`
- `comment-pending.html`
- `...etc`

This is now handled through a single `comment.html` template.

Templates received a major overhaul in Comments 1.x, and it's best to refer to the [latest templates](https://github.com/verbb/comments/blob/craft-3/src/templates/_special/comments.html). There are too many changes to list here, and the fundamental structure and implementation of the updated templates have changed too much to guide through changes.

## Github
You can always view the Craft 2 version on [Github](https://github.com/verbb/comments/blob/craft-2/comments)