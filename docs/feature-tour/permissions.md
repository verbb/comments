# Permissions

Comments' permissions decide who can look after comments in the control panel. A moderator might need to correct and delete other people's comments, while an editor who only reads what visitors are saying needs nothing beyond access to the comments index. Set them in **Settings → Users → User Groups**, or on an individual user's **Permissions** tab.

**Access Comments** is listed under **General**, with Craft's other permissions for reaching each plugin's section. The other three are listed under **Comments**.

| Permission | What It Allows |
| --- | --- |
| Access Comments | Open the comments index and each comment's page, and delete their own comments there. |
| Edit other users’ comments | Save changes to comments written by anyone else, including guests, from a comment's page. |
| Trash other users’ comments | Set someone else's comment to **Trashed** from its page. |
| Delete comments | Delete comments written by anyone else from the comments index. |

Anyone given one of the other permissions needs **Access Comments** too, and **Trash other users’ comments** only works alongside **Edit other users’ comments**, because trashing a comment from its page saves it. A user's own comments, from when they commented on the site while signed in, can be edited and deleted without these permissions.

Moderators usually need **Edit other users’ comments**, so they can correct a comment and change its status from its page. Keep **Delete comments** for the people who should be able to remove a comment for good, because deleting takes it out of the comments index as well as off the site, while setting it to **Spam** or **Trashed** keeps it on record.

Settings under **Comments → Settings** are for administrators only, and only on environments that allow admin changes, because they're stored in Craft's project config. That includes the choice of which sections, groups and volumes visitors can comment on, described in [Where Visitors Can Comment](docs:feature-tour/comments#where-visitors-can-comment).
