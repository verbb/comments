# Field Overrides

Custom fields on the comment form are drawn by Comments, each the way its type needs, from text inputs and dropdowns to a file tray for an Assets field. A field override draws one field your own way by its handle, on the form, on the comment or both, such as a rating drawn as five stars rather than a number input. The plugin's [Custom Fields](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/custom-fields) guide covers adding fields to the form, whether their values are shown, and registering a field type of your own. This page covers drawing them.

Overrides go in `fields`, keyed by the field's handle, with `input` for the form and `display` for the comment. Each is a function, a registered custom element's name, or `{ component }` for a framework component, the same kinds as a [part override](/browser/part-overrides).

## A Star Rating

Suppose the comment form asks for a `rating`, a Number field set to **Collect and show**, with a minimum of 1 and a maximum of 5. These overrides draw it as stars on the form and on each comment.

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments.configure({
        fields: {
            rating: {
                // Five stars on the form, which post a number from 1 to 5.
                input: ({ html, value, setValue, id, labelId, describedBy, error }) => html`
                    <div id=${id} role="radiogroup" aria-labelledby=${labelId} aria-describedby=${describedBy ?? ''} aria-invalid=${error ? 'true' : 'false'}>
                        ${[1, 2, 3, 4, 5].map((stars) => html`
                            <button type="button" role="radio" aria-label=${`${stars} out of 5`} aria-checked=${String(Number(value) === stars)} @click=${() => setValue(stars)}>★</button>`)}
                    </div>`,
                // And on the comment.
                display: ({ html, value }) => html`<span role="img" aria-label=${`Rated ${value} out of 5`}>${'★'.repeat(value)}${'☆'.repeat(5 - value)}</span>`,
            },
        },
    });
});
```

On the form, Comments keeps the field's label, its instructions and its error around your stars, and posts whatever `setValue()` was given last. On each comment that has a rating, the stars appear wherever the field is placed, after the date or in the list under the text. The [Ask Commenters for More with Custom Fields](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/ask-commenters-for-more-with-custom-fields) user guide starts from creating the fields in Craft and includes drawing a rating as stars.

## What an Input Override Is Given

Alongside `labels`, `html`, `controlAttributes()` and `defaultRender()`, which draws Comments' own input:

<!-- #region field-input-context -->
| Key | What it is |
| --- | --- |
| `field` | The field's description, with its `handle`, `label`, `instructions`, `required`, `type`, `multiple`, `options` and `attributes`, such as a Number field's `min` and `max`. |
| `value` | The value so far, the edited comment's or the last one `setValue()` was given. |
| `setValue(value)` | Sets what the form posts for this field, in the shape the data API takes, such as a number, a string, an option's value or a list of them. |
| `error` | The field's error, if the last post was refused because of it, or `null`. |
| `id`, `name`, `labelId` and `describedBy` | For your own control. Put `id` on the control, or on a group that holds it, so a refused post can move focus there. |
| `mode` | `create`, `reply` or `edit`. |
| `files` | An Assets field only. The edited comment's files, each with `id`, `url`, `filename` and `kind`. |
| `upload(file)` | An Assets field only. Uploads a `File` to the field and resolves with its upload, whose `id` goes into `setValue()`. |
<!-- #endregion field-input-context -->

<!-- #region field-input-assets -->
For an Assets field, `value` is the list of file IDs the form posts. An override that adds a file uploads it first, then sets the list with the new ID added.
<!-- #endregion field-input-assets -->

```js
// Part of an Assets field's input override: adds each chosen file to what the form posts.
const add = async (event, { value, setValue, upload }) => {
    for (const file of event.target.files) {
        const uploaded = await upload(file);
        value = [...(value ?? []), uploaded.id];
        setValue(value);
    }
};
```

<!-- #region field-input-uploads -->
A guest's files upload one at a time, each with a fresh spam protection preparation, so each waits the site's minimum time before it's sent, as the comment itself does. Uploads are checked on the server whatever your override allows.
<!-- #endregion field-input-uploads -->

## What a Display Override Is Given

Alongside `labels`, `html` and `defaultRender()`, which draws Comments' own text:

<!-- #region field-display-context -->
| Key | What it is |
| --- | --- |
| `field` | The comment's entry for the field, with its `handle`, `label`, `type`, `placement`, `value` and `text`. |
| `comment` | The whole comment. |
| `value` | The raw value, such as a number, a date as `YYYY-MM-DD`, an option's value or a list of files. |
| `text` | The value formatted as text in the site's language. |
<!-- #endregion field-display-context -->

Draw every value as text through `html`, never as HTML.

<!-- #region field-display-privacy -->
Only values from fields set to **Collect and show** ever reach the browser, so a display override can't reveal a value that's only collected. Comments links a web address or a file only when it's `http` or `https`, or a path on the same site, always with `rel="ugc nofollow noopener"`, and an override that draws its own links should do the same.
<!-- #endregion field-display-privacy -->

## Fields Only an Override Can Draw

<!-- #region field-custom -->
A field type registered by a plugin can describe its input as `custom`, which means Comments doesn't draw it at all. Such a field needs an `input` override, and logs a warning in the browser console naming the handle until it has one. Anything the field type's description adds for you is in `field.meta`.
<!-- #endregion field-custom -->

## Where to Set Them

`fields` merges by handle and by `input` or `display`, so a later `configure()` call can add a display override without losing an earlier input override. A thread's own options win over `Comments.configure()`, and the React and Vue components take `fields` as a prop and in their provider settings, with components as overrides.

In Twig, `craft.comments.render()` takes field overrides in its config too, as custom element names only, because the mount point carries data rather than code.

```twig
{{ craft.comments.render(entry, {
    fields: {
        rating: { input: 'acme-star-input', display: 'acme-star-rating' },
    },
}) }}
```
