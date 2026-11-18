# Field Overrides

Custom fields on the comment form are drawn by Comments, each the way its type needs, from text inputs and dropdowns to a file tray for an Assets field. A field override draws one field your own way by its handle, on the form, on the comment or both, such as a rating drawn as five stars rather than a number input. The plugin's [Custom Fields](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/custom-fields) guide covers adding fields to the form, whether their values are shown, and registering a field type of your own.

Overrides go in the element's `fields` property, keyed by the field's handle, with `input` for the form and `display` for the comment. Each is a function or a registered custom element's name, the same kinds as a [part override](/web-components/part-overrides).

## A Star Rating

Suppose the comment form asks for a `rating`, a Number field set to **Collect and show**, with a minimum of 1 and a maximum of 5. These overrides draw it as stars on the form and on each comment.

```js
document.querySelector('verbb-comments').fields = {
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
};
```

On the form, Comments keeps the field's label, its instructions and its error around your stars, and posts whatever `setValue()` was given last. On each comment that has a rating, the stars appear wherever the field is placed, after the date or in the list under the text.

## What an Input Override Is Given

Alongside `labels`, `html`, `controlAttributes()` and `defaultRender()`, which draws Comments' own input:

<!--@include: ../browser/field-overrides.md#field-input-context-->

Put `id` on your control, or on a group that holds it, and set `aria-invalid` while `error` is set, as the rating does, so a refused post can move focus there and announce why.

<!--@include: ../browser/field-overrides.md#field-input-assets-->

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

<!--@include: ../browser/field-overrides.md#field-input-uploads-->

## What a Display Override Is Given

Alongside `labels`, `html` and `defaultRender()`, which draws Comments' own text:

<!--@include: ../browser/field-overrides.md#field-display-context-->

Draw every value as text through `html`, or `textContent` in a custom element, never as HTML.

<!--@include: ../browser/field-overrides.md#field-display-privacy-->

## As Custom Elements

An override can be a registered custom element's name instead. Comments creates the element once and sets each key above on it as a property every time the field is drawn, as [Part Overrides with Custom Elements](/web-components/part-overrides) describes for parts.

```js
class StarRating extends HTMLElement {
    set value(value) {
        const score = Math.max(0, Math.min(5, Number(value) || 0));

        this.setAttribute('role', 'img');
        this.setAttribute('aria-label', `Rated ${score} out of 5`);
        this.textContent = '★'.repeat(score) + '☆'.repeat(5 - score);
    }
}

customElements.define('acme-star-rating', StarRating);

document.querySelector('verbb-comments').fields = { rating: { display: 'acme-star-rating' } };
```

A custom element name is also the only kind a thread Craft renders can take through `craft.comments.render()`, because the mount point carries data rather than code.

## Fields Only an Override Can Draw

<!--@include: ../browser/field-overrides.md#field-custom-->

## Where to Set Them

`fields` merges by handle and by `input` or `display`. Overrides given to `configure()` from `@verbb/comments-browser` reach every thread on the page, and an element's own `fields` win over them, so the page can give every thread a rating input while one element adds its own display.
