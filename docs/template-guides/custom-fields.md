# Custom Fields

The form for users to enter their comments can have custom fields attached to it. This gives you the flexibility to get commenters to supply extra content, stored alongside their comment.

Custom fields will not be shown automatically. Using the `showCustomFields` config setting, these fields can be automatically rendered on the comment form. Or, if you're using your own templates, you've got full control over how to output these fields. If using your own templates, you can ignore the `showCustomFields` altogether.

Whilst you can add any Craft custom field to the comments form, currently Comments only supports basic fields, such as:
- Assets
- Checkboxes
- Color
- Dropdown
- Email
- Number
- Plain Text
- Radio Buttons
- URL

If you want to support any additional custom fields, or your own, you'll need to write your own templates for them. Check out the check out the [source code](https://github.com/verbb/comments/tree/craft-3/src/templates/_special) for existing field templates as a start.

## Customising
If you choose to use your own templates, you can output custom fields any manner of ways. To fetch the custom fields you've defined for Comments, you can use the following:

```twig
{% set fieldLayout = craft.app.fields.getLayoutByType('verbb\\comments\\elements\\Comment') %}

{% for field in fieldLayout.getFields() %}
    <label>{{ field.name }}</label>

    <input type="text" name="fields[{{ field.handle }}]">
{% endfor %}

```

From the above, you have access to a `field` variable, which is a [Field](https://docs.craftcms.com/api/v3/craft-base-field.html) model. Take note of the `name` attribute for inputs, which is the only required template portion you need to adhere to. These must be formatted like `fields[myFieldHandle]` in order for values to be saved to the comment element. Otherwise, you have complete control over everything else.

Another example might be you want to just include some of the custom field you've added to Comments, rather than all of them. In this instance, you need to take note of the field handles for the fields you want to include.

```twig
<input type="file" name="fields[myFieldHandle][]" multiple>
```

For another example, let's say you want the commenter to upload a number of files. But, the trick is that you want to also include some other assets along with every upload. You would first fetch the assets, and add them to your 

```twig
{% set entry = craft.entries.slug('some-entry').one() %}

{% for asset in entry.someAssetField %}
    <input type="hidden" name="fields[myFieldHandle][]" value="{{ asset.id }}">
{% endfor %}

<input type="file" name="fields[myFieldHandle][]" multiple>
```

The resulting comment would include all the assets from the `someAssetField` on the entry with an ID slug `some-entry`, and any assets the commenter decided to upload in the form.

For more examples, check out the [source code](https://github.com/verbb/comments/tree/craft-3/src/templates/_special) for our basic field implementations.