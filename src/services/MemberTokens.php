<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\exceptions\ApiException;
use verbb\comments\models\AllowedOrigin;
use verbb\comments\records\MemberToken as MemberTokenRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\Request;

use yii\db\IntegrityException;

use DateTime;
use Throwable;

/**
 * Member tokens sign a member in to the data API from a front end on another site (tier 3), where the
 * browser can't carry Craft's session.
 *
 * The site's own server, which knows who its visitor is, asks for one with a request signed by the
 * **Member Token Key** (`memberTokenKey`): an HMAC-SHA256 over the timestamp, a nonce, the method, the
 * action and the body, in `X-Comments-Signature`. A request more than five minutes out, or whose
 * nonce has been seen in the last ten, is refused. During a rotation the previous key
 * (`memberTokenPreviousKey`) is accepted too.
 *
 * A token is 256 random bits, stored only as a hash. It lasts 15 minutes, works only from the origin
 * it was minted for and only on the data API (including Editor's mentions and uploads through it), and
 * stops working when it's revoked, its origin is removed or no longer allows member tokens, or its
 * member is suspended, deleted or changes their password. The front end gets a new one by asking its
 * own server again; there's no refresh token in the browser.
 */
class MemberTokens extends Component
{
    // Static Methods
    // =========================================================================

    /**
     * What a signed request signs: the timestamp, the nonce, the method, the action route (such as
     * `comments/api/member-token`, whatever URL reached it) and the raw body, one per line.
     */
    public static function signaturePayload(int $timestamp, string $nonce, string $method, string $route, string $body): string
    {
        return implode("\n", [(string)$timestamp, $nonce, strtoupper($method), trim($route, '/'), $body]);
    }

    /**
     * The `X-Comments-Signature` header for a request, for a site's server written in PHP, and tests.
     */
    public static function signatureHeader(string $key, string $method, string $route, string $body, ?int $timestamp = null, ?string $nonce = null): string
    {
        $timestamp ??= DateTimeHelper::currentTimeStamp();
        $nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', self::signaturePayload($timestamp, $nonce, $method, $route, $body), $key);

        return "t=$timestamp, n=$nonce, v1=$signature";
    }


    // Constants
    // =========================================================================

    public const SIGNATURE_HEADER = 'X-Comments-Signature';
    public const TOKEN_PREFIX = 'cmt_';

    // A token lasts 15 minutes unless the mint asks for less, and at least one.
    public const DEFAULT_DURATION = 900;
    public const MIN_DURATION = 60;

    // How far a signed request's timestamp may be from now, either way, and how long a nonce is
    // remembered, which must be longer.
    public const SIGNATURE_WINDOW = 300;
    public const NONCE_DURATION = 600;

    // Expired and revoked tokens are kept a day, so a refusal can say which it was and the log can be
    // matched to them, then removed by garbage collection.
    public const RETENTION = 86400;

    public const TABLE = '{{%comments_member_tokens}}';
    public const NONCE_TABLE = '{{%comments_member_token_nonces}}';


    // Public Methods
    // =========================================================================

    /**
     * Check a server-to-server request's signature and return its JSON body. Everything that can go
     * wrong with the signature gives the same `signature_invalid`, and nothing about users is looked at
     * until it's passed. The nonce is only remembered once the signature is good, so unsigned requests
     * can't fill the table.
     */
    public function verifySignedRequest(Request $request, string $route): array
    {
        $keys = CommentsPlugin::$plugin->getSettings()->getMemberTokenKeys();
        // One answer for every way a signature fails, so it doesn't say whether member tokens are set
        // up or that a nonce was seen. Craft's log says which it was, for whoever is setting it up.
        $invalid = static function(string $reason): ApiException {
            CommentsPlugin::warning('Refused a signed request: {reason}.', ['reason' => $reason]);

            return new ApiException(Craft::t('comments', 'This request isn’t signed correctly.'), 'signature_invalid', 401);
        };

        if ($keys === []) {
            throw $invalid('no Member Token Key is set');
        }

        $parts = $this->_parseSignature((string)$request->getHeaders()->get(self::SIGNATURE_HEADER));

        if ($parts === null) {
            throw $invalid('the signature header is missing or malformed');
        }

        [$timestamp, $nonce, $signature] = $parts;

        if (abs(DateTimeHelper::currentTimeStamp() - $timestamp) > self::SIGNATURE_WINDOW) {
            throw $invalid('its timestamp is more than five minutes from now');
        }

        $body = $request->getRawBody();
        $payload = self::signaturePayload($timestamp, $nonce, $request->getMethod(), $route, $body);
        $matched = false;

        foreach ($keys as $key) {
            if (hash_equals(hash_hmac('sha256', $payload, $key), $signature)) {
                $matched = true;
            }
        }

        if (!$matched) {
            throw $invalid('the signature doesn\'t match');
        }

        try {
            Db::insert(self::NONCE_TABLE, [
                'nonceHash' => hash('sha256', $nonce),
                'dateCreated' => Db::prepareDateForDb(DateTimeHelper::now()),
            ]);
        } catch (IntegrityException) {
            throw $invalid('its nonce has been used');
        }

        try {
            $data = Json::decode($body);
        } catch (Throwable) {
            $data = null;
        }

        if (!is_array($data)) {
            throw new ApiException(Craft::t('comments', 'The request body must be a JSON object.'), 'invalid_request', 400);
        }

        return $data;
    }

    /**
     * The member a signed request names, by `{ "uid" }`, `{ "id" }` or `{ "email" }`, if they may be
     * given a token: an active account, not pending, suspended or deleted. Everything else gets the
     * same refusal, so the key's holder can't tell an unknown member from an ineligible one.
     */
    public function eligibleUser(mixed $reference, bool $anyStatus = false): User
    {
        $refused = new ApiException(Craft::t('comments', 'This member can’t be signed in here.'), 'user_not_eligible', 403);

        if (!is_array($reference) || count($reference) !== 1) {
            throw $refused;
        }

        $query = User::find()->status(null);
        $value = reset($reference);

        match (key($reference)) {
            'uid' => is_string($value) && StringHelper::isUUID($value) ? $query->uid($value) : throw $refused,
            'id' => is_int($value) && $value > 0 ? $query->id($value) : throw $refused,
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? $query->email(Db::escapeParam($value)) : throw $refused,
            default => throw $refused,
        };

        $user = $query->one();

        if (!$user instanceof User || (!$anyStatus && $user->getStatus() !== User::STATUS_ACTIVE)) {
            throw $refused;
        }

        return $user;
    }

    /**
     * A new token for `$user` on `$origin`, which must be listed with member tokens. Every mint is
     * logged with the member and origin, never the token.
     */
    public function mint(User $user, AllowedOrigin $origin, mixed $duration = null): array
    {
        if (!$origin->memberTokens) {
            throw new ApiException(Craft::t('comments', 'This site doesn’t accept comments requests from here.'), 'origin_not_allowed', 403);
        }

        $duration = is_int($duration) ? max(self::MIN_DURATION, min(self::DEFAULT_DURATION, $duration)) : self::DEFAULT_DURATION;
        $token = self::TOKEN_PREFIX . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = DateTimeHelper::currentTimeStamp() + $duration;

        $record = new MemberTokenRecord();
        $record->tokenHash = $this->_hash($token);
        $record->userId = (int)$user->id;
        $record->origin = $origin->origin;
        $record->dateExpires = Db::prepareDateForDb(new DateTime('@' . $expiresAt));
        $record->save(false);

        CommentsPlugin::info('Minted a member token for user {id} on {origin}.', ['id' => $user->id, 'origin' => $origin->origin]);

        return [
            'token' => $token,
            'expiresAt' => $expiresAt,
            'origin' => $origin->origin,
        ];
    }

    /**
     * The member an `Authorization: Bearer` header signs in, from `$origin`, with the token's ID.
     * Refused with `sign_in_required` when there's no token, `token_expired` once it's run out,
     * `token_revoked` when it's revoked, unknown or its member can no longer be signed in, and
     * `origin_not_allowed` from any origin but its own.
     */
    public function authenticate(string $header, string $origin): array
    {
        // The scheme's name is case-insensitive; the token isn't.
        if (!preg_match('/^(?i:Bearer)\s+(' . preg_quote(self::TOKEN_PREFIX, '/') . '[A-Za-z0-9_-]{43})$/', trim($header), $matches)) {
            throw new ApiException(Craft::t('comments', 'Sign in to continue.'), 'sign_in_required', 401);
        }

        $revoked = new ApiException(Craft::t('comments', 'You’ve been signed out. Sign in again to continue.'), 'token_revoked', 401);
        // Craft's user query doesn't load when the password last changed, so it comes with the token.
        $row = (new Query())
            ->select(['tokens.id', 'tokens.userId', 'tokens.origin', 'tokens.dateExpires', 'tokens.dateRevoked', 'tokens.dateCreated', 'users.lastPasswordChangeDate'])
            ->from(['tokens' => self::TABLE])
            ->leftJoin(['users' => Table::USERS], '[[users.id]] = [[tokens.userId]]')
            ->where(['tokens.tokenHash' => $this->_hash($matches[1])])
            ->one();

        if (!$row || $row['dateRevoked'] !== null) {
            throw $revoked;
        }

        if (!hash_equals((string)$row['origin'], $origin)) {
            throw new ApiException(Craft::t('comments', 'This site doesn’t accept comments requests from here.'), 'origin_not_allowed', 403);
        }

        if (Db::prepareDateForDb(DateTimeHelper::now()) >= $row['dateExpires']) {
            throw new ApiException(Craft::t('comments', 'Your sign-in expired. Please try again.'), 'token_expired', 401);
        }

        $user = Craft::$app->getUsers()->getUserById((int)$row['userId']);

        // A member suspended, deleted or no longer active since is signed out at once, as is one whose
        // password has changed since, such as after a reset.
        if (!$user || $user->getStatus() !== User::STATUS_ACTIVE) {
            throw $revoked;
        }

        if ($row['lastPasswordChangeDate'] !== null && $row['lastPasswordChangeDate'] > $row['dateCreated']) {
            throw $revoked;
        }

        return [$user, (int)$row['id']];
    }

    /**
     * Revoke every token a member has, such as when they sign out on the other site.
     */
    public function revokeForUser(int $userId): int
    {
        return Db::update(self::TABLE, ['dateRevoked' => Db::prepareDateForDb(DateTimeHelper::now())], ['userId' => $userId, 'dateRevoked' => null]);
    }

    /**
     * Revoke every member's tokens at once, such as after a Member Token Key may have leaked.
     */
    public function revokeAll(): int
    {
        return Db::update(self::TABLE, ['dateRevoked' => Db::prepareDateForDb(DateTimeHelper::now())], ['dateRevoked' => null]);
    }

    /**
     * Remove tokens that ran out or were revoked more than a day ago, and nonces older than the time
     * a signed request can be replayed in.
     */
    public function deleteExpired(): int
    {
        $tokenCutoff = Db::prepareDateForDb(new DateTime('@' . (DateTimeHelper::currentTimeStamp() - self::RETENTION)));
        $nonceCutoff = Db::prepareDateForDb(new DateTime('@' . (DateTimeHelper::currentTimeStamp() - self::NONCE_DURATION)));
        $deleted = Db::delete(self::TABLE, ['or', ['<', 'dateExpires', $tokenCutoff], ['<', 'dateRevoked', $tokenCutoff]]);

        return $deleted + Db::delete(self::NONCE_TABLE, ['<', 'dateCreated', $nonceCutoff]);
    }


    // Private Methods
    // =========================================================================

    private function _hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * `t=<unix time>, n=<nonce>, v1=<hex HMAC-SHA256>`, in any order. Anything else is null.
     */
    private function _parseSignature(string $header): ?array
    {
        $parts = [];

        foreach (explode(',', $header) as $pair) {
            $pair = trim($pair);

            if ($pair === '' || !str_contains($pair, '=')) {
                return null;
            }

            [$name, $value] = explode('=', $pair, 2);

            if (isset($parts[$name])) {
                return null;
            }

            $parts[$name] = $value;
        }

        if (!isset($parts['t'], $parts['n'], $parts['v1'])
            || !preg_match('/^\d{1,12}$/', $parts['t'])
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/', $parts['n'])
            || !preg_match('/^[0-9a-f]{64}$/', $parts['v1'])
        ) {
            return null;
        }

        return [(int)$parts['t'], $parts['n'], $parts['v1']];
    }
}
