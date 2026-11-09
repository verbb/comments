# Anti-Spam

Comments checks public comments before saving them, using the bundled Protect module. Configure it under **Comments** → **Settings** → **Spam**.

![The Spam settings, with the passive checks, rate limits, challenge provider and Akismet.](../../screenshots/settings/spam.png) Control panel edits, imports and element resaves aren't checked, because they don't come from a public form.

You don't need to add anything to your templates: `craft.comments.render()` includes everything its forms need. If you write your own forms, see [Displaying and Protecting Comments](docs:template-guides/displaying-and-protecting-comments). To match comments against your own words and addresses, see [Security](docs:feature-tour/security).

## Passive Checks

These run on every browser form and don't ask a visitor to do anything. They're on by default, apart from **Require JavaScript**.

- **Honeypot** adds a hidden field that people leave empty and bots tend to fill in.
- **Minimum Form Time** rejects a form posted less than three seconds after it was opened.
- **Prevent Replayed Submissions** lets each prepared form be used once. The default form gets a fresh one for its next attempt.
- **Require JavaScript** rejects forms that can't show JavaScript ran. Leave this off to support visitors without JavaScript.

## Rate Limits

**Limit Comment Attempts** allows five attempts per minute for each person, whether or not they succeed. **Minimum Time Between Comments** adds a wait between comments, which is off until you set it. Signed-in visitors are counted by their user account and guests by IP address. If your site is behind a proxy or CDN, read [Client IP Addresses](docs:feature-tour/security#client-ip-addresses) first, or all your guests will share one limit. A visitor who goes over gets a message to wait, and API responses use HTTP 429 with a `Retry-After` header.

Votes, reports and guest subscriptions have their own fixed limits, which apply even when **Enable Protection** is off:

| Action | Limit for Each Person |
| --- | --- |
| Voting | 60 a minute |
| Reporting | 3 every 10 minutes |
| Guest subscribing | 5 an hour |

Replay protection and rate limits are kept in Craft's cache, so keep the cache shared across web servers, or each one counts separately. Clearing the cache resets them.

## Challenge Providers

A challenge asks a third-party service to check that each visitor is a person. Choose **Cloudflare Turnstile** or **reCAPTCHA v3** as the **Challenge Provider** and enter its site key and secret key. Environment variables are supported.

Turnstile can show an interactive check when it needs one. reCAPTCHA v3 gives each visitor a score, which has to reach the **Minimum Score** of 0.5 by default. Both need JavaScript to run, so visitors without it can't post while a challenge is on.

**Verify Signed-in Visitors** is on by default, and turning it off limits challenges to guests. **Send IP to Provider** is off by default, and shares the visitor's address with the provider. A challenge is only accepted from the hostname of the site's base URL. If comments are posted from other domains, list them in the [`protectionHostnames`](docs:get-started/configuration#protectionhostnames) setting. The action names providers receive are `comments_create` and `comments_edit`.

## Akismet

Turn on **Enable Akismet** and enter your **Akismet API Key** to have comments classified. Akismet receives the comment text, the author's name and email, and the visitor's IP address and user agent, so check this suits your privacy policy. **Akismet Site URL** defaults to the site's base URL, and **Akismet Test Mode** keeps test requests from affecting your results.

Comments Akismet finds to be spam are saved with the Spam status for review when **Keep Spam for Review** is on, which is the default. Otherwise they're rejected.

## Reviewing What Spam Protection Found
Comments keeps what spam protection found on each comment it checks, such as Akismet's result or a failed bot check. Open a comment from the index's **Spam** source and the notice at the top of the comment lists those findings, so you can see why it was caught before deciding. A comment held as **Possible spam** under **Needs review** shows them too.

![A comment caught as spam, with spam protection's findings in the notice and Not spam in the sidebar.](../../screenshots/spam/caught-comment.png)

**Not spam** returns a comment to **Needs review**, where you can approve it. [Moderation](docs:feature-tour/moderation#deciding-a-comment) covers the other decisions.

**Send Moderator Spam Corrections** is off by default. It tells Akismet whenever a moderator marks a comment as spam or not spam, as long as the comment has an IP address stored.

## When Something Goes Wrong

A failed check, such as a tripped honeypot or a failed challenge, rejects the comment and asks the visitor to refresh the form and try again. The default form keeps what they typed.

A provider being unavailable is treated differently, because it says nothing about the comment. The error is logged and the other checks still apply. Turn on **Reject on Provider Errors** to reject comments in this situation instead, with a message to try again. This only covers providers contacted from the server, not checks made in the browser.

A misconfigured provider, such as a wrong secret key, will keep failing until someone fixes it, so nothing gets published unchecked. Comments are held for moderation, or rejected if **Reject on Provider Errors** is on, and the reason is logged. Settings that can't work are also reported when you save them, such as a challenge on a site with no URL to take a hostname from.

Check that this is all working by posting a comment from a signed-out browser, then looking at its status in the Comments index.
