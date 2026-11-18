<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\enums\RequestProfile;
use verbb\comments\exceptions\ApiException;
use verbb\comments\models\AllowedOrigin;
use verbb\comments\models\RequestSignIn;
use verbb\comments\services\Api;
use verbb\comments\services\Frontend;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\Response;
use yii\web\UploadedFile;

use Throwable;

/**
 * The data API, `comments/api/*`, which every thread is drawn from. Its JSON format has an API version:
 * every response says `"apiVersion": 1`, and a request naming another version in the
 * `X-Comments-Api-Version` header is refused rather than answered in a format it can't read.
 *
 * `thread`, `replies`, `comment` and `structured-data` never read who's asking, start a session or set a
 * cookie, so anyone can cache them. Everything personal comes from `viewer` and `edit`, which are never
 * cached. Writes need an `operationId`, and a repeat of one returns the first result rather than acting
 * twice.
 *
 * Everything else goes through the sign-in tiers in `SignIn`: the request's `X-Comments-Profile` must
 * fit the origin it came from, other origins get CORS headers only when **Allowed Origins** lists them,
 * and a front end on another site is identified by a member token or a guest session token rather than
 * a cookie. `member-token` and `revoke-member-tokens` are for a site's own server, signed with the
 * Member Token Key, and send no CORS headers, so no browser can read them.
 */
class ApiController extends Controller
{
    // Constants
    // =========================================================================

    public const API_VERSION_HEADER = 'X-Comments-Api-Version';

    // Reads that never look at who's asking, answered the same to every origin.
    private const PUBLIC_ACTIONS = ['thread', 'replies', 'comment', 'structured-data'];

    // Requests from a site's own server, signed with the Member Token Key.
    private const SIGNED_ACTIONS = ['member-token', 'revoke-member-tokens'];


    // Properties
    // =========================================================================

    protected array|bool|int $allowAnonymous = [
        'thread' => self::ALLOW_ANONYMOUS_LIVE,
        'replies' => self::ALLOW_ANONYMOUS_LIVE,
        'comment' => self::ALLOW_ANONYMOUS_LIVE,
        'structured-data' => self::ALLOW_ANONYMOUS_LIVE,
        'viewer' => self::ALLOW_ANONYMOUS_LIVE,
        'protection' => self::ALLOW_ANONYMOUS_LIVE,
        'session' => self::ALLOW_ANONYMOUS_LIVE,
        'edit' => self::ALLOW_ANONYMOUS_LIVE,
        'mentions' => self::ALLOW_ANONYMOUS_LIVE,
        'upload' => self::ALLOW_ANONYMOUS_LIVE,
        'create' => self::ALLOW_ANONYMOUS_LIVE,
        'update' => self::ALLOW_ANONYMOUS_LIVE,
        'delete' => self::ALLOW_ANONYMOUS_LIVE,
        'vote' => self::ALLOW_ANONYMOUS_LIVE,
        'report' => self::ALLOW_ANONYMOUS_LIVE,
        'follow' => self::ALLOW_ANONYMOUS_LIVE,
        'moderate' => self::ALLOW_ANONYMOUS_LIVE,
        'member-token' => self::ALLOW_ANONYMOUS_LIVE,
        'revoke-member-tokens' => self::ALLOW_ANONYMOUS_LIVE,
    ];


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        // Writes check the CSRF token themselves, in `_requireCsrf()`, so a refusal is the API's JSON
        // error rather than Craft's error page, and so a request signed in by a header token alone can
        // go without one. Anyone can call these actions; each one says what it needs.
        $this->enableCsrfValidation = false;

        // The data API is only for the site. A control panel URL would skip Craft's control panel
        // access check for anonymous actions and look like a control panel request to the comment, so
        // it's answered as an action that doesn't exist, before anything else runs.
        if ($this->request->getIsCpRequest()) {
            $this->response->format = Response::FORMAT_JSON;
            $this->_error(new ApiException(Craft::t('comments', 'This can’t be shown.'), 'not_found', 404));

            return false;
        }

        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->response->format = Response::FORMAT_JSON;
        $this->response->getHeaders()
            ->set('X-Robots-Tag', 'noindex')
            ->set(self::API_VERSION_HEADER, (string)Frontend::API_VERSION);


        Comments::$plugin->getSignIn()->reset();

        // A browser asks before sending a request another origin couldn't make with a form. The answer
        // ends here, without running the action.
        if ($this->request->getIsOptions()) {
            if (!in_array($action->id, self::SIGNED_ACTIONS, true)) {
                Comments::$plugin->getSignIn()->preflight($this->request, $this->response, in_array($action->id, self::PUBLIC_ACTIONS, true));
            } else {
                $this->response->setStatusCode(204);
                $this->response->format = Response::FORMAT_RAW;
                $this->response->data = '';
            }

            return false;
        }

        return true;
    }

    public function afterAction($action, $result): mixed
    {
        // A tier 3 answer never carries a cookie, and a guest's carries their refreshed session token.
        Comments::$plugin->getSignIn()->finish($this->response);

        return parent::afterAction($action, $result);
    }

    /**
     * `GET comments/api/thread`: a page of a thread, with `ownerId` or `ownerUri`, `siteId`, `sort`,
     * `limit` and `cursor`.
     */
    public function actionThread(): Response
    {
        return $this->_public(fn(): array => Comments::$plugin->getApi()->thread(
            $this->_owner(),
            $this->request->getQueryParam('sort'),
            $this->request->getQueryParam('limit'),
            $this->request->getQueryParam('cursor'),
        ));
    }

    /**
     * `GET comments/api/replies`: a page of a comment's replies, with `parentUid`, `limit` and `cursor`.
     */
    public function actionReplies(): Response
    {
        return $this->_public(fn(): array => Comments::$plugin->getApi()->replies(
            $this->request->getQueryParam('parentUid'),
            $this->request->getQueryParam('limit'),
            $this->request->getQueryParam('cursor'),
        ));
    }

    /**
     * `GET comments/api/comment`: one comment in its thread, by `uid`, or by `id`, the number in its
     * permalink (`#comment-123`), for a link to it.
     */
    public function actionComment(): Response
    {
        return $this->_public(fn(): array => Comments::$plugin->getApi()->comment($this->request->getQueryParam('uid'), $this->request->getQueryParam('id')));
    }

    /**
     * `GET comments/api/structured-data`: the thread's schema.org JSON-LD, with `ownerId` or `ownerUri`,
     * `siteId`, `limit`, `includeAuthors` and `id`.
     */
    public function actionStructuredData(): Response
    {
        return $this->_public(function(): array {
            $includeAuthors = $this->request->getQueryParam('includeAuthors');

            return [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                'structuredData' => Comments::$plugin->getApi()->structuredData($this->_owner(), [
                    'limit' => $this->request->getQueryParam('limit'),
                    'includeAuthors' => in_array($includeAuthors, ['1', 'true', 1, true], true),
                    'id' => $this->request->getQueryParam('id'),
                ]),
            ];
        });
    }

    /**
     * `GET comments/api/viewer`: everything personal for the window a `thread` request covers, with the
     * same `sort`, `limit` and `cursor`, plus `uids[]` for comments loaded since and `ids[]` for ones
     * a link names by number.
     */
    public function actionViewer(): Response
    {
        return $this->_private(function(): array {
            $api = Comments::$plugin->getApi();
            $api->rateLimit('viewer', $api::VIEWER_READS_PER_MINUTE);
            // Someone Craft has signed in without a cookie, such as through HTTP authentication, is
            // answered in full too, as `session` answers them. Asking doesn't start a session.
            $slim = !$api->hasSession() && !Comments::$plugin->getService()->getUser();
            $owner = $this->_owner();
            $csrf = $slim ? null : $this->_csrf();

            return Comments::$plugin->getApi()->viewer(
                $owner,
                $this->request->getQueryParam('sort'),
                $this->request->getQueryParam('limit'),
                $this->request->getQueryParam('cursor'),
                $this->request->getQueryParam('uids', []),
                $slim,
                $csrf,
                ids: $this->request->getQueryParam('ids', []),
            );
        });
    }

    /**
     * `GET comments/api/edit`: what the asker needs to edit their own comment, by `uid`.
     */
    public function actionEdit(): Response
    {
        return $this->_private(fn(): array => Comments::$plugin->getApi()->edit($this->request->getQueryParam('uid')));
    }

    /**
     * `GET comments/api/protection`: a fresh protection preparation and CSRF token for someone about to
     * write, with `ownerId` or `ownerUri`, `siteId`, `commentUid` for an edit, and `channel`.
     */
    public function actionProtection(): Response
    {
        return $this->_private(fn(): array => [
            'apiVersion' => Frontend::API_VERSION,
            'status' => 'ok',
            'protection' => $this->_limited('protection', Comments::$plugin->getApi()::PROTECTION_READS_PER_MINUTE)->protection(
                $this->_owner(),
                $this->request->getQueryParam('commentUid'),
                $this->request->getQueryParam('channel'),
            ),
            // Someone starting to write is the moment a guest's session and CSRF cookie begin.
            'csrf' => $this->_csrf(),
        ]);
    }

    /**
     * `GET comments/api/session`: who's signed in and the CSRF token, for a front end about to write
     * without one, such as a guest's first vote or a sibling subdomain (tier 2). It starts a guest's
     * session, and for a guest on another site (`cross-origin-public`) it issues a guest session token
     * in `X-Comments-Session`, or refreshes the one sent, which writes need from then on. `ownerId` or
     * `ownerUri` and `siteId` are optional, for that thread's `viewer`.
     */
    public function actionSession(): Response
    {
        return $this->_private(function(): array {
            $api = $this->_limited('session', Api::SESSION_READS_PER_MINUTE);
            $hasOwner = $this->request->getQueryParam('ownerId') !== null || $this->request->getQueryParam('ownerUri') !== null;

            return $api->session($hasOwner ? $this->_owner() : null, $this->_csrf());
        }, startGuestSession: true);
    }

    /**
     * `GET` or `POST comments/api/mentions`: Editor's mention search for a member, with Editor's
     * `context`, `scope` and `query`, answered in Editor's format (`results`) under the sign-in tiers.
     * Editor posts JSON with the CSRF token; the comments package sends a `GET`.
     */
    public function actionMentions(): Response
    {
        return $this->_signedIn(function(): array {
            if (!$this->request->getIsGet() && !$this->request->getIsHead() && !$this->request->getIsPost()) {
                throw new ApiException(Craft::t('comments', 'This action only accepts GET and POST requests.'), 'method_not_allowed', 405);
            }

            $api = $this->_limited('mentions', Api::MENTION_SEARCHES_PER_MINUTE);
            $params = $this->request->getIsPost() ? $this->request->getBodyParams() : $this->request->getQueryParams();

            return $api->mentions($params['context'] ?? null, $params['scope'] ?? [], $params['query'] ?? '');
        }, $this->request->getIsPost());
    }

    /**
     * `POST comments/api/upload`: a member's upload for Editor's attachments or inline images, as
     * Editor sends it (`file`, `kind`, `context` and `scope` as JSON), under the sign-in tiers and
     * Comments' upload rules, answered in Editor's format (`asset`).
     *
     * With `field`, an upload for an Assets custom field instead, by anyone the field is offered to on
     * that comment: `ownerId` or `ownerUri` and `siteId` (with `parentUid` for a reply), or `commentUid`
     * for the asker's own comment. It answers `field` and `file`, whose `id` a comment is then posted
     * with in `fields`.
     */
    public function actionUpload(): Response
    {
        return $this->_signedIn(function(): array {
            if (!$this->request->getIsPost()) {
                throw new ApiException(Craft::t('comments', 'This action only accepts POST requests.'), 'method_not_allowed', 405);
            }

            // A guest on another site uploads as the guest their session token names, as for a write.
            $signIn = Comments::$plugin->getSignIn()->current();

            if ($this->request->getBodyParam('field') !== null && $signIn?->profile === RequestProfile::CrossOriginPublic && $signIn->guestSecret === null) {
                throw new ApiException(Craft::t('comments', 'Your session expired. Refresh the page and try again.'), 'session_expired', 401);
            }

            $api = $this->_limited('uploads', Api::UPLOADS_PER_MINUTE);

            return $api->upload(UploadedFile::getInstanceByName('file'), $this->request->getBodyParams());
        }, true);
    }

    /**
     * `POST comments/api/member-token`, from a site's own server: a member token for `user` (`{ uid }`,
     * `{ id }` or `{ email }`) on `origin`, which must be listed with member tokens, lasting
     * `expiresIn` seconds (15 minutes at most, and by default). Signed in `X-Comments-Signature`.
     */
    public function actionMemberToken(): Response
    {
        return $this->_signed('comments/api/member-token', function(array $body): array {
            $tokens = Comments::$plugin->getMemberTokens();
            $user = $tokens->eligibleUser($body['user'] ?? null);
            $origin = Comments::$plugin->getSignIn()->allowedOrigin(AllowedOrigin::normalize($body['origin'] ?? null));

            if (!$origin || !$origin->memberTokens) {
                throw new ApiException(Craft::t('comments', 'This origin isn’t allowed member tokens.'), 'origin_not_allowed', 403);
            }

            return $tokens->mint($user, $origin, $body['expiresIn'] ?? null);
        });
    }

    /**
     * `POST comments/api/revoke-member-tokens`, from a site's own server: revoke every token a member
     * has, such as when they sign out there. Signed in `X-Comments-Signature`.
     */
    public function actionRevokeMemberTokens(): Response
    {
        return $this->_signed('comments/api/revoke-member-tokens', function(array $body): array {
            $tokens = Comments::$plugin->getMemberTokens();
            $user = $tokens->eligibleUser($body['user'] ?? null, true);

            return ['revoked' => $tokens->revokeForUser((int)$user->id)];
        });
    }

    public function actionCreate(): Response
    {
        return $this->_write('create', fn(array $body): array => Comments::$plugin->getApi()->create($body));
    }

    public function actionUpdate(): Response
    {
        return $this->_write('update', fn(array $body): array => Comments::$plugin->getApi()->update($body));
    }

    public function actionDelete(): Response
    {
        return $this->_write('delete', fn(array $body): array => Comments::$plugin->getApi()->delete($body));
    }

    public function actionVote(): Response
    {
        return $this->_write('vote', fn(array $body): array => Comments::$plugin->getApi()->vote($body));
    }

    public function actionReport(): Response
    {
        return $this->_write('report', fn(array $body): array => Comments::$plugin->getApi()->report($body));
    }

    public function actionFollow(): Response
    {
        return $this->_write('follow', fn(array $body): array => Comments::$plugin->getApi()->follow($body));
    }

    public function actionModerate(): Response
    {
        return $this->_write('moderate', fn(array $body): array => Comments::$plugin->getApi()->moderate($body));
    }


    // Private Methods
    // =========================================================================

    /**
     * A read anyone can cache. It never looks at the session, a cookie or `Authorization`, so it can
     * answer the same for everyone, and it's limited per address so a cache is the way to serve heavy
     * traffic.
     *
     * Browsers check with the server each time, through an `ETag`, so someone who has just posted sees
     * their comment on a reload, while a CDN keeps it for a minute and, where it reads
     * `CDN-Cache-Control`, a few more while it fetches a fresh one. While Craft takes `@web` from the
     * request's host, nothing is shared, because a forged host header could otherwise reach everyone.
     */
    private function _public(callable $read): Response
    {
        // The same answer for every origin, so any site can read it and one cached copy serves them
        // all. It only varies by the API version a client asks for.
        $headers = $this->response->getHeaders();
        $headers->set('Vary', self::API_VERSION_HEADER);
        Comments::$plugin->getSignIn()->applyPublicCors($this->response);
        $api = Comments::$plugin->getApi();

        // Craft reads the signed-in user on every request, which opens the session and holds its lock.
        // Nothing here needs it, so it's released straight away.
        $session = Craft::$app->getSession();

        if ($session->getIsActive()) {
            $session->close();
        }

        try {
            $this->_requireGet();
            $this->_checkApiVersion();
            $api->limitPublicRead();
            $data = $read();
        } catch (Throwable $e) {
            return $this->_error($e);
        }

        // Craft marks every action request as uncacheable, and an opened session adds its own headers
        // and cookie, none of which belong on a response anyone can share.
        $this->_removeSessionHeaders();
        $this->response->getCookies()->removeAll();
        $headers->remove('Pragma');
        $headers->remove('Expires');

        if ($api->isHostDependent()) {
            $headers->set('Cache-Control', 'private, no-cache');
        } else {
            $headers->set('Cache-Control', 'public, max-age=0, s-maxage=60, must-revalidate');
            $headers->set('CDN-Cache-Control', 'max-age=60, stale-while-revalidate=300');
        }

        $etag = '"' . hash('sha256', Json::encode($data)) . '"';
        $headers->set('ETag', $etag);

        if ($this->_matchesEtag($etag)) {
            $this->response->setStatusCode(304);
            $this->response->format = Response::FORMAT_RAW;
            $this->response->data = '';

            return $this->response;
        }

        return $this->asJson($data);
    }

    /**
     * Whether the request's `If-None-Match` names this ETag. The comparison is weak, as the standard
     * says it must be for `If-None-Match`, because a server compressing the response, such as nginx
     * with gzip, sends it on as `W/"…"`, and the browser asks with that.
     */
    private function _matchesEtag(string $etag): bool
    {
        $header = trim((string)$this->request->getHeaders()->get('If-None-Match'));

        if ($header === '') {
            return false;
        }

        if ($header === '*') {
            return true;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);

            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }

            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    /**
     * PHP sends these itself when a session starts, outside Craft's response, so they're taken back
     * here. There are none to take back from the command line.
     */
    private function _removeSessionHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header_remove('Set-Cookie');
        header_remove('Expires');
        header_remove('Pragma');
        header_remove('Cache-Control');
    }

    /**
     * A read for one person, which is never stored anywhere.
     */
    private function _private(callable $read, bool $startGuestSession = false): Response
    {
        try {
            // The origin is checked first, so a listed origin can read every refusal after it.
            $signIn = $this->_begin();
            $this->_requireGet();
            $this->_checkApiVersion();
            $this->_identify($signIn, $startGuestSession);
            $response = $this->asJson($read());
        } catch (Throwable $e) {
            return $this->_error($e);
        }

        $this->_noStore();

        return $response;
    }

    /**
     * A request for someone signed in that may be a `GET` or a `POST`, such as Editor's, with the CSRF
     * token checked on `$checkCsrf` for cookie sign-ins.
     */
    private function _signedIn(callable $handle, bool $checkCsrf): Response
    {
        try {
            $signIn = $this->_begin();
            $this->_checkApiVersion();
            $this->_identify($signIn);

            if ($checkCsrf) {
                $this->_requireCsrf();
            }

            $response = $this->asJson($handle());
        } catch (Throwable $e) {
            return $this->_error($e, true);
        }

        $this->_noStore();

        return $response;
    }

    /**
     * A request from a site's own server, signed with the Member Token Key. It reads no session or
     * cookie and sets none, and carries no CORS headers.
     */
    private function _signed(string $route, callable $handle): Response
    {
        $session = Craft::$app->getSession();

        if ($session->getIsActive()) {
            $session->close();
        }

        try {
            if (!$this->request->getIsPost()) {
                throw new ApiException(Craft::t('comments', 'This action only accepts POST requests.'), 'method_not_allowed', 405);
            }

            $this->_checkApiVersion();
            // Signed requests come from a site's own server, which can make many for a busy site, but
            // not without limit.
            Comments::$plugin->getApi()->rateLimit($route, Api::SIGNED_REQUESTS_PER_MINUTE);
            $body = Comments::$plugin->getMemberTokens()->verifySignedRequest($this->request, $route);
            $response = $this->asJson([
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                ...$handle($body),
            ]);
        } catch (Throwable $e) {
            $response = $this->_error($e);
        }

        $this->_noStore();
        $this->response->getCookies()->removeAll();
        $this->_removeSessionHeaders();

        return $response;
    }

    /**
     * Check the request's profile against its origin, which also sets the answer's CORS headers.
     */
    private function _begin(): RequestSignIn
    {
        return Comments::$plugin->getSignIn()->begin($this->request, $this->response);
    }

    /**
     * Work out who a request that passed `_begin()` identifies. A tier 3 request is identified by its
     * token alone.
     */
    private function _identify(RequestSignIn $signIn, bool $startGuestSession = false): void
    {
        Comments::$plugin->getSignIn()->identify($signIn, $this->request, $startGuestSession);
    }

    /**
     * Cookie sign-ins need the CSRF token on anything that changes something, because another page
     * on the same site can make the browser send its cookies. A request identified only by a header
     * token doesn't: no other page can make the browser send that.
     */
    private function _requireCsrf(): void
    {
        if (Comments::$plugin->getSignIn()->usesHeaderToken()) {
            return;
        }

        if (!$this->request->validateCsrfToken()) {
            throw new ApiException(Craft::t('comments', 'Your session expired. Refresh the page and try again.'), 'csrf_failed', 403);
        }
    }

    /**
     * A write, with its CSRF token checked and its `operationId` remembered, so a repeat returns the
     * first result marked `replayed` rather than acting twice. Failures aren't remembered, so a retry
     * after one, such as with fresh protection, is carried out.
     */
    private function _write(string $name, callable $write): Response
    {
        try {
            $signIn = $this->_begin();

            if (!$this->request->getIsPost()) {
                throw new ApiException(Craft::t('comments', 'This action only accepts POST requests.'), 'method_not_allowed', 405);
            }

            $this->_checkApiVersion();
            $this->_identify($signIn);
            $this->_requireCsrf();

            // A guest on another site writes as the guest their session token names, which they get
            // from `session` first, as a guest here gets their CSRF token.
            if ($signIn->profile === RequestProfile::CrossOriginPublic && $signIn->guestSecret === null) {
                throw new ApiException(Craft::t('comments', 'Your session expired. Refresh the page and try again.'), 'session_expired', 401);
            }

            $body = $this->request->getBodyParams();
            [$result, $replayed] = Comments::$plugin->getApi()->once($name, $body['operationId'] ?? null, fn(): array => [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                ...$write($body),
            ]);
        } catch (Throwable $e) {
            return $this->_error($e);
        }

        $this->_noStore();

        return $this->asJson([...$result, 'replayed' => $replayed]);
    }

    /**
     * `$forEditor` adds the message at the top level, where Editor reads a refusal's reason, for the
     * mentions and upload actions it calls.
     */
    private function _error(Throwable $e, bool $forEditor = false): Response
    {
        $api = Comments::$plugin->getApi();
        $error = $api->errorFor($e);

        if ($error->errorCode === 'server_error') {
            Craft::$app->getErrorHandler()->logException($e);
        }

        $this->_noStore();
        $headers = $this->response->getHeaders();

        if ($error->retryAfter !== null) {
            $headers->set('Retry-After', (string)$error->retryAfter);
        }

        $this->response->setStatusCode($error->httpStatus);
        $data = $api->errorData($error);

        if ($forEditor) {
            $data['message'] = $error->getMessage();
        }

        return $this->asJson($data);
    }

    /**
     * Set last, because asking Craft for a CSRF token marks the response uncacheable in its own words.
     */
    private function _noStore(): void
    {
        $headers = $this->response->getHeaders();
        $headers->remove('Pragma');
        $headers->remove('Expires');
        $headers->set('Cache-Control', 'private, no-store');
    }

    private function _requireGet(): void
    {
        if (!$this->request->getIsGet() && !$this->request->getIsHead()) {
            throw new ApiException(Craft::t('comments', 'This action only accepts GET requests.'), 'method_not_allowed', 405);
        }
    }

    /**
     * A client naming an API version this server doesn't serve is told so, rather than sent JSON in a
     * format it can't read. A request that names none, such as from a server-side script, gets the
     * current one.
     */
    private function _checkApiVersion(): void
    {
        $version = $this->request->getHeaders()->get(self::API_VERSION_HEADER);

        if ($version !== null && trim((string)$version) !== (string)Frontend::API_VERSION) {
            throw new ApiException(Craft::t('comments', 'This site serves version {version} of the comments API.', ['version' => Frontend::API_VERSION]), 'unsupported_api_version', 400);
        }
    }

    private function _owner(): ElementInterface
    {
        return Comments::$plugin->getApi()->resolveOwner(
            $this->request->getQueryParam('ownerId'),
            $this->request->getQueryParam('ownerUri'),
            $this->request->getQueryParam('siteId'),
        );
    }

    /**
     * The API service, once this address is within its limit for `$bucket`.
     */
    private function _limited(string $bucket, int $limit): Api
    {
        $api = Comments::$plugin->getApi();
        $api->rateLimit($bucket, $limit);

        return $api;
    }

    /**
     * The CSRF token for a cookie sign-in, or null for one by header token, which has no cookie to
     * match it against and doesn't need one.
     */
    private function _csrf(): ?array
    {
        if (Comments::$plugin->getSignIn()->usesHeaderToken()) {
            return null;
        }

        return [
            'name' => $this->request->csrfParam,
            'value' => $this->request->getCsrfToken(),
        ];
    }
}
