# Changing Data Before It's Drawn

`comments:before-render` fires each time new data is about to be drawn, with the thread's state in `detail.data`. A listener can change that state in place, and the thread draws what it's left with, or cancel the event to skip drawing this update. It suits small adjustments that don't justify a [part override](/browser/part-overrides), such as renaming a badge on one site or tidying how a value reads.

## Renaming a Badge

Suppose a site's moderators carry the **Staff** badge, and on one section of the site you'd rather it said **Team**. A listener can rename it before each draw:

```js
document.addEventListener('comments:before-render', (event) => {
    for (const comment of Object.values(event.detail.data.comments)) {
        for (const badge of comment.author?.badges ?? []) {
            if (badge.type === 'staff') {
                badge.label = 'Team';
            }
        }
    }
});
```

Every **Staff** badge in the thread reads **Team**, including on comments that load later, because the event fires again for each page of comments and each set of replies.

## What the Event Gives You

<!-- #region before-render-detail -->
`detail.reason` says what just changed.

| `reason` | What arrived |
| --- | --- |
| `thread` | The thread's first page, or the whole thread after a sort or a refresh |
| `page` | Another page of comments, from **Load more** |
| `replies` | A comment's replies |
| `viewer` | The visitor's own details, such as their votes and what they may do |
| `comment` | One comment and its parents, loaded for a link to it |
| `write` | The answer to something the visitor did, such as a new comment, an edit or a vote |

`detail.data` is the thread's state from its [store](/core/thread-store#state), with `comments` keyed by UID, `roots` for the order of the top-level comments, `replies` for each comment's replies, `viewer` for who's reading, `permissions` for what they may do with each comment, and `labels` for the thread's wording. Change what you need in place. Each comment is in the data API's shape, with its `author`, `content`, `display` for its formatted dates, `fields` for its shown custom field values and `custom` for anything your site adds on the server.

Calling `event.preventDefault()` skips drawing this update, and the thread draws again with the next one. That's rarely what you want for anything but a short pause, such as holding a redraw while your own animation finishes.
<!-- #endregion before-render-detail -->

## Data from the Server

<!-- #region before-render-server -->
The state is shared with everything else on the page that reads the thread, such as a part override or your own code reading `controller.store`, so a change you make here is what they see too. A change that every visitor should see is usually better made on the server, where it's cached with the thread. The plugin's `Api::EVENT_DEFINE_COMMENT_DATA` event adds your own data to each comment's `custom` object, and `Comments::EVENT_DEFINE_BADGES` adds badges such as “Verified buyer”, which the thread shows with no change in the browser. The plugin's [Events](https://verbb.io/craft-plugins/comments/docs/v4/developers/events) page covers both, and the [Add Your Own Data to Comments](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/forms-data/add-your-own-data-to-comments) user guide adds data on the server and draws it with a part override. Data added on the server is public and cached with the thread, so it must never depend on who's looking.
<!-- #endregion before-render-server -->
