<?php
namespace verbb\comments\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * One front end on another origin that may use the data API, from the **Allowed Origins** setting
 * (`allowedOrigins`). Listing an origin lets guests there post through a guest session token
 * (`cross-origin-public`). `credentials` adds members signed in through a cookie Craft shares with a
 * sibling subdomain (tier 2), and `memberTokens` adds members signed in by a token the site's own
 * server mints (tier 3). Moderating through a member token is off unless `moderation` is on, so a
 * leaked Member Token Key can't act as a moderator by default.
 *
 * An origin can carry its own challenge provider keys, for a front end that needs its own provider
 * account. Otherwise the site-wide keys are used, with this origin's hostname accepted.
 */
class AllowedOrigin extends Model
{
    // Static Methods
    // =========================================================================

    /**
     * The canonical form of an origin, `scheme://host[:port]` in lower case without a default port, or
     * null for anything that isn't exactly an origin: no path, query, fragment, credentials or wildcard.
     * Browsers send `Origin` in this form, so comparing canonical strings is exact.
     */
    public static function normalize(mixed $origin): ?string
    {
        if (!is_string($origin)) {
            return null;
        }

        $origin = trim($origin);

        if ($origin === '' || str_contains($origin, '*') || preg_match('/[\s\\\\]/', $origin)) {
            return null;
        }

        $parts = parse_url($origin);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            return null;
        }

        $host = strtolower($parts['host']);

        if (!preg_match('/^(\[[0-9a-f:.]+\]|[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*)$/', $host)) {
            return null;
        }

        $port = $parts['port'] ?? null;

        if ($port === ($scheme === 'https' ? 443 : 80)) {
            $port = null;
        }

        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }


    // Properties
    // =========================================================================

    public string $origin = '';
    public bool $credentials = false;
    public bool $memberTokens = false;
    public bool $moderation = false;
    public ?string $protectionSiteKey = null;
    public ?string $protectionSecretKey = null;


    // Public Methods
    // =========================================================================

    public function getHostname(): string
    {
        return (string)parse_url($this->origin, PHP_URL_HOST);
    }

    /**
     * This origin's own challenge keys, read from their environment variables, or null when it uses the
     * site-wide keys.
     */
    public function getProtectionKeys(): ?array
    {
        $siteKey = App::parseEnv($this->protectionSiteKey);
        $secretKey = App::parseEnv($this->protectionSecretKey);

        if (!is_string($siteKey) || $siteKey === '' || !is_string($secretKey) || $secretKey === '') {
            return null;
        }

        return ['siteKey' => $siteKey, 'secretKey' => $secretKey];
    }
}
