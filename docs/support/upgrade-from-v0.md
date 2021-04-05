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