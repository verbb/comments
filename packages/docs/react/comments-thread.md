# The `<CommentsThread>` Component

`<CommentsThread>` is one thread. It renders the same mount point `craft.comments.render()` writes, with the reserved space, the heading and the count, and the browser package draws the thread inside it once the component has mounted in the browser.

```tsx
'use client';

import { CommentsThread } from '@verbb/comments-react';

export function Comments({ entry }) {
    return (
        <CommentsThread
            key={entry.uri}
            ownerUri={entry.uri}
            sort="top"
            loadMode="button"
            onCommentCreated={(event) => track('Comment posted', event.detail.moderationState)}
        />
    );
}
```

## Props

| Prop | What it does |
| --- | --- |
| `ownerId` or `ownerUri` | The element the comments belong to, by its ID or its URI. |
| `siteId` | The site, for a multi-site install. Defaults to the provider's. |
| `endpoint` | The data API's address. Defaults to the provider's, then `/actions/comments/api` on the page's own origin. |
| `loadMode` | `visible`, the default, loads the thread just before it scrolls into view, `button` waits for **Show comments**, and `eager` loads it straight away, as [below](#when-the-thread-loads). |
| `sort` | `newest`, `oldest` or `top`. |
| `limit` | Top-level comments in each page, from 1 to 50. |
| `theme` | `light`, `dark`, `auto` or `none`. Defaults to the provider's, then `light`, as [Dark Mode](/react/styling#dark-mode) explains. |
| `darkSelector` | A selector, such as `html.dark`, that switches the thread to dark while it matches `<html>`. |
| `themeConfig` | Classes and attributes for the ten controls, merged control by control over the provider's, as [Styling](/react/styling#themeconfig-second) explains. |
| `parts` and `fields` | Part and field overrides, as [Part Overrides](/react/part-overrides) and [Field Overrides](/react/field-overrides) explain. |
| `labels` | Wording to use instead of the site's translations, as [below](#labels). |
| `count` | The comment count to show before the thread's data arrives, such as from `fetchCommentsCount()`. |
| `id` | The mount point's `id`. Defaults to `comments-<ownerId>-<siteId>`, as `render()` writes it. |
| `className` | Classes for the mount point, alongside `comments`. |
| `onMounted`, `onCommentCreated` and the rest | A prop for every event, as [Events and Validation](/react/events) lists. |

## When Props Change

Give the component a `key` of the owner, as above, so moving to another post starts a fresh thread rather than reusing the old one. It also starts a new thread by itself when `ownerId`, `ownerUri`, `siteId`, `endpoint` or `limit` changes. A new `sort` re-sorts a loaded thread in place, keeping a half-written comment and the reader's place, and starts a thread that's still waiting again in the new order, as it does a thread whose `sort` is taken away, which goes back to the site's default. A new `theme` switches the drawn thread in place, and new `themeConfig`, `parts`, `fields` or `labels` redraw it. `loadMode` and `count` are read when the thread starts.

Props that are objects, such as `themeConfig` and `parts`, are compared by what they hold, so writing them inline doesn't redraw the thread on every render. Components given as overrides are the exception, as [Part Overrides with Components](/react/part-overrides#defining-components) explains.

## The Provider

`<CommentsProvider>` gives every thread and hook inside it the same settings, and a thread's own props win. `themeConfig` merges control by control, so the provider can put your design system's classes on every button while one thread adds a title to its own. Providers can be nested, each adding to the one around it.

| Setting | What it does |
| --- | --- |
| `endpoint` | The data API's address, such as `https://cms.example.com/actions/comments/api`. |
| `auth` | How the person is identified, [below](#signing-in). |
| `siteId` | The site, for a multi-site install. |
| `themeConfig`, `parts`, `fields` and `labels` | The same as the props, for every thread. |
| `theme` and `darkSelector` | The same as the props, for every thread. |
| `loadEditor` | Loads Editor for rich comments. Defaults to `import('@verbb/editor')`, which your bundler keeps in a file of its own. |
| `nonce` | A Content Security Policy nonce for the scripts and stylesheets a thread loads, such as the spam protection's challenges. |
| `fetch` | A `fetch` to use instead of the global one. |

## Signing In {#signing-in}

`auth` chooses how the person is identified, and depends on where your app runs relative to Craft. Each way comes from `@verbb/comments-core`, and [Signing In](/core/signing-in) explains them in full.

- On Craft's own domain, or behind a proxy that forwards `/actions/comments/*` to Craft, leave `auth` out. Craft's session cookie identifies the person.
- On another subdomain of the same site, use `cookieSession({ profile: 'same-site-credentialed' })`. Craft's `defaultCookieDomain` must be the parent domain, and the app's origin must be listed under **Settings** → **Other Sites** with **Shared Cookies** on.
- On a different site, use `memberToken()` for signed-in members and `guestSession()` for guests, with the app's origin listed under **Settings** → **Other Sites**, and **Member Tokens** on for members.

```tsx
'use client';

import { guestSession, memberToken } from '@verbb/comments-core';
import { CommentsProvider } from '@verbb/comments-react';
import { useMemo, type ReactNode } from 'react';

export function CommentsSettings({ signedIn, children }: { signedIn: boolean; children: ReactNode }) {
    // One provider for each kind of sign-in, so signing in or out starts the threads again as that person.
    const auth = useMemo(() => signedIn
        ? memberToken({ getToken: () => fetch('/api/comments-token', { cache: 'no-store' }).then((response) => response.json()) })
        : guestSession({ storage: sessionStorage }), [signedIn]);

    return <CommentsProvider endpoint="https://cms.example.com/actions/comments/api" auth={auth}>{children}</CommentsProvider>;
}
```

Make the provider once, outside your component or with `useMemo()`, as above. Each provider keeps its own token, so one made afresh on every render fetches a new member token for its first request, and a `guestSession()` without `storage` starts a new guest session each time, losing the guest's pending comment. A new provider of the same kind is taken over without starting the thread again, so nobody loses the comment they're writing, while a different kind, such as a guest signing in as a member, starts the thread again. On sign-out, call `reset()` on the provider you gave last, and every thread and hook using it clears what was private and reads the viewer again.

## When the Thread Loads

Most visitors to a post never scroll down to its comments, so a thread waits until it's needed before it loads anything. Until then the page has only the mount point, with the heading, the comment count and the space the thread will fill.

<!--@include: ../browser/lazy-loading.md#load-modes-->

`loadMode` is read when the thread starts. To load a `button` thread from a control of your own, such as a drawer's toggle, keep its controller from `onMounted` and call `load()`, which does nothing to a thread that has loaded.

```tsx
const [controller, setController] = useState<CommentsController>();

<button type="button" onClick={() => { setOpen(true); controller?.load(); }}>Comments</button>
<CommentsThread ownerUri={uri} loadMode="button" onMounted={(event) => setController(event.detail.controller)} />
```

<!--@include: ../browser/lazy-loading.md#load-pieces-->

## Labels {#labels}

<!--@include: ../browser/configuring-threads.md#labels-intro-->

```tsx
<CommentsProvider endpoint="https://cms.example.com/actions/comments/api" labels={{ post: 'Send', editorPlaceholder: 'What do you think?' }}>
    {children}
</CommentsProvider>
```

A thread's own `labels` merge over the provider's, key by key.

::: details Every label, with its default
<!--@include: ../browser/configuring-threads.md#labels-rules-->
:::

## Events {#events}

Every event a thread dispatches is a prop, named after the event, such as `onCommentCreated` for `comments:comment-created`. [Events and Validation](/react/events) lists them all with what each one gives, and covers checking a comment before it's posted.

## Server Rendering

On the server, `<CommentsThread>` renders only its mount point, so there's nothing inside it for hydration to disagree with, and the thread loads in the browser. The package's main entry is a client module, marked `'use client'`, so a server component can render `<CommentsThread>` directly as long as it passes no functions. Event props and component overrides are functions, so put a thread that has them in a file of your own that starts with `'use client'`, as [Next.js](/react/nextjs) shows.
