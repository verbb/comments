# TypeScript

Every Comments package ships its own TypeScript types, so there's nothing extra to install. `@verbb/comments-vue` types its component, composables and override props, and re-exports the types you're most likely to need from the browser and core packages, so most apps import from it alone.

## Project Settings

The packages are ES modules only. TypeScript projects need `moduleResolution` set to `bundler`, `node16` or `nodenext`, which Vite's and Nuxt's templates already use, and `lib` needs `DOM`.

## Settings

`CommentsProviderSettings` types what `CommentsPlugin` and `provideCommentsSettings()` take, and `ThemeConfig` types `themeConfig` on its own, so a shared object is checked where it's written rather than where it's used.

```ts
import type { CommentsProviderSettings, ThemeConfig } from '@verbb/comments-vue';

export const themeConfig: ThemeConfig = {
    primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
};

export const settings: CommentsProviderSettings = {
    endpoint: 'https://cms.example.com/actions/comments/api',
    theme: 'auto',
    themeConfig,
};
```

## Events

Every component event is typed, so a handler in `<script setup lang="ts">` knows what `event.detail` holds. `event.detail.controller` is `CommentsController | undefined`, because an event about a thread that has already stopped may have none, so reach it with `?.`. `CommentsEventMap` names each event's type.

```vue
<script setup lang="ts">
import { CommentsThread, type CommentsEventMap } from '@verbb/comments-vue';

defineProps<{ uri: string }>();

function trackVote(event: CommentsEventMap['comments:vote-changed']) {
    analytics.track('Vote', { comment: event.detail.commentUid, vote: event.detail.vote ?? 'removed' });
}
</script>

<template>
    <CommentsThread :owner-uri="uri" @vote-changed="trackVote" />
</template>
```

`useCommentsEvent()` types its handler from the event's name in the same way.

## Part and Field Components

A part override component's props are `CommentsPartProps`. Its shared keys, such as `labels`, `actions`, `controlAttributes()` and `renderDefaultInto()`, are typed, as are `comment`, `viewer` and `conversation`, while a part's own keys, such as a badge's `badge`, are `unknown` because each part has its own. Give them their type from `@verbb/comments-core`.

```vue
<script setup lang="ts">
import type { CommentsPartProps } from '@verbb/comments-vue';
import type { BadgeData } from '@verbb/comments-core';
import { computed } from 'vue';

const props = defineProps<Pick<CommentsPartProps, 'badge'>>();
const badge = computed(() => props.badge as BadgeData);
</script>

<template>
    <span :class="`badge badge-${badge.type}`">{{ badge.label }}</span>
</template>
```

Field override components take `CommentsFieldInputProps` for `input` and `CommentsFieldDisplayProps` for `display`, as [Field Overrides with Components](/vue/field-overrides) shows. `CommentsPartOverrides` and `CommentsFieldOverrides` type the `parts` and `fields` objects.

## Composables

`UseCommentsThreadOptions` and `UseCommentsThreadResult` type `useCommentsThread()`, and `UseCommentFormOptions`, `UseCommentFormResult` and `CommentFormInput` type `useCommentForm()`. The state is a `ThreadState`, and each comment in it a `CommentData`.

## Exported Types

| From | Types |
| --- | --- |
| `@verbb/comments-vue` | `CommentsProviderSettings`, `UseCommentsThreadOptions`, `UseCommentsThreadResult`, `UseCommentFormOptions`, `UseCommentFormResult`, `CommentFormInput`, `UseCommentsEventOptions`, `UseCommentsStoreEventOptions`, `CommentsPartProps`, `CommentsPartOverride`, `CommentsPartOverrides`, `CommentsFieldInputProps`, `CommentsFieldDisplayProps`, `CommentsFieldOverride`, `CommentsFieldOverrides`, `TemplatePart`, `CommentsEmitName` and `CommentsThreadEmits` |
| Re-exported from `@verbb/comments-browser` | `CommentsController`, `CommentsEventMap`, `CommentsTheme` and `LoadMode` |
| Re-exported from `@verbb/comments-core` | `CommentData`, `SortOrder`, `ThemeConfig` and `ThreadState` |

The data's other types come from `@verbb/comments-core`, with `*Response` for a whole answer and `*Data` for an object inside one, such as `ViewerData`, `BadgeData` and `AuthorData`, along with `CommentsRequestError` and `CommentsErrorCode`.
