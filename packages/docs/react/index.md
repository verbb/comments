# React

`@verbb/comments-react` puts Comments threads in React 19 apps, including Next.js. `<CommentsThread>` draws a thread with the same renderer the plugin uses on Craft's own pages, so readers, commenters and moderators get everything the plugin offers, while your app keeps control of the settings, the events and any part you'd rather draw with your own components. For a completely custom interface, the hooks give you the thread's state and actions from `@verbb/comments-core` with none of Comments' markup.

## Installing

```bash
npm install @verbb/comments-react @verbb/comments-browser @verbb/comments-core @verbb/editor
```

`@verbb/comments-browser` holds the stylesheet, `@verbb/comments-core` the sign-in providers, and `@verbb/editor` the rich text editor, which the thread loads only when someone starts writing. Import the stylesheets once, such as in your app's entry or root layout:

```tsx
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';
```

## Your First Thread

```tsx
import { CommentsProvider, CommentsThread } from '@verbb/comments-react';

export function Post({ entry }) {
    return (
        <CommentsProvider endpoint="https://cms.example.com/actions/comments/api">
            <article>
                <h1>{entry.title}</h1>
                {/* The post itself. */}
            </article>

            <CommentsThread key={entry.uri} ownerUri={entry.uri} theme="auto" />
        </CommentsProvider>
    );
}
```

`<CommentsProvider>` gives every thread inside it the Craft site's data API, and `<CommentsThread>` draws the entry's comments, loading them just before they scroll into view. The `key` of the entry's URI starts a fresh thread when the app moves to another post.

Where your app runs relative to Craft decides how people sign in. An app served from Craft's own domain, or behind a proxy that forwards `/actions/comments/*` to Craft, needs nothing more. An app on another subdomain or another site passes a sign-in provider as the provider's `auth`, which [The `<CommentsThread>` Component](/react/comments-thread#signing-in) shows.

## Going Further

- [The `<CommentsThread>` Component](/react/comments-thread) covers its props, the provider's settings, signing in, loading and labels.
- [Events and Validation](/react/events) lists every event prop, and covers checking a comment before it's posted.
- [Styling](/react/styling) covers design tokens, your design system's classes, Tailwind and dark mode.
- [Part Overrides](/react/part-overrides) and [Field Overrides](/react/field-overrides) draw a part of the thread, or one custom field, with your own components.
- [Hooks for Your Own UI](/react/hooks) builds a completely custom interface.
- [Next.js](/react/nextjs) covers server components, the server helpers and caching.

The [React starter](/starters/react) and [Next.js starter](/starters/next) are complete, tested apps to compare against.

## On a Craft Site

A page that also loads the plugin's script leaves `<CommentsThread>` to the component, because its mount point is marked as an adapter's, even when a site script calls `Comments.mount()` over the whole page. Turn off **Load Bundled JavaScript** in the Comments settings for an app that draws its threads with this package, so the page runs one copy of the renderer.

## Requirements

React and React DOM 19. The package is ES modules only, and supports the current and previous major versions of Chrome, Edge and Firefox, and Safari 15.4 or later.
