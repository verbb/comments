# Signing In

Comments never handles passwords. People sign in to Craft, or to your own site, and every request that reads who's asking carries something that identifies them. What that is depends on where your front end runs relative to Craft, because browsers only send Craft's cookies to Craft's own site. Core's sign-in providers make the choice one option, `auth`, which the client, the browser package, the web components and the React and Vue packages all take.

This page covers the browser side. Configuring Craft for each case, such as listing your front end's origin under **Settings** → **Other Sites**, and minting member tokens on your server, are covered in the plugin's [Signing In Across Sites](https://verbb.io/craft-plugins/comments/docs/v4/headless/signing-in-across-sites) guide, and its [Headless Overview](https://verbb.io/craft-plugins/comments/docs/v4/headless/headless-overview) helps you choose. The user guides [Comments on a Headless Site on the Same Domain](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/comments-on-a-headless-site-on-the-same-domain) and [Comments on a Headless Site on Another Domain](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/comments-on-a-headless-site-on-another-domain) set up each case from start to finish.

| Where your front end runs | Members | Guests |
| --- | --- | --- |
| On Craft's own domain, or behind a proxy that forwards `/actions/comments/*` to Craft | `cookieSession()`, the default | The same |
| On another subdomain of the same site, such as `www.example.com` with Craft on `cms.example.com` | `cookieSession({ profile: 'same-site-credentialed' })` | The same |
| On a different site, such as `example.com` with Craft on `example-cms.net` | `memberToken({ getToken })` | `guestSession()` |

## On Craft's Own Domain

`cookieSession()` uses Craft's session cookie, which the browser sends with every request to Craft. It's the default, so leave `auth` out. Writes carry Craft's CSRF token, which the provider reads from the `viewer` or `session` answer and keeps in memory, reading `session` first for a write that comes before either. A front end behind a proxy that forwards `/actions/comments/*` to Craft is on Craft's origin as far as the browser is concerned, so it signs in the same way.

## On Another Subdomain

A front end on another subdomain of the same site can share Craft's session cookie, if Craft sets it on the parent domain. Craft's `defaultCookieDomain` must be the parent domain, such as `.example.com`, and the front end's origin must be listed under **Settings** → **Other Sites** with **Shared Cookies** on.

```js
import { cookieSession } from '@verbb/comments-core';

const auth = cookieSession({ profile: 'same-site-credentialed' });
```

Requests then go with credentials, Comments answers them with credentialed CORS for that origin only, and writes carry the CSRF token as on Craft's own domain. People sign in through Craft, such as a login page on the Craft host, and are signed in on both. The [Nuxt starter](/starters/nuxt) works this way.

## On a Different Site

Browsers block third-party cookies, so a front end on a different site can't carry Craft's session at all. Instead your site's own server, which already knows who its visitor is, asks Comments for a short-lived member token with a request signed with the **Member Token Key**, and the browser sends the token with each request.

```js
import { guestSession, memberToken } from '@verbb/comments-core';

const auth = signedIn
    ? memberToken({ getToken: () => fetch('/api/comments-token', { cache: 'no-store' }).then((response) => response.json()) })
    : guestSession({ storage: sessionStorage });
```

Here `signedIn` is whether your own site has someone signed in, and `/api/comments-token` is an endpoint on your site's server that answers with a token for them.

### Members

`memberToken({ getToken })` calls `getToken()` on the first request that identifies the person, again shortly before the token runs out, and again after the server says it has expired or been revoked, then retries the request once. `getToken()` resolves with `{ token, expiresAt }`, where `expiresAt` is in Unix seconds, which is what Comments' `member-token` endpoint answers, so your endpoint can pass its answer straight on. `refreshBefore` sets how many seconds before `expiresAt` the next token is fetched, 60 by default.

The token goes in an `Authorization: Bearer` header on every request that identifies the person, and never on public reads, so cached threads stay the same for everyone. It's kept in memory only, never in storage, and requests go without cookies, so no CSRF token is needed. The [Next.js starter](/starters/next) has an endpoint like this in a route handler.

### Guests

`guestSession()` gives a guest an opaque session token from Comments' `session` endpoint, fetched before their first write rather than when they read, and sends it as `X-Comments-Session`, taking the refreshed token each answer carries. The token keeps a guest's own pending comment and votes with them across pages while it lasts. Without `storage`, it lives in memory and a reload starts a new guest session. Pass `sessionStorage` to keep it for the tab, under the key `verbb-comments-session:` followed by the endpoint, until the tab closes or `reset()` is called.

Guests on another site can't edit, mention people or moderate, so a thread store shows those as unavailable whatever the server says. They can upload files to the custom fields they're offered.

## Signing Out and Changing Who's Signed In

Every provider has `reset()`, which forgets what it holds in memory and tells every thread store using it, so each clears what was private and reads the viewer again. Call it after someone signs in or out.

```js
import { cookieSession } from '@verbb/comments-core';

export const auth = cookieSession();

// Signs out of Craft with its own actions, then has every thread read the viewer again.
export async function signOut() {
    const session = await fetch('/actions/users/session-info', { headers: { Accept: 'application/json' } }).then((response) => response.json());

    await fetch('/actions/users/logout', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': session.csrfTokenValue } });
    auth.reset();
}
```

Make each provider once and share it, rather than making a new one on every render, because each keeps its own token. A new member token provider fetches a new token for its first request, and a new `guestSession()` without `storage` starts a new guest session, losing the guest's pending comment. The React and Vue packages take a new provider of the same kind over without starting the thread again, and start it again for a different kind, such as a guest signing in as a member.

## Your Own Provider

A sign-in provider is an object with a `profile`, the fetch `credentials` mode, an optional list of `unavailable` actions, and `headers(context)`, which resolves with the headers to add. `observe()`, `recover()`, `reset()` and `onReset()` are optional. The built-in providers cover every case Comments' server accepts, so a provider of your own is mostly useful for wrapping one, such as to log when a token is fetched.
