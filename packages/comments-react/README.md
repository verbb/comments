# Comments React

`@verbb/comments-react` puts [Comments](https://verbb.io/craft-plugins/comments) threads in React 19 apps, including Next.js. `<CommentsThread>` draws a thread with the same renderer the plugin uses on Craft's own pages, so readers, commenters and moderators get everything the plugin offers, while your app keeps control of the settings, the events and any part you'd rather draw with your own components. For a completely custom interface, the hooks give you the thread's state and actions from `@verbb/comments-core`.

```bash
npm install @verbb/comments-react
```

```tsx
import { CommentsProvider, CommentsThread } from '@verbb/comments-react';
import '@verbb/comments-browser/css/comments.css';

export function Post({ entry }) {
    return (
        <CommentsProvider endpoint="https://cms.example.com/actions/comments/api">
            <article>{/* … */}</article>
            <CommentsThread key={entry.uri} ownerUri={entry.uri} theme="auto" onCommentCreated={(event) => track(event.detail.commentUid)} />
        </CommentsProvider>
    );
}
```

The thread loads when it's about to scroll into view (`loadMode="visible"`, the default), when a visitor presses **Show comments** (`loadMode="button"`), or straight away (`loadMode="eager"`). `loadMode` is read when the thread starts. Give the component a `key` of the owner, as above, so moving to another post starts a fresh thread rather than reusing the old one. It also starts a new thread by itself when `ownerId`, `ownerUri`, `siteId`, `endpoint` or `limit` changes. A new `sort` re-sorts a loaded thread in place, keeping a half-written comment and the reader's place, as `<verbb-comments>` does, and starts a thread that's still waiting again in the new order, as it does a thread whose `sort` is taken away, which goes back to the default. `count` is the number shown before the thread's data arrives, read when the thread starts.

## Settings

`<CommentsProvider>` gives every thread and hook inside it the same `endpoint`, `auth`, `siteId`, `themeConfig`, `parts`, `fields`, `labels`, `theme`, `darkSelector` and `loadEditor`. A thread's own props win, and `themeConfig` merges per control, so a provider can put your design system's classes on every button while one thread adds a title to its own. With `darkSelector`, such as `html.dark`, threads follow your site's own dark-mode toggle.

### Signing in

`auth` chooses how the person is identified, and depends on where your app runs relative to Craft. Each way comes from `@verbb/comments-core`.

- **On Craft's own domain**, or behind a proxy that forwards `/actions/comments/*` to Craft, leave `auth` out. Craft's session cookie identifies the person.
- **On another subdomain of the same site**, such as `www.example.com` with Craft on `cms.example.com`, use `cookieSession({ profile: 'same-site-credentialed' })`. Craft's `defaultCookieDomain` must be the parent domain (`.example.com`), and the app's origin must be listed in **Settings → Other Sites** with **Shared Cookies** on.
- **On a different site**, use `memberToken()` for signed-in members and `guestSession()` for guests. List the app's origin in **Settings → Other Sites**, with **Member Tokens** on for members. `getToken()` fetches a short-lived token from your own server, which asks Comments for it with a request signed with the **Member Token Key**, and answers `{ token, expiresAt }`.

```tsx
import { cookieSession, guestSession, memberToken } from '@verbb/comments-core';

// Another subdomain of the same site.
const auth = cookieSession({ profile: 'same-site-credentialed' });

// A different site.
const auth = member
    ? memberToken({ getToken: () => fetch('/api/comments-token').then((response) => response.json()) })
    : guestSession({ storage: sessionStorage });

<CommentsProvider endpoint="https://cms.example.com/actions/comments/api" auth={auth}>…</CommentsProvider>
```

Make the provider once, outside your component or with `useMemo()`. Each provider keeps its own token, so one made afresh on every render fetches a new member token for its first request, and a `guestSession()` without `storage` starts a new guest session each time, losing the guest's pending comment. A new provider of the same kind is taken over without starting the thread again, so nobody loses the comment they're writing, while a different kind (a guest signing in as a member) starts the thread again. On sign-out, call `reset()` on the provider you gave last, and every thread and hook using it clears what was private and reads the viewer again.

### Editor

Rich authoring loads Editor from npm when someone starts writing, with `import('@verbb/editor')`, so your bundler keeps it in a chunk of its own, and registers its element. Editor's stylesheet is yours to import alongside Comments', as `import '@verbb/editor/styles'`. Pass `loadEditor` to load Editor another way.

## Events

Every browser event is a prop, named after the event: `onMounted`, `onCommentCreated`, `onVoteChanged`, `onBeforeSubmit` and the rest. Each handler gets the event itself, so a cancelable one can stop what's about to happen.

```tsx
<CommentsThread
    ownerId={entry.id}
    onBeforeSubmit={(event) => {
        if (String(event.detail.formData.get('content') ?? '').trim().length >= 20) return;
        event.preventDefault();
        event.detail.controller?.showFormErrors(event.detail.form, { fieldErrors: { content: ['Please write at least 20 characters.'] } });
    }}
/>
```

`useCommentsEvent('comments:comment-created', handler)` listens for an event from any thread on the page, or from one with `{ instanceId }`, or on an element of your own with `{ target: ref }`.

## Component overrides

A part override replaces the markup of one part of the thread, such as the name of a comment's author, its badges or the empty state, and keeps everything else. Pass a React component and it's rendered through a portal into the place the renderer leaves for it, so it stays inside your app's tree, with your context, state and router, and it follows its comment when a vote or a reply redraws the thread. The component gets the part's data as props, with `labels`, `actions` bound to the comment (`vote()`, `reply()`, `report()` and the rest) and `controlAttributes()` for your `themeConfig`.

```tsx
import { CommentsThread, type CommentsPartProps } from '@verbb/comments-react';

function Author({ comment }: CommentsPartProps) {
    const { member } = useSession();
    return <strong className="author">{comment.author.name}{comment.author.name === member?.name ? ' (you)' : null}</strong>;
}

<CommentsThread ownerId={entry.id} parts={{ authorName: Author }} />
```

Define override components outside your own component, as above. A component written inline is a new component on every render, so React would mount it afresh each time and lose its state. If one throws, the error is logged and Comments' own markup is drawn in its place, so the rest of your app keeps working.

`renderDefaultInto(element)` draws Comments' own markup for the part into an element of your component, for a component that wraps it. For static markup that needs no React at all, `templatePart()` passes a function to the renderer instead, which is cheaper. A registered custom element's name works too.

```tsx
import { html } from 'lit-html';
import { templatePart } from '@verbb/comments-react';

parts={{ badge: templatePart(({ badge, defaultRender }) => badge.type === 'verified' ? html`<span class="badge">✓ ${badge.label}</span>` : defaultRender()) }}
```

Custom fields can be overridden by handle with `fields`. An input component draws its control from `value` and calls `setValue()`, and Comments keeps the label, the instructions and the error around it and posts whatever was set last. Put the context's `id` on the control (or on a group that holds it), and set `aria-invalid` while `error` is set, so a refused post can move focus there.

```tsx
function Stars({ value, setValue, error, id, labelId }: CommentsFieldInputProps) {
    return (
        <div id={id} role="radiogroup" aria-labelledby={labelId} aria-invalid={error ? 'true' : undefined}>
            {[1, 2, 3, 4, 5].map((n) => <button key={n} type="button" aria-pressed={Number(value) >= n} onClick={() => setValue(n)}>★</button>)}
        </div>
    );
}

<CommentsThread ownerId={entry.id} fields={{ rating: { input: Stars } }} />
```

## Your own interface

`useCommentsThread()` gives you the thread's state and actions without any of Comments' markup, and `useCommentForm()` posts from a form of your own, mounting the site's spam protection on it when someone starts writing.

```tsx
import { useCommentForm, useCommentsThread } from '@verbb/comments-react';

function Discussion({ uri }) {
    const thread = useCommentsThread({ ownerUri: uri });
    const form = useCommentForm({ thread });

    return (
        <>
            {thread.roots.map((comment) => (
                <p key={comment.uid}>
                    {comment.content.text}
                    <button onClick={() => thread.vote(comment.uid, 'upvote')}>Like ({comment.interaction.upvotes})</button>
                </p>
            ))}
            <form ref={form.ref} onSubmit={form.handleSubmit}>
                <textarea name="content" />
                {form.fieldErrors.content?.map((message) => <p key={message}>{message}</p>)}
                <button disabled={form.posting}>Post</button>
            </form>
        </>
    );
}
```

The form reads `content`, `guest[name]`, `guest[email]` and `fields[<handle>]` from its own fields, or takes them as `submit({ content, guest, fields })`. After a post it empties the fields the browser holds, keeping a guest's name and email for their next comment. A controlled input's value is your own state, so empty it yourself when `submit()` resolves with an answer rather than `null`. Trying the same comment again after a failure reuses its operation ID, so the server never posts it twice. Pass `mode: 'reply'` with `parentUid`, or `mode: 'edit'` with `commentUid`, for replies and edits.

## Server rendering

On the server `<CommentsThread>` renders only its mount point (the reserved space, the heading and the count), so there's nothing inside it for hydration to disagree with, and the thread itself loads in the browser. The package's main entry is a client module (`'use client'`), so a server component can render `<CommentsThread>` directly, as long as it passes no functions. Event props and component overrides are functions, so put the thread with those in a file of your own that starts with `'use client'`.

The helpers in `@verbb/comments-react/server` import nothing that touches the page, so they run in server components, route handlers and plain Node. They read the data API's public responses, which Craft and a CDN cache. Write the structured data with `jsonLdScript()`, never `JSON.stringify()` alone, which would let a comment containing `</script>` end the script and run the rest as HTML.

```tsx
// app/blog/[slug]/comments.tsx
'use client';

import { CommentsThread } from '@verbb/comments-react';

export function Comments({ uri, count, endpoint }) {
    return <CommentsThread key={uri} endpoint={endpoint} ownerUri={uri} count={count} onCommentCreated={(event) => track(event.detail.commentUid)} />;
}
```

```tsx
// app/blog/[slug]/page.tsx, a server component
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-react/server';
import { Comments } from './comments';

export const revalidate = 60;

export default async function Post({ params }) {
    const entry = await getEntry(params.slug);
    const endpoint = process.env.COMMENTS_ENDPOINT;
    const [count, jsonLd] = await Promise.all([
        fetchCommentsCount({ endpoint, ownerUri: entry.uri }).catch(() => 0),
        fetchStructuredData({ endpoint, ownerUri: entry.uri }).catch(() => null),
    ]);

    return (
        <>
            {jsonLd ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(jsonLd) }} /> : null}
            <Comments uri={entry.uri} count={count} endpoint={endpoint} />
        </>
    );
}
```

Both helpers throw when the read fails, such as for an owner that doesn't take comments or after the 30-second timeout (`timeoutMs`), so catch them, as above, where the page should render without its comments. Next.js caches the page for `revalidate` seconds; to cache the reads on their own, pass a `fetch` that adds Next's options, such as `fetch: (input, init) => fetch(input, { ...init, next: { revalidate: 60 } })`.

## Alongside the plugin's own script

A page that also loads the plugin's bundled script leaves `<CommentsThread>` to the component, because its mount point is marked as an adapter's, even when a site script calls `Comments.mount()` over the whole page. Turn off **Load Bundled JavaScript** in the Comments settings for an app that draws its threads with this package, so the page runs one copy of the renderer.

## Requirements

- React and React DOM 19.
- The package is ES modules only.
- Browsers: the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later.

## Documentation

The [Comments package docs](https://docs.verbb.io/comments) cover the component, the hooks, signing in across sites, part and field overrides, events and Next.js.

## Licence

Comments React is released under the Verbb licence in `LICENSE.md`, the same as the Comments plugin.
