# Changelog

## 1.1.6 - 2019-04-27 [CRITICAL]

### Fixed
- Fix comment-saving always assuming comments are from the current user.
- Fix lack of validation for editing a comment from another user.
- Fix JS bug where a user could act on another users’ comment from the front-end in some cases.

## 1.1.5 - 2019-04-24

### Added
- Add support for max length. (thanks @ilicmarko).

### Changed
- Prevent whitespace being submitted as comments. (thanks @ilicmarko).
- Clear form if comment or reply was successful. (thanks @ilicmarko).
- Comments from a deleted user now show as from '[Delete User]'.

### Fixed
- Fix error when showing a comment from a deleted user.

## 1.1.4 - 2019-04-21

### Changed
- Use Craft's date utilities for getting translatable time diff.
- Remove Carbon dependancy.

### Fixed
- Fix error with `sql_mode=only_full_group_by`.
- Fix error with auto close days enabled.

## 1.1.3 - 2019-04-07

### Added
- Add Dutch language file. (thanks @skoften).

### Changed
- Improve comments sidebar (Don’t include deleted elements, Add config limiter, Use group-by query).

## 1.1.2 - 2019-03-19

### Added
- Add override notice for settings fields.

### Changed
- Allow `render()` to override comments query properly.

### Fixed
- Fix commentDate not being set in some cases.

## 1.1.1 - 2019-03-02

### Fixed
- Fixed more migration cases from Craft 2.

## 1.1.0 - 2019-03-01

### Added
- Add Google reCAPTCHA v3 for better spam protection.
- Add better support for multi-site and the owner element (the commented-upon element).

### Fixed
- Fixed some migration cases from Craft 2.

## 1.0.7.1 - 2019-02-25

### Fixed
- Add more checks to migrations.

## 1.0.7 - 2019-02-24

### Fixed
- Add limit to comments index for large amounts of comments.
- Fix forcing the current time on any new comment’s `commentDate`.
- Add more checks to migrations.

## 1.0.6.3 - 2019-02-21

### Fixed
- Fix validation being triggered when comments re-saved through queue

## 1.0.6.2 - 2019-02-20

### Fixed
- Fix migration from 1.0.6 (again).

## 1.0.6.1 - 2019-02-20

### Fixed
- Fix migration from 1.0.6.

## 1.0.6 - 2019-02-20

### Added
- Add email/name to sort in the CP.
- Add searching for comment, name and email in the CP.

### Changed
- Template now produces `#cc-w-{{ elementId }}` as opposed to a random number. (thanks @skoften).

### Fixed
- Fix only being able to vote on a comment once.
- Fix only being able to flag a comment once.
- Fix missing `owner` query function.
- Refactor CP routes and fix “save and continue route”, fixing an error.
- Fix error in the CP when trying to refer to an element has been deleted.

## 1.0.5 - 2019-01-23

### Added
- Added support for Emoji's in comments.

### Fixed
- Fix incorrect commentDate column in flags and votes tables.
- Fix error thrown when author emails couldn’t be sent.
- Fix comment not being cleared once submitted (thanks @skoften).
- Fix error in the CP when trying to refer to an element has been deleted.

## 1.0.4 - 2018-11-06

### Fixed
- Fix SQL error when trying to order by `commentDate`.
- Fix error with multisite saving edit form (thanks @colinmeinke).

## 1.0.3 - 2018-10-25

### Fixed
- Improve comments index to show each commented-on element.
- Fix SQL bug in comments index.
- Fix error on settings for Craft Solo.
- Properly minify in-built JS.
- Refactor and improve translations in front-end JS.
- Fix not passing in siteId for front-end requests, meaning multisite wasn't working.
- Fix author photo not working (thanks @stevenvandemoortele).
- Added try/catch block to prevent server error (thanks @skoften).
- Fix template bug (thanks @stevenvandemoortele).

## 1.0.2 - 2018-09-17

### Fixed
- Fix missing element methods
- Fix missing elementId in query for `render()`
- Fix `commentDate` not being set after new comment is created (causing an error to be thrown)
- Add flag user feedback
- Fix migration for Craft 2 not renaming `comments` table


## 1.0.1 - 2018-08-26

### Changed
- Switch from `dateCreated` to `commentDate`
- Remove on-delete cascades for owner users and elements. If a user or owner element is deleted, their respective comments won't also be deleted.

### Fixed
- Ensure structure is cleaned up after uninstall
- Removed craft 2 plugin that stuck around (wha?)


## 1.0.0 - 2018-08-25

### Added
- Craft 3 release, major refactor and rewrite from the ground up.
- Brand new, single-line include via `craft.comments.render()` which renders comments and form. Includes front-end CSS and Ajax-driven vanilla JS and is designed to be a drop-in solution to add comments to your site. You can of course still override or replace all aspects of the front end resources.

### Changed
- `craft.comments.all()` has been deprecated. Use `craft.comments.fetch()` instead.
- `craft.comments.form()` has been deprecated. Use `craft.comments.render()` instead.
- Simplified templates - find them in `comments/src/templates/_special`.
- Countless bug fixes, and improvements!

## 0.4.9 - 2017-11-04

### Fixed
- Minor fix for sidebar icon.

## 0.4.8 - 2017-10-17

### Added
- Verbb marketing (new plugin icon, readme, etc).
- Add plugin settings variable.

### Changed
- Improved `onTrashComment` and `onBeforeTrashComment` events.
- Swap comment output macro for native `{% nav %}` tag - better supports element queries such as level.

## 0.4.7 - 2016-10-09

### Fixed
- Fixed template error for permissions settings for Craft Personal/Client versions.
- Fixed `EntryModel.owner` issue where checking for non-entry elements being commented on.

## 0.4.6 - 2016-09-23

### Added
- Added ability to disable spam checks.

### Fixed
- Fixed support for adding comments to non-entry elements.
- Fixed typo in edit comment controller action.

## 0.4.5 - 2016-09-02

### Added
- Added support to permanently delete comment from within the CP.
- Added user permissions to allow edit or trash of comments for other users.
- Added `canEdit()` and `canTrash()` method to Comment Model.

### Changed
- Changed current 'delete' terminology to 'trashed', which is technically more correct.
- `onBeforeDeleteComment` event changed to `onBeforeTrashComment`.
- `onDeleteComment` event changed to `onTrashComment`.
- `deleteActionUrl` template action is now `trashActionUrl`.
- `comments/delete` controller action is now `comments/trash`. Raises a deprecation notice.
- Commenting permissions now only support native Craft elements.

### Fixed
- Fixed case where another logged in user could edit/save/delete comments from another user.
- Fixed permissions errors when checking for elements.

## 0.4.4 - 2016-06-24

### Fixed
- Fixed issue with comments index showing comment id, not user name.
- Move Save Comment button above content on edit comment page (for consistent Craft behaviour).

## 0.4.3 - 2016-06-23

### Added
- Permissions to control when elements are comment-able.
- New fieldtype to provide element-specific control on commenting.
- Added support for [craft.spamguard](https://github.com/selvinortiz/craft.spamguard).

### Fixed
- Fixed error in comments element index - introduced in 0.4.2.

## 0.4.2 - 2016-04-02

### Added
- Added `element` attribute to CommentModel.
- Added `parent` attribute to CommentModel.
- Added `comment` attribute to FlagModel.
- Added `user` attribute to FlagModel.
- Added `comment` attribute to VoteModel.
- Added `user` attribute to VoteModel.

### Changed
- Code formatting.

## 0.4.1 - 2016-04-02

### Added
- Added `author` attribute to CommentModel.
- Added `isGuest()` method to CommentModel.
- Added email notifications for element authors and for other commenters when replying.

### Changed
- Comments are now a little tidier in the element index table.
- Filter via Status in control panel.

## 0.4.0 - 2016-03-16

### Added
- Craft 2.5+ support, including release feed and icons.
- Comments can now be edited through the front-end.
- Added support for querying comments via votes
- Added `onBeforeSaveComment` hook.
- Added `onSaveComment` hook.
- Added `onBeforeDeleteComment` hook.
- Added `onDeleteComment` hook.
- Added `onBeforeFlagComment` hook.
- Added `onFlagComment` hook.
- Added `onBeforeVoteComment` hook.
- Added `onVoteComment` hook.

### Changed
- Reorganise settings pages - separate flag/voting.
- Flagging comments and voting can be enabled/disabled altogether.
- Comments now require moderation by default.
- Lots of refactoring and reorganising.
- Removed Comments fieldtype.
- Comments are shown in hierarchical structure when in control panel.
- Full ajax and non ajax support for endpoints.
- Renamed `comments/add` to `comments/save`.
- Simplified and improved templates for single template tag.
- Simplified example code for ajax and non ajax handling.
- Comment forms handle validation properly, actual error messages and remembers text on validation.

### Fixed
- Fixed `comments/edit` endpoint to include validation/security.

## 0.3.8 - 2015-12-31

- Fixes for field settings messing things up. **Notice** The Comments fieldtype will soon be removed in favour of centralized permissions.
- Other bugfixes and improvements.
- Ensure works well on Craft Client/Craft Personal.

## 0.3.7 - 2015-12-31

- Added the ability to get total comments per element id using `{{ craft.comments.total(elementId) }}`.

## 0.3.6 - 2015-12-31

- Added security measures. You can now provide values to check against _all_ attributes of a comment and action accordingly. Comments can be marked as pending, spam, or simply not allowed to be submitted. See [Security](https://github.com/verbb/comments/wiki/Security)
- Support sorting comments by votes.
- Added optional flood-control settings. Enforces minimum time to wait between posts. Works for anonymous and logged-in users.

## 0.3.5 - 2015-12-31

- Added Schema Tags to templates (see [#19](https://github.com/verbb/comments/issues/19)).

## 0.3.4 - 2015-12-31

- Added threshold for downvotes - if over specified limit, `isPoorlyRated` will be true.
- Comments can be set to auto-close after a defined amount of days since the elements creation.

## 0.3.3 - 2015-12-31

- Altered settings pane - now with multiple tabs.
- Updated permissions UI.
- Comments can be closed, disabling editing, deleting and replies. Accessible through `isClosed`.

## 0.3.2 - 2015-12-31

- Added basic sharing comment options. Provides permalink, Twitter and Facebook sharing.

## 0.3.1 - 2015-12-31

- Organise examples seperately. Better testing locally (symlinks).
- Added standard, non-Ajax example.
- Cleanup Ajax example - refactor.
- Checked all routes perform either via Ajax, or standard POST.

## 0.3.0 - 2015-12-31

- Added edit/delete (trashing) for users. Anonymous users can't do either.
- Added new set of templates for different comment statuses, allowing different templates for pending, approved, trashed and spam comments.
- Cleaned up comments UI to include dropdown options. Better user-handling.
- Alter Ajax example, ajax for voting, flagging, editing, deleting.
- Fixed, users could vote on their own comments.
- Fixed, remove voting arrows when unable to vote.
- Added `canVote`, `canUpVote`, `canDownVote` variables for better handling in templates. Checks are also done server-side.
- Some serious spelling mistakes for voting functions :)
- Added back `voteCount`.

## 0.2.1 - 2015-12-31

- Seperated plugin settings to its own 'real' tab. Bad UX being directed off to plugin settings, then being redirected to plugins screen.
- Added Permissions to control elements (and element types) comments are allowed to be made on. More convenient/centralised that custom field for each element.

## 0.2.0 - 2015-12-31

- Preserve comment formatting. Changed comment field to textarea in templates.
- Comments have access to objects for [flags](https://github.com/verbb/comments/wiki/Comment-ElementType#flags) and [votes](https://github.com/verbb/comments/wiki/Comment-ElementType#votes). Removed flagCount and votesCount in favour of using `length` Twig filter.

## 0.1.2 - 2015-12-31

- Added support for comments to be made on any element type (entries, users, assets, etc).

## 0.1.1 - 2015-12-31

- Added support for voting on comments.
- Added support for flagging inappropriate comments.
- After set amount of flags made against a comment, the 'isFlagged' property on the comment is true. Configurable through settings.

## 0.1.0 - 2015-12-31

- Initial beta release.
