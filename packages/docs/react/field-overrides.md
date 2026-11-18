# Field Overrides with Components

Custom fields on the comment form are drawn by Comments, each the way its type needs, from text inputs and dropdowns to a file tray for an Assets field. A field override draws one field with your own component by its handle, on the form, on the comment or both, such as a rating drawn as five stars rather than a number input. The plugin's [Custom Fields](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/custom-fields) guide covers adding fields to the form, whether their values are shown, and registering a field type of your own.

Overrides go in `fields`, keyed by the field's handle, with an `input` component for the form and a `display` component for the comment. Like [part overrides](/react/part-overrides), they're rendered through portals, so they stay inside your app's tree with its context and state.

## A Star Rating

Suppose the comment form asks for a `rating`, a Number field set to **Collect and show**, with a minimum of 1 and a maximum of 5. An input component draws its control from `value` and calls `setValue()`, and Comments keeps the label, the instructions and the error around it and posts whatever was set last.

```tsx
import type { CommentsFieldDisplayProps, CommentsFieldInputProps } from '@verbb/comments-react';

export function RatingInput({ value, setValue, error, id, labelId, describedBy }: CommentsFieldInputProps) {
    const current = Number(value) || 0;

    return (
        <div id={id} role="radiogroup" aria-labelledby={labelId} aria-describedby={describedBy ?? undefined} aria-invalid={error ? 'true' : undefined}>
            {[1, 2, 3, 4, 5].map((score) => (
                <button key={score} type="button" role="radio" aria-checked={current === score} aria-label={`${score} out of 5`} onClick={() => setValue(current === score ? null : score)}>
                    ★
                </button>
            ))}
        </div>
    );
}

export function RatingDisplay({ value }: CommentsFieldDisplayProps) {
    const score = Math.max(0, Math.min(5, Number(value) || 0));

    return <span role="img" aria-label={`Rated ${score} out of 5`}>{'★'.repeat(score)}{'☆'.repeat(5 - score)}</span>;
}
```

```tsx
// Defined once, outside the component that renders the thread.
const fields = { rating: { input: RatingInput, display: RatingDisplay } };

<CommentsThread ownerUri={uri} fields={fields} />
```

Pressing a star again clears the rating, by setting `null`. On each comment that has a rating, the stars appear wherever the field is placed, after the date or in the list under the text. The [React starter](/starters/react) uses these two components for its rating field.

## What an Input Component Is Given

Its props, typed `CommentsFieldInputProps`, are these, along with `labels`, `controlAttributes()` and `renderDefaultInto(element)`, which draws Comments' own input into an element of your component's.

<!--@include: ../browser/field-overrides.md#field-input-context-->

Put `id` on your control, or on a group that holds it, and set `aria-invalid` while `error` is set, as the rating does, so a refused post can move focus there and announce why.

<!--@include: ../browser/field-overrides.md#field-input-assets-->

```tsx
import type { CommentsFieldInputProps } from '@verbb/comments-react';

// An Assets field's input: adds each chosen file to what the form posts.
export function Attachments({ value, setValue, upload, id }: CommentsFieldInputProps) {
    const add = async (chosen: FileList | null) => {
        let ids = (value as number[] | null) ?? [];

        for (const file of Array.from(chosen ?? [])) {
            const uploaded = await upload!(file);
            ids = [...ids, uploaded.id];
            setValue(ids);
        }
    };

    return <input id={id} type="file" multiple onChange={(event) => add(event.target.files)} />;
}
```

<!--@include: ../browser/field-overrides.md#field-input-uploads-->

## What a Display Component Is Given

Its props, typed `CommentsFieldDisplayProps`, are these, along with `labels` and `renderDefaultInto(element)`, which draws Comments' own text.

<!--@include: ../browser/field-overrides.md#field-display-context-->

React escapes whatever you render as text, so draw values in JSX rather than through `dangerouslySetInnerHTML`.

<!--@include: ../browser/field-overrides.md#field-display-privacy-->

## Fields Only an Override Can Draw

<!--@include: ../browser/field-overrides.md#field-custom-->

## Where to Set Them

`fields` is a prop of `<CommentsThread>` and a setting of `<CommentsProvider>`. They merge by handle and by `input` or `display`, so the provider can give every thread a rating input while one thread adds its own display, and a thread's own overrides win.

Define the components outside your own, as above, or memoise them, for the reason [Part Overrides](/react/part-overrides#defining-components) gives. As with parts, an override can also be a [`templatePart()`](/react/part-overrides#overrides-without-react) function or a registered custom element's name, and a component that throws is replaced by Comments' own field so the rest of the form keeps working.
