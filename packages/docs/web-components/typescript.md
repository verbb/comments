# TypeScript

Every Comments package ships its own TypeScript types, so there's nothing extra to install. `@verbb/comments-web-components` types the element and re-exports the browser package's types you're most likely to need, so most sites import from it alone.

## Project Settings

The packages are ES modules only. TypeScript projects need `moduleResolution` set to `bundler`, `node16` or `nodenext`, and `lib` needs `DOM`, which a browser project has already.

## The Element

Importing the package adds `<verbb-comments>` to `HTMLElementTagNameMap`, so `querySelector()` and `createElement()` know it's a `CommentsThreadElement`, with its attributes as typed properties and its object settings, such as `themeConfig`, `parts` and `auth`, checked where you set them.

```ts
import { registerCommentsWebComponents, type ThemeConfig } from '@verbb/comments-web-components';

registerCommentsWebComponents();

const themeConfig: ThemeConfig = {
    primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
};

const thread = document.querySelector('verbb-comments');

if (thread) {
    thread.themeConfig = themeConfig;
    thread.controller?.load();
}
```

`controller` is `CommentsController | null`, because it's `null` until the thread starts.

## Events

Importing the package also adds every event to `HTMLElementEventMap`, `DocumentEventMap` and `WindowEventMap`, so a listener knows what `event.detail` holds with no casting. `event.detail.controller` is `CommentsController | undefined`, because an event about a thread that has already stopped may have none, so reach it with `?.`.

```ts
thread?.addEventListener('comments:before-submit', (event) => {
    if (!String(event.detail.formData.get('content') ?? '').trim()) {
        event.preventDefault();
        event.detail.controller?.showFormErrors(event.detail.form, { fieldErrors: { content: ['Say something.'] } });
    }
});
```

For a listener written on its own, `CommentsEventMap` names each event's type.

```ts
import type { CommentsEventMap } from '@verbb/comments-web-components';

function trackVote(event: CommentsEventMap['comments:vote-changed']) {
    analytics.track('Vote', { comment: event.detail.commentUid, vote: event.detail.vote ?? 'removed' });
}

thread?.addEventListener('comments:vote-changed', trackVote);
```

## Part and Field Overrides

A part override is given a `PartContext`. Its shared keys, such as `html`, `labels` and `defaultRender()`, are typed, as are `comment`, `viewer` and `conversation`, while a part's own keys, such as a badge's `badge`, are `unknown` because each part has its own. Give them their type from `@verbb/comments-core`.

```ts
import type { PartContext } from '@verbb/comments-web-components';
import type { BadgeData } from '@verbb/comments-core';

export const badge = ({ badge, html, defaultRender }: PartContext) => {
    const { type, label } = badge as BadgeData;

    return type === 'verified' ? html`<span class="badge badge-success">✓ ${label}</span>` : defaultRender();
};
```

A field override is typed `FieldOverride`, and its functions are given a `FieldInputContext` for `input` or a `FieldDisplayContext` for `display`, which come from `@verbb/comments-browser`.

## Exported Types

| From | Types |
| --- | --- |
| `@verbb/comments-web-components` | `CommentsThreadElement`, and re-exported from the browser package, `CommentsController`, `CommentsEventMap`, `CommentsEventName`, `CommentsTheme`, `LoadMode`, `PartName`, `PartContext`, `PartOverride`, `FieldOverride`, `ComponentOverride` and `ThemeConfig` |
| `@verbb/comments-browser` | `FieldInputContext`, `FieldDisplayContext`, `CommentsSettings` for `configure()`, and the rest of the browser package's types |
| `@verbb/comments-core` | The data, with `*Response` for a whole answer and `*Data` for an object inside one, such as `CommentData`, `ViewerData` and `BadgeData`, along with `CommentsRequestError` and the sign-in providers' types |
