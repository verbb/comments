# Comments

Comments is a Craft CMS plugin that allows your users to comment on entries. Not a fan of using Disqus? All you comments are stored in your Craft install, and hooked up to your existing users.

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


## Install

- Add the `comments` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.

**Plugin Settings**

- Allow anonymous comments
- Comments require moderation
- Template Folder Override


## Documentation

Please visit the [Wiki](https://github.com/engram-design/Comments/wiki) for all documentation, including template tags, and an overview of how Comments functions. **Note:** This is being constantly added to and improved over the beta process.


## Roadmap

- Implement anti-spam solution. Server-side validation for comments. Look at better third-party solutions to integrate.
- Provide fieldtype to disable comments per-entry. Also provide global settings to opt-in to comments, rather than opt-out.
- Support field layouts to allow full customisation over comment form. Especially useful for capturing more than name/email for anonymous users.
- Social media login integration.
- Edit/Delete comments from front-end. Handle anonymous users somehow through sessions/cookies.
- Gravatar support.
- Preserve comment formatting.


### Changelog

#### 0.1

- Initial beta release.