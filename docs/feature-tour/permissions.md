# Permissions

Comments lets you choose where comments are allowed, and what each member of your team can do with them.

## Which Elements Can Have Comments

By default, comments are enabled for all elements. Go to **Comments** → **Settings** → **Commenting** to narrow this down for entries, categories, assets and users, by choosing their sections and entry types, category groups, volumes and user groups. To do the same in your config file, see the [`permissions`](docs:get-started/configuration#permissions) setting.

For an element that isn't allowed, `craft.comments.render()` outputs nothing, and comments can't be posted to it.

## Per-Element Settings

Add a **Comments** field when editors need to override this for an individual element. Create it under **Settings** → **Fields**, then add it to the field layouts of the entry types, category groups, volumes or user layouts where editors need it. We recommend giving it a tab of its own, named **Comments**, so it stays out of the way of writers while moderators and editors know where to look. The field's label is the card's heading, so a short label such as “Comments” suits it.

The field is a select with four options:

| Option | Result |
| --- | --- |
| Default | Follows the settings above, and shows what they give in brackets, such as **Default (Open)**. This is what every element starts with, unless the field's **Start Closed** setting is on. |
| Open | Visitors can read and post, even if the element's source doesn't allow comments. |
| Closed | Visitors can read the existing comments, but not post. If the element's source doesn't allow comments, they stay hidden, as only **Open** overrides the source. |
| Off | Comments are hidden from visitors, along with the form. |

To make comments opt-in, turn on **Start Closed** in the field's settings. New elements, and existing ones that have never been saved with the field, are then **Closed**, so nobody can post until an editor chooses **Open**. An editor can still choose **Default** to follow the settings above.

A note under the select explains the option chosen. The field also shows a summary of the element's comments to people who can view them, which [Moderation](docs:feature-tour/moderation#moderating-from-an-element) describes.

**Close Comments Globally** and **Auto-close Comments**, both under **Comments** → **Settings** → **General Settings**, work the same way as closing an element, and setting an element to **Open** doesn't reopen it while either applies.

## User Permissions

Comments adds these under each user group's permissions, alongside **Access Comments**. The moderation permissions are nested under **View comments**, so give someone that first.

| Permission | Handle | What It Allows |
| --- | --- | --- |
| View comments | `comments-view` | The Comments index, reading comments in the control panel, and the summary in the Comments field. |
| Moderate comments | `comments-moderate` | Approving, keeping and marking comments as spam or not spam, the Moderation sources, the Activity tab and its notes, [Commenters](docs:feature-tour/moderation#commenters) and review settings, and moderating from the site. |
| Edit other users’ comments | `comments-edit` | Editing any comment in the control panel, including changing its element and author. This also lifts the **Author Edit Window**. |
| Trash other users’ comments | `comments-trash` | Removing and restoring comments, and restoring comments from Craft's trash. |
| Delete comments | `comments-delete` | Moving comments to Craft's trash from the Comments index and deleting them permanently from the trash. With **Moderate comments** as well, **Delete permanently…** on a spam comment's page. On the site, only a comment's author can delete it. |
| Manage spam settings | `comments-manageSpamSettings` | Changing the settings on the [Spam Protection](docs:feature-tour/anti-spam) page, including provider keys and keyword filters, even where admin changes are turned off. Only admins can enter environment variables for the keys. |

Admins can do all of this, along with seeing email addresses in Commenters and a comment's IP address, user agent and the page it was posted from. Changing **Comments** → **Settings** needs an admin account and Craft's `allowAdminChanges` setting. **Comments** → **Spam Protection** is open to admins and people with **Manage spam settings** on any environment. Nobody can make moderation decisions on their own comments.

Signed-in commenters don't need any of these to work with their own comments. They can edit and delete them, within the **Author Edit Window** if you've set one, though a comment marked as spam or removed can't be edited.
