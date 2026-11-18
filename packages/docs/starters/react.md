# React Starter

The `react/` folder is a Vite and React app with two pages, one drawing the thread with `<CommentsThread>` and one drawing its own interface with the hooks. Locally it runs at `http://localhost:5273`, and its hosted copy is at [comments-starters.verbb.io/react/](https://comments-starters.verbb.io/react/).

## How It Reaches Craft

The dev server forwards `/actions/*` to the Craft site, so the browser sees one origin and Craft's session cookie identifies the person, as it does when the built app is served from the Craft site's own domain. `src/craft.ts` makes one `cookieSession()` for the whole app, and signs in and out with Craft's own `users/login` and `users/logout` actions, calling the provider's `reset()` afterwards so every thread reads the viewer again. `vite.config.ts` shows what the forwarding needs, and explains how a production proxy differs.

## The Thread Page

`src/pages/ThreadPage.tsx` draws the thread for the post you choose, with a `key` of its URI, so each post gets a fresh thread. It shows three things:

- A part override, `src/components/VerifiedBadge.tsx`, which draws the `verified` badge with a tick and keeps Comments' own classes for every other badge.
- Field overrides, `src/components/Rating.tsx`, which draw the `rating` field as five stars on the form and on each comment.
- Event props, which log what the thread tells the app, such as `onCommentCreated` and `onVoteChanged`, in a sidebar.

```tsx
const parts: CommentsPartOverrides = { badge: VerifiedBadge };
const fields: CommentsFieldOverrides = { rating: { input: RatingInput, display: RatingDisplay } };

<CommentsThread
    key={uri}
    ownerUri={uri}
    parts={parts}
    fields={fields}
    onCommentCreated={(event) => log(`Posted a comment (${event.detail.moderationState})`)}
/>
```

`src/App.tsx` wraps both pages in a `<CommentsProvider>` with the endpoint, the sign-in and the automatic theme. [Part Overrides with Components](/react/part-overrides) walks through the badge and the rating.

## Your Own Interface

`src/pages/CustomPage.tsx`, at `/custom`, draws the same thread with the app's own components, on `useCommentsThread()` and `useCommentForm()`. It handles loading and errors, a guest's name and email fields, states such as a comment waiting for approval, votes, replies and **Load more**, which makes it a good starting point for an interface of your own. [Hooks for Your Own UI](/react/hooks) explains the hooks it uses.
