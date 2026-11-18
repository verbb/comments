# Starters

The Comments starters are small example apps that show each package against one Craft site. Every starter shows the same seeded blog posts, so you can compare how each one draws the thread, signs people in and changes the look, from the one-line Twig default to a Next.js app on another domain. They're tested together, with a smoke test that drives each one in a browser, and their code is MIT licensed, so you can copy what you need into your own site.

The starters live in the [`verbb/comments-starters`](https://github.com/verbb/comments-starters) repository. Hosted copies run at [comments-starters.verbb.io](https://comments-starters.verbb.io), whose home page links to each one.

| Starter | What it shows | How people sign in | Local address |
| --- | --- | --- | --- |
| [Craft](/starters/craft) | The Craft site every starter reads from, with the default thread and examples for Sprig, Turbo and Datastar | Craft's own session | `https://comments-starters.ddev.site` |
| [React](/starters/react) | `<CommentsThread>` with component overrides, and a page built on the hooks | Craft's own session, on Craft's domain | `http://localhost:5273` |
| [Vue](/starters/vue) | The React starter again, in Vue | Craft's own session, on Craft's domain | `http://localhost:5274` |
| [Web Components](/starters/web) | `<verbb-comments>` styled with Tailwind, with `themeConfig` and a dark mode toggle | Guests from another site | `http://localhost:5275` |
| [Next.js](/starters/next) | Server-rendered count and structured data, member tokens from a route handler, and a proxy variant | Member tokens from another site | `http://localhost:3101` |
| [Nuxt](/starters/nuxt) | Server-rendered count and structured data on a subdomain of the Craft site | Craft's session cookie, shared with the subdomain | `https://nuxt.comments-starters.ddev.site:3102` |

## The Seeded Site

The Craft site has a blog with posts in different states, such as a long conversation and a post whose comments are closed, and comments in every state, from approved to held and reported. Its comment form asks for four custom fields, which between them show each way a field can be used.

| Field | Type | Shows |
| --- | --- | --- |
| Rating (`rating`) | Number, 1 to 5 | **Collect and show**, after the date, on top-level comments only. The React, Vue, Next.js and Nuxt starters draw it as stars. |
| Reading From (`readingFrom`) | Plain Text | **Collect and show**, after the date, on replies too, offered only to members through a user condition |
| Photo (`photo`) | Assets, one image | **Collect and show**, under the comment's text |
| Order Number (`orderNumber`) | Plain Text | **Collect** only, seen by moderators and the comment's author, and never in a public response |

The seeded people all have the password `password`. Priya, Dan and Alex are members, and Priya is also a verified buyer, which the starters' Craft module adds as a `verified` badge with the plugin's `EVENT_DEFINE_BADGES` event. Sam is a moderator, who signs in only locally, so a hosted demo can't be moderated by its visitors.

## Running Them Locally

You'll need [DDEV](https://ddev.com) for the Craft site, Node.js 22 or later for the front ends, and [mkcert](https://github.com/FiloSottile/mkcert), which DDEV installs, for the Nuxt starter's HTTPS dev server. Build the Craft site first. The script installs Craft from the committed project config, seeds the posts, people and comments, and creates each front end's environment file from its `.env.example`.

```bash
craft/scripts/install.sh
```

Then install each front end's packages, as the repository's README describes, and start every dev server from the repository's root folder:

```bash
npm run dev
```

Open the Craft site's home page, which links to each starter. The README also covers building every app and running the smoke test.

Turnstile can't issue a token in a headless browser, so set `COMMENTS_TURNSTILE=off` in `craft/.env` before running anything automated that posts comments. The starters use Cloudflare's test keys, which always pass in a real browser.
