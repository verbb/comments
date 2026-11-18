# Security
Comments checks posted comments against your own lists of words and addresses, and relies on knowing each visitor's real IP address for rate limits. For spam protection through honeypots, challenges and spam classifiers, see [Anti-Spam](docs:feature-tour/anti-spam).

## Keyword Filters
Set these under **Keyword Filters** in **Comments** → **Spam Protection**, with one rule per line. They're useful for a problem address or network, or words you never want on your site, and they apply whether or not **Enable Protection** is on.

| Filter | What Happens to a Match |
| --- | --- |
| **Moderated Keywords** | The comment is marked as Pending, with the reason **Keyword filter**, even for a Trusted commenter. |
| **Spam Keywords** | The comment is marked as Spam. |
| **Banned Keywords** | The comment isn't submitted, and the visitor sees an error. |

A filter checks its rules in order and stops at the first match. Saving a filter with a rule it can't read shows which line is wrong.

### Words and Phrases
A plain line is a word or phrase, compared with the comment's text, the author's name and email, and the IP address.

```
casino
cheap watches
192.0.2.8
```

Matching is case-insensitive and finds a word inside a longer one, so `craft` also matches `crafty`. **Enable Exact Keyword Matching** matches whole words only, here and in `[match: …]` rules.

### Combining Words
`[match: …]` combines words and phrases with `AND` and `OR`, using parentheses to group them. `AND` is checked before `OR`, so `casino OR viagra AND cheap` means `casino OR (viagra AND cheap)`.

```
# Both must appear
[match: crypto AND guaranteed returns]

# Either one, along with “cheap”
[match: (viagra OR casino) AND cheap]
```

Each word or phrase is looked for in the comment's text and the author's name and email, so one rule can match a word in the comment and a domain in the email. These rules don't look at the IP address. `AND` and `OR` must be capitals, so `[match: rock and roll]` is the phrase “rock and roll”.

### IP Addresses
`[ip: …]` compares the visitor's IP address, and nothing else, with addresses, ranges and CIDR blocks, separated by commas. IPv6 works the same way.

```
[ip: 192.0.2.8]
[ip: 192.0.2.8, 198.51.100.7]
[ip: 192.0.2.1-192.0.2.50]
[ip: 203.0.113.0/24, 2001:db8::/32]
```

Unlike an address on a plain line, `[ip: 192.0.2.8]` doesn't also match `192.0.2.80`, or the address written in a comment. Address rules only work when Comments knows each visitor's real address. See [Client IP Addresses](#client-ip-addresses).

### Notes
A line starting with `#` and a space is a note, and never matches. A word starting with `#`, such as `#casino`, is still a keyword.

## Client IP Addresses
Comments records the visitor's IP address on each comment posted through a public form, and shows it to admins in the comment's sidebar in the control panel. Rate limits for guests, keyword filters on addresses, Akismet and **Send IP to Provider** all use the same address.

Comments gets this address from Protect, which only believes forwarding headers when your config says the request came through a proxy of yours. Any visitor can send those headers themselves, so believing them unchecked would let someone post as any address, and get around a rate limit by changing it on each request.

### Sites Behind a Proxy or CDN
Craft's `trustedHosts` setting defaults to `['any']`, which Protect treats as not knowing about any proxies. It uses the address of whatever connected to your server. On a site behind a load balancer, Cloudflare or another CDN, that's the proxy rather than the visitor, which means:

- Every guest shares a rate limit, so five comments a minute is five for everyone.
- Every comment records the proxy's address, which makes keyword filters on addresses useless.
- Spam classifiers and challenge providers are given the wrong address.

If forwarding headers arrive while `trustedHosts` is still `['any']`, Protect writes a warning to your logs once a day.

To fix this, set `trustedHosts` in `config/general.php` to your proxy's address ranges:

```php
<?php

use craft\config\GeneralConfig;

return GeneralConfig::create()
    ->trustedHosts(['10.0.0.0/8', '192.0.2.10']);
```

Protect then works through `X-Forwarded-For` from the closest hop back, skipping the addresses you've listed, and uses the first one it doesn't recognise as the visitor. A request that didn't come from a trusted address has any forwarding headers ignored. Use the real ranges for your host or CDN, which they publish, rather than something broader, because any address you list can claim to be forwarding for anyone.

### Choosing the Header
Craft's `ipHeaders` setting names which headers hold the address, for a proxy that uses something other than `X-Forwarded-For`, such as `CF-Connecting-IP` on Cloudflare or `X-Real-IP` on some load balancers:

```php
<?php

use craft\config\GeneralConfig;

return GeneralConfig::create()
    ->trustedHosts(['192.0.2.0/24'])
    ->ipHeaders(['CF-Connecting-IP', 'X-Forwarded-For']);
```

The headers are tried in order, and `Forwarded` is never read. `X-Forwarded-For`, `Forwarded-For` and `X-Cluster-Client-IP` can list several addresses and are worked through as above. Any other header has to hold exactly one.

You can also name a single-address header without setting `trustedHosts`, and Protect will believe it, while logging the same warning. Only do this if visitors can't reach your server except through the proxy, as anyone connecting directly could send the header themselves. Headers that can list several addresses are never believed unless `trustedHosts` is set.

### Checking the Recorded IP Address
Post a comment from a signed-out browser, then open it in the control panel as an admin. The **IP Address** in its sidebar should be your own public address, and not your proxy's or a private one.

## Gravatar
With **Use Gravatar** on, under **Avatars** in **Comments** → **Settings** → **Appearance**, a commenter without a user photo shows their [Gravatar](https://gravatar.com). It's off by default, and is built so that it can't be used to identify or impersonate anyone.

- **Only addresses known to be the commenter's are looked up.** That means signed-in users, and guests once they've confirmed their email. A guest who hasn't confirmed shows the **Placeholder Avatar**, so nobody can borrow someone else's Gravatar by typing their address.
- **Your site serves the image.** A queue job on your server fetches each image from Gravatar and keeps a copy, which the thread shows from your site's own address. Visitors' browsers never contact Gravatar, and the hash Gravatar knows an address by is never in the page, where it could be checked against a guessed address or used to follow someone between sites.
- **Pages never wait for Gravatar.** Nothing is fetched while a page renders. A commenter's image appears once the queue has fetched it, and each copy, or knowing someone has no Gravatar, is checked again after a week.
- **Only G-rated images are used.**

A user's own photo always comes first, then their Gravatar, then the **Placeholder Avatar**. When a user is deleted, or a guest's address is no longer confirmed, their copy is removed.

Your server sends Gravatar a SHA-256 hash of each address it looks up, so your privacy policy may need to say that you use Gravatar.
