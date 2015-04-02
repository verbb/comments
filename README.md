# Comments

Comments is a Craft CMS plugin that allows your users to comment on elements. Not a fan of using Disqus? All you comments are stored in your Craft install, and hooked up to your existing users.

<img src="https://raw.githubusercontent.com/engram-design/Comments/master/screenshots/main.png" />


## Currently in Beta

Please be aware that Comments is being released at this stage very much in beta. Please **do not** use this plugin in a production environment until v1.0 is released. We would greatly value your feedback through the beta process, so feel free to [make a suggestion](https://github.com/engram-design/Comments/issues)


## Features

- Threaded comments - users can comment on other comments.
- Anonymous users can comment (configurable).
- Comment moderation by admins - prevent comments from appearing on your site until approved.
- Ready-to-go, single template tag for full comments list and reply forms.
- Template override folder, so you can completely customise your own comment forms.
- [Full-featured examples](https://github.com/engram-design/Comments/tree/master/examples) including CSS and JS to get started quickly.
- Comments can be made on any element type (entries, users, assets, etc).


## Install

- Add the `comments` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.

**Plugin Settings**

- Allow anonymous comments
- Comments require moderation
- Template Folder Override
- Flagged comments threshold


## Documentation

Please visit the [Wiki](https://github.com/engram-design/Comments/wiki) for all documentation, including template tags, and an overview of how Comments functions. **Note:** This is being constantly added to and improved over the beta process.


## Roadmap

**0.3.0**

- Edit/Delete comments from front-end. Handle anonymous users somehow through sessions/cookies.
- Provide fieldtype to disable comments per-element. Also provide global settings to opt-in to comments, rather than opt-out.
- Implement anti-spam solution. Server-side validation for comments. Look at better third-party solutions to integrate.

**0.4.0**

- Support sharing comment. Post to social media, permalink.
- Fully Ajax-support all endpoints.
- Support closing comments. No new comments can be made.

**0.5.0**

- Provide a set of hooks for third-party plugins.

**1.0+**

- Provide simple WYSIWYG editor for front-end forms. Optional.
- Social media login integration.
- Gravatar support.
- Support email notifications.
- Support field layouts to allow full customisation over comment form. Especially useful for capturing more than name/email for anonymous users.


### Changelog

#### 0.2.0

- Preserve comment formatting. Change to textarea in templates.
- Support returning user collection that have flagged, or voted, on a comment - for use in templates. Removed flagCount and votesCount in favour of using `length` Twig filter.

#### 0.1.2

- Added support for comments to be made on any element type (entries, users, assets, etc).

#### 0.1.1

- Added support for voting on comments.
- Added support for flagging inappropriate comments.
- After set amount of flags made against a comment, the 'isFlagged' property on the comment is true. Configurable through settings.

#### 0.1.0

- Initial beta release.