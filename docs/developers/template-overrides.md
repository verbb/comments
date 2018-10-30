# Template Overrides

If you'd prefer to make your own templates for listing comments - go right ahead! You can either use the `all()` tag to completely write your own, or override the default templates for the single-line `form()` tag.

To override the `form()` tag templates, first create a folder in your Craft templates folder. Go to the Comments plugin settings, and enter this folder name in the field provided, and hit Save.

Navigate to the following folder in the plugin directory `craft/plugins/comments/templates/_forms/templates`. Copy all the files in this folder into the folder you created in your templates folder. You're now all set to change anything you like in these files.

### Template files

There are a number of template files that are available to you. You can use all, just the `comments.html` file, or create your own. The only compulsory file is the `comments.html` file.

#### comments.html [view template](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/comments.html)

This is the main template called by the `form()` tag - this is a required template. It simply calls the two templates below. You could contain all your templates through this single template file - that's entirely up to you.

You'll have access to the following variables globally in the template:

```
{
    element, // the ElementModel that we're fetching comment on
    comments, // a structured, nested array of all [Comment Models](/craft-plugins/comments/docs/developers/comment-model) for this element.
}
```

#### comment.html [view template](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/comment.html)

This contains a macro that recursively prints out a list of comments for the current element. This produces a tree-like list of comments.

It also includes a comment form underneath each comment, to allow users to easily reply to a comment.

Separate files are used to print out the body of the comment, depending on the status of the comment (see [comment-approved.html](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/comment-approved.html), [comment-spam.html](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/comment-spam.html), [comment-pending.html](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/comment-pending.html), [comment-trashed.html](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/comment-trashed.html) ).

#### form.html [view template](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/form.html)

A simple form that handles both logged-in users, and anonymous users. There are some required fields and settings that you must include however. Below is the bare-minimum form implementation with all required fields:

```twig
<form method="post" action="" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/save">
    <input type="hidden" name="elementId" value="{{ element.id }}">
    {{ craft.comments.protect() }}
    {{ getCsrfInput() }}

    {% if not currentUser %}
        <input name="fields[name]" type="text" />

        <input name="fields[email]" type="text" />
    {% endif %}

    <textarea name="fields[comment]"></textarea>

    <input type="submit" value="Add Comment" />
</form>
```

The crucial parts are the `name` attributes for form elements, along with the `elementId` and `action` hidden fields.

The `craft.comments.protect()` call includes additional fields that are used to prevent spam comments being submitted. Read the [Anti-Spam](/craft-plugins/comments/docs/feature-tour/anti-spam) page for more.