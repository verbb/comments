# Ask Commenters for More with Custom Fields

A comment box on its own is enough for most discussions, but some sites need a little more from the people commenting. A recipe site wants a rating and a photo of the finished dish. A shop's support team wants an order number, without it being shown to everyone. A community wants members to say where they're writing from, without asking guests the same.

Comments handles all of these with ordinary Craft custom fields. You add the fields to the comment's field layout, then choose for each one whether visitors fill it in on the comment form, and whether what they enter is shown on the comment or kept private. There's no template to write for any of it, because the thread draws each field with an input that suits its type.

In this guide you'll set up four fields for the blog of a cookware shop called Hearth & Hob, whose recipes are written with its own pans:

- **Rating**, a score from 1 to 5 on the recipe, shown on each top-level comment, and drawn as stars on the form and the comment.
- **Photo**, a picture of the cook's finished dish, shown under their comment.
- **Cooking From**, the city a member is cooking in, shown after the date, and only asked of signed-in members.
- **Order Number**, for anyone having trouble with a pan, which only the shop's team and the commenter can see.

The [Craft starter](https://docs.verbb.io/comments/starters/craft) has the same four kinds of field, named Rating, Photo, Reading From and Order Number, if you'd like to see them working first.

## What You'll Need

You'll need Comments installed and its thread on your entry template, output with `craft.comments.render(entry)`, as [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) describes. You'll need an admin account to create fields and change Comments' settings, and, for the members-only field, a user group for your members. This guide's group is called **Members**.

The photo field needs an asset volume to upload into, with public URLs so the photos can be shown on comments. This guide uses a volume called **Comment Photos**. You'll also need a JavaScript file your layout loads, for the star rating near the end of the guide.

## The Choices

Each custom field on a comment has two choices about the site:

- **The Comment Form** has a switch for each field, which decides whether the site's comment form asks for it. A field that isn't asked for is only filled in by someone editing the comment in the control panel. Every field starts not asked for. **Include for replies** decides whether replies ask for it too, as well as top-level comments.
- **Where its answer shows**, once someone posts: **Beside the date**, for short values such as a number or a city, **Under the text**, in a labelled list for longer values, lists and files, or **Not shown**. An answer that's not shown is private: moderators see it on the comment in the control panel, and the commenter sees it when they edit their own comment, but it's never shown in the thread, sent to other visitors, or included in Comments' emails, structured data or public GraphQL.

This is a decision about privacy as much as layout, so think about each field before showing it. Something a commenter would be uncomfortable seeing published, such as an order number or a phone number, should only ever be **Not shown**.

## Creating the Fields

Create each field under **Settings** → **Fields** with **New field**, as you would any Craft field:

| Name | Handle | Field Type | Settings |
| --- | --- | --- | --- |
| Rating | `rating` | Number | **Min Value** `1`, **Max Value** `5`, **Decimals** `0` |
| Photo | `photo` | Assets | Restrict uploads to the **Comment Photos** volume, allow only **Image** files, and set **Max Relations** to `1` |
| Cooking From | `cookingFrom` | Plain Text | **Character Limit** `60`, with the instructions "Your city, if you'd like to share it." |
| Order Number | `orderNumber` | Plain Text | **Character Limit** `20`, with the instructions "Only our team sees this. We'll use it to look into your order." |

The handle is the name a field is known by in code. You'll use `rating` again when you draw the stars, so keep it as it is.

The instructions show under the field's label on the comment form, so write them for the people commenting. The Order Number's instructions tell them it's private, which makes them more comfortable giving it.

## Adding the Fields to Comments

Go to **Comments** → **Settings** → **Comment Fields**. The **Comment Field Layout** at the top decides the fields a comment has. Add the four fields to it, in the order you'd like them on the form, and put Rating above **Comment**, so the form asks for the rating before the comment, as review forms do. Then press **Save**.

The page now shows two more parts below the layout, **The Comment Form** and **Answers on Comments**, each with a preview beside a list of the fields. Every field starts not asked for, so adding a field to the layout never puts it on your site by itself.

You can also set each field's width in the layout, from its **Settings**. Give Cooking From and Order Number half the width each, and they sit side by side on the form.

Some fields may say they can't be collected, with the reason. That happens for a field type Comments doesn't know how to draw on the site, such as a Matrix field, which you can still use in the control panel. Every field in this guide is a type Comments draws.

## Choosing What Each Field Does

Under **The Comment Form**, turn on the switch beside all four fields, then set them like this, and press **Save**:

| Field | Include for Replies | Answers on Comments |
| --- | --- | --- |
| Rating | Off | **Beside the date** |
| Photo | Off | **Under the text** |
| Cooking From | On | **Beside the date** |
| Order Number | Off | **Not shown** |

The previews change as you go, so you can see the form and a posted comment before you save.

Here's why each one is set that way.

**Rating** is a rating of the recipe, so it belongs on top-level comments only. A reply such as "Did you use fresh thyme?" isn't rating anything, so **Include for replies** is off, and the reply form leaves the field out. It's a single short number, so it sits after the date.

**Photo** is a file, which needs room for a thumbnail, so it goes in the details under the comment's text. A photo of the dish only makes sense on a review, so it's off for replies too.

**Cooking From** is short, so it sits after the date, and it's fine on replies.

**Order Number** is asked for but **Not shown**, so it's on the form but never shown.

Open a recipe on your site, signed in, and press into the comment box. Below it, the form now has **Rating** as a number input, **Photo** with a button to add a file, **Cooking From** and **Order Number** as text inputs, each with its label and instructions. Press **Reply** on an existing comment, and the reply form only has **Cooking From**.

### Making a Field Required

A field that must be filled in is set as required in the field layout, the same as anywhere in Craft. Back in the **Comment Field Layout**, click the **Rating** field to open its settings, turn on **Required** and save the layout, then save the settings page. The form now marks Rating as required, and refuses a comment without one with a message beside the field.

Only make a field required when a comment really can't do without it. Every required field is one more reason for someone to give up on commenting.

## Asking Only Members for a Field

Hearth & Hob only wants to ask signed-in members where they're cooking from, because a city from an anonymous guest isn't worth much. A Craft field layout can show a field to some people and not others with a condition, and Comments follows that condition on the site's form too.

In the **Comment Field Layout**, click the **Cooking From** field to open its settings. Under **Current User Condition**, which says "Only show for users who match the following rules", add a rule for **User Group** and choose **Members**. Apply the settings, save the layout, and save the settings page.

Open a recipe in a private window and choose **Comment as a guest**. The form has no **Cooking From** field. Signed in as a member, it's there. Comments checks the condition on the server too, so a guest who sends a value for the field anyway, by crafting a request themselves, is refused.

Conditions on the person work well on the site's form. Conditions on what's entered in other fields, such as showing a field only when Rating is 1, are checked once as the form opens, before anything's entered, so the settings page marks a field with that kind of condition with a note.

## Letting Guests Upload Photos

Members can upload a photo straight away. Guests can't, unless you allow it, because a guest's upload is anonymous, so Comments can only limit it by the address it comes from.

If you want guests to share photos, turn on **Guests Can Upload Files** at the bottom of the page and save. Guests are then offered the Photo field too. Their uploads are checked for spam like a comment, limited in number and size from any one address, and removed after two hours if the comment is never posted.

Try it as a guest. Choose a photo in the Photo field, and the form uploads it straight away, showing a thumbnail with **Remove**. When you post the comment, the photo moves into the **Comment Photos** volume, and the comment shows it under its text.

## Testing What Each Visitor Sees

Post a few test comments to check each field's mode works as you'd expect:

1. Signed in as a member, post a comment with a rating of 4, a photo, "Ballarat" in **Cooking From** and "HH-20931" in **Order Number**.
2. In a private window, signed out, reload the recipe. The comment shows "4" and "Ballarat" after its date, and the photo under its text, labelled **Photo**. The order number is nowhere on the page.
3. Back as the member, open the comment's **···** menu and choose **Edit**. All four fields are on the edit form, including the order number, because it's their own comment.
4. In the control panel, open the comment under **Comments**. Its custom fields are all there, the order number included.

You can confirm the order number isn't sent to visitors by opening the thread's data yourself, at your site's URL followed by `/actions/comments/api/thread?ownerId=` and the entry's ID. The comment's `fields` list has the rating, city and photo, and nothing for the order number.

A comment keeps the fields it was posted with, so editing it later asks for the same ones, even if the field's settings have changed since. Showing a field that wasn't shown only shows values entered from then on, so nobody's private answer is published by a later change of settings.

## Drawing the Rating as Stars

The rating works, but "4" after the date doesn't say much, and a number input isn't how people expect to rate a recipe. A **field override** replaces how one field is drawn, by its handle, on the form, on the comment, or both. Everything around it stays Comments' own, including the field's label, instructions and error message, so you only draw the stars.

Add this to the JavaScript file your layout loads, such as `web/js/site.js`:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments?.configure({
        fields: {
            rating: {
                // Five stars on the form, which post a number from 1 to 5.
                input: ({ html, value, setValue, error, id, labelId, describedBy }) => {
                    const current = Number(value) || 0;

                    return html`
                        <div id=${id} class="hob-stars" role="radiogroup" aria-labelledby=${labelId} aria-describedby=${describedBy ?? ''} aria-invalid=${error ? 'true' : 'false'}>
                            ${[1, 2, 3, 4, 5].map((score) => html`
                                <button type="button" role="radio" class="hob-star" aria-checked=${String(current === score)} aria-label=${`${score} out of 5`} @click=${() => setValue(current === score ? null : score)}>★</button>`)}
                        </div>`;
                },
                // The same stars on each comment.
                display: ({ html, value }) => {
                    const score = Math.max(0, Math.min(5, Number(value) || 0));

                    return html`<span class="hob-stars" role="img" aria-label=${`Rated ${score} out of 5`}>${'★'.repeat(score)}<span class="hob-stars__empty">${'★'.repeat(5 - score)}</span></span>`;
                },
            },
        },
    });
});
```

Comments' script runs just before the `DOMContentLoaded` event, so `window.Comments` is ready inside the listener, and `?.` skips pages without comments. `fields` holds overrides keyed by field handle, and `rating` has two, `input` for the form and `display` for the comment.

The `input` override is given the field's current `value`, and `setValue()` to change what the form posts. Pressing a star calls `setValue()` with its score, or with `null` to clear the rating if it's pressed again. The form posts whatever was set last, so there's no hidden input to manage. The override is also given `id`, `labelId` and `describedBy`, which connect the stars to the field's label and instructions for screen readers, and `error`, which is set when a post was refused because of this field, such as a missing required rating. Putting `id` on the group lets Comments move the keyboard focus to it in that case.

The `display` override is given the comment's `value` for the field and draws filled stars for the score and empty ones for the rest, with a label saying the score for screen readers.

Both build their markup with `html`, a template tag that escapes whatever is inserted with `${…}`. Always build an override's markup with it, so nothing a commenter entered can turn into markup on your page.

The stars need a little CSS. Add this to your stylesheet:

```css
.hob-stars {
    display: inline-flex;
    gap: 0.125rem;
    color: #d97706;
}

.hob-stars .hob-star {
    border: 0;
    background: none;
    padding: 0;
    font-size: 1.5rem;
    line-height: 1;
    color: #d6d3d1;
    cursor: pointer;
}

.hob-stars .hob-star[aria-checked='true'],
.hob-stars .hob-star:has(~ .hob-star[aria-checked='true']) {
    color: #d97706;
}

.hob-stars__empty {
    color: #d6d3d1;
}
```

The star rules start with `.hob-stars` so they're specific enough to win over Comments' own rule for buttons in the thread, wherever your stylesheet loads. The third rule colours the chosen star and every star before it, so pressing the fourth lights up four.

Reload a recipe and open the comment form. Rating is now five grey stars. Press the fourth and the first four turn amber, and the comment is posted with a rating of 4. On the comment, four amber stars and one grey follow the date.

In a React or Vue app, a field override is one of your own components, given the same props. The [React starter](https://docs.verbb.io/comments/starters/react) and [Vue starter](https://docs.verbb.io/comments/starters/vue) draw this rating with components called `RatingInput` and `RatingDisplay`.

## Showing Values in Your Own Templates

The thread shows each field's value by itself. If you also list comments in Twig yourself, such as the latest reviews on a recipe index, show their values with `comment.shownFields`. It holds only the values the comment shows publicly, each with its `label`, its value as text in `text`, its `handle` and its raw `value`:

```twig
{% for comment in craft.comments.fetch().owner(entry).limit(5).all() %}
    <article>
        <p><strong>{{ comment.authorName }}</strong></p>

        {% for field in comment.shownFields %}
            <p>{{ field.label }}: {{ field.text }}</p>
        {% endfor %}
    </article>
{% endfor %}
```

A comment with a rating and a city lists "Rating: 4" and "Cooking From: Ballarat". The order number never appears, because it's collected rather than shown. For a photo, `text` is the file's name, and `value` holds each file with its `url`, for an image tag.

Don't output a field by its handle, such as `comment.orderNumber`, in a template visitors see. That gives you the value whatever the field's mode, so it would publish a private order number. Use `comment.shownFields` instead. [Custom Fields](docs:template-guides/custom-fields) covers the template side in more detail.

## Using a Field Type from Another Plugin

The thread knows how to draw Craft's own field types, such as Plain Text, Number, Email, Link, Date, Dropdown, Radio Buttons, Checkboxes, Lightswitch and Assets. A field type from another plugin, or one of your own, shows under **The Comment Form** as one that can't be collected, until that plugin tells Comments how to draw it.

A plugin does that by registering a description of its field type with Comments, saying which input to draw, how to read what's posted, and how to show the value on a comment. It can use one of the thread's built-in inputs, or draw its own with a field override like the stars above. [Custom Form Field Types](docs:developers/custom-form-field-types) explains how to write one. If a plugin you use doesn't have one yet, it's worth asking its developer.
