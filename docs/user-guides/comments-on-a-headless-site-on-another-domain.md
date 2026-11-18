# Comments on a Headless Site on Another Domain

When a site's front end runs separately from Craft, such as a Next.js or Nuxt app on its own server, it usually ends up on a different address from the Craft install behind it. Comments works there too, with the same thread as on Craft's own pages. The difference is in how the thread knows who's reading, because the browser won't send Craft's sign-in cookie to an app on another domain the way it does for Craft's own pages.

This guide covers the two situations you're likely to be in:

- The front end is on **another subdomain** of the same site, such as `www.example.com` with Craft on `cms.example.com`. Craft's session cookie can be shared between the two, and members sign in on the Craft site.
- The front end is on **a different site** altogether, such as `www.example.com` with Craft on `example-cms.net`. The front end's own server signs members in to Comments with short-lived **member tokens**.

Each has its own walkthrough, the first with a Nuxt app and the second with a Next.js app, and both finish with the same steps for spam protection, rendering the comment count and search engine data on the server, and testing. Read the one that matches your site, then the shared sections after it.

When you're done, your front end shows the full comment thread on each post, guests can comment, members comment as themselves, and the page's HTML includes the comment count and the comments as structured data for search engines.

If your front end can be served from Craft's own domain instead, or forward Craft's requests through its own server, [Comments on a Headless Site on the Same Domain](docs:user-guides/comments-on-a-headless-site-on-the-same-domain) is simpler, and worth checking first.

## What You'll Need

You'll need Comments installed on your Craft site, with comments allowed on the entries your front end shows. [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) covers that, and you can skip its template steps. Your front end needs each entry's URI, such as `blog/first-light`, which tells Comments which entry's comments to show. If your front end gets its content from Craft through GraphQL, it's the entry's `uri`.

Both of your sites must use HTTPS, in development as well as production. Browsers treat `http` and `https` addresses as different sites, and won't share cookies or send credentials between them.

You'll need an admin account on Craft to change Comments' settings, access to Craft's `.env` file and `config` folder, and Node.js with npm for the front end. Install the packages for your framework in the front end's folder, choosing the version that matches your Comments plugin, which is under **Settings** → **Plugins** in Craft's control panel:

```bash
# For Nuxt or Vue
npm install @verbb/comments-vue @verbb/comments-browser @verbb/comments-core @verbb/editor

# For Next.js or React
npm install @verbb/comments-react @verbb/comments-browser @verbb/comments-core @verbb/editor
```

The [Nuxt starter](https://docs.verbb.io/comments/starters/nuxt) and [Next.js starter](https://docs.verbb.io/comments/starters/next) are complete, working versions of both walkthroughs.

## How Comments Decides Who's Reading

Reading comments works from any site with no setup at all. The thread's public data is the same for everyone, so Comments answers any site that asks for it. Everything personal is different. Posting, voting, following, reporting and seeing your own comment while it waits for approval all need Comments to know who's asking, and only from a site you trust.

So for each front end on another address, you tell Comments two things. First, that the front end's **origin**, its scheme and host, such as `https://www.example.com`, is allowed to make these requests. Second, how its visitors are identified, which depends on how closely the two addresses are related. The headless docs call these the tiers, and [Signing In Across Sites](docs:headless/signing-in-across-sites) is the full reference for them.

## On Another Subdomain, with Nuxt

In this walkthrough, Craft is on `https://cms.example.com` and a Nuxt app is on `https://www.example.com`. They're two subdomains of `example.com`, so the browser counts them as the same site, and will send a cookie set for `.example.com` to both.

### Sharing Craft's Session Cookie

Craft normally sets its cookies for its own host only. Tell it to set them for the parent domain instead, in `config/general.php`:

```php
<?php

use craft\config\GeneralConfig;

return GeneralConfig::create()
    ->defaultCookieDomain('.example.com');
```

If your `general.php` already returns a `GeneralConfig::create()` chain, add `->defaultCookieDomain('.example.com')` to it. Craft's session cookie now reaches every subdomain of `example.com`. Members who were signed in need to sign in again once, to get a cookie with the new domain.

Every subdomain under that domain now receives the cookie, including any that run software you don't control, such as a help desk or a site where people upload files. If one does, use the different-site walkthrough below instead, which doesn't share the cookie at all.

### Allowing the Front End's Origin

Go to **Comments** → **Settings** → **Other Sites**. Under **Allowed Origins**, add a row with `https://www.example.com` as the **Origin**, turn on **Shared Cookies**, and save. Each origin is matched exactly, so list the exact address, with no path or trailing slash.

**Shared Cookies** tells Comments to answer this origin's requests with the browser's credentials, which is what lets Craft's cookie travel with them. An origin that isn't listed can still read comments, but can't post.

### Adding the Thread in Nuxt

Create a Nuxt plugin that sets Comments up once for the app, in `app/plugins/comments.ts`:

```ts
import { cookieSession } from '@verbb/comments-core';
import { CommentsPlugin } from '@verbb/comments-vue';

export default defineNuxtPlugin((nuxtApp) => {
    nuxtApp.vueApp.use(CommentsPlugin, {
        endpoint: 'https://cms.example.com/actions/comments/api',
        // Craft's session cookie, sent to Craft's subdomain with each request.
        auth: cookieSession({ profile: 'same-site-credentialed' }),
        theme: 'auto',
    });
});
```

`endpoint` is the address of Comments' data API on your Craft site. `cookieSession({ profile: 'same-site-credentialed' })` identifies the reader by Craft's session cookie, and sends it with each request to Craft's subdomain. Before the first change a reader makes, such as posting, it also fetches Craft's CSRF token from Comments, which Craft needs on every change.

Add Comments' stylesheets to the app's main stylesheet, such as `app/assets/css/main.css`, which `nuxt.config.ts` lists in its `css` option as `'~/assets/css/main.css'`:

```css
@import '@verbb/comments-browser/css/comments.css';
@import '@verbb/editor/styles';
```

If your stylesheet uses Tailwind or other cascade layers, import them into a layer instead, as [Style Comments with Tailwind or a Design System](docs:user-guides/style-comments-with-tailwind-or-a-design-system#bundling-comments-stylesheet-into-your-own-build) shows.

Then put the thread on your post page, such as `app/pages/blog/[slug].vue`:

```vue
<script setup lang="ts">
import { CommentsThread } from '@verbb/comments-vue';

const route = useRoute();
const uri = computed(() => `blog/${route.params.slug}`);
</script>

<template>
    <article>
        <!-- The post's content. -->
    </article>

    <CommentsThread :key="uri" :owner-uri="uri" />
</template>
```

The `key` starts a fresh thread when the reader moves to another post. On the server, Nuxt renders only the thread's placeholder, and the comments load in the browser as the reader scrolls near them. [Rendering the Count and Structured Data on the Server](#rendering-the-count-and-structured-data-on-the-server) adds the comment count and search engine data to this page.

Open a post on your Nuxt app. The thread loads under it, and if guests can comment, you can post as a guest.

### Signing In on the Craft Site

Comments never handles passwords, and Craft's sign-in action can't be used from another origin, so members sign in on the Craft site itself, such as at `https://cms.example.com/login`. Link to it from your app's header. Once they've signed in there, Craft's cookie is set for the whole of `example.com`, so coming back to the Nuxt app, the thread shows them as signed in, with "Posting as" and their name above the comment form.

Signing out works the same way, with a link to Craft's logout, such as `https://cms.example.com/logout`.

Now skip to [Protecting the Form from Spam](#protecting-the-form-from-spam).

## On a Different Site, with Next.js

In this walkthrough, Craft is on `https://example-cms.net` and a Next.js site is on `https://www.example.com`. They're different sites, so the browser won't send Craft's cookies to the Next.js site's requests at all.

Instead, the Next.js site's own server, which already knows who's signed in to it, asks Comments for a short-lived **member token** for that person. The browser sends the token with Comments' requests in place of a cookie. Guests get a **guest session** from Comments instead, which works without any of your code.

A member token signs someone in to Comments as a Craft user, so each of your site's members needs a Craft user account, which Comments finds by their email address. If your front end has its own user database, it needs to create a matching Craft user for each member, such as when they register.

### Setting Up Craft

First, create the **Member Token Key**, a secret only Craft and your Next.js server know, which your server signs its requests with. Generate a random value of at least 32 characters, such as with `openssl rand -hex 32`, and add it to Craft's `.env` file:

```bash
COMMENTS_MEMBER_TOKEN_KEY="paste-the-generated-value-here"
```

Go to **Comments** → **Settings** → **Other Sites** and enter `$COMMENTS_MEMBER_TOKEN_KEY` as the **Member Token Key**, so the setting reads the value from `.env` and the secret never goes into your project config. Then add a row under **Allowed Origins** with `https://www.example.com` as the **Origin**, turn on **Member Tokens**, and save.

### Signing Requests from Your Server

Add three values to your Next.js site's environment, in `.env.local` for development and in your hosting's settings for production:

```bash
# Comments' data API, as the browser reaches it.
NEXT_PUBLIC_COMMENTS_ENDPOINT=https://example-cms.net/actions/comments/api

# The same API for this site's server, which can be an internal address.
COMMENTS_SERVER_URL=https://example-cms.net/actions/comments/api

# The same Member Token Key as Craft's. Never sent to the browser.
COMMENTS_MEMBER_TOKEN_KEY="paste-the-generated-value-here"
```

Only variables starting `NEXT_PUBLIC_` are sent to the browser, so the key stays on your server.

Your server proves a request comes from it by signing it with the key. Create `lib/comments-server.ts` with a function that makes a signed request to Comments:

```ts
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

The signature is a hash of the time, a random one-off value called a nonce, the method, the action and the request's exact body, made with the key. Comments makes the same hash with its copy of the key, and only accepts the request if they match, the time is within five minutes, and the nonce hasn't been used before. So nobody without the key can ask for a token, and a request that's been seen can't be sent again. The `import 'server-only'` line makes Next.js refuse to build if this file is ever imported into code that runs in the browser.

### Handing Tokens to the Browser

The browser asks your server for a token, and your server asks Comments. Create the route the browser calls, at `app/api/comments-token/route.ts`:

```ts
import { NextResponse } from 'next/server';

import { signedRequest } from '@/lib/comments-server';
import { getCurrentMember } from '@/lib/auth';

export async function GET() {
    const member = await getCurrentMember();

    if (!member) {
        return NextResponse.json({ message: 'Not signed in.' }, { status: 401 });
    }

    try {
        const minted = await signedRequest<{ token: string; expiresAt: number }>('member-token', {
            user: { email: member.email },
            origin: 'https://www.example.com',
        });

        return NextResponse.json({ token: minted.token, expiresAt: minted.expiresAt }, { headers: { 'Cache-Control': 'no-store' } });
    } catch (error) {
        return NextResponse.json({ message: error instanceof Error ? error.message : 'Couldn’t get a token.' }, { status: 502 });
    }
}
```

`getCurrentMember()` stands in for your site's own sign-in, whatever it is, and returns the signed-in member, or nothing for a visitor who isn't signed in. Only use an email address your site has verified, because the token signs in whoever has that address in Craft.

The request names the member and the origin the token is for. Comments answers with a `token` and the time it expires, 15 minutes later, and the route passes them on. A token only works from the origin it was issued for, so one copied to another site is useless there.

### Adding the Thread in Next.js

The thread's settings include functions, so it goes in a client component. Create `components/Comments.tsx`:

```tsx
'use client';

import { guestSession, memberToken } from '@verbb/comments-core';
import { CommentsProvider, CommentsThread } from '@verbb/comments-react';
import { useMemo } from 'react';

export function Comments({ uri, count, signedIn }: { uri: string; count: number; signedIn: boolean }) {
    // Made again only when someone signs in or out, so the thread starts over as that person.
    const auth = useMemo(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }

        return signedIn
            ? memberToken({ getToken: () => fetch('/api/comments-token', { cache: 'no-store' }).then((response) => response.json()) })
            : guestSession({ storage: sessionStorage });
    }, [signedIn]);

    return (
        <CommentsProvider endpoint={process.env.NEXT_PUBLIC_COMMENTS_ENDPOINT} auth={auth} theme="auto">
            <CommentsThread key={uri} ownerUri={uri} count={count} />
        </CommentsProvider>
    );
}
```

For a signed-in member, `memberToken()` calls `getToken()` when the thread first needs to know who's reading, and again shortly before each token expires, so a page left open all afternoon stays signed in. The token is kept in memory only, and sent with Comments' requests, but never with the public reads, which stay cacheable.

For a guest, `guestSession()` starts a guest session with Comments the first time they post, vote, report or follow. `storage: sessionStorage` keeps the session for the browser tab, so a guest who reloads still sees their own comment while it waits for a moderator.

The component builds `auth` only in the browser, because the server renders just the thread's placeholder. `useMemo()` keeps the same `auth` between renders, and makes a new one only when `signedIn` changes, which starts the thread over as the new person. Making a new one on every render would fetch a new token each time.

Add Comments' stylesheets to your site's global stylesheet, such as `app/globals.css`, which your root layout imports:

```css
@import '@verbb/comments-browser/css/comments.css';
@import '@verbb/editor/styles';
```

Then render the component on your post page, passing whether the reader is signed in. You'll add `count` in a moment:

```tsx
<Comments uri={`blog/${slug}`} count={0} signedIn={Boolean(await getCurrentMember())} />
```

Open a post as a visitor who isn't signed in. The thread loads, and guests can post if your settings allow. Sign in to your site, reload, and the thread shows "Posting as" with your name. In your browser's developer tools, the **Network** panel shows a request to `/api/comments-token`, then Comments' requests with an `Authorization` header.

### Signing Members Out

When a member signs out of your site, also revoke their tokens at Comments, so a token still held by another open tab stops working straight away, rather than when it would have expired. In your sign-out code on the server:

```ts
await signedRequest('revoke-member-tokens', { user: { email: member.email } });
```

The thread in the tab they're signing out from starts over as a guest once `signedIn` changes.

### Moderators on Another Site

A moderator signed in by a member token can't moderate in the thread, unless you allow it for that origin. Without it, they see the thread as any member does, and moderate in Craft's control panel instead. To allow it, turn on **Moderation** for the origin under **Allowed Origins**, but only for a front end you trust as much as your control panel, because anyone holding the Member Token Key could then act as a moderator.

## Protecting the Form from Spam

If your site uses a challenge provider such as Cloudflare Turnstile on the comment form, its key has to accept your front end's hostname as well as Craft's, because the challenge now runs on the front end's pages. In the provider's settings for your key, add the front end's hostname, such as `www.example.com`. Comments accepts each allowed origin's hostname alongside your own site's, so there's nothing else to change in Craft.

If the front end needs its own account with the provider, give its row under **Allowed Origins** a **Challenge Site Key** and a **Challenge Secret Key**. Requests from that origin then use those keys instead.

## Rendering the Count and Structured Data on the Server

The thread's comments load in the browser, so they aren't in the page's HTML. Two things are worth rendering on the server, though. The comment count, so the thread's heading says "12 comments" from the first paint rather than jumping when the comments load, and the comments as [structured data](docs:template-guides/structured-data), a JSON-LD script that search engines read without running any JavaScript.

Both packages have server helpers for this, which read Comments' public data and import nothing that touches the browser. In a Next.js server component, such as `app/blog/[slug]/page.tsx`:

```tsx
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-react/server';

import { Comments } from '@/components/Comments';
import { getCurrentMember } from '@/lib/auth';

export default async function Post({ params }: { params: Promise<{ slug: string }> }) {
    const { slug } = await params;
    const uri = `blog/${slug}`;
    const endpoint = process.env.COMMENTS_SERVER_URL!;

    const [count, structuredData, member] = await Promise.all([
        fetchCommentsCount({ endpoint, ownerUri: uri }).catch(() => 0),
        fetchStructuredData({ endpoint, ownerUri: uri }).catch(() => null),
        getCurrentMember(),
    ]);

    return (
        <article>
            {structuredData ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(structuredData) }} /> : null}

            {/* The post's content. */}

            <Comments uri={uri} count={count} signedIn={Boolean(member)} />
        </article>
    );
}
```

`fetchCommentsCount()` returns the number of comments, which the thread shows until its own data arrives. `fetchStructuredData()` returns the comments as schema.org data. Write it into the page with `jsonLdScript()`, never with `JSON.stringify()` alone, because `jsonLdScript()` escapes the characters that would let a comment containing `</script>` end the script early. Both helpers throw if the read fails, such as for a post that doesn't take comments, so the `.catch()` calls let the page render without them.

In Nuxt, the same helpers come from `@verbb/comments-vue/server`. Run them inside `useAsyncData()`, which reads them on the server for the first page a visitor opens, and in the browser when they move to another post. Replace your post page, `app/pages/blog/[slug].vue`, with:

```vue
<script setup lang="ts">
import { CommentsThread } from '@verbb/comments-vue';
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-vue/server';

const route = useRoute();
const uri = computed(() => `blog/${route.params.slug}`);
const endpoint = 'https://cms.example.com/actions/comments/api';

const { data } = await useAsyncData(() => `comments:${uri.value}`, async () => {
    const [count, jsonLd] = await Promise.all([
        fetchCommentsCount({ endpoint, ownerUri: uri.value }).catch(() => null),
        fetchStructuredData({ endpoint, ownerUri: uri.value }).catch(() => null),
    ]);

    return { count, jsonLd };
});

// The structured data goes in the page's head, escaped by jsonLdScript().
useHead(() => ({
    script: data.value?.jsonLd ? [{ key: 'comments-json-ld', type: 'application/ld+json', innerHTML: jsonLdScript(data.value.jsonLd) }] : [],
}));
</script>

<template>
    <article>
        <!-- The post's content. -->
    </article>

    <CommentsThread :key="uri" :owner-uri="uri" :count="data?.count ?? undefined" />
</template>
```

`useAsyncData()` keeps its answer under its key, one per post here, so moving back to a post reuses what was read. `useHead()` adds a JSON-LD script to the page's `<head>`, and `:count` gives the thread the count to show until its own data arrives. The reads are public, so the server and the browser can both use the same `endpoint`. If your Nuxt server reaches Craft at an internal address, keep it in a server-only setting in `nuxt.config.ts` and use it when `import.meta.server` is true, as the [Nuxt starter](https://docs.verbb.io/comments/starters/nuxt) does.

Inside a `<script setup>` block, never write a closing script tag, even in a comment or a string, because Vue reads it as the end of the block. Letting `useHead()` write the script tag, with `jsonLdScript()` escaping its contents, avoids that.

## Testing It

Test on the real addresses, or on local ones with HTTPS that have the same relationship, such as two subdomains of your DDEV site for the subdomain case.

1. Open a post in a private window. The thread loads with its comments, and the heading shows the count straight away.
2. View the page's source. It includes the comment count and a `<script type="application/ld+json">` with the comments, and nothing about the visitor.
3. If guests can comment, post one as a guest, then reload. Your comment is still shown to you, marked as waiting if moderation is on.
4. Sign in, on the Craft site for a subdomain or on your own site for a different one, and reload the post. The comment form shows "Posting as" with your name. Post a comment, edit it and vote on another.
5. Sign out, and check the thread shows you as a guest again.

When a request is refused, the **Network** panel in your browser's developer tools shows Comments' answer, with a `code` saying why. `origin_not_allowed` means the origin isn't listed, isn't spelt exactly as the browser sends it, or doesn't have the right switch turned on. `csrf_failed` on a subdomain means Craft's cookie didn't travel with the request, so check `defaultCookieDomain` and that both sites use HTTPS. `signature_invalid` means your server's signature doesn't match, usually because the two copies of the Member Token Key differ, and Craft's log says which check failed. [Signing In Across Sites](docs:headless/signing-in-across-sites#checking-it-works) has more on each.
