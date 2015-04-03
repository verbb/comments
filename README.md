# Comments

Comments is a Craft CMS plugin that allows your users to comment on elements. Not a fan of using Disqus? All you comments are stored in your Craft install, and hooked up to your existing users.

<img src="https://raw.githubusercontent.com/engram-design/Comments/master/screenshots/main.png" />


## Currently in Beta

Please be aware that Comments is being released at this stage very much in beta. As such, there will be lots of rapid, breaking changes being made to the plugin. It's for this reason that it's not suitable for use in a production environment just yet. It's certainly safe to do so - you just may be updating it a lot!

We would greatly value your feedback through the beta process, so feel free to [make a suggestion](https://github.com/engram-design/Comments/issues)


## Features

- Threaded comments - users can comment on other comments.
- Anonymous users can comment (configurable).
- Comment moderation by admins - prevent comments from appearing on your site until approved.
- Ready-to-go, single template tag for full comments list and reply forms.
- Template override folder, so you can completely customise your own comment forms.
- [Full-featured examples](https://github.com/engram-design/Comments/tree/master/examples) including CSS and JS to get started quickly.
- Comments can be made on any element type (entries, users, assets, etc).
- Set permissions for each element (and element type) to allow or disable comments.


## Install

- Add the `comments` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.

**Plugin Settings**

- Allow anonymous comments
- Comments require moderation
- Template Folder Override
- Flagged comments threshold


## Documentation

Please visit the [Wiki](https://github.com/engram-design/Comments/wiki) for all documentation, including template tags, and an overview of how Comments functions.

**Note:** This is being constantly added to and being improved during the beta process.


## Roadmap

**0.4.0**

- Support sharing comment. Post to social media, permalink.
- Support closing comments. No new comments can be made, but still visisible.

**0.5.0**

- Provide a set of hooks for third-party plugins.
- Support [craft.spamguard](https://github.com/selvinortiz/craft.spamguard).

**1.0+**

- Provide simple WYSIWYG editor for front-end forms. Optional.
- Social media login integration.
- Gravatar support.
- Support email notifications. Likely through [Postmaster](https://github.com/objectivehtml/Postmaster-for-Craft-CMS)
- Support field layouts to allow full customisation over comment form. Especially useful for capturing more than name/email for anonymous users.
- Create Pusher-integrated real-time example comments form.
- Utalise sessions to show user's unread comments from others (see [#5](https://github.com/engram-design/Comments/issues/5)).


### Changelog

#### 0.3.1

- Organise examples seperately. Better testing locally (symlinks).
- Added standard, non-Ajax example.
- Cleanup Ajax example - refactor.
- Checked all routes perform either via Ajax, or standard POST.

#### 0.3.0

- Added edit/delete (trashing) for users. Anonymous users can't do either.
- Added new set of templates for different comment statuses, allowing different templates for pending, approved, trashed and spam comments.
- Cleaned up comments UI to include dropdown options. Better user-handling.
- Alter Ajax example, ajax for voting, flagging, editing, deleting.
- Fixed, users could vote on their own comments.
- Fixed, remove voting arrows when unable to vote.
- Added `canVote`, `canUpVote`, `canDownVote` variables for better handling in templates. Checks are also done server-side.
- Some serious spelling mistakes for voting functions :)
- Added back `voteCount`.

#### 0.2.1

- Seperated plugin settings to its own 'real' tab. Bad UX being directed off to plugin settings, then being redirected to plugins screen.
- Added Permissions to control elements (and element types) comments are allowed to be made on. More convenient/centralised that custom field for each element.

#### 0.2.0

- Preserve comment formatting. Changed comment field to textarea in templates.
- Comments have access to objects for [flags](https://github.com/engram-design/Comments/wiki/Comment-ElementType#flags) and [votes](https://github.com/engram-design/Comments/wiki/Comment-ElementType#votes). Removed flagCount and votesCount in favour of using `length` Twig filter.

#### 0.1.2

- Added support for comments to be made on any element type (entries, users, assets, etc).

#### 0.1.1

- Added support for voting on comments.
- Added support for flagging inappropriate comments.
- After set amount of flags made against a comment, the 'isFlagged' property on the comment is true. Configurable through settings.

#### 0.1.0

- Initial beta release.