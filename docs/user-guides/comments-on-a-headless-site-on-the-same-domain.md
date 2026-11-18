# Comments on a Headless Site on the Same Domain

Plenty of Craft sites have a front end built as a JavaScript app, with React or Vue drawing the pages from Craft's content. When that app is served from the same domain as Craft, the browser treats the app and Craft as one site, so adding comments needs no setup on the Craft side. Craft's own sign-in works in the app, and there are no keys, tokens or allowed origins to set up.

In this guide you'll add a comment thread to a React app that shows a Craft site's blog posts. You'll install Comments' React package, put a thread on each post, let members sign in with their Craft accounts from inside the app, and see how to run the app in development and serve it in production. A section near the end shows the same thing in Vue.

When you're done, each post in the app has the full comment thread under it, the same one Craft's own templates would show, with replies, votes, reports and following. Guests can comment if your settings allow them, and members who sign in through the app comment as themselves, and moderators can moderate in place.

The [React starter](https://docs.verbb.io/comments/starters/react) and [Vue starter](https://docs.verbb.io/comments/starters/vue) are complete, working versions of this guide, if you'd like to compare as you go.

## What You'll Need

You'll need Comments installed on your Craft site, with comments allowed on your blog's entries. If you haven't set Comments up yet, [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) covers it, and you can skip its template steps, because the app draws the thread.

The app needs to know each post's URI, the path Craft gives the entry, such as `blog/first-light`, which it uses to tell Comments which post's comments to show. If your app already fetches posts from Craft, through GraphQL or Element API, the URI is on each entry as `uri`.

You'll need Node.js and npm, and a React app built with Vite. This guide starts from a new one, created with `npm create vite@latest`, and assumes your Craft site runs locally at `https://blog.ddev.site`. Comments' React package needs React 19.

## Why the Same Domain Needs No Setup

A browser keeps each site's cookies to itself. When the app is served from the same scheme, host and port as Craft, such as both from `https://blog.ddev.site`, the browser sends Craft's cookies with every request the app makes to Craft. Craft's session cookie says who's signed in, and Craft's CSRF protection works as it does for any form, so Comments knows who the reader is without any extra setup. The headless docs call this tier 1, [the same origin](docs:headless/signing-in-across-sites#the-same-origin).

In production, the app is the same origin as Craft when its built files are served from Craft's own domain, such as from a folder in Craft's `web` folder. In development, Vite's dev server runs on a port of its own, such as `http://localhost:5173`, which is a different origin. The dev server can forward Craft's requests for you, though, so the browser still only sees one origin. You'll set that up first.

## Installing the Packages

In your app's folder, install Comments' React package and the two packages it builds on, along with Editor, which Comments uses for rich text comments:

```bash
npm install @verbb/comments-react @verbb/comments-browser @verbb/comments-core @verbb/editor
```

The packages are released alongside the plugin, with the same version number, so install the version that matches your plugin, such as `@verbb/comments-react@4.0.0`. Your plugin's version is under **Settings** → **Plugins** in the control panel.

## Forwarding Craft's Requests in Development

Open `vite.config.js` and replace it with:

```js
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const craftUrl = 'https://blog.ddev.site';

export default defineConfig({
    plugins: [react()],
    server: {
        proxy: {
            '/actions': {
                target: craftUrl,
                changeOrigin: true,
                // A local site's certificate often isn't trusted by Node.
                secure: false,
                // Keep Craft's cookies on the dev server's own address.
                cookieDomainRewrite: { '*': '' },
                configure(proxy) {
                    // Craft refuses a change that comes from another origin. Requests from the dev
                    // server's own pages are passed on as Craft's, and anything else keeps its origin.
                    proxy.on('proxyReq', (request, incoming) => {
                        const origin = request.getHeader('origin');

                        if (origin && origin === `http://${incoming.headers.host}`) {
                            request.setHeader('origin', new URL(craftUrl).origin);
                        }
                    });
                },
            },
        },
    },
});
```

Every request the app makes to a path starting `/actions`, such as Comments' `/actions/comments/api/thread`, now goes to the dev server, which forwards it to Craft and passes back the answer. Each option has a job:

- `changeOrigin` sends the request to Craft with Craft's own host name, so Craft recognises it as its own site.
- `cookieDomainRewrite` removes the domain from the cookies Craft sets, so the browser keeps them for the dev server's address.
- The `configure` function deals with Craft's check that a request that changes something, such as posting a comment, comes from Craft's own pages. Requests from the dev server's pages are passed on with Craft's origin, while a request from any other site keeps its own, so Craft still refuses it.

Change `craftUrl` to your Craft site's address. This forwarding is only for development. You'll see how the app reaches Craft in production later.

## Setting Up Comments Once for the App

Create `src/comments.js`, which holds the two things every thread in the app shares:

```js
import { cookieSession } from '@verbb/comments-core';

// Comments' data API, on the same origin as the app.
export const endpoint = '/actions/comments/api';

// Craft's session cookie identifies the reader. Made once, so every thread shares it.
export const auth = cookieSession();
```

`endpoint` is the address of Comments' data API, which the thread loads comments from and posts to. It's a path, so it's on whatever origin the app is served from, which is the dev server now and Craft's domain in production.

`auth` decides how the reader is identified. `cookieSession()` uses Craft's session cookie, which is right for the same origin. It's created once here and shared, because it keeps track of the reader's session, and you'll call it again after someone signs in.

## Adding the Thread to a Post

Put `<CommentsProvider>` around your app, so every thread inside it shares the endpoint and sign-in. Open `src/App.jsx` and replace it with a minimal page for one post:

```jsx
import { CommentsProvider, CommentsThread } from '@verbb/comments-react';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';

import { auth, endpoint } from './comments';

export default function App() {
    // In your app, these come from the post your router is showing.
    const post = { title: 'First Light', uri: 'blog/first-light' };

    return (
        <CommentsProvider endpoint={endpoint} auth={auth} theme="auto">
            <article>
                <h1>{post.title}</h1>
                {/* The post's content goes here. */}
            </article>

            <CommentsThread key={post.uri} ownerUri={post.uri} />
        </CommentsProvider>
    );
}
```

Change the `uri` to one of your own posts. Here's what the new parts do.

The two `import` lines for styles bring in Comments' stylesheet and Editor's. Vite adds them to the app's CSS.

`<CommentsProvider>` gives every thread inside it the same settings. `theme="auto"` makes the threads follow each reader's light or dark preference. Any setting Comments' threads take can go here, such as `themeConfig` for your design system's button classes, and a thread's own props win over the provider's.

`<CommentsThread>` draws the thread for the post whose URI is `ownerUri`. It's drawn by the same code that draws threads on Craft's own pages, so it looks and works the same, and it loads the comments as it nears the bottom of the screen. The `key` is the post's URI, so when your router moves to another post, React starts a fresh thread for it rather than reusing the last one.

Start the dev server with `npm run dev` and open the address it shows. Scroll to the bottom of the post, and the thread loads under it, with the post's comments, the sort options and the **Join the conversation** card. If guests can comment on your site, press **Comment as a guest** and post a test comment.

If the thread says something went wrong instead, open your browser's developer tools at the **Network** panel and look at the request to `/actions/comments/api/thread`. A 404 from the dev server means the proxy isn't forwarding, so check `craftUrl` and restart the dev server. A `not_found` answer from Comments means the URI doesn't match a live entry that takes comments. A post that's refused with `origin_not_allowed` means the `configure` function isn't passing Craft's origin on, so check it matches the code above.

## Letting Members Sign In

Comments never asks for passwords, so members sign in to Craft itself, with Craft's own `users/login` action. Because the app is on Craft's origin, the session Craft starts is the same one Comments reads. Add these to `src/comments.js`:

```js
// Calls one of Craft's own actions, through the same origin.
async function craftAction(path, options = {}) {
    const response = await fetch(`/actions/${path}`, {
        ...options,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(body.message ?? `Craft answered ${response.status}.`);
    }

    return body;
}

// Who's signed in, and the CSRF token Craft needs for the sign-in and sign-out actions.
export function getSession() {
    return craftAction('users/session-info');
}

export async function signIn(loginName, password) {
    const { csrfTokenValue } = await getSession();

    await craftAction('users/login', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfTokenValue },
        body: JSON.stringify({ loginName, password }),
    });

    // Every thread reads who's reading again.
    auth.reset();
}

export async function signOut() {
    const { csrfTokenValue } = await getSession();

    await craftAction('users/logout', { method: 'POST', headers: { 'X-CSRF-Token': csrfTokenValue } });
    auth.reset();
}
```

`getSession()` asks Craft who's signed in, and returns the CSRF token that Craft needs on every change, such as signing in. `signIn()` sends the member's username or email and password to Craft's login action, and `signOut()` signs them out. Both then call `auth.reset()`, which tells every thread using that `auth` to forget what it knew about the reader and ask again, so the thread changes to show the member, or the guest, straight away.

Call these from a sign-in form of your own. Here's a small one, which shows who's signed in or a form to sign in:

```jsx
import { useEffect, useState } from 'react';

import { getSession, signIn, signOut } from './comments';

export function SignIn() {
    const [session, setSession] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        getSession().then(setSession);
    }, []);

    async function run(task) {
        setError(null);

        try {
            await task();
            setSession(await getSession());
        } catch (caught) {
            setError(caught.message);
        }
    }

    if (!session) {
        return null;
    }

    if (!session.isGuest) {
        return (
            <p>
                Signed in as {session.username}. <button type="button" onClick={() => run(signOut)}>Sign out</button>
            </p>
        );
    }

    return (
        <form onSubmit={(event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            run(() => signIn(data.get('loginName'), data.get('password')));
        }}>
            <input name="loginName" placeholder="Username or email" autoComplete="username" required />
            <input name="password" type="password" placeholder="Password" autoComplete="current-password" required />
            <button>Sign in</button>
            {error ? <p role="alert">{error}</p> : null}
        </form>
    );
}
```

Save it as `src/SignIn.jsx`, and add `<SignIn />` to `App.jsx`, above the article.

Reload the app and sign in with a Craft account. The **Join the conversation** card above the comments changes to the comment form, with "Posting as" and your name. Post a comment, and it's posted as you. Sign out, and the thread goes back to the sign-in card.

Signing in with an account that's a moderator shows the comments waiting for review in the thread, with **Approve** and the other decisions, so moderators can work from the app too.

## Serving the App in Production

In production, the app has to be on Craft's origin, so the browser sends Craft's cookies with its requests. There are two common ways.

**Serve the built app from Craft's domain.** Set Vite's `base` to the folder the app will live in, such as `base: '/app/'` in `vite.config.js`, run `npm run build`, and copy the `dist` folder's contents to that folder in Craft's `web` folder, such as `web/app/`. Visitors open `https://blog.example.com/app/`, and the app's requests to `/actions/comments/api` go straight to Craft. If the app has its own routes, such as `/app/blog/first-light`, your web server needs to send those to the app's `index.html`, with a rule like Nginx's `try_files $uri $uri/ /app/index.html;` in a `location` block for the folder.

**Serve the app from its own server, and forward Craft's actions.** If the app runs on a host of its own, it can forward everything under `/actions/comments/` to Craft, the same way Vite's dev server does, with a rule in your web server or your hosting's rewrites. The forwarding has to do the same jobs as the dev server's proxy, and also tell Craft the visitor's real address, so rate limits apply to each visitor rather than to your server. [Forwarding Requests Through Your Front End](docs:headless/signing-in-across-sites#forwarding-requests-through-your-front-end) lists exactly what's needed.

If neither is possible, and the app has to run on another subdomain or a different domain, follow [Comments on a Headless Site on Another Domain](docs:user-guides/comments-on-a-headless-site-on-another-domain) instead.

## Testing It

Test the built app in production, or locally with `npm run build` and the folder copied into Craft's `web` folder, as well as on the dev server:

1. Open a post as a guest. The thread loads under it, and if guests can comment, you can post as a guest.
2. Sign in as a member and post a comment, then reply to someone else's. Vote on a comment and take the vote back.
3. Move to another post in the app. The thread changes to that post's comments, and the comment you were writing on the last post doesn't follow you.
4. Sign out, and the thread goes back to showing the sign-in card.
5. Sign in as a moderator and approve a comment that's waiting.

## The Same in Vue

The Vue package works the same way, with a plugin in place of the provider. In `src/main.js`:

```js
import { CommentsPlugin } from '@verbb/comments-vue';
import { createApp } from 'vue';
import '@verbb/comments-browser/css/comments.css';
import '@verbb/editor/styles';

import App from './App.vue';
import { auth, endpoint } from './comments';

createApp(App)
    .use(CommentsPlugin, { endpoint, auth, theme: 'auto' })
    .mount('#app');
```

`auth` and `endpoint` come from the same `src/comments.js` as in the React app, so the sign-in functions there reset the very `auth` the threads use. Creating a second `cookieSession()` here would leave the threads showing a guest after someone signs in, because `reset()` only reaches the threads using the instance it's called on.

Then put the thread on each post:

```vue
<script setup>
import { CommentsThread } from '@verbb/comments-vue';

defineProps(['post']);
</script>

<template>
    <article>
        <h1>{{ post.title }}</h1>
    </article>

    <CommentsThread :key="post.uri" :owner-uri="post.uri" />
</template>
```

Install `@verbb/comments-vue` in place of `@verbb/comments-react`. The Vite proxy (with `@vitejs/plugin-vue` in place of the React plugin), `src/comments.js` with its sign-in functions, and the production setup are the same as above.

From here, the other guides apply to your app too. [Change a Part's Structure](docs:user-guides/change-a-parts-structure) shows how to draw parts of the thread with your own components, [Ask Commenters for More with Custom Fields](docs:user-guides/ask-commenters-for-more-with-custom-fields) adds fields such as a star rating, and [Validate and Track Comments](docs:user-guides/validate-and-track-comments) covers the thread's events as props.
