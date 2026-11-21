# Custom Fields

You can ask commenters for more than their comment, such as where they're from, a star rating or an order number. Add the fields to the Comment field layout, then choose which of them the site's comment form asks for, and whether what people enter is shown on their comment or kept for your moderators.

## Adding Fields to the Form

Go to **Comments** → **Settings** → **Comment Fields**. The page has three parts, each answering one question:

1. **Comment Field Layout:** which fields comments have, and their order, labels and widths. It's Craft's own field layout designer.
2. **The Comment Form:** which of those fields the site's comment form asks for, with a preview of the form beside a switch for each field.
3. **Answers on Comments:** where each answer appears once someone posts, with a preview of a comment beside a choice for each field.

Add your fields to the layout, then save, and they're listed in the other two parts. Every field starts not asked for, so adding one to the layout never puts it on your site by itself. Under **The Comment Form**, turn on the switch beside each field the site's form should ask for. A field that isn't asked for is only filled in from the control panel, which suits something only your team uses.

For each field that's asked for, choose where its answer shows:

| Choice | Shown on the Comment |
| --- | --- |
| **Beside the date** | Yes, cut to 40 characters beside the comment's date, which suits a short answer such as where someone's from |
| **Under the text** | Yes, with its label in a list under the comment's text, which suits longer answers, lists and files |
| **Not shown** | No. Moderators see it in the control panel, and the comment's author sees it when they edit their comment. |

A field that's asked for but **Not shown** stays private however a request is put together, which makes it the right choice for something like an order number. Show a field for something everyone should see, such as a star rating.

Showing a field that wasn't shown before only shows what people enter from then on. A value entered while the field wasn't shown stays private until its author edits the comment or you save it in the control panel, so changing the setting never publishes something people were told was private. Switching a field to **Not shown** stops Comments showing it straight away, though a CDN in front of your site can keep serving the old thread for a few minutes.

The previews use Comments' default theme and sample answers, so a site with its own theme or field overrides looks different, but its fields sit in the same places.

The same choices can be set in `config/comments.php` with [`formFields`](docs:get-started/configuration#formFields), though the settings page is the practical way to set them, because the setting is keyed by each field's place in the field layout.

## Where Fields Sit on the Form

The form asks for the fields in the layout's order, at the width set for each field, from a quarter of the form's row to all of it, so a short field can sit beside another. On a narrow form, such as on a phone, every field takes the whole row.

A field placed before the **Comment** element is drawn above the comment box, which suits a star rating, as review forms ask for the rating first. Fields after it are drawn below the box, before **Post**. With fields on the form, the comment box and its formatting toolbar are outlined together, so the toolbar reads as the comment's rather than the whole form's.

Tabs only group fields in the control panel. The site's form asks for every tab's fields, in order, as one list.

## Replies

A field is only asked for on top-level comments unless you turn on **Include for replies**, so a rating isn't asked for again on every reply. A field that's off for replies isn't accepted on a reply either.

## Who's Asked

The field layout's conditions decide who each field is offered to, on the site as in the control panel. A field with a user condition, such as one for a member group, is never offered to a guest. Anyone a field isn't offered to can't fill it in, however they send it, and isn't held to its **Required** setting.

Conditions are checked once, as the form opens, against the comment as it's about to be written, with its element, site, author and, for a reply, the comment it replies to. A condition that depends on what's entered in another field can't follow along as someone types, so the settings page points such a field out.

## Editing a Comment

Each comment remembers the fields it was posted with. When its author edits it, the form asks for those same fields with what they entered, even if the field layout, its conditions or the fields' settings have changed since, apart from any field you've since turned **Off**, whose value is left as it was. Moderators edit every field in the control panel, as for any element.

## Supported Field Types

The form can draw these field types:

| Field Type | Input | Shown As |
| --- | --- | --- |
| Plain Text | A text input, or a text area for multi-line fields | The text |
| Number | A number input, with the field's minimum, maximum and step or decimals | The number, with the field's prefix and suffix |
| Email | An email input | The address |
| Link | A URL input, for Link fields that take web addresses | A link, for `http` and `https` addresses only |
| Date | A date input | The date in the site's language |
| Dropdown, Radio Buttons | A select or radio buttons | The option's label |
| Multi-select, Checkboxes | A multiple select or checkboxes | The options' labels |
| Lightswitch | A checkbox | The field's on or off label |
| Assets | A file upload | Each file, as a link, with a thumbnail for images |

Field types that extend one of these work the same way. Other field types are listed with a note saying the form can't draw them, and can't be chosen until a plugin describes them, as [Custom Form Field Types](docs:developers/custom-form-field-types) explains.

::: warning
An Email field that's shown puts each commenter's address on their comment to everyone, which the settings page warns about.
:::

## File Uploads

An Assets field takes files uploaded through Comments as they're chosen, before the comment is posted, which the thread does for you. Comments checks each file the same way as attachments. Pages, SVG drawings, XML and scripts are refused whatever the field allows, a file has to be what its extension says, and images lose their metadata, including where a photo was taken, with HEIC and AVIF photos only accepted where the server can remove it, as [Keeping Uploads Safe](docs:feature-tour/rich-authoring#keeping-uploads-safe) explains. The field's own file kinds and limit on the number of files apply too, along with Comments' **Maximum File Size** and Craft's `maxUploadFileSize`.

A file waits in a temporary folder until its comment is posted, then moves to the field's upload location. One that's never posted is removed after a day, and each person can have 20 files waiting.

Guests are only offered Assets fields when **Guests Can Upload Files** is on, which it isn't to start with, because their uploads are anonymous. With it on, each guest upload is checked by spam protection like a comment, guests from one address can have 20 files and 50 MB waiting between them, and a guest's upload is removed after two hours unless it's posted. Everyone only ever attaches their own files.

Serve the upload volume from a separate host with `X-Content-Type-Options: nosniff`, as [Keeping Uploads Safe](docs:feature-tour/rich-authoring#keeping-uploads-safe) shows.

::: warning
A file collected by an Assets field that's **Not shown** is only private through Comments. If the field's volume has public URLs, anyone with a file's address can open it, and if a public GraphQL schema can read the volume, anyone can list its files, so the settings page warns about both. Use a volume without public URLs for private files.
:::

## Showing Values in Your Own Code

When you list comments yourself with a [comment query](docs:getting-elements/comment-queries), use `comment.shownFields` to show their custom field values. It holds the values a comment shows publicly, in the field layout's order, under the same rules as the thread, so it only has shown fields, and only values entered while the field was shown. Each item has the field's `handle`, `label`, `type`, `placement` (`details` or `inline`), its raw `value` and its formatted `text`.

This example shows each comment's public values under its text, in an entry template:

```twig
{% set comments = craft.comments.fetch()
    .owner(entry)
    .orderBy('commentDate desc')
    .limit(10)
    .all() %}

{% for comment in comments %}
    <article>
        <p>{{ comment.getAuthorName() }}, {{ comment.commentDate | date('short') }}</p>
        <p>{{ comment.getComment() | nl2br }}</p>

        {% if comment.shownFields %}
            <dl>
                {% for field in comment.shownFields %}
                    <dt>{{ field.label }}</dt>
                    <dd>{{ field.text }}</dd>
                {% endfor %}
            </dl>
        {% endif %}
    </article>
{% endfor %}
```

A comment with a “Where are you?” field shows “Where are you?” followed by “Rotorua”, and a comment without any shown values shows only its text. `text` is plain text, which Twig escapes, so it's always safe to output.

Use `value` when you need the raw value instead. For an Assets field it's a list of files, each with its `url`, `filename`, `kind` and, for an image, `width` and `height`. For a Link field it's the address, which is `null` unless it's an `http` or `https` address, so check it before you link to it:

```twig
{% for field in comment.shownFields %}
    {% if field.type == 'file' %}
        {% for file in field.value %}
            <a href="{{ file.url }}" rel="ugc nofollow noopener">{{ file.filename }}</a>
        {% endfor %}
    {% elseif field.type == 'url' and field.value %}
        <a href="{{ field.value }}" rel="ugc nofollow noopener">{{ field.text }}</a>
    {% else %}
        {{ field.text }}
    {% endif %}
{% endfor %}
```

::: warning
A comment's field values are also available by their handle, such as `comment.orderNumber`, as on any element. That returns the value whatever the field's setting, including one that's **Not shown** and meant to stay private, so never print a custom field that way on a public page. Use `comment.shownFields` instead.
:::

In the data API and the JavaScript thread, each comment's `fields` holds the same items, as described in [Data API](docs:headless/data-api#the-comment-shape). Values that are only collected are never there, nor in GraphQL for a schema that can't moderate comments, the [structured data](docs:template-guides/structured-data), notification emails or anything cached.

::: warning
If you add your own data to comments with the [`defineCommentData`](docs:developers/events#the-defineCommentData-event) event, it's public and cached, so never include a value that's only collected. In Dev Mode, Comments logs a warning when it finds one.
:::

## Drawing a Field Your Own Way

To draw a field differently in the thread, such as a Number field drawn as stars on the form and on each comment, give it a field override by its handle. An `input` override draws the field on the comment form, inside Comments' own label, instructions and error message, and a `display` override draws its value on each comment. The [package docs](https://docs.verbb.io/comments/browser/field-overrides) explain field overrides for the bundled script, the web component and the React and Vue components, and [Ask Commenters for More with Custom Fields](docs:user-guides/forms-data/ask-commenters-for-more-with-custom-fields) builds a star rating step by step.

When your overrides are custom elements you've registered, you can name them in your template with `render()`'s `fields` option, keyed by field handle:

```twig
{{ craft.comments.render(entry, {
    fields: {
        rating: { input: 'star-rating-input', display: 'star-rating' },
    },
}) }}
```

The comment editor in the control panel always uses the field's own input, so none of this affects moderators.

## Handles Comments Uses

A field on the Comment layout can't use a handle Comments uses itself, such as `comment`, `email`, `name`, `ownerId`, `parentUid`, `notifyReplies` or `operationId`, because the comment form, the data API and GraphQL already send those. The layout won't save with one, and a field that already has one can't be chosen until its handle changes.

## GraphQL

`createComment` and `saveComment` take the fields that are on the form as arguments, under the same rules as the form. A schema that can moderate comments reads every field, for someone who can moderate. Any other gets the fields a comment shows and `null` for the rest, can't search comments by custom fields or order them by anything but their own columns, such as `commentDate`, and can't relate comments to other elements in either direction while the layout has a relation field. [GraphQL Mutations](docs:graphql/mutations) has the details.
