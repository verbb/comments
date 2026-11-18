# Signing In Across Sites

When comments are shown on Craft's own pages, Craft's session cookie tells Comments who's signed in, as it does for any form on your site. A front end somewhere else, such as a React app on `www.example.com` with Craft on `cms.example.com`, or a Next.js site on a different domain altogether, needs Comments to know which other sites it can trust and how their visitors are identified. This page covers that setup on the Craft side, along with the small piece of code each kind of front end needs.

Where your front end lives decides which of three tiers it uses:

| Front End | Tier | How Members Are Identified | How Guests Are Identified | What You Set Up |
| --- | --- | --- | --- | --- |
| Craft's own pages, or an app served from Craft's domain | 1. The same origin | Craft's session cookie | Craft's cookies | Nothing |
| Another subdomain of the same site, such as `www.example.com` with Craft on `cms.example.com` | 2. Another subdomain | Craft's session cookie, shared across the subdomains | Craft's cookies | A shared cookie domain, and the origin listed with **Shared Cookies** |
| A different site, such as `example.com` with Craft on `example-cms.net` | 3. A different site | A short-lived member token from your front end's own server | A guest session token | A **Member Token Key**, the origin listed with **Member Tokens**, and a token endpoint on your server |

An origin is a scheme and host, with a port when it isn't the usual one, such as `https://www.example.com`. Reading comments works from any origin without any of this, because the data API's public reads answer every site the same way. The tiers only matter for what's personal to the visitor, such as what they can do, posting, voting and moderating.

Which JavaScript package you use doesn't change any of this. The browser package, the web component and the React and Vue components all take the same sign-in setting, explained in the [package docs](https://docs.verbb.io/comments/core/signing-in).

## The Same Origin

Craft's own templates, and an app served from the same scheme, host and port as Craft, need nothing set up. Craft's session cookie identifies members, and every write carries Craft's CSRF token, as any form on your site does. Comments only accepts these requests from Craft's own origins, which are the address the request was made to, each site's base URL and the control panel's, so a multi-site install answers its own pages on every domain.

### Forwarding Requests Through Your Front End

A front end on another domain can still use this tier if its server forwards Comments' requests to Craft, so the browser only ever talks to one origin. Point the front end's data API address at a path on its own domain, such as `/actions/comments/api`, and forward everything under `/actions/comments/` to Craft, with Next.js rewrites or a route handler, Nuxt's route rules or a rule in your web server. The forwarding needs to do three things:

1. Craft has to see the request as coming from one of its own origins. Keep the `Host` header, or send an `X-Forwarded-Host` that Craft trusts, or have the front end's address be one of your Craft sites' base URLs. Otherwise Comments refuses writes with `origin_not_allowed`.
2. Craft's cookies have to be valid for the front end's host, so drop their `Domain` on the way back if it names Craft's host.
3. Craft has to see the visitor's address rather than the forwarding server's, or every visitor shares one rate limit. Send `X-Forwarded-For`, and add the forwarding server to Craft's [`trustedHosts`](docs:feature-tour/security#client-ip-addresses).

The [Next.js starter](https://docs.verbb.io/comments/starters/next) has a working example of this alongside its member tokens. When your hosting allows it, this is the simplest way to run a front end on another domain.

## Another Subdomain

When the front end and Craft are subdomains of the same site, such as `www.example.com` and `cms.example.com`, the browser can send Craft's session cookie to both. The requests are still between two origins, so Comments has to be told the front end is trusted.

First, make Craft's cookies cover the parent domain, by setting Craft's `defaultCookieDomain` in `config/general.php`:

```php
use craft\config\GeneralConfig;

return GeneralConfig::create()
    ->defaultCookieDomain('.example.com');
```

Then list the front end's origin under **Comments** → **Settings** → **Other Sites** → **Allowed Origins** with **Shared Cookies** turned on, or in `config/comments.php`:

```php
'allowedOrigins' => [
    [
        'origin' => 'https://www.example.com',
        'credentials' => true,
    ],
],
```

Each origin is matched exactly, so `https://www.example.com` doesn't cover `https://example.com` or `http://www.example.com`, and wildcards aren't accepted. You can use an environment variable, such as `$COMMENTS_FRONT_END_ORIGIN`, for the origin. [`allowedOrigins`](docs:get-started/configuration#allowedOrigins) lists every option.

On the front end, use the `same-site-credentialed` profile, which sends Craft's cookies with each request and fetches Craft's CSRF token from Comments' `session` read before the first write. With the React or Vue packages, that's the `auth` setting:

```js
import { cookieSession } from '@verbb/comments-core';

const auth = cookieSession({ profile: 'same-site-credentialed' });
```

Members sign in on the Craft site itself, through a login page there, because Comments never handles passwords and Craft's login action can't be called from another origin with cookies. Once they have, their session reaches the front end's threads. Guests are identified by Craft's cookies too, exactly as on Craft's own pages.

::: warning
Craft's session cookie reaches every subdomain of the parent domain, including any that serve content you don't control, such as a help desk or a site where users upload files. If any subdomain under the shared cookie domain could run someone else's scripts, use [a different site's setup](#a-different-site) for your front end instead, and leave `defaultCookieDomain` unset.
:::

Comments answers these requests with credentialed CORS for that exact origin only, and an origin that isn't listed gets no CORS headers at all, so the browser refuses it. A request from a listed origin without **Shared Cookies** is refused with `origin_not_allowed`.

## A Different Site

Browsers don't send a site's cookies to another site's requests, so Craft's session can't identify anyone on a different domain. Instead, your front end's own server, which already knows who its visitor is, asks Comments for a short-lived **member token** for them. The browser sends that token with Comments' requests in place of a cookie.

### Setting Up Craft

Go to **Comments** → **Settings** → **Other Sites** and set the **Member Token Key**, a secret your front end's server signs its requests with. It has to be an environment variable holding at least 32 random characters, such as `$COMMENTS_MEMBER_TOKEN_KEY`, so the key never reaches project config, and the settings page won't save a key typed in directly. A key shorter than 32 characters is ignored. Generate one with `openssl rand -hex 32` and add it to your Craft `.env` file and your front end server's environment. Never send it to a browser.

Then list the front end's origin under **Allowed Origins** with **Member Tokens** turned on. In `config/comments.php`, both look like this:

```php
'allowedOrigins' => [
    [
        'origin' => 'https://shop.example.net',
        'memberTokens' => true,
    ],
],
'memberTokenKey' => '$COMMENTS_MEMBER_TOKEN_KEY',
```

Guests on a listed origin can comment without any further setup, as described under [Guests on Another Site](#guests-on-another-site).

### Asking for a Member Token

Your front end's server asks for a token by sending a signed `POST` to Comments' `member-token` action, such as `https://example-cms.net/actions/comments/api/member-token`, with a JSON body naming the member and the origin the token is for:

```json
{ "user": { "email": "alex@example.com" }, "origin": "https://shop.example.net" }
```

The member can be given as `{ "uid": "…" }`, `{ "id": 123 }` or `{ "email": "…" }`. Only use an email address your own site has verified, because the token signs in whoever has that address in Craft. You can add `expiresIn`, from 60 to 900 seconds, to ask for a shorter token than the 15-minute default. The origin must be listed with **Member Tokens**.

The request is signed in an `X-Comments-Signature` header of the form `t=<time>, n=<nonce>, v1=<signature>`. `t` is the current Unix time in seconds, `n` is a random value of 16 to 128 letters, numbers, `-` or `_`, used only once, and `v1` is the hex HMAC-SHA256, with the Member Token Key, of these five lines joined by a newline:

1. The time.
2. The nonce.
3. `POST`.
4. The action's route, `comments/api/member-token`, whatever URL you send it to.
5. The exact body you send.

This route handler from the [Next.js starter](https://docs.verbb.io/comments/starters/next) signs the request with Node's `crypto`, and returns the token to the browser for whoever is signed in to the app. `currentPerson()` and `people` stand for your app's own sign-in, and `COMMENTS_SERVER_URL` is the data API's address, such as `https://example-cms.net/actions/comments/api`:

```ts
// lib/comments-server.ts
import 'server-only';

import { createHmac, randomBytes } from 'node:crypto';

const serverUrl = (process.env.COMMENTS_SERVER_URL ?? '').replace(/\/$/, '');

export async function signedRequest<T>(action: 'member-token' | 'revoke-member-tokens', payload: object): Promise<T> {
    const key = process.env.COMMENTS_MEMBER_TOKEN_KEY;

    if (!key || !serverUrl) {
        throw new Error('Set COMMENTS_SERVER_URL and COMMENTS_MEMBER_TOKEN_KEY to sign members in.');
    }

    const body = JSON.stringify(payload);
    const timestamp = Math.floor(Date.now() / 1000);
    const nonce = randomBytes(18).toString('base64url');
    const signature = createHmac('sha256', key).update([timestamp, nonce, 'POST', `comments/api/${action}`, body].join('\n')).digest('hex');

    const response = await fetch(`${serverUrl}/${action}`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Comments-Signature': `t=${timestamp}, n=${nonce}, v1=${signature}`,
        },
        body,
        cache: 'no-store',
    });
    const result = await response.json().catch(() => null);

    if (!response.ok) {
        throw new Error(result?.message ?? `Comments answered ${response.status}.`);
    }

    return result as T;
}
```

```ts
// app/api/comments-token/route.ts
import { NextResponse, type NextRequest } from 'next/server';

import { signedRequest } from '@/lib/comments-server';
import { people } from '@/lib/people';
import { currentPerson } from '@/lib/session';

export async function GET(request: NextRequest) {
    const person = await currentPerson();

    if (!person) {
        return NextResponse.json({ message: 'Not signed in.' }, { status: 401 });
    }

    try {
        const minted = await signedRequest<{ token: string; expiresAt: number }>('member-token', {
            user: { email: people[person].email },
            origin: process.env.STARTER_PUBLIC_ORIGIN || request.nextUrl.origin,
        });

        return NextResponse.json({ token: minted.token, expiresAt: minted.expiresAt }, { headers: { 'Cache-Control': 'no-store' } });
    } catch (error) {
        return NextResponse.json({ message: error instanceof Error ? error.message : 'Couldn’t get a token.' }, { status: 502 });
    }
}
```

Comments answers with `token`, which starts with `cmt_`, `expiresAt` in Unix seconds and the `origin`. Behind a proxy of your own, the request's URL may not be the origin the browser sees, so set the public origin, as `STARTER_PUBLIC_ORIGIN` does here.

Comments refuses a request whose signature doesn't match, whose time is more than five minutes away from the server's, or whose nonce it has seen in the last ten minutes, all with `signature_invalid` and the reason in Craft's log. A member who doesn't exist or isn't active gets `user_not_eligible`, the same answer either way, and an origin that isn't listed with **Member Tokens** gets `origin_not_allowed`. Each token issued is logged with the member and origin, but never the token. These requests send no CORS headers, so a browser can't make them.

### Using the Token in the Browser

On the front end, the thread uses the `memberToken()` sign-in from `@verbb/comments-core`, given a function that fetches a token from your route. It sends the token as an `Authorization: Bearer` header in place of a cookie, so these requests don't need a CSRF token, and asks your route for a new one before each expires. The [package docs](https://docs.verbb.io/comments/core/signing-in) explain how it behaves in the browser.

On Craft's side, a token only works from the origin it was issued for, only for the data API, and for 15 minutes at most. It can't open the control panel, use GraphQL or reach any other Craft action. Comments answers an expired token with `token_expired` and a revoked or unknown one with `token_revoked`, and also stops accepting a token if its member is suspended or changes their password after it was issued.

### Signing Members Out

When a member signs out of your front end, drop the token in the browser by starting the thread again, and have your server revoke their tokens with a signed `POST` to `revoke-member-tokens`, using the same signature with `comments/api/revoke-member-tokens` as the route. The body names the member as before:

```js
await signedRequest('revoke-member-tokens', { user: { email: people[person].email } });
```

Comments answers with `revoked`, the number of tokens it revoked. Any tab that still holds one stops working straight away, rather than when it would have expired. From the command line, `php craft comments/member-tokens/revoke alex@example.com` revokes one member's tokens, and `--all` revokes everyone's, for when a key may have leaked, as described in [Console Commands](docs:developers/console-commands#member-tokens).

### Changing the Member Token Key

To change the key without signing anyone out, put the current key in **Previous Member Token Key**, set the new one as **Member Token Key**, then update your front end's servers. Requests signed with either key are accepted until you clear the previous key, once every server has the new one. Tokens already issued keep working until they expire.

### Moderating from Another Site

A member signed in by token can't moderate, even if they're a moderator in Craft, unless their origin is listed with **Moderation** turned on. Without it, they get no moderator controls in the thread, their comments go through pre-moderation like anyone else's, and they keep to the **Author Edit Window**. Turn it on only for a front end you trust as much as your control panel.

## Guests on Another Site

Guests on a different site's listed origin, with or without **Member Tokens**, use a guest session token in place of Craft's cookies. Guests on another subdomain listed with **Shared Cookies** use Craft's cookies, as on Craft's own pages. Comments issues the token from its `session` read the first time the guest posts, votes, reports or follows, in an `X-Comments-Session` response header, and every answer to them carries a refreshed one. A token runs out two hours after the guest's last request, and only works from the origin it was issued for, and Comments answers one that has run out, been changed or come from another origin with `session_expired` or `origin_not_allowed`.

In the browser, the `guestSession()` sign-in from `@verbb/comments-core` handles this, as the [package docs](https://docs.verbb.io/comments/core/signing-in) explain, including keeping the token for the tab. [Cookies & Storage](docs:template-guides/cookies-storage#front-ends-on-other-sites) says what that stores.

A guest on another site can do everything a guest on Craft's own pages can, including uploading to an Assets custom field while **Guests Can Upload Files** is on.

## Spam Protection on Other Sites

Challenge providers' keys only work on the hostnames they're set up for. By default every front end uses your site's own keys, and Comments accepts each listed origin's hostname alongside the ones it already accepts, which are your site's own hostname or those in [`protectionHostnames`](docs:get-started/configuration#protectionHostnames), so you only need to add the front end's hostname to the key at the provider.

For a front end with its own provider account, give its origin a **Challenge Site Key** and **Challenge Secret Key** under **Allowed Origins**, as described under [`allowedOrigins`](docs:get-started/configuration#allowedOrigins). Requests from that origin then use those keys, and only accept its hostname.

## Checking It Works

Open a page on your front end in a private window and post a comment as a guest. It should appear, or wait for a moderator if pre-moderation is on. Then sign in on your front end, or on Craft for another subdomain, and check the thread shows **Posting as** with your name, and that **Edit** appears on your new comment.

When something's refused, your browser's developer tools show the request to Comments and its answer. Each error has a `code`, such as `origin_not_allowed` for an origin that isn't listed for the tier it's using, `csrf_failed` when a subdomain's request didn't carry Craft's cookies, or `signature_invalid` when the token request's signature doesn't match. [Errors](docs:headless/data-api#errors) lists every code.
