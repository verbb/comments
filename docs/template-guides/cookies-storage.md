# Cookies & Storage

If your site has a cookie or consent notice, you'll want to know what Comments keeps in a visitor's browser. Reading comments stores nothing at all. Cookies and stored drafts only start once a visitor does something, such as starting to write, voting, reporting or following a discussion, and each one is there so that action works. This page lists each of them, when it's set and how long it lasts, so you can describe them accurately in your notice.

## What Comments Stores

| Name | Kind | Set By | What It's For | How Long It Lasts |
| --- | --- | --- | --- | --- |
| `CRAFT_CSRF_TOKEN` | Cookie | Craft | Protects comments, votes, reports and follows from being sent by another site on the visitor's behalf | Until the browser closes |
| `comments_guest_token` | Cookie | Comments | Links a guest to their own comments, votes, reports and follows, so they can see their own comment while it waits for a moderator and the form can remember their name and email | 400 days from the guest's first action |
| `CraftSessionId` | Cookie | Craft | Keeps a member signed in, and carries Craft's flash messages | Until the browser closes, or the visitor signs out |
| `comments-draft:…` | Local storage | Comments | Keeps what someone has typed in the plain comment box, so a reload or a closed tab doesn't lose it | Until the comment is posted or the draft discarded |
| `verbb-editor-draft:…` | Local storage | Editor | The same, for the rich text editor when [rich authoring](docs:feature-tour/rich-authoring) is on | Until the comment is posted or the draft discarded |

Comments sets `comments_guest_token` as an `HttpOnly` cookie, so scripts on the page can't read it, with `SameSite=Lax` unless Craft's `sameSiteCookieValue` says otherwise, and with Craft's `defaultCookieDomain` and secure-cookie settings. It holds a random value, and Comments keeps only a hash of it, so the cookie identifies a browser to Comments without containing anything about the guest. The cookie isn't renewed as the guest comes back, and nothing clears it, so it lasts the full 400 days unless the visitor clears their cookies, after which their earlier comments are no longer linked to them.

`CRAFT_CSRF_TOKEN` and `CraftSessionId` are Craft's own cookies, named by Craft's `csrfTokenName` and `phpSessionName` settings, and work the same as for any form on your site. Members already have Craft's session cookie from signing in, along with Craft's remember-me cookies if they ticked that box, and Comments adds nothing to it.

## When Each One Is Set

**Reading.** Loading a page with comments, scrolling to the thread and reading it stores nothing. The requests that fetch comments never start a session or set a cookie, and the thread's request for what the visitor can do doesn't either while they have no session. A visitor who only reads leaves no trace in their browser.

**Starting to write.** When a visitor clicks into the comment box, or presses **Reply**, the form gets ready to post, which sets `CRAFT_CSRF_TOKEN`. Typing then saves a draft in local storage after a short pause, but only while the text differs from what the form started with. The same CSRF cookie is set when a guest first votes, reports or follows.

**A guest's first action.** A guest's first comment, vote, report, follow by email or file upload sets `comments_guest_token`. Members never get it, because their account identifies them.

**Drafts.** Drafts are kept for each page and form, and for each signed-in member separately. They're removed when the comment is posted, when the visitor chooses **Discard**, and when they delete everything they'd typed. Signing out doesn't remove them, and guests on the same browser share one draft for each form, so a shared computer can show the last guest's unposted text to the next. Drafts stay on the visitor's device and are never sent anywhere until they post.

**Plain form posts.** A form that posts to Comments' form actions, such as `comments/comments/save`, without JavaScript starts Craft's session cookie, because those actions show their messages through Craft's flash messages. The thread never uses these actions.

## Spam Protection Challenges

Comments' own spam checks, such as the honeypot field and the minimum time a form must be open, use hidden form fields and Craft's server-side cache, and store nothing in the browser.

When a **Challenge Provider** is chosen under **Comments** → **Spam Protection**, the provider's script loads as a guest starts writing, and for members too while **Verify Signed-in Visitors** is on. Cloudflare Turnstile loads from `challenges.cloudflare.com`, Google reCAPTCHA v3 from `www.google.com`, hCaptcha from `js.hcaptcha.com` and Friendly Captcha from `cdn.jsdelivr.net`, and each may set its own cookies or storage, which Comments doesn't control. Check the provider's own documentation for what it stores and include it in your notice. [Anti-Spam](docs:feature-tour/anti-spam#challenge-providers) explains the providers.

## Front Ends on Other Sites

A front end on another subdomain that shares Craft's session cookie, set up as described in [Signing In Across Sites](docs:headless/signing-in-across-sites), uses the same cookies as above, set on the parent domain by Craft's `defaultCookieDomain`.

A front end on a different site uses no cookies at all, because browsers don't send Craft's cookies there. Members sign in with a short-lived token from your own server, which is kept in the page's memory and never stored. A guest gets a guest session token from Comments when they first post, vote, report or follow, and that is kept in memory too, so reloading the page starts a new guest session. If your front end passes `sessionStorage` to keep it across pages, it's stored under `verbb-comments-session:` followed by the data API's address, until the tab closes. That token expires two hours after the guest's last request.

## Adding Comments to Your Notice

The cookies and drafts above are all needed for something the visitor has asked to do, such as posting or voting, and none of them track visitors or are shared with anyone else. Whether that needs consent depends on the rules your site follows, so check with whoever handles your site's privacy obligations. A short note beside the thread is often enough to tell visitors what happens when they take part. Put it in your entry template, after the thread:

```twig
{{ craft.comments.render(entry) }}

<p class="comments-privacy">
    Posting, voting or following stores a cookie that links your activity to this browser, and what you type is kept on your device until you post it. See our <a href="{{ url('privacy') }}">privacy policy</a>.
</p>
```

If a challenge provider is on and your notice needs consent before third-party scripts load, the provider's script is the only one Comments loads from another company, and only once someone starts writing, so the thread can stay on the page for reading while visitors decide.
