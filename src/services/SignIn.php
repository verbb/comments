<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\enums\RequestProfile;
use verbb\comments\exceptions\ApiException;
use verbb\comments\models\AllowedOrigin;
use verbb\comments\models\RequestSignIn;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\web\Request;
use craft\web\Response;

use Throwable;

/**
 * The data API's sign-in tiers: which request profile a request uses, which origins may use it, the
 * CORS headers each answer carries, and who a request identifies once it's allowed.
 *
 * - `same-origin-browser` (tier 1): Craft's own pages. Craft's session, the CSRF token on writes, and
 *   no other origin.
 * - `same-site-credentialed` (tier 2): another subdomain listed with `credentials`. Craft's session
 *   through credentialed CORS for that exact origin, with the CSRF token on writes.
 * - `cross-origin-member` (tier 3): a member on a site listed with `memberTokens`, by a member token
 *   bound to that origin. No cookies are read, so no CSRF token is needed.
 * - `cross-origin-public` (tier 3): a guest on any listed site, by an opaque guest session token bound
 *   to that origin. No cookies are read either.
 *
 * Public reads never come here: they answer everyone the same, with `Access-Control-Allow-Origin: *`.
 */
class SignIn extends Component
{
    // Constants
    // =========================================================================

    public const PROFILE_HEADER = 'X-Comments-Profile';
    public const SESSION_HEADER = 'X-Comments-Session';

    // How long a guest session token lasts without being used. Each answer to a guest using one carries
    // a fresh token, so it runs out two hours after their last request.
    public const GUEST_SESSION_DURATION = 7200;

    // How long a browser may remember a preflight's answer. Removing an origin takes this long to reach
    // browsers that asked recently, though every request is still checked when it arrives.
    public const PREFLIGHT_MAX_AGE = 3600;

    // Headers a front end may send. Core sends the API version and profile, the CSRF token or a token in
    // `Authorization` or `X-Comments-Session`, and Editor sends JSON with `Accept`.
    public const ALLOWED_HEADERS = ['Accept', 'Content-Type', 'X-Comments-Api-Version', 'X-Comments-Profile', 'X-CSRF-Token', 'Authorization', 'X-Comments-Session', 'X-Requested-With'];

    // Headers a front end on another origin needs to read: the version check, a refreshed guest session
    // and how long to wait after `rate_limited`.
    public const EXPOSED_HEADERS = ['X-Comments-Api-Version', 'X-Comments-Session', 'Retry-After'];

    private const GUEST_SESSION_VERSION = 1;
    private const GUEST_SESSION_INFO = 'verbb-comments-guest-session';
    private const GUEST_SESSION_MAX_LENGTH = 2048;


    // Properties
    // =========================================================================

    private ?RequestSignIn $_current = null;


    // Public Methods
    // =========================================================================

    /**
     * The sign-in of the data API request being handled, once `begin()` has checked it. Null outside
     * the data API's private reads and writes, such as for GraphQL, public reads and Twig.
     */
    public function current(): ?RequestSignIn
    {
        return $this->_current;
    }

    /**
     * Forget the current sign-in, after a request or between tests.
     */
    public function reset(): void
    {
        $this->_current = null;
    }

    /**
     * The request's `Origin` in canonical form, `'null'` for an opaque or malformed one, or null when
     * it sent none, as same-origin `GET` requests and servers usually don't.
     */
    public function requestOrigin(Request $request): ?string
    {
        $origin = $request->getHeaders()->get('Origin');

        if ($origin === null || $origin === '') {
            return null;
        }

        return AllowedOrigin::normalize($origin) ?? 'null';
    }

    /**
     * The origins tier 1 accepts: the one the request was made to, and each site's and the control
     * panel's own, so a multi-site install answers its own pages on every domain.
     */
    public function ownOrigins(Request $request): array
    {
        $origins = [];

        try {
            $origins[] = AllowedOrigin::normalize($request->getHostInfo());
        } catch (Throwable) {
            // A request without a host has no origin of its own.
        }

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $origins[] = AllowedOrigin::normalize($this->_originOf((string)$site->getBaseUrl()));
        }

        $cpUrl = Craft::$app->getConfig()->getGeneral()->baseCpUrl;

        if (is_string($cpUrl) && $cpUrl !== '') {
            $origins[] = AllowedOrigin::normalize($this->_originOf(Craft::getAlias($cpUrl)));
        }

        return array_values(array_unique(array_filter($origins)));
    }

    public function allowedOrigin(?string $origin): ?AllowedOrigin
    {
        if ($origin === null) {
            return null;
        }

        return CommentsPlugin::$plugin->getSettings()->getAllowedOrigins()[$origin] ?? null;
    }

    /**
     * Public reads answer every origin the same and are never credentialed, so they allow any origin.
     * They don't vary by `Origin`, which keeps one cached copy for every site.
     */
    public function applyPublicCors(Response $response): void
    {
        $response->getHeaders()
            ->set('Access-Control-Allow-Origin', '*')
            ->set('Access-Control-Expose-Headers', implode(', ', self::EXPOSED_HEADERS));
    }

    /**
     * Answer a CORS preflight. A public read is allowed from anywhere, without credentials. Anything
     * that reads who's asking is only allowed from a listed origin, with credentials when it's listed
     * for them. Any other origin gets no CORS headers, so the browser refuses the request itself.
     */
    public function preflight(Request $request, Response $response, bool $public): void
    {
        $headers = $response->getHeaders();
        $origin = $this->requestOrigin($request);
        $allowed = $public ? null : $this->allowedOrigin($origin);

        $headers->set('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');

        if ($public) {
            $headers->set('Access-Control-Allow-Origin', '*')
                ->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS')
                ->set('Access-Control-Allow-Headers', 'Accept, X-Comments-Api-Version')
                ->set('Access-Control-Max-Age', (string)self::PREFLIGHT_MAX_AGE);
        } elseif ($allowed) {
            $headers->set('Access-Control-Allow-Origin', $allowed->origin)
                ->set('Access-Control-Allow-Methods', 'GET, HEAD, POST, OPTIONS')
                ->set('Access-Control-Allow-Headers', implode(', ', self::ALLOWED_HEADERS))
                ->set('Access-Control-Max-Age', (string)self::PREFLIGHT_MAX_AGE);

            if ($allowed->credentials) {
                $headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        $response->setStatusCode(204);
        $response->format = Response::FORMAT_RAW;
        $response->data = '';
    }

    /**
     * Check a private read or write's profile against where it came from, and set its CORS headers.
     * It's refused when the profile doesn't fit the origin:
     *
     * - `same-origin-browser`, or no profile, from another origin.
     * - `same-site-credentialed` from an origin not listed with `credentials`.
     * - `cross-origin-member` from an origin not listed with `memberTokens`.
     * - `cross-origin-public` from an origin that isn't listed.
     *
     * A listed origin's refusals carry CORS headers, so the front end can read why.
     */
    public function begin(Request $request, Response $response): RequestSignIn
    {
        $this->_current = null;
        $profile = RequestProfile::fromHeader($request->getHeaders()->get(self::PROFILE_HEADER));

        if (!$profile) {
            throw new ApiException(Craft::t('comments', 'This request names a profile the comments API doesn’t know.'), 'invalid_request', 400);
        }

        $origin = $this->requestOrigin($request);
        $allowed = $this->allowedOrigin($origin);

        if ($allowed) {
            $headers = $response->getHeaders();
            $headers->set('Access-Control-Allow-Origin', $allowed->origin)
                ->set('Access-Control-Expose-Headers', implode(', ', self::EXPOSED_HEADERS))
                ->set('Vary', 'Origin');

            if ($allowed->credentials) {
                $headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        $fits = match ($profile) {
            RequestProfile::SameOriginBrowser => $origin === null || in_array($origin, $this->ownOrigins($request), true),
            RequestProfile::SameSiteCredentialed => $allowed !== null && $allowed->credentials,
            RequestProfile::CrossOriginMember => $allowed !== null && $allowed->memberTokens,
            RequestProfile::CrossOriginPublic => $allowed !== null,
        };

        if (!$fits) {
            throw new ApiException(Craft::t('comments', 'This site doesn’t accept comments requests from here.'), 'origin_not_allowed', 403);
        }

        return $this->_current = new RequestSignIn([
            'profile' => $profile,
            'origin' => $origin,
            'allowedOrigin' => $profile === RequestProfile::SameOriginBrowser ? null : $allowed,
        ]);
    }

    /**
     * Work out who a request that passed `begin()` identifies. Tiers 1 and 2 are Craft's session, as
     * on any page. Tier 3 requests are identified by their header token alone: Craft's session is
     * closed unread and the signed-in user is replaced by the token's member, or by nobody for a
     * guest, so a cookie that came along can't add anyone to it.
     *
     * `$startGuestSession` is for the `session` read, which starts a guest's session when they have
     * none or it has run out, rather than refusing.
     */
    public function identify(RequestSignIn $signIn, Request $request, bool $startGuestSession = false): void
    {
        if (!$signIn->usesHeaderToken()) {
            return;
        }

        $session = Craft::$app->getSession();

        if ($session->getIsActive()) {
            $session->close();
        }

        if ($signIn->profile === RequestProfile::CrossOriginMember) {
            [$user, $tokenId] = CommentsPlugin::$plugin->getMemberTokens()->authenticate(
                (string)$request->getHeaders()->get('Authorization'),
                (string)$signIn->origin,
            );

            $signIn->user = $user;
            $signIn->memberTokenId = $tokenId;
            Craft::$app->getUser()->setIdentity($user);

            return;
        }

        Craft::$app->getUser()->setIdentity(null);
        $token = $request->getHeaders()->get(self::SESSION_HEADER);

        if (is_string($token) && $token !== '') {
            try {
                $signIn->guestSecret = $this->_readGuestSession($token, (string)$signIn->origin);
            } catch (ApiException $e) {
                if (!$startGuestSession) {
                    throw $e;
                }
            }
        }

        if ($signIn->guestSecret === null && $startGuestSession) {
            $signIn->guestSecret = CommentsPlugin::$plugin->getIdentities()->generateGuestToken();
        }

        // Every answer to a guest with a session carries a fresh token, so it lasts two hours from
        // their last request rather than from their first.
        $signIn->sendGuestSession = $signIn->guestSecret !== null;
    }

    /**
     * Whether this request identifies people by a header token rather than by cookies.
     */
    public function usesHeaderToken(): bool
    {
        return $this->_current?->usesHeaderToken() ?? false;
    }

    /**
     * Whether `$actor` may moderate through the request being handled. Only the member a member token
     * signed in is limited, by its origin's `moderation` setting, so checks about anyone else, such as
     * who to tell about a new comment, aren't affected.
     */
    public function allowsModeration(?User $actor = null): bool
    {
        $signIn = $this->_current;

        if (!$signIn || $signIn->allowsModeration()) {
            return true;
        }

        return $actor !== null && $signIn->user !== null && (int)$actor->id !== (int)$signIn->user->id;
    }

    /**
     * Whether `$secret` is the one behind this request's guest session token. A secret that came from
     * a token Comments issued can name a new guest identity, as a cookie's can't.
     */
    public function ownsGuestSecret(string $secret): bool
    {
        $current = $this->_current?->guestSecret;

        return $current !== null && $this->usesHeaderToken() && hash_equals($current, $secret);
    }

    /**
     * Hand a new guest's secret to the front end as a guest session token rather than a cookie, when
     * the request uses one. Returns false for requests that use cookies.
     */
    public function issueGuestSession(string $secret): bool
    {
        $signIn = $this->_current;

        if (!$signIn || $signIn->profile !== RequestProfile::CrossOriginPublic) {
            return false;
        }

        $signIn->guestSecret = $secret;
        $signIn->sendGuestSession = true;

        return true;
    }

    /**
     * Finish an answer for the current sign-in. A tier 3 answer never sets a cookie, however one was
     * started along the way, and a guest's carries their refreshed session token.
     */
    public function finish(Response $response): void
    {
        $signIn = $this->_current;

        if (!$signIn || !$signIn->usesHeaderToken()) {
            return;
        }

        $response->getCookies()->removeAll();

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header_remove('Set-Cookie');
        }

        if ($signIn->sendGuestSession && $signIn->guestSecret !== null && $signIn->origin !== null) {
            $response->getHeaders()->set(self::SESSION_HEADER, $this->guestSessionToken($signIn->guestSecret, $signIn->origin));
        }
    }

    /**
     * A guest session token: the guest's secret, the origin it's bound to and when it runs out,
     * encrypted and authenticated with Craft's security key, so it can't be read, changed or moved to
     * another origin.
     */
    public function guestSessionToken(string $secret, string $origin, ?int $expiresAt = null): string
    {
        $payload = Json::encode([
            'v' => self::GUEST_SESSION_VERSION,
            'g' => $secret,
            'o' => $origin,
            'e' => $expiresAt ?? DateTimeHelper::currentTimeStamp() + self::GUEST_SESSION_DURATION,
        ]);

        $encrypted = Craft::$app->getSecurity()->encryptByKey($payload, null, self::GUEST_SESSION_INFO);

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }


    // Private Methods
    // =========================================================================

    /**
     * The secret behind a guest session token sent from `$origin`. A token that's been changed, has
     * run out, or belongs to another origin is refused with `session_expired`, so the front end asks
     * `session` for a new one.
     */
    private function _readGuestSession(string $token, string $origin): string
    {
        $expired = new ApiException(Craft::t('comments', 'Your session expired. Refresh the page and try again.'), 'session_expired', 401);

        if (strlen($token) > self::GUEST_SESSION_MAX_LENGTH || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            throw $expired;
        }

        $encrypted = base64_decode(strtr($token, '-_', '+/'), true);
        $json = $encrypted !== false ? Craft::$app->getSecurity()->decryptByKey($encrypted, null, self::GUEST_SESSION_INFO) : false;

        if (!is_string($json)) {
            throw $expired;
        }

        try {
            $payload = Json::decode($json);
        } catch (Throwable) {
            throw $expired;
        }

        if (!is_array($payload)
            || ($payload['v'] ?? null) !== self::GUEST_SESSION_VERSION
            || !is_string($payload['g'] ?? null) || $payload['g'] === ''
            || !is_string($payload['o'] ?? null)
            || !is_int($payload['e'] ?? null)
            || $payload['e'] < DateTimeHelper::currentTimeStamp()
        ) {
            throw $expired;
        }

        if (!hash_equals($payload['o'], $origin)) {
            throw new ApiException(Craft::t('comments', 'This site doesn’t accept comments requests from here.'), 'origin_not_allowed', 403);
        }

        return $payload['g'];
    }

    private function _originOf(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
