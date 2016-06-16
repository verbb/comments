# Comments

Comments is a Craft CMS plugin that allows your users to comment on elements. Not a fan of using Disqus? All you comments are stored in your Craft install, and hooked up to your existing users.

<img src="https://raw.githubusercontent.com/engram-design/Comments/master/screenshots/main.png" />


## Features

- Threaded comments - users can comment on other comments.
- Anonymous users can comment (configurable).
- Comment moderation by admins - prevent comments from appearing on your site until approved.
- Supports upvoting and downvoting, along with flagging inappropriate comments.
- Front-end editing and deleting of comment for logged in users.
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
- Enable flagging of comments
- Enable voting on comments
- Flagged comments threshold
- Downvote comments threshold
- Auto-close comments


## Documentation

Please visit the [Wiki](https://github.com/engram-design/Comments/wiki) for all documentation, a getting started guide, template tags, and developer resources.


## Roadmap

**0.5.0**

- Add permissions to control commenting settings per section.
- Support [craft.spamguard](https://github.com/selvinortiz/craft.spamguard).
- Support querying comments via votes (up and down), and flags (see [#20](https://github.com/engram-design/Comments/issues/20)).

**0.6.0**

- Provide security policies through:
	- Ban unwanted users from current topic
	- Ban users from all comment forms

**1.0+**

- Support Facebook/Twitter for sharing with App ID and other credentials
- Provide simple WYSIWYG editor for front-end forms. Optional.
- Social media login integration.
- Gravatar support.
- Support field layouts to allow full customisation over comment form. Especially useful for capturing more than name/email for anonymous users.
- Create Pusher-integrated real-time example comments form.
- Utalise sessions to show user's unread comments from others (see [#5](https://github.com/engram-design/Comments/issues/5)).
- Dashboard widget (see [#15](https://github.com/engram-design/Comments/issues/15)).
- Migrate guest comments to user account upon user registration - made with the same email.


### Changelog

[View JSON Changelog](https://github.com/engram-design/Comments/blob/master/changelog.json)
