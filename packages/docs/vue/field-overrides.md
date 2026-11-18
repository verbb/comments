# Field Overrides with Components

Custom fields on the comment form are drawn by Comments, each the way its type needs, from text inputs and dropdowns to a file tray for an Assets field. A field override draws one field with your own component by its handle, on the form, on the comment or both, such as a rating drawn as five stars rather than a number input. The plugin's [Custom Fields](https://verbb.io/craft-plugins/comments/docs/v4/template-guides/custom-fields) guide covers adding fields to the form, whether their values are shown, and registering a field type of your own.

Overrides go in `fields`, keyed by the field's handle, with an `input` component for the form and a `display` component for the comment. Like [part overrides](/vue/part-overrides), they're rendered through teleports, so they stay inside your app with its plugins and provided values.

## A Star Rating

Suppose the comment form asks for a `rating`, a Number field set to **Collect and show**, with a minimum of 1 and a maximum of 5. An input component draws its control from `value` and calls `setValue()`, and Comments keeps the label, the instructions and the error around it and posts whatever was set last.

```vue
<!-- RatingInput.vue -->
<script setup lang="ts">
import type { CommentsFieldInputProps } from '@verbb/comments-vue';
import { computed } from 'vue';

const props = defineProps<Pick<CommentsFieldInputProps, 'value' | 'setValue' | 'error' | 'id' | 'labelId' | 'describedBy'>>();
const current = computed(() => Number(props.value) || 0);
</script>

<template>
    <div :id="id" role="radiogroup" :aria-labelledby="labelId" :aria-describedby="describedBy ?? undefined" :aria-invalid="error ? 'true' : undefined">
        <button
            v-for="score in 5"
            :key="score"
            type="button"
            role="radio"
            :aria-checked="current === score"
            :aria-label="`${score} out of 5`"
            @click="setValue(current === score ? null : score)"
        >
            ★
        </button>
    </div>
</template>
```

```vue
<!-- RatingDisplay.vue -->
<script setup lang="ts">
import type { CommentsFieldDisplayProps } from '@verbb/comments-vue';
import { computed } from 'vue';

const props = defineProps<Pick<CommentsFieldDisplayProps, 'value'>>();
const score = computed(() => Math.max(0, Math.min(5, Number(props.value) || 0)));
</script>

<template>
    <span role="img" :aria-label="`Rated ${score} out of 5`">{{ '★'.repeat(score) }}{{ '☆'.repeat(5 - score) }}</span>
</template>
```

```vue
<CommentsThread :owner-uri="uri" :fields="{ rating: { input: RatingInput, display: RatingDisplay } }" />
```

Pressing a star again clears the rating, by setting `null`. On each comment that has a rating, the stars appear wherever the field is placed, after the date or in the list under the text. The [Vue starter](/starters/vue) uses these two components for its rating field.

## What an Input Component Is Given

Its props, typed `CommentsFieldInputProps`, are these, along with `labels`, `controlAttributes()` and `renderDefaultInto(element)`, which draws Comments' own input into an element of your component's.

<!--@include: ../browser/field-overrides.md#field-input-context-->

Put `id` on your control, or on a group that holds it, and set `aria-invalid` while `error` is set, as the rating does, so a refused post can move focus there and announce why.

<!--@include: ../browser/field-overrides.md#field-input-assets-->

```vue
<!-- Attachments.vue, an Assets field's input: adds each chosen file to what the form posts. -->
<script setup lang="ts">
import type { CommentsFieldInputProps } from '@verbb/comments-vue';

const props = defineProps<Pick<CommentsFieldInputProps, 'value' | 'setValue' | 'upload' | 'id'>>();

async function add(event: Event) {
    let ids = (props.value as number[] | null) ?? [];

    for (const file of Array.from((event.target as HTMLInputElement).files ?? [])) {
        const uploaded = await props.upload!(file);
        ids = [...ids, uploaded.id];
        props.setValue(ids);
    }
}
</script>

<template>
    <input :id="id" type="file" multiple @change="add" />
</template>
```

<!--@include: ../browser/field-overrides.md#field-input-uploads-->

## What a Display Component Is Given

Its props, typed `CommentsFieldDisplayProps`, are these, along with `labels` and `renderDefaultInto(element)`, which draws Comments' own text.

<!--@include: ../browser/field-overrides.md#field-display-context-->

Vue escapes whatever you render with `{{ }}`, so draw values that way rather than with `v-html`.

<!--@include: ../browser/field-overrides.md#field-display-privacy-->

## Fields Only an Override Can Draw

<!--@include: ../browser/field-overrides.md#field-custom-->

## Where to Set Them

`fields` is a prop of `<CommentsThread>` and a setting of `CommentsPlugin` and `provideCommentsSettings()`. They merge by handle and by `input` or `display`, so the plugin can give every thread a rating input while one thread adds its own display, and a thread's own overrides win.

As with parts, an override can also be a [`templatePart()`](/vue/part-overrides#overrides-without-vue) function or a registered custom element's name.
