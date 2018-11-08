# Template Overrides

If you'd prefer to make your own templates for listing comments - go right ahead! You can either use the `fetch()` tag to completely write your own, or override the default templates for the single-line `render()` tag.

To override the `render()` tag templates, first create a folder in your Craft templates folder. Go to the Comments plugin settings, and enter this folder name in the field provided, and hit Save.

Navigate to the default template folder in the plugin directory [templates/\_special](https://github.com/verbb/comments/tree/craft-3/src/templates/_special). Copy all the files in this folder into the folder you created in your templates folder. You're now all set to change anything you like in these files.

### Template files

By default, there are three template files, two of which are required. You're free to organise this template folder however you see fit.

#### comments.html [view template](https://github.com/verbb/comments/blob/craft-3/src/templates/_special/comments.html)

This is the main template called by the `render()` tag - this is a required template. It simply calls the two templates below. You could contain all your templates through this single template file - that's entirely up to you.

You'll have access to the following variables globally in the template:

```twig
{# The Element that we're fetching comment on - an Entry for example #}
{{ element }}

{# The element query that's ready to call our comments. You can modify this before calling `.all()` #}
{{ commentsQuery }}

{# The Settings Model for the Comments plugin - important for checking permissions and what's enabled #}
{{ settings }}
```

#### comment.html [view template](https://github.com/verbb/comments/blob/craft-3/src/templates/_special/comment.html)

This represents the individual comment, but also calls nested comments recursively to produce a tree-like structure of comments.

#### form.html [view template](https://github.com/verbb/comments/blob/craft-2/comments/templates/_forms/templates/form.html)

A simple form that handles both logged-in users, and anonymous users. There are some required fields and settings that you must include however. Below is the bare-minimum form implementation with all required fields:

```twig
<form method="post" action="" accept-charset="UTF-8">
    <input type="hidden" name="action" value="comments/save">
    <input type="hidden" name="elementId" value="{{ element.id }}">
    {{ craft.comments.protect() }}
    {{ csrfInput() }}

    {% if not currentUser %}
        <input name="fields[name]" type="text" />

        <input name="fields[email]" type="text" />
    {% endif %}

    <textarea name="fields[comment]"></textarea>

    <input type="submit" value="Add Comment" />
</form>
```

The crucial parts are the `name` attributes for form elements, along with the `elementId` and `action` hidden fields.

The `craft.comments.protect()` call includes additional fields that are used to prevent spam comments being submitted. Read the [Anti-Spam](docs:feature-tour/anti-spam) page for more.