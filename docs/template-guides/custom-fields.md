# Custom Fields

You can ask commenters for more than their comment, such as where they're from or a star rating. The values are stored on the comment like any other element's custom fields.

## Adding Fields to the Form

1. Go to **Comments** → **Settings** → **Comment Fields**, and add your field to the **Comment Field Layout**.
2. Save, then return to the same page and tick the field under **Public Fields**, which lists the fields in the saved layout.

The field appears on the form below the comment box, using the label, instructions and required setting from the field layout. Your visitors can fill it in, and you'll see the value on the comment in the control panel.

**Public Fields** decides what visitors are allowed to fill in, through the form, the frontend API and GraphQL. Anything not ticked can only be edited in the control panel, however a request is put together, so it's safe to keep a private field for your moderators on the same layout. A required field that isn't ticked doesn't stop a visitor from posting.

The default form has inputs for these field types:

| Field Type | Input |
| --- | --- |
| Plain Text | A text input, or a `textarea` for multi-line fields |
| Number | A number input |
| Email | An email input |
| Link | A URL input |
| Date | A date input |
| Dropdown | A `select` |
| Multi-select | A `select` with `multiple` |
| Radio Buttons | Radio buttons |
| Checkboxes | Checkboxes |
| Lightswitch | A checkbox |
| Assets | A file input, with `multiple` unless the field's limited to one |

Other field types can be saved if they're ticked, but the default form leaves them out, so you'd add your own input for them.

## Showing Fields on a Comment

The default templates show short values beside each comment, after its date. That covers single-line Plain Text, Number, Dropdown, Radio Buttons and Multi-select fields ticked under **Public Fields**, with each value cut to 40 characters, so a “Location” field shows something like “Rotorua” without any template changes. The values are in `comment.metaLine` if you [override](docs:template-guides/custom-templates) `_comment/author`.

To show any other field, override `_comment/content`. That template gets prepared data rather than the Comment element, so fetch the element for its field values. This example assumes a Date field with the handle `visitDate`, and goes in your copy of the default template:

```twig
{# templates/_comments/frontend/_comment/content.twig, added to a copy of the default #}
{% set element = craft.comments.fetch().id(comment.id).one() %}

{% if element and element.visitDate %}
    <p>{{ 'Visited {date}' | t('comments', { date: element.visitDate | date('long') }) }}</p>
{% endif %}
```

This runs one query for each comment, and only finds approved comments, so moderators won't see the value on a held one. If you're showing fields on a long list, build it from a [comment query](docs:getting-elements/comment-queries) instead, where `comment.visitDate` is available directly.

## Customising the Inputs

Override `_composer/custom-fields` to change how inputs are drawn. It outputs the comment box first, then loops over `composer.publicFields`, where each row has:

| Key | What It Is |
| --- | --- |
| `handle` | The field's handle. |
| `label`, `instructions`, `required` | From the field layout. |
| `type` | `text`, `textarea`, `number`, `email`, `url`, `date`, `select`, `radio`, `checkboxes`, `lightswitch` or `file`. |
| `multiple` | Whether it takes several values. |
| `options` | For Dropdown, Multi-select, Radio Buttons and Checkboxes fields, an array of `label` and `value`. |
| `values` | The current values, as an array of strings. A date is in `YYYY-MM-DD` form, and a file input's is always empty. |

The one thing your input has to do is use the right `name`, which is `fields[myFieldHandle]`, with `[]` on the end if it takes several values. For example, to replace the input for a Number field with the handle `rating` with a choice of stars, change the loop in your copy so that field gets its own markup and the others keep the default:

```twig
{# Partial: the loop in templates/_comments/frontend/_composer/custom-fields.twig #}
{% for field in composer.publicFields %}
    {% if field.handle == 'rating' %}
        <fieldset class="comments__field">
            <legend class="comments__field-label">{{ field.label }}</legend>

            {% for star in 1..5 %}
                <label>
                    <input type="radio" name="fields[rating]" value="{{ star }}" {% if (star ~ '') in field.values %}checked{% endif %}>
                    {{ star }}
                </label>
            {% endfor %}
        </fieldset>
    {% else %}
        {# The default template's markup for each field type #}
    {% endif %}
{% endfor %}
```

A form with a file input needs `enctype="multipart/form-data"`. The default `_composer/form` adds it whenever `composer.hasFileInputs` is true, and your own forms need to do the same. An Assets field's input takes its name from the field, with `[]` so it can hold several files:

```twig
<input type="file" name="fields[myFieldHandle][]" multiple>
```

Uploaded files go to the Assets field's own upload location. The default template only marks a required file input as `required` when a comment is first posted, because an edited comment already has its files.

The comment editor in the control panel always uses the field's own input, so none of this affects moderators.
