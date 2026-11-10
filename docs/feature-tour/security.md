# Security
Comments checks posted comments against your own lists of words and addresses, and relies on knowing each visitor's real IP address for rate limits. For spam protection through honeypots, challenges and Akismet, see [Anti-Spam](docs:feature-tour/anti-spam).

## Keyword Filters
Set these under **Keyword Filters** in **Comments** → **Settings** → **Spam**, with one word or IP address per line. They're useful for a problem address, or words you never want on your site, and they apply whether or not **Enable Protection** is on.

| Filter | What Happens to a Match |
| --- | --- |
| **Moderated Keywords** | The comment is marked as `pending`, with the reason **Keyword filter**, even for a Trusted commenter. |
| **Spam Keywords** | The comment is marked as `spam`. |
| **Banned Keywords** | The comment isn't submitted, and the visitor sees an error. |

Each line is compared with the comment's text, the author's name and email, and the IP address. Matching is case-insensitive and finds a word inside a longer one, so `craft` also matches `crafty`. **Enable Exact Keyword Matching** matches whole words only.


## Client IP Addresses
Comments records the visitor's IP address on each comment posted through a public form, and shows it to admins in the comment's sidebar in the control panel. Rate limits for guests, keyword filters on addresses, Akismet and **Send IP to Provider** all use the same address.

Comments gets this address from Protect, which only believes forwarding headers when your config says the request came through a proxy of yours. Any visitor can send those headers themselves, so believing them unchecked would let someone post as any address, and get around a rate limit by changing it on each request.

### Sites Behind a Proxy or CDN
Craft's `trustedHosts` setting defaults to `['any']`, which Protect treats as not knowing about any proxies. It uses the address of whatever connected to your server. On a site behind a load balancer, Cloudflare or another CDN, that's the proxy rather than the visitor, which means:

- Every guest shares a rate limit, so five comments a minute is five for everyone.
- Every comment records the proxy's address, which makes keyword filters on addresses useless.
- Akismet and challenge providers are given the wrong address.

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

### Checking It Works
Post a comment from a signed-out browser, then open it in the control panel as an admin. The **IP Address** in its sidebar should be your own public address, and not your proxy's or a private one.
