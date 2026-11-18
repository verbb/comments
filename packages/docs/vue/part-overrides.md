# Part Overrides with Components

A part override replaces the markup of one part of the thread, such as the name of a comment's author, its badges or the empty state, and keeps everything else. In Vue, the override is a component. It's rendered through a `Teleport` into the place the renderer leaves for it, so it stays inside your app's tree, with your provides, stores and router, and it follows its comment when a vote or a reply redraws the thread.

## A Verified Badge

Suppose some commenters carry a `verified` badge, which your site adds on the server with the plugin's `Comments::EVENT_DEFINE_BADGES` event. This component draws it with a tick, and keeps Comments' own badge classes for every other badge so its stylesheet still styles them:

```vue
<!-- VerifiedBadge.vue -->
<script setup lang="ts">
type Badge = { type: string; label: string };

// An override gets only the props it declares.
defineProps<{ badge: Badge }>();
</script>

<template>
    <span v-if="badge.type !== 'verified'" :class="`comments__badge comments__badge--${badge.type.replace(/[^\w-]/g, '')}`">{{ badge.label }}</span>
    <span v-else class="badge badge-success">✓ {{ badge.label }}</span>
</template>
```

```vue
<script setup lang="ts">
import { CommentsThread, type CommentsPartOverrides } from '@verbb/comments-vue';
import VerifiedBadge from './VerifiedBadge.vue';

defineProps<{ uri: string }>();

const parts: CommentsPartOverrides = { badge: VerifiedBadge };
</script>

<template>
    <CommentsThread :key="uri" :owner-uri="uri" :parts="parts" />
</template>
```

The [Vue starter](/starters/vue) draws its badges with this component.

## What a Component Is Given

A component gets the part's context as props, typed as `CommentsPartProps`. It gets only the props it declares, in the component itself or through its `extends` or mixins, so the rest never land on its element as attributes, and a component that declares none gets none.

| Prop | What it is |
| --- | --- |
| The part's data | Such as `comment`, `viewer`, `permissions` and `conversation`, or `badge` for a badge. The [table](#the-parts) lists each part's. |
| `labels` | The thread's translated wording. |
| `actions` | For a comment's parts, `vote()`, `reply()`, `edit()`, `delete()`, `report()`, `copyLink()`, `moderate()`, `toggleReplies()` and `loadReplies()`, bound to the comment. |
| `controlAttributes` | A function giving the attributes of one of the ten controls with your `themeConfig` applied, which you can bind with `v-bind`. |
| `renderDefaultInto` | A function that draws Comments' own markup for the part into an element of your component's. |

## Wrapping Comments' Own Markup

`renderDefaultInto(element)` draws Comments' own markup for the part into an element of your component's, for a component that adds something around it. Call it after each render, such as from `onMounted()` and `onUpdated()`.

```vue
<script setup>
import { onMounted, onUpdated, useTemplateRef } from 'vue';

const props = defineProps(['comment', 'renderDefaultInto']);
const inner = useTemplateRef('inner');
const draw = () => inner.value && props.renderDefaultInto(inner.value);

onMounted(draw);
onUpdated(draw);
</script>

<template>
    <div ref="inner" class="content-card" />
</template>
```

For the whole `comment` part, Comments keeps its list item, the comment's form and its replies around your component, so the component draws only what goes inside.

## Defining Components

Import override components, as above, rather than defining them in a render function, which would make a new component each time. If a component throws, the error reaches your app's `errorHandler`, and Comments' own markup is drawn in its place.

## Overrides Without Vue

For static markup that needs no Vue at all, `templatePart()` passes a function to the renderer instead, which is cheaper than a `Teleport`. It's given the same context as a [function override](/browser/part-overrides#a-function), with lit-html's `html` tag. A registered custom element's name works too, as [Part Overrides with Custom Elements](/web-components/part-overrides) describes.

```js
import { templatePart } from '@verbb/comments-vue';

const parts = {
    badge: templatePart(({ badge, html, defaultRender }) => badge.type === 'verified' ? html`<span class="badge">✓ ${badge.label}</span>` : defaultRender()),
};
```

## The Parts {#the-parts}

<!--@include: ../_generated/parts.md-->

The comment form itself, the comment box, the rich text editor and the report form can't be overridden, because they carry spam protection, the editor's loading and keyboard focus. Their look is reached through [styling](/vue/styling), and custom fields on the form and the comment have components of their own, by handle, as [Field Overrides with Components](/vue/field-overrides) describes.

## Removing Something

<!--@include: ../browser/part-overrides.md#parts-removing-->
