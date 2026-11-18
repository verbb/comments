<?php
namespace verbb\comments\enums;

/**
 * Where a front end calling the data API lives relative to Craft, sent by the comments package in the
 * `X-Comments-Profile` header on every request that reads who's asking. It decides how the person is
 * identified, which origins may make the request and whether the CSRF token is needed.
 */
enum RequestProfile: string
{
    // Static Methods
    // =========================================================================

    /**
     * A request that names no profile is treated as coming from Craft's own pages, which is the
     * strictest: it's refused from any other origin.
     */
    public static function fromHeader(mixed $value): ?self
    {
        if ($value === null || $value === '') {
            return self::SameOriginBrowser;
        }

        return is_string($value) ? self::tryFrom(trim($value)) : null;
    }


    // Constants
    // =========================================================================

    /** Tier 1: Craft's own pages, or an app on Craft's origin, identified by Craft's session cookie. */
    case SameOriginBrowser = 'same-origin-browser';

    /** Tier 2: another subdomain of the same site, by a parent-domain cookie and credentialed CORS. */
    case SameSiteCredentialed = 'same-site-credentialed';

    /** Tier 3: a member on a different site, by a short-lived member token in `Authorization`. */
    case CrossOriginMember = 'cross-origin-member';

    /** Tier 3: a guest on a different site, by an opaque guest session token in `X-Comments-Session`. */
    case CrossOriginPublic = 'cross-origin-public';


    // Public Methods
    // =========================================================================

    /**
     * Whether the person is identified by a token in a header rather than by cookies. These requests
     * never read Craft's session or a cookie, which is why they don't need the CSRF token: a page on
     * another site can make a browser send its cookies, but not a header it doesn't know.
     */
    public function usesHeaderToken(): bool
    {
        return $this === self::CrossOriginMember || $this === self::CrossOriginPublic;
    }
}
