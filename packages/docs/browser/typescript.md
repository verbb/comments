# TypeScript

Every Comments package ships its own TypeScript types, so there's nothing extra to install. The browser package's types cover `window.Comments`, each thread's controller, the settings, the part and field override contexts, and every `comments:*` event with its `detail`. The types of the data itself, such as a comment or the viewer, come from `@verbb/comments-core`.

## Project Settings

The packages are ES modules only. TypeScript projects need `moduleResolution` set to `bundler`, `node16` or `nodenext`, and `lib` needs `DOM`, which a browser project has already.

## Events

Importing the package adds every event to `HTMLElementEventMap`, `DocumentEventMap` and `WindowEventMap`, so a listener knows what `event.detail` holds with no casting:

```ts
import '@verbb/comments-browser';

document.addEventListener('comments:before-submit', (event) => {
    // `event` is a CustomEvent with `form`, `mode`, `commentUid` and `formData` in its detail.
    if (!String(event.detail.formData.get('content') ?? '').trim()) {
        event.preventDefault();
        event.detail.controller?.showFormErrors(event.detail.form, { fieldErrors: { content: ['Say something.'] } });
    }
});
```

`detail.controller` is typed `CommentsController | undefined`, because an event about a thread that has already stopped may have none, so reach it with `?.`. For a listener written on its own, `CommentsEventMap` names each event's type:

```ts
import type { CommentsEventMap } from '@verbb/comments-browser';

function track(event: CommentsEventMap['comments:vote-changed']) {
    analytics.track('Vote', { comment: event.detail.commentUid, vote: event.detail.vote ?? 'removed' });
}

document.addEventListener('comments:vote-changed', track);
```

## Settings and Controllers

```ts
import { Comments, type CommentsController, type CommentsSettings } from '@verbb/comments-browser';

const settings: CommentsSettings = {
    theme: 'auto',
    darkSelector: 'html.dark',
    themeConfig: {
        primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } },
    },
};

Comments.configure(settings);

const root = document.querySelector<HTMLElement>('[data-comments-root]');
const controller: CommentsController | undefined = root ? Comments.get(root) : undefined;

controller?.refresh({ sort: 'newest' });
```

`window.Comments` is typed as `CommentsApp | undefined`, because a page may not have loaded the script.

## Part and Field Overrides

A part override is given a `PartContext`. Its shared keys, such as `html`, `labels` and `defaultRender()`, are typed, as are `comment`, `viewer` and `conversation`, while a part's own keys, such as a badge's `badge`, are `unknown` because each part has its own. Give them their type from `@verbb/comments-core`:

```ts
import type { PartContext } from '@verbb/comments-browser';
import type { BadgeData } from '@verbb/comments-core';

export const badge = ({ badge, html, defaultRender }: PartContext) => {
    const { type, label } = badge as BadgeData;

    return type === 'verified' ? html`<span class="badge badge-success">✓ ${label}</span>` : defaultRender();
};
```

A field override is given a `FieldInputContext` for `input` or a `FieldDisplayContext` for `display`, and a framework's hosts are `ComponentHost` objects.

## Exported Types

| Package | Main types |
| --- | --- |
| `@verbb/comments-browser` | `CommentsApp`, `CommentsController`, `CommentsSettings`, `MountOptions`, `ThreadOptions`, `CommentsTheme`, `LoadMode`, `CommentsEventMap`, `CommentsEventName`, `CommentsEventDetail`, `PartName`, `PartContext`, `PartOverride`, `FieldOverride`, `FieldInputContext`, `FieldDisplayContext`, `ComponentHost`, `ThemeConfig` and `ThemeControlName` |
| `@verbb/comments-core` | The data, with `*Response` for a whole answer and `*Data` for an object inside one, such as `ThreadResponse`, `CommentData`, `AuthorData`, `BadgeData`, `ViewerData` and `ErrorData`, along with `CommentsClient`, `ThreadStore`, `ThreadState`, `ThreadStoreEventMap`, `SignInProvider` and `CommentsErrorCode` |

The React and Vue packages export the types for their own props, such as `CommentsThreadProps`, `CommentsPartProps` and `CommentsFieldInputProps` in React, and re-export the common ones from the packages above.
