# Next.js Starter

The `next/` folder is a Next.js App Router app on another site from Craft, with a sign-in of its own. Locally it runs at `http://localhost:3101`, and its hosted copy runs as a Node server on a domain of its own, linked from the [Craft starter](/starters/craft)'s home page. It shows the most involved setup of the starters, where Craft's cookies never reach the app, so members are signed in to Comments with member tokens from the app's own server.

## Rendering on the Server

`app/blog/[slug]/page.tsx` is a server component. It reads the post's comment count and structured data with `fetchCommentsCount()` and `fetchStructuredData()`, writes the structured data with `jsonLdScript()`, and passes the count to the thread, so the heading is right from the first paint. Either read failing leaves the page without it rather than breaking it. [Next.js](/react/nextjs) explains each part.

`components/Comments.tsx` is the client component with the thread, because its overrides are functions. It uses the same `VerifiedBadge` and rating components as the React starter.

## Member Tokens

When someone is signed in to the app, `components/Comments.tsx` gives the thread a `memberToken()` provider, and a `guestSession()` provider otherwise, making a new one only when who's signed in changes:

```tsx
const auth = useMemo(() => signedIn
    ? memberToken({ getToken: () => fetch('/api/comments-token', { cache: 'no-store' }).then((response) => response.json()) })
    : guestSession({ storage: sessionStorage }), [signedIn, endpoint]);
```

`app/api/comments-token/route.ts` is the route `getToken()` calls. It asks Comments for a short-lived token for whoever is signed in, for this app's origin only, with a request signed by `lib/comments-server.ts` using the **Member Token Key** from the server's environment. `app/api/session/route.ts` signs people in and out of the demo, and revokes their member tokens when they sign out, so a token still held by an open tab stops working straight away. [Signing In](/core/signing-in) covers the browser side, and the plugin's [Signing In Across Sites](https://verbb.io/craft-plugins/comments/docs/v4/headless/signing-in-across-sites) covers the signed request.

Moderating through a member token is off unless the Craft site allows it for the app's origin, so Sam sees the thread as a member does here.

## The Proxy Variant

The `/proxy/…` pages show the alternative to member tokens. `app/actions/comments/[...path]/route.ts` forwards Comments' actions to Craft, so the browser only ever talks to the app's origin and Craft's own session applies. Its comments explain what any production proxy needs to do, which is to pass requests from the app's own pages on as Craft's own origin, keep Craft's cookies valid for the app's host, and pass the visitor's address on in `X-Forwarded-For`.

## Environment

The app reads `NEXT_PUBLIC_COMMENTS_ENDPOINT` for the browser, `COMMENTS_SERVER_URL` for the server's reads and signed requests, which can be an internal address, and `COMMENTS_MEMBER_TOKEN_KEY`, which matches Craft's **Member Token Key** and stays on the server.
