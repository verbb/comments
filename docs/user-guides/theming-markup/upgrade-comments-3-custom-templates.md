# Upgrade Comments 3 Custom Templates

Comments 3 drew its thread with Twig templates, and let you replace any of them by copying it into a folder of your own and naming that folder in the **Template Folder Override** setting. Comments 4 draws the thread in the browser from data instead, so there are no thread templates to override, and the files in your folder stop being used when you upgrade. Nothing breaks, and your pages show Comments' default thread until you move your changes across.

Moving them across rarely means rebuilding a template. Most changes people made in their copies were small, such as a different class, a hidden link, a changed word or an extra label, and each of those now has a lighter home than a copied template. This guide takes an example set of Comments 3 overrides and moves every change in it, one at a time, so you can do the same with yours.

In the example, a site's `templates/_comments` folder overrides five of Comments 3's templates, and its stylesheet restyles the thread through Comments 3's `cc-*` classes. These are the kinds of changes most overrides made. By the end of the guide, every change is back in place on Comments 4, the folder can be deleted, and you'll know where any change of your own belongs.

## What You'll Need

You'll need to have run the upgrade to Comments 4, as described in [Upgrading from Comments 3](docs:get-started/upgrading-from-v3), with your site working on the new version. Keep your old override folder, either in place or in your version control's history, because you'll work through it file by file.

It helps to have Comments 3's own copies of the templates to compare yours with, so you can see exactly what you changed. They're in Comments 3's source on GitHub, in [`src/templates/_special`](https://github.com/verbb/comments/tree/craft-5/src/templates/_special), or in `vendor/verbb/comments/src/templates/_special` in a copy of your project from before the upgrade.

Some of the changes need a little PHP in a Craft module, and one needs a small script. Each step explains where its code goes.

## Finding Your Overrides

Go to **Comments** → **Settings** → **Upgrade Report**. If your site used **Template Folder Override**, the report says so and lists every template file it found in that folder. If the setting was empty, it lists any templates in `templates/_comments`, the folder Comments 3's docs suggested, which may be overrides or your own form partials.

For the example site, the report lists these:

```
_comments/_includes/header.html
_comments/_includes/avatar.html
_comments/_includes/footer.html
_comments/_includes/comment.html
_comments/form-fields/fields/dropdown.html
```

If you set `templateFolderOverride` in `config/comments.php`, it's ignored now, and Craft's deprecation warnings mention it until you remove it, which you'll do at the end.

## Where Each Template's Markup Is Drawn Now

Every Comments 3 template has a counterpart in the thread Comments 4 draws. This table says where, so you know which part of the thread to look at for each file:

| Comments 3 Template | Comments 4 |
| --- | --- |
| `comments.html` | The thread as a whole. Its heading, sort options and **Follow** button are the `header` part, and the message for a thread with no comments is the `empty` part. |
| `_includes/header.html` | The commenter's name, badges and the comment's date, which are the `authorName`, `badge` and `meta` parts. |
| `_includes/comment.html` | The whole comment, which is the `comment` part, or the `content` part for only the comment's text. |
| `_includes/avatar.html` | The avatar, which is the `avatar` part, with its size set by the `--comments-avatar-size` token. |
| `_includes/footer.html` | The row of votes, **Reply** and the **···** menu under each comment, which is the `actions` part. |
| `_includes/form.html` | The comment form. It can't be replaced, because it carries spam protection and Editor, but it's styled with class names, tokens and `themeConfig`, and the sign-in card, the **Posting as** line and the custom fields around it are the `join`, `postingAs` and `formFields` parts. |
| `form-fields/*` | Custom fields on the comment form, which are now chosen in Comments' settings and drawn by the thread, with a field override for any one field. |

A **part** is a named piece of the thread whose markup you can replace on its own, with a **part override**. You'll only need one for a change that truly alters the markup. [Change a Part's Structure](docs:user-guides/theming-markup/change-a-parts-structure) explains them in depth.

## Choosing the Lightest Replacement

For each file, compare it with Comments 3's original and list what you changed. Then move each change to the lightest place that does the same job, working down this list:

1. **A setting**, for anything you hid, such as the avatar or the report link. Turning a feature off in settings also stops the server offering it, which hiding it in a template never did.
2. **A translation**, for anything you reworded.
3. **Tokens and class names in your CSS**, for anything that only changed the look.
4. **`themeConfig`**, for your design system's classes on Comments' buttons and inputs.
5. **A PHP event**, for anything you added from the commenter or their account, such as a label or a field from their user profile.
6. **A part override**, for markup that has to be different.

A lighter replacement keeps Comments' own markup, so it keeps everything that markup does, such as keyboard support, screen reader labels and the thread's behaviour, and it keeps working when Comments is updated. The rest of this guide works through the example's files in that order.

## Hidden Avatars and Report Links Become Settings

In the example, `_includes/avatar.html` was empty, to hide avatars, and its `_includes/footer.html` had the **Flag** form taken out, so readers couldn't report comments.

Both are settings now. Go to **Comments** → **Settings** → **Appearance** and turn off **Show Avatar**, then go to **Interactions** and turn off **Allow Reporting**, and save.

Reload a page with comments. The thread has no avatars, and each comment's **···** menu has no **Report** option. Because reporting is off in the settings, Comments also refuses a report sent any other way, such as straight to its data API.

The same goes for votes, which you'd hide by setting **Interaction Mode** to **None**, and for other features in the thread, each of which has a setting under **Comments** → **Settings**.

## Changed Wording Becomes a Translation

The footer override also changed the **Reply** link's text to "Respond". Comments' wording comes from Craft's static translations in the `comments` category, so a word is changed with a translation file, without touching markup.

Create `translations/en/comments.php` in your project's root folder, next to `templates`, or add to it if you already have one:

```php
<?php

return [
    'Reply' => 'Respond',
];
```

The key is Comments' original English text, exactly as it appears in `vendor/verbb/comments/src/translations/en/comments.php`, and the value is your wording. Reload a page with comments, and every **Reply** button now says **Respond**. [Text & Translations](docs:template-guides/text-translations) explains translation files, including other languages.

## Styles on `cc-*` Classes Move to `comments__*` Classes

The example site's stylesheet had rules like these, written against Comments 3's classes:

```css
.cc-i-author {
    color: #2e5e3e;
    font-weight: 700;
}

.cc-v-up,
.cc-i-reply {
    color: #2e5e3e;
}

.cc-w {
    font-family: Georgia, serif;
}
```

Comments 4's markup is new, so the `cc-*` classes are gone. Every element in the thread has a class starting `comments__` instead, and these are kept the same between releases. On top of that, most colours, fonts and sizes are now set with **tokens**, CSS custom properties that the whole thread reads, so one line can change every place a colour is used.

Here are the same changes for Comments 4:

```css
[data-comments-root] {
    --comments-font-family: Georgia, serif;
    --comments-color-accent: #2e5e3e;
    --comments-color-link: #2e5e3e;
}

.comments .comments__author-name {
    color: #2e5e3e;
}
```

The token rule sets the thread's font, and the accent and link colours, which cover the **Post** button, the selected sort option, links and other highlighted controls. The commenter's name has no token of its own, so it's styled through its class. The extra `.comments` in front of the class makes the rule more specific than Comments' own, so it wins wherever your stylesheet loads.

Reload the page, and the thread is in Georgia, with green names, buttons and links. [Make a Custom Theme](docs:user-guides/theming-markup/make-a-custom-theme) walks through the tokens in full, and inspecting an element in your browser's developer tools shows its `comments__` classes.

## A Wrapper Becomes CSS

The example's `_includes/comment.html` wrapped each comment's content in a `<div class="comment-card">`, which the site's stylesheet gave a border, a pale background and rounded corners. That's a change of look, not of content, so it doesn't need new markup. Style the comment's own class instead:

```css
.comments .comments__comment {
    padding: 1rem;
    border: 1px solid #d5e3d8;
    border-radius: 0.75rem;
    background: #f6faf6;
}

.comments .comments__replies .comments__comment {
    padding: 0;
    border: 0;
    background: none;
}
```

The first rule gives each comment the card. Replies are comments too, inside their parent's card, so the second rule keeps them plain, the way the example's cards worked.

## An Extra Label Becomes a Badge

The example's `_includes/header.html` had this added after the commenter's name:

```twig
{% if comment.author and comment.author.isInGroup('patrons') %}
    <span class="patron-label">Patron</span>
{% endif %}
```

A label beside the commenter's name is a **badge** now. Your own PHP adds it on the server, and the thread shows it with Comments' other badges, such as the one for staff, with no markup of your own.

Add this to a module's `init()` method. If your project doesn't have a module, Craft's documentation on [how to build a module](https://craftcms.com/docs/5.x/extend/module-guide.html) explains how to add one, and [Add Your Own Data to Comments](docs:user-guides/forms-data/add-your-own-data-to-comments#creating-the-module) walks through it for Comments:

```php
use verbb\comments\events\DefineBadgesEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event) {
    $user = $event->comment->getUser();

    if ($user && $user->isInGroup('patrons')) {
        $event->badges[] = ['type' => 'patron', 'label' => 'Patron'];
    }
});
```

The listener runs for each comment as the thread's data is built, and adds a badge when the commenter is in the Patrons group. The thread shows its label beside their name, in an element with the classes `comments__badge` and `comments__badge--patron`, so the old `.patron-label` styles move to `.comments .comments__badge--patron`.

The thread's data is the same for every visitor and cached, so a badge must only depend on the commenter, never on who's reading. Comments 3's templates ran for each visitor, so check any condition you move across. A label shown only to signed-in readers, for example, belongs in a part override that checks the reader, not in a badge.

## Data from the Commenter's Account Becomes Comment Data

The same header override showed each member's job title, from a **Job Title** field with the handle `jobTitle` on their user account:

```twig
{% if comment.author.jobTitle ?? null %}
    <span class="job-title">{{ comment.author.jobTitle }}</span>
{% endif %}
```

This isn't a badge, it's a piece of text, so it needs two steps. Your PHP adds the job title to each comment's data, and a part override draws it.

First, add the data with `Api::EVENT_DEFINE_COMMENT_DATA`, in the same module:

```php
use craft\elements\User;
use verbb\comments\elements\Comment;
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\services\Api;
use yii\base\Event;

Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event) {
    $userIds = array_unique(array_filter(array_map(fn(Comment $comment) => $comment->userId, $event->comments)));

    if (!$userIds) {
        return;
    }

    $users = User::find()->id(array_values($userIds))->indexBy('id')->all();

    foreach ($event->comments as $uid => $comment) {
        $jobTitle = $users[$comment->userId]->jobTitle ?? null;

        if ($jobTitle) {
            $event->custom[$uid] = ['jobTitle' => (string)$jobTitle];
        }
    }
});
```

The event runs once for all the comments in a response, so it looks the commenters up in one query rather than one per comment, which the Comments 3 template couldn't do. Each comment with a job title gets it under its `custom` data.

Then draw it, with an override of the `authorName` part that adds the job title after Comments' own name. Add this to a script your layout loads on pages with comments:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments?.configure({
        parts: {
            authorName: ({ comment, html, defaultRender }) => html`
                ${defaultRender()}
                ${comment.custom.jobTitle ? html`<span class="job-title">${comment.custom.jobTitle}</span>` : ''}`,
        },
    });
});
```

`defaultRender()` draws Comments' own name, so only the job title is new. The `html` tag escapes what it inserts, so a job title is always shown as text. Reload a page, and members with a job title have it after their name, styled by your existing `.job-title` rule.

## Custom Form Fields Become Field Settings

The example's `form-fields/fields/dropdown.html` drew a **Topic** Dropdown field with a `form-select` class from the site's design system. In Comments 3, a custom field only reached the form through templates like this. In Comments 4, the thread draws each field itself.

Go to **Comments** → **Settings** → **Comment Fields**, turn on the switch beside **Topic** under **The Comment Form**, then under **Answers on Comments** choose **Under the text** to show the answer on comments, or **Not shown** to keep it private, and save. The form now has the field, as a `<select>` with its options. To give it the design system's class, pass `themeConfig` to `render()` in your entry template:

```twig
{{ craft.comments.render(entry, {
    themeConfig: {
        select: { attributes: { class: 'form-select' } },
    },
}) }}
```

`themeConfig` adds classes to the thread's controls, and `select` is every dropdown on the form. [Ask Commenters for More with Custom Fields](docs:user-guides/forms-data/ask-commenters-for-more-with-custom-fields) covers the field modes, required fields and drawing a field your own way, and [Style Comments with Tailwind or a Design System](docs:user-guides/theming-markup/style-comments-with-tailwind-or-a-design-system) covers `themeConfig` for every control.

## Markup That Has to Be Different

Everything the example changed had a lighter home than a template. If one of your changes really does need different markup, such as an extra button beside each comment's actions, replace just that part with a part override, as [Change a Part's Structure](docs:user-guides/theming-markup/change-a-parts-structure) shows. Override the smallest part that holds your change, such as `meta` rather than `comment`, because a smaller part leaves more of Comments' own markup and behaviour in place.

### A Fully Custom Thread

Some Comments 3 sites didn't override the thread's templates at all, and instead listed comments with `craft.comments.fetch()` in their own templates, posting with their own forms. That approach still works. Your listing is an ordinary element query, and your forms can keep posting to Comments' form actions, with the changes described under [Custom Forms](docs:get-started/upgrading-from-v3#custom-forms) in the upgrade guide. Everything else the thread does, such as voting, loading replies in place and letting moderators decide held comments, is then yours to build as well.

To keep those features and still draw every comment and reply in your own markup, choose how much of the thread you want to own.

To own each comment, override the `comment` part. It replaces everything inside a comment, replies included, while Comments keeps drawing the thread around it, with its sort options, sign-in card and comment form. Your override calls `form()` and `replies()` so that **Reply** and the comment's replies keep working, as [Replacing a Whole Comment](docs:user-guides/theming-markup/change-a-parts-structure#replacing-a-whole-comment-and-when-to-stop) explains. This suits a Twig site that wants a bespoke look for its comments and is happy with Comments' own form.

To own everything, forms included, build your own interface. On a site without a JavaScript framework, the thread store from `@verbb/comments-core` gives your code the thread's comments, permissions and actions, such as voting and loading replies, with none of Comments' markup. `commentForm()` from `@verbb/comments-browser/form` then posts from your own `<form>`, with the site's spam protection mounted on it, for a new comment or a reply. [Your Own Interface](https://docs.verbb.io/comments/browser/your-own-interface) in the package docs walks through both.

If your front end is a React or Vue app, the same approach comes as hooks. `useCommentsThread()` gives the thread's state and actions, and `useCommentForm()` posts from your own form, with `mode: 'reply'` and the comment's `parentUid` for a reply. [React hooks](https://docs.verbb.io/comments/react/hooks) and [Vue composables](https://docs.verbb.io/comments/vue/composables) cover them.

The [web components](https://docs.verbb.io/comments/starters/web), [React](https://docs.verbb.io/comments/starters/react) and [Vue](https://docs.verbb.io/comments/starters/vue) starters each have a **Your own interface** page built this way, with replies, votes and a guest's name and email, so you can see a complete version working before you write your own.

## Removing the Old Folder

Once every change is moved, remove what's left of the old setup:

1. If `config/comments.php` has a `templateFolderOverride` line, delete it.
2. Delete your override folder, such as `templates/_comments`. Check first that none of its files are your own form partials that something else includes.
3. Remove any CSS rules still written against `cc-*` classes, and any JavaScript that used Comments 3's script, such as `new Comments.Instance()`.

## Testing It

Check every page type that shows comments, signed out and signed in, against how it looked on Comments 3:

1. Avatars are hidden, and there's no **Report** in any comment's menu.
2. Every **Reply** button says **Respond**, including on replies.
3. The thread uses Georgia, and names, buttons and links are green.
4. Each top-level comment is in its card, and replies inside it aren't.
5. A member of the Patrons group has the **Patron** badge, and members with a job title show it after their name.
6. The comment form has **Topic** with the `form-select` class.
7. **Comments** → **Settings** → **Upgrade Report** no longer lists any templates once the folder is gone.

A Comments 4 thread does several things a Comments 3 template couldn't, such as loading as the reader scrolls near it, updating in place, and letting moderators decide held comments where they are. So expect a few differences you didn't make yourself, and check each change you did make is there.
