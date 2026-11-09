# Permissions

Comments has two sets of permissions: which elements can have comments, and what your team can do with them.

## Which Elements Can Have Comments

By default, comments are enabled for all elements. Go to **Comments** → **Settings** → **Commenting** to narrow this down for entries, categories, assets and users, by choosing their sections and entry types, category groups, volumes and user groups. To do the same in your config file, see the [`permissions`](docs:get-started/configuration#permissions) setting.

An element that isn't allowed shows nothing on the site: `craft.comments.render()` outputs nothing, and comments can't be posted to it.

## Per-Element Settings

Add a **Comments** field to an element's field layout when editors need to override this for an individual element. The field is a select with four options:

| Option | Result |
| --- | --- |
| Default | Follows the settings above, and shows what they give in brackets, such as **Default (Open)**. This is what every element starts with. |
| Open | Visitors can read and post, even if the element's source doesn't allow comments. |
| Closed | Visitors can read the existing comments, but not post. |
| Off | Comments are hidden from visitors, along with the form. |

A note under the select explains the option chosen. The field also shows a summary of the element's comments to people who can view them, which [Control Panel](docs:feature-tour/control-panel#comments-field) describes along with where to put the field.

**Close Comments Globally** under **Comments** → **Settings** → **General Settings** and the **Auto-close Comments** setting both work the same way as closing an element, and setting an element to **Open** doesn't reopen it while either applies.

## User Permissions

Comments adds these under each user group's permissions, alongside **Access Comments**. The others are nested under **View comments**, so give someone that first.

| Permission | Handle | What It Allows |
| --- | --- | --- |
| View comments | `comments-view` | The Comments index, reading comments in the control panel, and the summary in the Comments field. |
| Moderate comments | `comments-moderate` | Approving, keeping and marking comments as spam or not spam, the Moderation sources, the Activity tab and its notes, [Commenters](docs:feature-tour/moderation#commenters) and review settings, and moderating from the site. |
| Edit other users’ comments | `comments-edit` | Editing any comment in the control panel, including changing its element and author. This also lifts the **Author Edit Window**. |
| Trash other users’ comments | `comments-trash` | Removing and restoring comments, and restoring comments from Craft's trash. |
| Delete comments | `comments-delete` | Moving comments to Craft's trash from the Comments index, deleting spam permanently, and deleting any comment from the site. |

Admins can do all of this, along with seeing email addresses in Commenters and a comment's IP address, user agent and the page it was posted from. Changing **Comments** → **Settings** needs an admin account and Craft's `allowAdminChanges` setting. Nobody can make moderation decisions on their own comments.

Signed-in commenters don't need any of these to work with their own comments. They can edit and delete them, within the **Author Edit Window** if you've set one, though a comment marked as spam or removed can't be edited.
