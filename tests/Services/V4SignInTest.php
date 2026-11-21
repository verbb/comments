<?php

declare(strict_types=1);

use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\SpamSettingsStore;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\console\controllers\MemberTokensController;
use verbb\comments\elements\Comment;
use verbb\comments\enums\RequestProfile;
use verbb\comments\models\AllowedOrigin;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\RequestSignIn;
use verbb\comments\models\Settings;
use verbb\comments\services\MemberTokens;

/**
 * The data API's sign-in tiers through real requests: request profiles against origins, CORS and
 * preflights, the `session` read, guest session tokens, member tokens (minting, signatures, expiry,
 * origin binding, revoking and rotation), moderation through a token, CSRF only for cookie sign-ins,
 * Editor's mentions and uploads through the data API, the upload rules, and what's cached. Each test
 * names the threat-model item in plan section 5.5 it covers.
 */

const SIGN_IN_PASSWORD = 'Comments-SignIn-Http-1!';
const SIGN_IN_KEY = 'signin-test-current-key-0123456789abcdefghijklmnop';
const SIGN_IN_PREVIOUS_KEY = 'signin-test-previous-key-0123456789abcdefghijklmn';
const SIGN_IN_TIER2 = 'https://www.signin.example.test';
const SIGN_IN_TIER3 = 'https://app.signin-elsewhere.test';
const SIGN_IN_MODERATING = 'https://moderate.signin-elsewhere.test';
const SIGN_IN_GUESTS = 'https://guests.signin-elsewhere.test';
const SIGN_IN_FOREIGN = 'https://evil.example.test';
const SIGN_IN_FROM_ENV = 'https://env.signin-elsewhere.test';

function signInUser(string $prefix, array $permissions = []): User
{
    $user = CommentsFixtureFactory::user($prefix);
    $user->newPassword = SIGN_IN_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    if ($permissions) {
        Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);
    }

    return $user;
}

function signInBrowser(?User $user = null): HttpClient
{
    // Craft limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();

    if ($user) {
        $browser->login((string)$user->username, SIGN_IN_PASSWORD);
    }

    return $browser;
}

function signInHeader(array $response, string $name): ?string
{
    $values = $response['headers'][strtolower($name)] ?? null;

    return $values ? implode(', ', $values) : null;
}

function signInCode(array $response): ?string
{
    return $response['data']['errors'][0]['code'] ?? null;
}

function signInOperation(): string
{
    return StringHelper::UUID();
}

function signInPost($entry, string $text, ?User $user = null, bool $held = false): Comment
{
    Comments::$plugin->getSettings()->requireModeration = $held;
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => $user ? (int)$user->id : (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')->id,
    ]))->comment;
}

/**
 * A signed request from a site's own server, with the exact body that was signed.
 */
function signInSigned(string $action, array $body, string $key = SIGN_IN_KEY, ?int $timestamp = null, ?string $nonce = null, ?string $signedBody = null): array
{
    $raw = json_encode($body, JSON_UNESCAPED_SLASHES);
    $header = MemberTokens::signatureHeader($key, 'POST', 'comments/api/' . $action, $signedBody ?? $raw, $timestamp, $nonce);

    return (new HttpClient())->request('POST', '/actions/comments/api/' . $action, [
        'body' => $raw,
        'headers' => ['Content-Type' => 'application/json', MemberTokens::SIGNATURE_HEADER => $header],
    ]);
}

function signInMint(User $user, string $origin = SIGN_IN_TIER3, array $extra = []): string
{
    $response = signInSigned('member-token', ['user' => ['uid' => $user->uid], 'origin' => $origin, ...$extra]);
    expect($response['status'])->toBe(200, $response['body']);

    return (string)$response['data']['token'];
}

/** Headers for a member signed in by a token from `$origin`. */
function signInBearer(string $token, string $origin = SIGN_IN_TIER3): array
{
    return ['Origin' => $origin, 'X-Comments-Profile' => 'cross-origin-member', 'Authorization' => 'Bearer ' . $token];
}

/** Headers for a guest on another site, with their session token once they have one. */
function signInGuestHeaders(?string $session = null, string $origin = SIGN_IN_GUESTS): array
{
    return array_filter(['Origin' => $origin, 'X-Comments-Profile' => 'cross-origin-public', 'X-Comments-Session' => $session]);
}

function signInCsrf(HttpClient $browser, array $headers = []): string
{
    $response = $browser->get('/actions/comments/api/session', [], $headers);
    expect($response['status'])->toBe(200, $response['body']);

    return (string)$response['data']['csrf']['value'];
}

/** A JPEG that says where it was taken, in an EXIF GPS block. */
function signInJpegWithLocation(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagejpeg($image);
    $jpeg = (string)ob_get_clean();

    // A little-endian TIFF block whose first directory points at a GPS directory holding a latitude.
    $tiff = 'II' . pack('v', 42) . pack('V', 8)
        . pack('v', 1) . pack('vvVV', 0x8825, 4, 1, 26) . pack('V', 0)
        . pack('v', 2)
        . pack('vvV', 0x0001, 2, 2) . "N\0\0\0"
        . pack('vvVV', 0x0002, 5, 3, 56)
        . pack('V', 0)
        . pack('VVVVVV', 51, 1, 30, 1, 0, 1);
    $payload = "Exif\0\0" . $tiff;
    $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

    return substr($jpeg, 0, 2) . $segment . "\xFF\xFE" . pack('n', 13) . '<script>' . "\0\0\0" . substr($jpeg, 2);
}

function signInUpload(HttpClient $browser, string $name, string $contents, array $headers, $entry, string $kind = 'attachment', ?string $csrf = null): array
{
    $multipart = [
        ['name' => 'file', 'contents' => $contents, 'filename' => $name],
        ['name' => 'kind', 'contents' => $kind],
        ['name' => 'context', 'contents' => 'comments.reply'],
        ['name' => 'scope', 'contents' => json_encode(['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId])],
    ];

    if ($csrf !== null) {
        $headers['X-CSRF-Token'] = $csrf;
    }

    return $browser->request('POST', '/actions/comments/api/upload', ['multipart' => $multipart, 'headers' => $headers]);
}

function signInSettings(): array
{
    return [
        'enableSpamChecks' => false,
        'requireModeration' => false,
        'allowGuest' => true,
        'guestRequireEmailName' => false,
        'guestShowEmailName' => true,
        'allowGuestVoting' => true,
        'allowGuestFlagging' => true,
        'participantNotifications' => [],
        'staffNotifications' => [],
        'subscriptionTypes' => [],
        'authoringMode' => 'rich',
        'allowMentions' => true,
        'memberTokenKey' => '$COMMENTS_SIGNIN_TEST_KEY',
        'memberTokenPreviousKey' => '$COMMENTS_SIGNIN_TEST_PREVIOUS_KEY',
        'allowedOrigins' => [
            ['origin' => SIGN_IN_TIER2, 'credentials' => true],
            ['origin' => SIGN_IN_TIER3, 'memberTokens' => true],
            ['origin' => SIGN_IN_MODERATING, 'memberTokens' => true, 'moderation' => true],
            ['origin' => SIGN_IN_GUESTS],
            ['origin' => '$COMMENTS_SIGNIN_ENV_ORIGIN'],
        ],
    ];
}

function signInApplySettings(array $settings): void
{
    $projectConfig = Craft::$app->getProjectConfig();

    foreach ($settings as $name => $value) {
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
    }

    $projectConfig->saveModifiedConfigData();
    SpamSettingsStore::apply($settings);
    Comments::$plugin->getSettings()->setAttributes($settings, false);
}

beforeAll(function() {
    // The web app reads its settings from project config and its keys from the environment it was
    // started with, so the server starts again after both are set. Everything is put back afterwards.
    putenv('COMMENTS_SIGNIN_TEST_KEY=' . SIGN_IN_KEY);
    putenv('COMMENTS_SIGNIN_TEST_PREVIOUS_KEY=' . SIGN_IN_PREVIOUS_KEY);
    putenv('COMMENTS_SIGNIN_ENV_ORIGIN=' . SIGN_IN_FROM_ENV);
    $projectConfig = Craft::$app->getProjectConfig();
    $projectConfig->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    $GLOBALS['signInConfig'] = $projectConfig->get('plugins.comments.settings');
    $GLOBALS['signInSpam'] = SpamSettingsStore::snapshot();
    $GLOBALS['signInSettings'] = Comments::$plugin->getSettings()->getAttributes();

    // A volume for uploads.
    $handle = 'signInUploads' . bin2hex(random_bytes(4));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => 'https://uploads.example.test/' . $handle . '/']);
    Craft::$app->getFs()->saveFilesystem($filesystem);
    $volume = new craft\models\Volume(['name' => $handle, 'handle' => $handle, 'fsHandle' => $handle]);
    Craft::$app->getVolumes()->saveVolume($volume);
    $GLOBALS['signInVolume'] = [$filesystem, $volume, $path];

    signInApplySettings([
        ...signInSettings(),
        'allowAttachments' => true,
        'attachmentVolumeId' => (int)$volume->id,
        'attachmentExtensions' => ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'svg', 'html', 'xml', 'js'],
    ]);
    verbb\comments\helpers\EditorContexts::refresh();

    HttpClient::stopServer();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
    SpamSettingsStore::restore($GLOBALS['signInSpam']);
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = $GLOBALS['signInConfig'];

    if ($previous === null) {
        $projectConfig->remove('plugins.comments.settings');
    } else {
        $projectConfig->set('plugins.comments.settings', $previous);
    }

    [$filesystem, $volume, $path] = $GLOBALS['signInVolume'];
    Craft::$app->getVolumes()->deleteVolume($volume);
    Craft::$app->getFs()->removeFilesystem($filesystem);
    craft\helpers\FileHelper::removeDirectory($path);
    $projectConfig->saveModifiedConfigData();
    Comments::$plugin->getSettings()->setAttributes($GLOBALS['signInSettings'], false);
    verbb\comments\helpers\EditorContexts::refresh();
    Comments::$plugin->getSignIn()->reset();
    putenv('COMMENTS_SIGNIN_TEST_KEY');
    putenv('COMMENTS_SIGNIN_TEST_PREVIOUS_KEY');
    putenv('COMMENTS_SIGNIN_ENV_ORIGIN');
});

beforeEach(function() {
    // Rate limits are per address, and every request here comes from the same one.
    Craft::$app->getCache()->flush();
    Comments::$plugin->getSettings()->enableSpamChecks = false;
    Comments::$plugin->getSignIn()->reset();
});

// Profiles, origins and CORS
// =========================================================================

it('answers public reads to any origin without credentials or cookies, preflights included (caching leaks, AD-C-1)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in public owner');
    signInPost($entry, 'Public everywhere');
    $member = signInUser('signInPublicReader');
    $token = signInMint($member);
    $browser = signInBrowser($member);

    $plain = $browser->get('/actions/comments/api/thread', ['ownerId' => $entry->id], ['Origin' => SIGN_IN_FOREIGN]);
    // A token, a session cookie and a profile on a public read change nothing about the answer.
    $identified = $browser->get('/actions/comments/api/thread', ['ownerId' => $entry->id], signInBearer($token));
    $preflight = $browser->request('OPTIONS', '/actions/comments/api/thread?ownerId=' . $entry->id, ['headers' => [
        'Origin' => SIGN_IN_FOREIGN,
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'x-comments-api-version',
    ]]);

    expect($plain['status'])->toBe(200, $plain['body'])
        ->and(signInHeader($plain, 'Access-Control-Allow-Origin'))->toBe('*')
        ->and(signInHeader($plain, 'Access-Control-Allow-Credentials'))->toBeNull()
        ->and(signInHeader($plain, 'Access-Control-Expose-Headers'))->toContain('X-Comments-Api-Version')
        ->and(signInHeader($plain, 'Vary'))->toBe('X-Comments-Api-Version')
        ->and(signInHeader($plain, 'Set-Cookie'))->toBeNull()
        ->and(signInHeader($plain, 'Cache-Control'))->toBe('public, max-age=0, s-maxage=60, must-revalidate')
        ->and(signInHeader($identified, 'Set-Cookie'))->toBeNull()
        ->and(signInHeader($identified, 'Access-Control-Allow-Origin'))->toBe('*')
        ->and(preg_replace('/"timeAgo":"[^"]*"/', '', $identified['body']))->toBe(preg_replace('/"timeAgo":"[^"]*"/', '', $plain['body']))
        ->and($preflight['status'])->toBe(204)
        ->and($preflight['body'])->toBe('')
        ->and(signInHeader($preflight, 'Access-Control-Allow-Origin'))->toBe('*')
        ->and(signInHeader($preflight, 'Access-Control-Allow-Headers'))->toContain('X-Comments-Api-Version')
        ->and(signInHeader($preflight, 'Access-Control-Allow-Credentials'))->toBeNull()
        ->and(signInHeader($preflight, 'Access-Control-Max-Age'))->toBe('3600');
});

it('answers private preflights only for listed origins, with credentials only for tier 2 (CSRF from another subdomain)', function() {
    $headersAsked = 'x-comments-api-version, x-comments-profile, x-csrf-token, authorization, x-comments-session, content-type';
    $browser = signInBrowser();
    $preflight = static fn(string $origin, string $action = 'viewer'): array => $browser->request('OPTIONS', '/actions/comments/api/' . $action, ['headers' => [
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => $headersAsked,
    ]]);

    $tier2 = $preflight(SIGN_IN_TIER2, 'create');
    $tier3 = $preflight(SIGN_IN_TIER3, 'create');
    $guests = $preflight(SIGN_IN_GUESTS, 'session');
    $foreign = $preflight(SIGN_IN_FOREIGN, 'create');
    $signed = $preflight(SIGN_IN_TIER3, 'member-token');

    expect($tier2['status'])->toBe(204)
        ->and(signInHeader($tier2, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER2)
        ->and(signInHeader($tier2, 'Access-Control-Allow-Credentials'))->toBe('true')
        ->and(signInHeader($tier2, 'Access-Control-Allow-Methods'))->toContain('POST')
        ->and(signInHeader($tier2, 'Access-Control-Max-Age'))->toBe('3600')
        ->and(signInHeader($tier2, 'Vary'))->toContain('Origin');

    foreach (['X-Comments-Api-Version', 'X-Comments-Profile', 'X-CSRF-Token', 'Authorization', 'X-Comments-Session'] as $name) {
        expect(signInHeader($tier2, 'Access-Control-Allow-Headers'))->toContain($name);
    }

    expect(signInHeader($tier3, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER3)
        ->and(signInHeader($tier3, 'Access-Control-Allow-Credentials'))->toBeNull()
        ->and(signInHeader($guests, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_GUESTS)
        ->and(signInHeader($guests, 'Access-Control-Allow-Credentials'))->toBeNull()
        // An origin nobody listed gets nothing, so the browser refuses the request itself.
        ->and($foreign['status'])->toBe(204)
        ->and(signInHeader($foreign, 'Access-Control-Allow-Origin'))->toBeNull()
        ->and(signInHeader($foreign, 'Access-Control-Allow-Credentials'))->toBeNull()
        // A site's own server signs its requests, so no browser is ever let read them.
        ->and(signInHeader($signed, 'Access-Control-Allow-Origin'))->toBeNull();
});

it('refuses a request whose profile doesn’t fit its origin, starting with a foreign origin in tier 1 (origin spoofing)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in tier 1 owner');
    $member = signInUser('signInTierOne');
    $browser = signInBrowser($member);
    $csrf = signInCsrf($browser);
    $own = HttpClient::baseUrl();
    $create = static fn(array $headers): array => $browser->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Tier one']], $headers, $csrf);

    $cases = [
        'no profile, foreign origin' => [['Origin' => SIGN_IN_FOREIGN], 403, 'origin_not_allowed'],
        'tier 1, foreign origin' => [['Origin' => SIGN_IN_FOREIGN, 'X-Comments-Profile' => 'same-origin-browser'], 403, 'origin_not_allowed'],
        'tier 1 from a tier 2 origin' => [['Origin' => SIGN_IN_TIER2, 'X-Comments-Profile' => 'same-origin-browser'], 403, 'origin_not_allowed'],
        'opaque origin' => [['Origin' => 'null'], 403, 'origin_not_allowed'],
        'tier 2 from a token origin' => [['Origin' => SIGN_IN_TIER3, 'X-Comments-Profile' => 'same-site-credentialed'], 403, 'origin_not_allowed'],
        'tier 2 from its own origin' => [['Origin' => $own, 'X-Comments-Profile' => 'same-site-credentialed'], 403, 'origin_not_allowed'],
        'member tokens from a tier 2 origin' => [['Origin' => SIGN_IN_TIER2, 'X-Comments-Profile' => 'cross-origin-member'], 403, 'origin_not_allowed'],
        'guests from an unlisted origin' => [['Origin' => SIGN_IN_FOREIGN, 'X-Comments-Profile' => 'cross-origin-public'], 403, 'origin_not_allowed'],
        'an unknown profile' => [['Origin' => $own, 'X-Comments-Profile' => 'trust-me'], 400, 'invalid_request'],
    ];

    foreach ($cases as $label => [$headers, $status, $code]) {
        foreach (['viewer' => $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], $headers), 'create' => $create($headers)] as $action => $response) {
            expect($response['status'])->toBe($status, "$label $action " . $response['body'])
                ->and(signInCode($response))->toBe($code, $label)
                ->and(signInHeader($response, 'Cache-Control'))->toBe('private, no-store');
        }
    }

    // An origin nobody listed can't read even the refusal.
    expect(signInHeader($browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], ['Origin' => SIGN_IN_FOREIGN]), 'Access-Control-Allow-Origin'))->toBeNull();

    // Craft's own pages, with or without `Origin`, and with the profile named.
    foreach ([[], ['Origin' => $own], ['Origin' => $own, 'X-Comments-Profile' => 'same-origin-browser']] as $headers) {
        $viewer = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], $headers);

        expect($viewer['status'])->toBe(200, $viewer['body'])
            ->and($viewer['data']['viewer']['isGuest'])->toBeFalse()
            ->and(signInHeader($viewer, 'Access-Control-Allow-Origin'))->toBeNull();
    }

    expect($create(['Origin' => $own])['status'])->toBe(200)
        ->and((int)Comment::find()->ownerId($entry->id)->count())->toBe(1);
});

it('uses credentialed CORS for tier 2 only, and still needs the CSRF token there (CSRF from another subdomain)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in tier 2 owner');
    $member = signInUser('signInTierTwo');
    $browser = signInBrowser($member);
    $tier2 = ['Origin' => SIGN_IN_TIER2, 'X-Comments-Profile' => 'same-site-credentialed'];

    $session = $browser->get('/actions/comments/api/session', [], $tier2);
    $viewer = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], $tier2);

    expect($session['status'])->toBe(200, $session['body'])
        ->and($session['data']['viewer'])->toMatchArray(['isGuest' => false, 'hasSession' => true])
        ->and($session['data']['csrf']['value'])->toBeString()
        ->and(signInHeader($session, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER2)
        ->and(signInHeader($session, 'Access-Control-Allow-Credentials'))->toBe('true')
        ->and(signInHeader($session, 'Vary'))->toContain('Origin')
        ->and(signInHeader($session, 'Access-Control-Expose-Headers'))->toContain('X-Comments-Api-Version')
        ->and(signInHeader($session, 'Cache-Control'))->toBe('private, no-store')
        ->and($viewer['data']['viewer']['isGuest'])->toBeFalse()
        ->and($viewer['data']['csrf']['value'])->toBeString();

    $body = ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'From the subdomain']];
    $withoutToken = $browser->postJson('/actions/comments/api/create', $body, $tier2);
    $forged = $browser->postJson('/actions/comments/api/create', $body, $tier2, 'forged-token');
    $created = $browser->postJson('/actions/comments/api/create', $body, $tier2, (string)$session['data']['csrf']['value']);

    expect($withoutToken['status'])->toBe(403)
        ->and(signInCode($withoutToken))->toBe('csrf_failed')
        // Readable by the listed origin, so it can fetch a fresh token.
        ->and(signInHeader($withoutToken, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER2)
        ->and(signInCode($forged))->toBe('csrf_failed')
        ->and($created['status'])->toBe(200, $created['body'])
        ->and(signInHeader($created, 'Access-Control-Allow-Credentials'))->toBe('true')
        ->and((int)Comment::find()->ownerId($entry->id)->count())->toBe(1);
});

it('starts a guest’s session with the session read, so their first vote works', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in guest session owner');
    $root = signInPost($entry, 'Vote for me');
    $guest = signInBrowser();

    $before = $guest->postJson('/actions/comments/api/vote', ['operationId' => signInOperation(), 'uid' => $root->uid, 'vote' => 'upvote']);
    $session = $guest->get('/actions/comments/api/session');
    $scoped = $guest->get('/actions/comments/api/session', ['ownerId' => $entry->id]);
    $vote = $guest->postJson('/actions/comments/api/vote', ['operationId' => signInOperation(), 'uid' => $root->uid, 'vote' => 'upvote'], csrfToken: (string)$session['data']['csrf']['value']);

    expect(signInCode($before))->toBe('csrf_failed')
        ->and($session['status'])->toBe(200, $session['body'])
        ->and($session['data'])->toHaveKeys(['apiVersion', 'status', 'viewer', 'csrf'])
        ->and($session['data']['viewer'])->toMatchArray(['isGuest' => true, 'hasSession' => true, 'canVote' => true])
        ->and($session['data']['csrf'])->toHaveKeys(['name', 'value'])
        ->and(signInHeader($session, 'Cache-Control'))->toBe('private, no-store')
        ->and($scoped['data']['viewer']['follow'])->toHaveKeys(['available', 'following', 'needsEmail'])
        ->and($vote['status'])->toBe(200, $vote['body'])
        ->and($vote['data']['comment']['interaction']['upvotes'])->toBe(1);
});

it('offers Craft’s sign-in to guests on Craft’s own pages, and not to guests on another site, where it wouldn’t sign them in', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in login link owner');
    $local = signInBrowser()->get('/actions/comments/api/session', ['ownerId' => $entry->id]);
    $remote = signInBrowser();
    $headers = signInGuestHeaders();
    $remoteSession = $remote->get('/actions/comments/api/session', ['ownerId' => $entry->id], $headers);
    $remoteViewer = $remote->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders((string)signInHeader($remoteSession, 'X-Comments-Session')));

    expect($local['data']['viewer']['loginUrl'])->toBeString()->not->toBe('')
        ->and($remoteSession['status'])->toBe(200, $remoteSession['body'])
        ->and($remoteSession['data']['viewer']['loginUrl'])->toBeNull()
        ->and($remoteViewer['status'])->toBe(200, $remoteViewer['body'])
        ->and($remoteViewer['data']['viewer']['isGuest'])->toBeTrue()
        ->and($remoteViewer['data']['viewer']['loginUrl'])->toBeNull();
});

// Member tokens
// =========================================================================

/** Run `$check` with Craft's clock stopped at `$timestamp`. */
function signInAt(int $timestamp, callable $check): mixed
{
    craft\helpers\DateTimeHelper::pause(new DateTime('@' . $timestamp));

    try {
        return $check();
    } finally {
        craft\helpers\DateTimeHelper::resume();
    }
}

/** The error code `$check` is refused with, or null when it isn't. */
function signInRefusal(callable $check): ?string
{
    try {
        $check();
    } catch (verbb\comments\exceptions\ApiException $e) {
        return $e->errorCode;
    }

    return null;
}

it('times member tokens, signed requests and guest sessions by Craft’s clock, to the second', function() {
    $member = signInUser('signInClock');
    $origin = Comments::$plugin->getSettings()->getAllowedOrigins()[SIGN_IN_TIER3];
    $tokens = Comments::$plugin->getMemberTokens();
    $now = time();

    // A token lasts 15 minutes, or what the mint asks for between one minute and 15.
    [$default, $short, $capped, $floored] = signInAt($now, static fn(): array => [
        $tokens->mint($member, $origin),
        $tokens->mint($member, $origin, 120),
        $tokens->mint($member, $origin, 86400),
        $tokens->mint($member, $origin, 5),
    ]);

    expect($default['expiresAt'])->toBe($now + 900)
        ->and($short['expiresAt'])->toBe($now + 120)
        ->and($capped['expiresAt'])->toBe($now + 900)
        ->and($floored['expiresAt'])->toBe($now + 60);

    // It works until the second it runs out.
    $authenticate = static fn(string $token) => static fn() => $tokens->authenticate('Bearer ' . $token, SIGN_IN_TIER3);

    expect(signInAt($now + 119, $authenticate($short['token']))[0]->id)->toBe($member->id)
        ->and(signInAt($now + 120, static fn() => signInRefusal($authenticate($short['token']))))->toBe('token_expired')
        ->and(signInAt($now + 899, $authenticate($default['token']))[0]->id)->toBe($member->id)
        ->and(signInAt($now + 900, static fn() => signInRefusal($authenticate($default['token']))))->toBe('token_expired');

    // A signed request's timestamp may be up to five minutes either side of now.
    $raw = json_encode(['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_TIER3], JSON_UNESCAPED_SLASHES);
    $verify = static function(int $timestamp) use ($tokens, $raw): ?string {
        $request = new craft\web\Request();
        $request->setRawBody($raw);
        $request->getHeaders()
            ->set('X-Http-Method-Override', 'POST')
            ->set(MemberTokens::SIGNATURE_HEADER, MemberTokens::signatureHeader(SIGN_IN_KEY, 'POST', 'comments/api/member-token', $raw, $timestamp));

        return signInRefusal(static fn() => $tokens->verifySignedRequest($request, 'comments/api/member-token'));
    };

    expect(signInAt($now, static fn() => $verify($now - 300)))->toBeNull()
        ->and(signInAt($now, static fn() => $verify($now + 300)))->toBeNull()
        ->and(signInAt($now, static fn() => $verify($now - 301)))->toBe('signature_invalid')
        ->and(signInAt($now, static fn() => $verify($now + 301)))->toBe('signature_invalid');

    // A guest on another site keeps their session for two hours, then asks for a new one.
    $signIn = Comments::$plugin->getSignIn();
    $guestToken = signInAt($now, static fn(): string => $signIn->guestSessionToken('clock-secret-' . uniqid(), SIGN_IN_GUESTS));
    $read = static fn() => (new ReflectionMethod($signIn, '_readGuestSession'))->invoke($signIn, $guestToken, SIGN_IN_GUESTS);

    expect(signInAt($now + 7200, $read))->toStartWith('clock-secret-')
        ->and(signInAt($now + 7201, static fn() => signInRefusal($read)))->toBe('session_expired');

    // Garbage collection keeps a nonce for ten minutes and an expired token for a day.
    $nonce = 'clock-nonce-' . uniqid();
    $expiredToken = 'clock-token-' . uniqid();
    Db::insert(MemberTokens::NONCE_TABLE, ['nonceHash' => hash('sha256', $nonce), 'dateCreated' => Db::prepareDateForDb(new DateTime('@' . $now))]);
    Db::insert(MemberTokens::TABLE, ['tokenHash' => hash('sha256', $expiredToken), 'userId' => $member->id, 'origin' => SIGN_IN_TIER3, 'dateExpires' => Db::prepareDateForDb(new DateTime('@' . $now))]);
    $exists = static fn(string $table, string $column, string $value): bool => (new craft\db\Query())->from($table)->where([$column => hash('sha256', $value)])->exists();

    signInAt($now + 600, static fn() => $tokens->deleteExpired());
    expect($exists(MemberTokens::NONCE_TABLE, 'nonceHash', $nonce))->toBeTrue();
    signInAt($now + 601, static fn() => $tokens->deleteExpired());
    expect($exists(MemberTokens::NONCE_TABLE, 'nonceHash', $nonce))->toBeFalse()
        ->and($exists(MemberTokens::TABLE, 'tokenHash', $expiredToken))->toBeTrue();
    signInAt($now + 86401, static fn() => $tokens->deleteExpired());
    expect($exists(MemberTokens::TABLE, 'tokenHash', $expiredToken))->toBeFalse();
});

it('mints a member token only for a correctly signed, fresh, unreplayed request, with either key (leaked key, replayed minting)', function() {
    $member = signInUser('signInMinted');
    $body = ['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_TIER3];

    // The web app runs in its own process on the same clock, so whatever moment it minted at lies
    // between these two readings, however slowly the requests run.
    $before = time();
    $current = signInSigned('member-token', $body);
    $previous = signInSigned('member-token', $body, SIGN_IN_PREVIOUS_KEY);
    $byEmail = signInSigned('member-token', ['user' => ['email' => $member->email], 'origin' => SIGN_IN_TIER3]);
    $byId = signInSigned('member-token', ['user' => ['id' => (int)$member->id], 'origin' => SIGN_IN_TIER3, 'expiresIn' => 120]);
    $capped = signInSigned('member-token', [...$body, 'expiresIn' => 86400]);
    $after = time();

    expect($current['status'])->toBe(200, $current['body'])
        ->and($current['data']['token'])->toMatch('/^cmt_[A-Za-z0-9_-]{43}$/')
        ->and($current['data']['origin'])->toBe(SIGN_IN_TIER3)
        ->and($current['data']['expiresAt'])->toBeGreaterThanOrEqual($before + 900)->toBeLessThanOrEqual($after + 900)
        ->and(signInHeader($current, 'Cache-Control'))->toBe('private, no-store')
        ->and(signInHeader($current, 'Set-Cookie'))->toBeNull()
        ->and(signInHeader($current, 'Access-Control-Allow-Origin'))->toBeNull()
        ->and($previous['status'])->toBe(200, $previous['body'])
        ->and($byEmail['status'])->toBe(200, $byEmail['body'])
        ->and($byId['data']['expiresAt'])->toBeGreaterThanOrEqual($before + 120)->toBeLessThanOrEqual($after + 120)
        ->and($capped['data']['expiresAt'])->toBeGreaterThanOrEqual($before + 900)->toBeLessThanOrEqual($after + 900);

    // Only a hash is stored.
    $stored = (new craft\db\Query())->from(MemberTokens::TABLE)->where(['userId' => $member->id])->all();

    expect($stored)->toHaveCount(5)
        ->and(json_encode($stored))->not->toContain(substr((string)$current['data']['token'], 4));

    $raw = json_encode($body, JSON_UNESCAPED_SLASHES);
    $replayHeader = MemberTokens::signatureHeader(SIGN_IN_KEY, 'POST', 'comments/api/member-token', $raw);
    $replay = static fn(): array => (new HttpClient())->request('POST', '/actions/comments/api/member-token', [
        'body' => $raw,
        'headers' => ['Content-Type' => 'application/json', MemberTokens::SIGNATURE_HEADER => $replayHeader],
    ]);
    $first = $replay();
    $again = $replay();

    $refusals = [
        'wrong key' => signInSigned('member-token', $body, 'not-the-key-0123456789abcdefghijklmnopqrstuvwxyz'),
        // Far enough outside the window that a slow request can't bring them back in. The exact edges
        // are checked against a paused clock below.
        'too old' => signInSigned('member-token', $body, timestamp: time() - 3600),
        'from the future' => signInSigned('member-token', $body, timestamp: time() + 3600),
        'body changed after signing' => signInSigned('member-token', $body, signedBody: json_encode(['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_MODERATING])),
        'replayed nonce' => $again,
        'short nonce' => signInSigned('member-token', $body, nonce: 'short'),
        'unsigned' => (new HttpClient())->request('POST', '/actions/comments/api/member-token', ['body' => $raw, 'headers' => ['Content-Type' => 'application/json']]),
    ];

    expect($first['status'])->toBe(200, $first['body']);

    foreach ($refusals as $label => $response) {
        expect($response['status'])->toBe(401, $label . ' ' . $response['body'])
            ->and(signInCode($response))->toBe('signature_invalid', $label)
            // One message for every way it fails, so nothing says why.
            ->and($response['data']['errors'][0]['message'])->toBe($refusals['wrong key']['data']['errors'][0]['message'])
            ->and(signInHeader($response, 'Cache-Control'))->toBe('private, no-store');
    }

    // Over GET, or for an origin that isn't listed for member tokens.
    expect((new HttpClient())->get('/actions/comments/api/member-token')['status'])->toBe(405)
        ->and(signInCode(signInSigned('member-token', ['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_TIER2])))->toBe('origin_not_allowed')
        ->and(signInCode(signInSigned('member-token', ['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_FOREIGN])))->toBe('origin_not_allowed')
        ->and(signInCode(signInSigned('member-token', ['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_TIER3 . '/path'])))->toBe('origin_not_allowed');
});

it('gives unknown and ineligible members the same refusal, behind the signature (enumeration)', function() {
    $suspended = signInUser('signInSuspended');
    Craft::$app->getUsers()->suspendUser($suspended);
    $pending = CommentsFixtureFactory::user('signInPending');
    Db::update(craft\db\Table::USERS, ['pending' => true, 'active' => false], ['id' => $pending->id]);

    $responses = [
        signInSigned('member-token', ['user' => ['uid' => StringHelper::UUID()], 'origin' => SIGN_IN_TIER3]),
        signInSigned('member-token', ['user' => ['email' => 'nobody-' . uniqid() . '@example.test'], 'origin' => SIGN_IN_TIER3]),
        signInSigned('member-token', ['user' => ['uid' => $suspended->uid], 'origin' => SIGN_IN_TIER3]),
        signInSigned('member-token', ['user' => ['uid' => $pending->uid], 'origin' => SIGN_IN_TIER3]),
        signInSigned('member-token', ['user' => ['uid' => $suspended->uid, 'id' => 1], 'origin' => SIGN_IN_TIER3]),
    ];

    foreach ($responses as $response) {
        expect($response['status'])->toBe(403, $response['body'])
            ->and(signInCode($response))->toBe('user_not_eligible')
            ->and($response['data']['errors'][0]['message'])->toBe($responses[0]['data']['errors'][0]['message']);
    }

    // Without a good signature, nothing about users is said at all.
    expect(signInCode(signInSigned('member-token', ['user' => ['uid' => $suspended->uid], 'origin' => SIGN_IN_TIER3], 'wrong-key-0123456789abcdefghijklmnopqrstuvwxyz')))->toBe('signature_invalid');
});

it('signs a member in by token alone, bound to its origin, without CSRF or cookies (token theft, CSRF only for cookies)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in bearer owner');
    $root = signInPost($entry, 'Token target');
    $member = signInUser('signInBearer');
    $someoneElse = signInUser('signInCookieHolder');
    $token = signInMint($member);
    // This browser holds another member's Craft session, which a token request must ignore.
    $browser = signInBrowser($someoneElse);

    $viewer = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer($token));
    $session = $browser->get('/actions/comments/api/session', [], signInBearer($token));
    $created = $browser->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Posted by token']], signInBearer($token));
    $vote = $browser->postJson('/actions/comments/api/vote', ['operationId' => signInOperation(), 'uid' => $root->uid, 'vote' => 'upvote'], signInBearer($token));

    expect($viewer['status'])->toBe(200, $viewer['body'])
        ->and($viewer['data']['viewer'])->toMatchArray(['isGuest' => false, 'hasSession' => true, 'name' => verbb\comments\helpers\CommentsHelper::publicUserName($member)])
        ->and($viewer['data']['csrf'])->toBeNull()
        ->and($viewer['data']['editor']['richEditor']['options']['craft']['csrfToken'] ?? false)->toBeFalse()
        ->and(signInHeader($viewer, 'Set-Cookie'))->toBeNull()
        ->and(signInHeader($viewer, 'Cache-Control'))->toBe('private, no-store')
        ->and(signInHeader($viewer, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER3)
        ->and(signInHeader($viewer, 'Access-Control-Allow-Credentials'))->toBeNull()
        ->and(signInHeader($viewer, 'Vary'))->toContain('Origin')
        ->and($session['data']['csrf'])->toBeNull()
        ->and($session['data']['viewer']['isGuest'])->toBeFalse()
        ->and($created['status'])->toBe(200, $created['body'])
        ->and(signInHeader($created, 'Set-Cookie'))->toBeNull()
        ->and($vote['status'])->toBe(200, $vote['body']);

    $posted = Comment::find()->uid($created['data']['comment']['uid'])->status(null)->one();

    expect((int)$posted->userId)->toBe((int)$member->id)
        ->and((int)$posted->userId)->not->toBe((int)$someoneElse->id);

    // The same token from another listed origin, from no origin, or under a cookie profile isn't a sign-in.
    $elsewhere = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer($token, SIGN_IN_MODERATING));
    $noOrigin = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], ['X-Comments-Profile' => 'cross-origin-member', 'Authorization' => 'Bearer ' . $token]);
    $tierOne = $browser->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Bearer under tier 1']], ['Authorization' => 'Bearer ' . $token]);
    $missing = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], ['Origin' => SIGN_IN_TIER3, 'X-Comments-Profile' => 'cross-origin-member']);
    $garbage = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer('cmt_' . str_repeat('A', 43)));
    $malformed = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer('not-a-token'));

    expect(signInCode($elsewhere))->toBe('origin_not_allowed')
        ->and(signInCode($noOrigin))->toBe('origin_not_allowed')
        // Under tier 1 the token is ignored and Craft's session needs its CSRF token, which wasn't sent.
        ->and(signInCode($tierOne))->toBe('csrf_failed')
        ->and(signInCode($missing))->toBe('sign_in_required')
        ->and($missing['status'])->toBe(401)
        ->and(signInCode($garbage))->toBe('token_revoked')
        ->and(signInCode($malformed))->toBe('sign_in_required')
        ->and(signInHeader($garbage, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER3);

    // A token signs in to the data API only: Craft's own actions don't know it.
    $craftSession = signInBrowser()->get('/actions/users/session-info', [], signInBearer($token));

    expect($craftSession['data']['isGuest'] ?? null)->toBeTrue($craftSession['body']);

    // A token request that's refused still never falls back to the cookie that came with it.
    $noToken = $browser->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Cookie only']], ['Origin' => SIGN_IN_TIER3, 'X-Comments-Profile' => 'cross-origin-member']);

    expect(signInCode($noToken))->toBe('sign_in_required')
        ->and((int)Comment::find()->ownerId($entry->id)->userId($someoneElse->id)->status(null)->count())->toBe(0);
});

it('stops a token once it runs out, is revoked, or its member is suspended or changes their password (token theft)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in revoked owner');
    $member = signInUser('signInRevoked');
    $browser = signInBrowser();
    $read = static fn(string $token): array => $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer($token));

    $expiring = signInMint($member);
    Db::update(MemberTokens::TABLE, ['dateExpires' => Db::prepareDateForDb(new DateTime('-1 minute'))], ['tokenHash' => hash('sha256', $expiring)]);
    $expired = $read($expiring);

    expect($expired['status'])->toBe(401)
        ->and(signInCode($expired))->toBe('token_expired')
        ->and($expired['data']['errors'][0]['recoverable'])->toBeTrue();

    // Signing out on the other site revokes every token the member has.
    $first = signInMint($member);
    $second = signInMint($member);
    $revoke = signInSigned('revoke-member-tokens', ['user' => ['uid' => $member->uid]]);

    expect($revoke['status'])->toBe(200, $revoke['body'])
        // Including the one that had already run out.
        ->and($revoke['data']['revoked'])->toBe(3)
        ->and(signInCode($read($first)))->toBe('token_revoked')
        ->and(signInCode($read($second)))->toBe('token_revoked')
        ->and(signInCode(signInSigned('revoke-member-tokens', ['user' => ['uid' => $member->uid]], 'wrong-key-0123456789abcdefghijklmnopqrstuvwxyz')))->toBe('signature_invalid');

    // So does the console command, for one member or for everyone.
    $third = signInMint($member);
    $controller = new MemberTokensController('member-tokens', Comments::$plugin);
    ob_start();
    $exit = $controller->actionRevoke((string)$member->username);
    ob_end_clean();

    expect($exit)->toBe(0)
        ->and(signInCode($read($third)))->toBe('token_revoked');

    $fourth = signInMint($member);
    $controller->all = true;
    ob_start();
    $controller->actionRevoke();
    ob_end_clean();

    expect(signInCode($read($fourth)))->toBe('token_revoked');

    // A password change after minting signs the member out at once.
    $fifth = signInMint($member);
    expect($read($fifth)['status'])->toBe(200);
    Db::update(craft\db\Table::USERS, ['lastPasswordChangeDate' => Db::prepareDateForDb(new DateTime('+1 minute'))], ['id' => $member->id]);
    expect(signInCode($read($fifth)))->toBe('token_revoked');
    Db::update(craft\db\Table::USERS, ['lastPasswordChangeDate' => null], ['id' => $member->id]);

    // As does a suspension.
    $sixth = signInMint($member);
    Craft::$app->getUsers()->suspendUser(Craft::$app->getUsers()->getUserById((int)$member->id));
    expect(signInCode($read($sixth)))->toBe('token_revoked')
        ->and((int)(new craft\db\Query())->from(MemberTokens::TABLE)->where(['userId' => $member->id, 'dateRevoked' => null])->count())->toBe(0);

    // Removing the origin from Allowed Origins ends its tokens too.
    $other = signInUser('signInOriginRemoved');
    $token = signInMint($other);
    signInApplySettings(['allowedOrigins' => array_values(array_filter(signInSettings()['allowedOrigins'], static fn(array $row): bool => $row['origin'] !== SIGN_IN_TIER3))]);

    try {
        expect(signInCode($read($token)))->toBe('origin_not_allowed');
    } finally {
        signInApplySettings(['allowedOrigins' => signInSettings()['allowedOrigins']]);
    }
});

it('lets a member token moderate only from an origin that allows it', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in moderation owner');
    $held = signInPost($entry, 'Held for review', signInUser('signInHeldAuthor'), held: true);
    $moderator = signInUser('signInModerator', ['comments-view', 'comments-moderate', 'comments-trash']);
    $browser = signInBrowser();

    $limited = signInMint($moderator);
    $viewer = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer($limited));
    $refused = $browser->postJson('/actions/comments/api/moderate', ['operationId' => signInOperation(), 'uid' => $held->uid, 'action' => 'approve'], signInBearer($limited));

    expect($viewer['status'])->toBe(200, $viewer['body'])
        ->and($viewer['data']['viewer']['isModerator'])->toBeFalse()
        ->and($viewer['data']['extraComments'])->toBe([])
        ->and($viewer['data']['moderation'])->toBeNull()
        ->and($refused['status'])->toBe(403)
        ->and(signInCode($refused))->toBe('forbidden');

    $allowed = signInMint($moderator, SIGN_IN_MODERATING);
    $moderatorView = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer($allowed, SIGN_IN_MODERATING));
    $approved = $browser->postJson('/actions/comments/api/moderate', ['operationId' => signInOperation(), 'uid' => $held->uid, 'action' => 'approve'], signInBearer($allowed, SIGN_IN_MODERATING));

    expect($moderatorView['data']['viewer']['isModerator'])->toBeTrue()
        ->and(array_column($moderatorView['data']['extraComments'], 'uid'))->toContain($held->uid)
        ->and($approved['status'])->toBe(200, $approved['body'])
        ->and($approved['data']['comment']['state'])->toBe('approved');
});

// Guests on another site
// =========================================================================

it('gives a guest on another site a session token that is theirs alone (guest hijack)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in guest token owner');
    $root = signInPost($entry, 'Guests reply here');
    signInApplySettings(['requireModeration' => true]);

    try {
        $guest = signInBrowser();

        $slim = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders());
        $tooSoon = $guest->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'No session yet']], signInGuestHeaders());
        $session = $guest->get('/actions/comments/api/session', [], signInGuestHeaders());
        $token = (string)signInHeader($session, 'X-Comments-Session');

        expect($slim['status'])->toBe(200, $slim['body'])
            ->and($slim['data']['viewer']['hasSession'])->toBeFalse()
            ->and(signInHeader($slim, 'X-Comments-Session'))->toBeNull()
            ->and(signInHeader($slim, 'Set-Cookie'))->toBeNull()
            ->and($tooSoon['status'])->toBe(401)
            ->and(signInCode($tooSoon))->toBe('session_expired')
            ->and($session['status'])->toBe(200, $session['body'])
            ->and($session['data']['csrf'])->toBeNull()
            ->and($session['data']['viewer']['isGuest'])->toBeTrue()
            ->and($token)->toMatch('/^[A-Za-z0-9_-]{40,}$/')
            ->and($token)->not->toContain('.')
            ->and(signInHeader($session, 'Access-Control-Expose-Headers'))->toContain('X-Comments-Session')
            ->and(signInHeader($session, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_GUESTS)
            ->and(signInHeader($session, 'Set-Cookie'))->toBeNull()
            ->and($guest->cookieNames())->toBe([]);

        // Posting needs no CSRF token: the guest is whoever their session token says.
        $body = ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'parentUid' => $root->uid, 'content' => ['format' => 'plain', 'text' => 'Waiting from elsewhere'], 'guest' => ['name' => 'Remote Guest']];
        $created = $guest->postJson('/actions/comments/api/create', $body, signInGuestHeaders($token));
        $replayed = $guest->postJson('/actions/comments/api/create', $body, signInGuestHeaders($token));
        $refreshed = (string)signInHeader($created, 'X-Comments-Session');
        $mine = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders($refreshed));

        expect($created['status'])->toBe(200, $created['body'])
            ->and($created['data']['moderationState'])->toBe('pending')
            ->and($refreshed)->not->toBe('')
            ->and($refreshed)->not->toBe($token)
            ->and(signInHeader($created, 'Set-Cookie'))->toBeNull()
            ->and($replayed['data']['replayed'])->toBeTrue()
            ->and($replayed['data']['comment']['uid'])->toBe($created['data']['comment']['uid'])
            ->and($mine['data']['viewer']['hasSession'])->toBeTrue()
            ->and(array_column($mine['data']['extraComments'], 'uid'))->toContain($created['data']['comment']['uid']);

        $identity = Comments::$plugin->getIdentities()->getById((int)Comment::find()->uid($created['data']['comment']['uid'])->status(null)->one()->identityId);
        expect($identity->name)->toBe('Remote Guest')
            ->and($identity->userId)->toBeNull();

        // Another guest, a cookie guest, a stolen token used from elsewhere, or a changed or expired
        // one sees none of it.
        $otherSession = (string)signInHeader($guest->get('/actions/comments/api/session', [], signInGuestHeaders()), 'X-Comments-Session');
        $other = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders($otherSession));
        $cookieGuest = signInBrowser();
        $cookieGuest->get('/actions/comments/api/session');
        $movedOrigin = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders($token, SIGN_IN_TIER3));
        $tampered = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders(substr($token, 0, -4) . 'AAAA'));
        $expiredToken = Comments::$plugin->getSignIn()->guestSessionToken('expired-secret-' . uniqid(), SIGN_IN_GUESTS, time() - 1);
        $expired = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders($expiredToken));

        expect(array_column($other['data']['extraComments'], 'uid'))->not->toContain($created['data']['comment']['uid'])
            ->and(array_column($cookieGuest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id])['data']['extraComments'] ?? [], 'uid'))->not->toContain($created['data']['comment']['uid'])
            ->and(signInCode($movedOrigin))->toBe('origin_not_allowed')
            ->and(signInCode($tampered))->toBe('session_expired')
            ->and(signInCode($expired))->toBe('session_expired')
            ->and($expired['status'])->toBe(401);

        // An expired session is replaced rather than refused by the session read.
        $renewed = $guest->get('/actions/comments/api/session', [], signInGuestHeaders($expiredToken));
        expect($renewed['status'])->toBe(200)
            ->and(signInHeader($renewed, 'X-Comments-Session'))->toBeString();

        // A token unlocks nothing a cookie guest couldn't do.
        $comment = Comment::find()->uid($created['data']['comment']['uid'])->status(null)->one();
        $edit = $guest->postJson('/actions/comments/api/update', ['operationId' => signInOperation(), 'uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Changed']], signInGuestHeaders($refreshed));
        $moderate = $guest->postJson('/actions/comments/api/moderate', ['operationId' => signInOperation(), 'uid' => $root->uid, 'action' => 'spam'], signInGuestHeaders($refreshed));
        $mention = $guest->get('/actions/comments/api/mentions', ['context' => 'comments.reply', 'query' => 'Te', 'scope' => ['ownerId' => $entry->id, 'siteId' => $entry->siteId]], signInGuestHeaders($refreshed));

        expect(signInCode($edit))->toBe('sign_in_required')
            ->and(signInCode($moderate))->toBe('sign_in_required')
            ->and(signInCode($mention))->toBe('sign_in_required');
    } finally {
        signInApplySettings(['requireModeration' => false]);
    }
});

it('never reads a guest cookie or Craft session for a guest on another site', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in guest cookie owner');
    signInApplySettings(['requireModeration' => true]);

    try {
        // A guest who posted on Craft's own pages has a guest cookie and Craft's session.
        $guest = signInBrowser();
        $csrf = signInCsrf($guest);
        $posted = $guest->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Cookie guest waiting']], csrfToken: $csrf);

        expect($posted['status'])->toBe(200, $posted['body'])
            ->and($guest->cookieNames())->toContain('comments_guest_token');

        $asRemote = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInGuestHeaders());

        expect($asRemote['data']['viewer']['hasSession'])->toBeFalse()
            ->and($asRemote['data']['extraComments'])->toBe([]);
    } finally {
        signInApplySettings(['requireModeration' => false]);
    }
});

// Editor's mentions and uploads
// =========================================================================

it('runs Editor’s mention search through each tier, with CSRF only for cookie sign-ins', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in mentions owner');
    $participant = signInUser('signInMentionable');
    signInPost($entry, 'Mention me', $participant);
    $member = signInUser('signInMentioner');
    $browser = signInBrowser($member);
    $scope = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId];
    $editorBody = ['context' => 'comments.reply', 'scope' => $scope, 'query' => 'Test'];
    $csrf = signInCsrf($browser);

    $tierOnePost = $browser->postJson('/actions/comments/api/mentions', $editorBody, csrfToken: $csrf);
    $tierOneNoCsrf = $browser->postJson('/actions/comments/api/mentions', $editorBody);
    $tierOneGet = $browser->get('/actions/comments/api/mentions', $editorBody);
    $tier2 = ['Origin' => SIGN_IN_TIER2, 'X-Comments-Profile' => 'same-site-credentialed'];
    $tierTwo = $browser->postJson('/actions/comments/api/mentions', $editorBody, $tier2, $csrf);
    $token = signInMint($member);
    $tierThree = signInBrowser()->postJson('/actions/comments/api/mentions', $editorBody, signInBearer($token));
    $tierThreeGet = signInBrowser()->get('/actions/comments/api/mentions', $editorBody, signInBearer($token));
    $guest = signInBrowser()->get('/actions/comments/api/mentions', $editorBody);
    $foreign = $browser->postJson('/actions/comments/api/mentions', $editorBody, ['Origin' => SIGN_IN_FOREIGN], $csrf);
    $elsewhere = $browser->postJson('/actions/comments/api/mentions', [...$editorBody, 'scope' => ['ownerId' => 999999999, 'siteId' => $entry->siteId]], csrfToken: $csrf);

    foreach (['tier 1' => $tierOnePost, 'tier 1 GET' => $tierOneGet, 'tier 2' => $tierTwo, 'tier 3' => $tierThree, 'tier 3 GET' => $tierThreeGet] as $label => $response) {
        expect($response['status'])->toBe(200, $label . ' ' . $response['body'])
            ->and(array_column($response['data']['results'], 'id'))->toContain((int)$participant->id)
            ->and(signInHeader($response, 'Cache-Control'))->toBe('private, no-store');
    }

    expect(signInCode($tierOneNoCsrf))->toBe('csrf_failed')
        ->and(signInHeader($tierTwo, 'Access-Control-Allow-Credentials'))->toBe('true')
        ->and(signInHeader($tierThree, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER3)
        ->and(signInCode($guest))->toBe('sign_in_required')
        // Editor reads `message` from a refusal.
        ->and($guest['data']['message'])->toBe($guest['data']['errors'][0]['message'])
        ->and(signInCode($foreign))->toBe('origin_not_allowed')
        ->and($elsewhere['status'])->toBe(403)
        ->and(signInCode($elsewhere))->toBe('forbidden');
});

it('takes uploads through each tier under Comments’ rules, and never from a guest (upload abuse)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in uploads owner');
    $member = signInUser('signInUploader');
    $browser = signInBrowser($member);
    $csrf = signInCsrf($browser);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

    $tierOne = signInUpload($browser, 'pixel.png', $png, [], $entry, 'inlineImage', $csrf);
    $tierOneNoCsrf = signInUpload($browser, 'pixel.png', $png, [], $entry, 'inlineImage');
    $tierTwo = signInUpload($browser, 'pixel.png', $png, ['Origin' => SIGN_IN_TIER2, 'X-Comments-Profile' => 'same-site-credentialed'], $entry, 'inlineImage', $csrf);
    $token = signInMint($member);
    $tierThree = signInUpload(signInBrowser(), 'notes.txt', 'Plain notes', signInBearer($token), $entry);
    $cookieGuest = signInBrowser();
    $guestCsrf = signInCsrf($cookieGuest);
    $guest = signInUpload($cookieGuest, 'pixel.png', $png, [], $entry, 'inlineImage', $guestCsrf);
    $remoteGuest = signInBrowser();
    $remoteSession = (string)signInHeader($remoteGuest->get('/actions/comments/api/session', [], signInGuestHeaders()), 'X-Comments-Session');
    $tokenGuest = signInUpload($remoteGuest, 'pixel.png', $png, signInGuestHeaders($remoteSession), $entry, 'inlineImage');

    foreach (['tier 1' => $tierOne, 'tier 2' => $tierTwo, 'tier 3' => $tierThree] as $label => $response) {
        expect($response['status'])->toBe(200, $label . ' ' . $response['body'])
            ->and($response['data']['asset']['temporary'])->toBeTrue()
            ->and($response['data']['asset']['id'])->toBeInt()
            ->and(signInHeader($response, 'Cache-Control'))->toBe('private, no-store');
    }

    expect(signInCode($tierOneNoCsrf))->toBe('csrf_failed')
        ->and(signInHeader($tierThree, 'Set-Cookie'))->toBeNull()
        ->and((int)Asset::find()->id($tierThree['data']['asset']['id'])->status(null)->one()->uploaderId)->toBe((int)$member->id)
        ->and(signInCode($guest))->toBe('sign_in_required')
        ->and(signInCode($tokenGuest))->toBe('sign_in_required')
        ->and(signInCode($browser->get('/actions/comments/api/upload')))->toBe('method_not_allowed');

    // Refused whatever the setting allows: SVG, HTML and XML, scripts, and files that aren't what
    // their name says.
    $refusals = [
        'svg' => ['drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
        'html' => ['page.html', '<!doctype html><html><body><script>alert(1)</script></body></html>'],
        'xml' => ['data.xml', '<?xml version="1.0"?><root><item>1</item></root>'],
        'script' => ['app.js', 'alert(document.cookie);'],
        'page as an image' => ['photo.jpg', '<!doctype html><html><body><script>alert(1)</script></body></html>'],
        'svg as an image' => ['photo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
        'image as a document' => ['report.pdf', $png],
        'text as a document' => ['report.pdf', 'Not really a PDF'],
        'not allowed at all' => ['archive.zip', "PK\x03\x04" . str_repeat("\0", 26)],
    ];

    foreach ($refusals as $label => [$name, $contents]) {
        $response = signInUpload($browser, $name, $contents, [], $entry, 'attachment', $csrf);

        expect($response['status'])->toBe(422, $label . ' ' . $response['body'])
            ->and(signInCode($response))->toBe('file_type_not_allowed', $label)
            ->and($response['data']['errors'][0]['category'])->toBe('validation');
    }

    $tooLarge = Comments::$plugin->getSettings()->attachmentMaxSize;
    signInApplySettings(['attachmentMaxSize' => 1]);
    verbb\comments\helpers\EditorContexts::refresh();

    try {
        $large = signInUpload($browser, 'large.txt', str_repeat('x', 1024 * 1024 + 1), [], $entry, 'attachment', $csrf);

        expect($large['status'])->toBe(422, $large['body'])
            ->and(signInCode($large))->toBe('file_too_large');
    } finally {
        signInApplySettings(['attachmentMaxSize' => $tooLarge]);
    }

    // Editor offers none of those extensions, even though the setting lists them.
    $options = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id])['data']['editor']['richEditor']['options']['craft'];

    expect($options['endpoints']['upload'])->toContain('comments/api/upload')
        ->and($options['endpoints']['mentions'])->toContain('comments/api/mentions')
        ->and($options['uploads']['attachmentExtensions'])->not->toContain('svg')->not->toContain('html')->not->toContain('xml')->not->toContain('js')
        ->and($options['uploads']['attachmentExtensions'])->toContain('pdf');
});

it('removes where a photo was taken before storing it', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in location owner');
    $member = signInUser('signInPhotographer');
    $browser = signInBrowser($member);
    $csrf = signInCsrf($browser);
    $jpeg = signInJpegWithLocation();
    $original = tempnam(sys_get_temp_dir(), 'signin-gps-') . '.jpg';
    file_put_contents($original, $jpeg);

    try {
        expect(@exif_read_data($original, 'GPS'))->toBeArray()
            ->and(@exif_read_data($original)['GPSLatitudeRef'] ?? null)->toBe('N');

        $response = signInUpload($browser, 'holiday.jpg', $jpeg, [], $entry, 'inlineImage', $csrf);

        expect($response['status'])->toBe(200, $response['body']);

        $asset = Asset::find()->id($response['data']['asset']['id'])->status(null)->one();
        $stored = $asset->getCopyOfFile();
        $contents = (string)file_get_contents($stored);

        expect(@getimagesize($stored))->toBeArray()
            ->and(@exif_read_data($stored, 'GPS'))->toBeFalse()
            ->and($contents)->not->toContain('Exif')
            ->and($contents)->not->toContain('<script>');
    } finally {
        @unlink($original);
    }
});

// Direct checks
// =========================================================================

it('keeps origins exact', function() {
    expect(AllowedOrigin::normalize('https://WWW.Example.com'))->toBe('https://www.example.com')
        ->and(AllowedOrigin::normalize('https://www.example.com:443'))->toBe('https://www.example.com')
        ->and(AllowedOrigin::normalize('http://localhost:3000'))->toBe('http://localhost:3000')
        ->and(AllowedOrigin::normalize('https://www.example.com/'))->toBe('https://www.example.com')
        ->and(AllowedOrigin::normalize('https://*.example.com'))->toBeNull()
        ->and(AllowedOrigin::normalize('*'))->toBeNull()
        ->and(AllowedOrigin::normalize('https://www.example.com/path'))->toBeNull()
        ->and(AllowedOrigin::normalize('https://user:pass@www.example.com'))->toBeNull()
        ->and(AllowedOrigin::normalize('ftp://www.example.com'))->toBeNull()
        ->and(AllowedOrigin::normalize('null'))->toBeNull()
        ->and(AllowedOrigin::normalize('www.example.com'))->toBeNull();
});

it('accepts keys only from environment variables, and origins only when exact', function() {
    putenv('COMMENTS_SIGNIN_SHORT_KEY=too-short');
    $settings = new Settings(signInSettings());

    try {
        expect($settings->validate(['memberTokenKey', 'memberTokenPreviousKey', 'allowedOrigins']))->toBeTrue(json_encode($settings->getErrors()))
            ->and($settings->getMemberTokenKeys())->toBe([SIGN_IN_KEY, SIGN_IN_PREVIOUS_KEY])
            ->and(array_keys($settings->getAllowedOrigins()))->toBe([SIGN_IN_TIER2, SIGN_IN_TIER3, SIGN_IN_MODERATING, SIGN_IN_GUESTS, SIGN_IN_FROM_ENV])
            ->and($settings->getAllowedOrigins()[SIGN_IN_TIER2]->credentials)->toBeTrue()
            ->and($settings->getAllowedOrigins()[SIGN_IN_TIER3]->moderation)->toBeFalse();

        $literal = new Settings(['memberTokenKey' => SIGN_IN_KEY]);
        $short = new Settings(['memberTokenKey' => '$COMMENTS_SIGNIN_SHORT_KEY']);
        $unset = new Settings(['memberTokenKey' => '$COMMENTS_SIGNIN_UNSET_KEY']);

        expect($literal->validate(['memberTokenKey']))->toBeFalse()
            ->and($short->validate(['memberTokenKey']))->toBeFalse()
            ->and($short->getMemberTokenKeys())->toBe([])
            ->and($unset->validate(['memberTokenKey']))->toBeTrue()
            ->and($unset->getMemberTokenKeys())->toBe([]);

        foreach ([
            [['origin' => 'https://*.example.com']],
            [['origin' => 'https://www.example.com/blog']],
            [['origin' => 'https://www.example.com'], ['origin' => 'https://WWW.example.com:443']],
            [['origin' => 'https://www.example.com', 'protectionSiteKey' => 'site-key']],
            [['origin' => 'https://www.example.com', 'protectionSiteKey' => 'site-key', 'protectionSecretKey' => 'literal-secret']],
        ] as $rows) {
            $invalid = new Settings(['allowedOrigins' => $rows]);

            expect($invalid->validate(['allowedOrigins']))->toBeFalse(json_encode($rows));
        }

        // A config file mistake leaves the bad row out rather than opening anything up.
        expect(array_keys((new Settings(['allowedOrigins' => [['origin' => '*', 'credentials' => true], ['origin' => 'https://ok.example.com']]]))->getAllowedOrigins()))->toBe(['https://ok.example.com']);
    } finally {
        putenv('COMMENTS_SIGNIN_SHORT_KEY');
    }
});

it('uses an origin’s own challenge keys and hostname for its requests (per-origin protection keys)', function() {
    putenv('COMMENTS_SIGNIN_TURNSTILE_SITE=origin-site-key');
    putenv('COMMENTS_SIGNIN_TURNSTILE_SECRET=origin-secret-key');
    $settings = new Settings([
        'enableSpamChecks' => true,
        'challengeProvider' => Settings::CHALLENGE_TURNSTILE,
        'turnstileKey' => 'site-wide-key',
        'turnstileSecret' => 'site-wide-secret',
    ]);
    $challenge = static function(?AllowedOrigin $origin) use ($settings): object {
        $plan = Comments::$plugin->getProtection()->plan((int)Craft::$app->getSites()->getPrimarySite()->id, false, settings: $settings, origin: $origin);

        foreach ($plan->checks() as $check) {
            if ($check instanceof verbb\protect\checks\Challenge) {
                return $check->provider;
            }
        }

        throw new RuntimeException('No challenge in the plan.');
    };

    try {
        $default = $challenge(null);
        $shared = $challenge(new AllowedOrigin(['origin' => SIGN_IN_TIER3]));
        $own = $challenge(new AllowedOrigin(['origin' => SIGN_IN_GUESTS, 'protectionSiteKey' => '$COMMENTS_SIGNIN_TURNSTILE_SITE', 'protectionSecretKey' => '$COMMENTS_SIGNIN_TURNSTILE_SECRET']));
        $siteHost = parse_url((string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), PHP_URL_HOST);

        expect($default->siteKey)->toBe('site-wide-key')
            ->and($default->hostnames)->toBe([$siteHost])
            ->and($shared->siteKey)->toBe('site-wide-key')
            ->and($shared->hostnames)->toBe([$siteHost, 'app.signin-elsewhere.test'])
            ->and($own->siteKey)->toBe('origin-site-key')
            ->and($own->secretKey)->toBe('origin-secret-key')
            ->and($own->hostnames)->toBe(['guests.signin-elsewhere.test']);
    } finally {
        putenv('COMMENTS_SIGNIN_TURNSTILE_SITE');
        putenv('COMMENTS_SIGNIN_TURNSTILE_SECRET');
    }
});

it('limits moderation by token to the member it signed in', function() {
    // Admins, so Craft's permission cache from creating them doesn't matter here.
    $moderator = signInUser('signInDirectModerator');
    $moderator->admin = true;
    $colleague = signInUser('signInDirectColleague');
    $colleague->admin = true;
    $signIn = Comments::$plugin->getSignIn();
    $moderation = Comments::$plugin->getModeration();
    $current = new RequestSignIn([
        'profile' => RequestProfile::CrossOriginMember,
        'origin' => SIGN_IN_TIER3,
        'allowedOrigin' => new AllowedOrigin(['origin' => SIGN_IN_TIER3, 'memberTokens' => true]),
        'user' => $moderator,
    ]);
    $property = new ReflectionProperty($signIn, '_current');
    $property->setValue($signIn, $current);

    try {
        // Checks about anyone else, such as who to tell about a new comment, aren't affected.
        expect($moderation->canModerateComment($moderator))->toBeFalse()
            ->and($moderation->canRemoveComment($moderator))->toBeFalse()
            ->and($moderation->canModerateComment($colleague))->toBeTrue()
            ->and($signIn->allowsModeration())->toBeFalse();

        $current->allowedOrigin->moderation = true;

        expect($moderation->canModerateComment($moderator))->toBeTrue()
            ->and($signIn->allowsModeration())->toBeTrue();
    } finally {
        $signIn->reset();
    }
});

it('removes expired tokens and old nonces in garbage collection', function() {
    $member = signInUser('signInCollected');
    $old = Db::prepareDateForDb(new DateTime('-2 days'));
    Db::insert(MemberTokens::TABLE, ['tokenHash' => hash('sha256', 'old-' . uniqid()), 'userId' => $member->id, 'origin' => SIGN_IN_TIER3, 'dateExpires' => $old]);
    Db::insert(MemberTokens::TABLE, ['tokenHash' => hash('sha256', 'fresh-' . uniqid()), 'userId' => $member->id, 'origin' => SIGN_IN_TIER3, 'dateExpires' => Db::prepareDateForDb(new DateTime('+10 minutes'))]);
    Db::insert(MemberTokens::NONCE_TABLE, ['nonceHash' => hash('sha256', 'old-nonce-' . uniqid()), 'dateCreated' => Db::prepareDateForDb(new DateTime('-11 minutes'))]);
    Db::insert(MemberTokens::NONCE_TABLE, ['nonceHash' => hash('sha256', 'fresh-nonce-' . uniqid()), 'dateCreated' => Db::prepareDateForDb(new DateTime())]);

    // Only garbage collection's event, which Comments listens to: a full forced run would also purge
    // what other tests still use.
    Craft::$app->getGc()->trigger(craft\services\Gc::EVENT_RUN);

    expect((int)(new craft\db\Query())->from(MemberTokens::TABLE)->where(['userId' => $member->id])->count())->toBe(1)
        ->and((int)(new craft\db\Query())->from(MemberTokens::NONCE_TABLE)->where(['<', 'dateCreated', Db::prepareDateForDb(new DateTime('-10 minutes'))])->count())->toBe(0)
        ->and((int)(new craft\db\Query())->from(MemberTokens::NONCE_TABLE)->count())->toBeGreaterThan(0);
});

it('applies the upload rules however an upload reaches Editor, including its own endpoint and service', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in direct upload owner');
    $member = signInUser('signInDirectUploader');
    $identity = Craft::$app->getUser()->getIdentity();
    Craft::$app->getUser()->setIdentity($member);
    $editor = verbb\editor\Editor::getInstance();
    $context = $editor->getEditorConfigs()->requireConfigByHandle('comments.reply');
    $file = static function(string $name, string $contents): yii\web\UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'signin-direct-');
        file_put_contents($path, $contents);

        return new yii\web\UploadedFile(['name' => $name, 'tempName' => $path, 'type' => 'image/png', 'size' => strlen($contents), 'error' => UPLOAD_ERR_OK]);
    };

    try {
        // The context's own list never holds them, and a list that somehow did still refuses them.
        expect($context->assets->allowedExtensions)->not->toContain('svg')->not->toContain('html');
        $context = clone $context;
        $context->assets = clone $context->assets;
        $context->assets->allowedExtensions = ['svg', 'html', 'png'];

        expect(fn() => $editor->getAssets()->upload($file('drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), $context, 'attachment'))
            ->toThrow(verbb\comments\exceptions\UploadRefusedException::class)
            ->and(fn() => $editor->getAssets()->upload($file('page.html', '<html><body>Hi</body></html>'), $context, 'attachment'))
            ->toThrow(verbb\comments\exceptions\UploadRefusedException::class)
            ->and(fn() => $editor->getAssets()->upload($file('pixel.png', '<svg xmlns="http://www.w3.org/2000/svg"/>'), $context, 'attachment'))
            ->toThrow(verbb\comments\exceptions\UploadRefusedException::class);
    } finally {
        Craft::$app->getUser()->setIdentity($identity);
    }
});

it('renders the Other Sites settings', function() {
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode($view::TEMPLATE_MODE_CP);

    try {
        $html = $view->renderTemplate('comments/settings/_panes/sites', ['settings' => new Settings(signInSettings())]);
    } finally {
        $view->setTemplateMode($mode);
    }

    expect($html)->toContain('allowedOrigins')
        ->toContain(SIGN_IN_TIER2)
        ->toContain('Member Token Key')
        ->toContain('Previous Member Token Key')
        ->toContain('COMMENTS_SIGNIN_TEST_KEY')
        ->not->toContain(SIGN_IN_KEY)
        ->and(verbb\comments\helpers\SettingsTabs::isPane('sites'))->toBeTrue();
});

// Review fixes
// =========================================================================

/**
 * Fill this address's rate limit for `$bucket` in the web app's cache, for this minute and the next. The
 * web app shares the cache folder but has Craft's default app ID, which prefixes its keys.
 */
function signInExhaust(string $bucket, int $limit): void
{
    $cache = clone Craft::$app->getCache();
    $cache->keyPrefix = 'CraftCMS';

    foreach ([0, 60] as $offset) {
        $cache->set('comments:api:rate:' . $bucket . ':' . hash('sha256', '127.0.0.1') . ':' . intdiv(time() + $offset, 60), $limit, 120);
    }
}

it('holds a moderator’s comments for review through a token from an origin without moderation (M1)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in moderator exemption owner');
    $group = new craft\models\UserGroup(['name' => 'Sign-in moderators', 'handle' => 'signInModerators' . bin2hex(random_bytes(3))]);
    Craft::$app->getUserGroups()->saveGroup($group);
    $moderator = signInUser('signInExemptModerator');
    Craft::$app->getUsers()->assignUserToGroups((int)$moderator->id, [(int)$group->id]);
    signInApplySettings(['requireModeration' => true, 'moderatorExcluded' => true, 'moderatorUserGroup' => $group->uid]);

    try {
        $browser = signInBrowser();
        $post = static fn(string $origin, string $text): array => $browser->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => $text]], signInBearer(signInMint($moderator, $origin), $origin));

        $held = $post(SIGN_IN_TIER3, 'From an origin without moderation');
        $exempt = $post(SIGN_IN_MODERATING, 'From an origin with moderation');

        expect($held['status'])->toBe(200, $held['body'])
            ->and($held['data']['moderationState'])->toBe('pending')
            ->and($exempt['data']['moderationState'])->toBe('approved', $exempt['body']);

        // Editing the approved one from the origin without moderation sends it back for review.
        $edited = $browser->postJson('/actions/comments/api/update', ['operationId' => signInOperation(), 'uid' => $exempt['data']['comment']['uid'], 'content' => ['format' => 'plain', 'text' => 'Edited without moderation']], signInBearer(signInMint($moderator), SIGN_IN_TIER3));

        expect($edited['status'])->toBe(200, $edited['body'])
            ->and($edited['data']['moderationState'])->toBe('pending');
    } finally {
        signInApplySettings(['requireModeration' => false, 'moderatorExcluded' => true, 'moderatorUserGroup' => null]);
        Craft::$app->getUserGroups()->deleteGroup($group);
    }
});

it('keeps the author edit window for staff signed in by a token from an origin without moderation (L2)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in edit window owner');
    $staff = signInUser('signInWindowStaff', ['comments-view', 'comments-edit']);
    $comment = signInPost($entry, 'Old enough', $staff);
    Db::update('{{%comments_comments}}', ['commentDate' => Db::prepareDateForDb(new DateTime('-1 hour'))], ['id' => $comment->id]);
    signInApplySettings(['authorEditWindow' => 5]);

    try {
        $browser = signInBrowser();
        $edit = static fn(string $origin): array => $browser->postJson('/actions/comments/api/update', ['operationId' => signInOperation(), 'uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Late edit from ' . $origin]], signInBearer(signInMint($staff, $origin), $origin));

        $refused = $edit(SIGN_IN_TIER3);
        $allowed = $edit(SIGN_IN_MODERATING);

        expect($refused['status'])->toBe(403, $refused['body'])
            ->and(signInCode($refused))->toBe('edit_window_passed')
            ->and($allowed['status'])->toBe(200, $allowed['body']);
    } finally {
        signInApplySettings(['authorEditWindow' => null]);
    }
});

it('rechecks at finalising a file uploaded through another Editor context, refusing one changed since (L1)', function() {
    $member = signInUser('signInOtherContext');
    $identity = Craft::$app->getUser()->getIdentity();
    Craft::$app->getUser()->setIdentity($member);
    $editor = verbb\editor\Editor::getInstance();
    $comments = $editor->getEditorConfigs()->requireConfigByHandle('comments.reply');
    $other = new verbb\editor\models\EditorConfig([
        'handle' => 'signin.other',
        'features' => ['image' => true, 'attachment' => true],
        'assets' => new verbb\editor\models\AssetConfig(['enabled' => true, 'volumeId' => $comments->assets->volumeId, 'allowedExtensions' => ['jpg', 'png']]),
    ]);
    $editor->getEditorConfigs()->registerConfig($other, true);
    $file = static function(string $name, string $contents): yii\web\UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'signin-finalise-');
        file_put_contents($path, $contents);

        return new yii\web\UploadedFile(['name' => $name, 'tempName' => $path, 'type' => 'image/jpeg', 'size' => strlen($contents), 'error' => UPLOAD_ERR_OK]);
    };

    try {
        // Editor cleans every upload, whichever context it came through, so this one passes the recheck.
        $elsewhere = $editor->getAssets()->upload($file('located.jpg', signInJpegWithLocation()), $other, 'inlineImage');
        $stored = Asset::find()->id($elsewhere['id'])->status(null)->one();

        expect($stored->getContents())->not->toContain("Exif\0\0")->not->toContain('<script>');

        // A temporary file changed after it was uploaded is refused, because the rules would change it.
        $changed = $editor->getAssets()->upload($file('changed.jpg', signInJpegWithLocation()), $other, 'inlineImage');
        $changedAsset = Asset::find()->id($changed['id'])->status(null)->one();
        $changedAsset->getFs()->write($changedAsset->getPath(), signInJpegWithLocation());
        $through = $editor->getAssets()->upload($file('clean.jpg', signInJpegWithLocation()), $comments, 'inlineImage');

        expect(fn() => $editor->getAssets()->finalize([['id' => $changed['id'], 'kind' => 'inlineImage']], $comments, (int)$member->id))
            ->toThrow(verbb\comments\exceptions\UploadRefusedException::class)
            ->and($editor->getAssets()->finalize([['id' => $elsewhere['id'], 'kind' => 'inlineImage']], $comments, (int)$member->id)[0]['temporary'])->toBeFalse()
            ->and($editor->getAssets()->finalize([['id' => $through['id'], 'kind' => 'inlineImage']], $comments, (int)$member->id)[0]['temporary'])->toBeFalse();
    } finally {
        Craft::$app->getUser()->setIdentity($identity);
    }
});

it('applies the upload rules through Editor’s own upload action over HTTP', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in Editor action owner');
    $member = signInUser('signInEditorAction');
    $browser = signInBrowser($member);
    $csrf = signInCsrf($browser);
    $response = $browser->request('POST', '/actions/verbb-editor/assets/upload', [
        'headers' => ['X-CSRF-Token' => $csrf],
        'multipart' => [
            ['name' => 'file', 'contents' => signInJpegWithLocation(), 'filename' => 'holiday.jpg'],
            ['name' => 'kind', 'contents' => 'inlineImage'],
            ['name' => 'context', 'contents' => 'comments.reply'],
            ['name' => 'scope', 'contents' => json_encode(['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId])],
            ['name' => 'CRAFT_CSRF_TOKEN', 'contents' => $csrf],
        ],
    ]);

    expect($response['status'])->toBe(200, $response['body']);

    $stored = Asset::find()->id($response['data']['asset']['id'])->status(null)->one()->getCopyOfFile();

    expect(@exif_read_data($stored, 'GPS'))->toBeFalse()
        ->and((string)file_get_contents($stored))->not->toContain('Exif');
});

it('answers early refusals readably to a listed origin, matches origins as browsers send them, and reads one from the environment (L5)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in early errors owner');
    $member = signInUser('signInEarly');
    $token = signInMint($member);
    $browser = signInBrowser();

    $version = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], [...signInBearer($token), 'X-Comments-Api-Version' => '2']);
    $method = $browser->postJson('/actions/comments/api/viewer', [], signInBearer($token));
    $writeAsGet = $browser->get('/actions/comments/api/create', [], signInBearer($token));

    foreach (['version' => [$version, 400, 'unsupported_api_version'], 'method' => [$method, 405, 'method_not_allowed'], 'write as GET' => [$writeAsGet, 405, 'method_not_allowed']] as $label => [$response, $status, $code]) {
        expect($response['status'])->toBe($status, $label . ' ' . $response['body'])
            ->and(signInCode($response))->toBe($code)
            ->and(signInHeader($response, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_TIER3);
    }

    // `Origin` in other cases or with its default port is the same origin.
    $variant = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id], signInBearer($token, 'https://APP.Signin-Elsewhere.test:443'));

    expect($variant['status'])->toBe(200, $variant['body'])
        ->and($variant['data']['viewer']['isGuest'])->toBeFalse();

    // An origin listed as an environment variable.
    $fromEnv = $browser->get('/actions/comments/api/session', [], signInGuestHeaders(null, SIGN_IN_FROM_ENV));

    expect($fromEnv['status'])->toBe(200, $fromEnv['body'])
        ->and(signInHeader($fromEnv, 'Access-Control-Allow-Origin'))->toBe(SIGN_IN_FROM_ENV)
        ->and(signInHeader($fromEnv, 'X-Comments-Session'))->toBeString();
});

it('can’t be turned into another method from another site (method override)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in method override owner');
    $member = signInUser('signInOverride');
    $browser = signInBrowser($member);

    // A form post from another site, asking Yii to treat it as a read, is refused by its origin.
    $formRead = $browser->request('POST', '/actions/comments/api/viewer?ownerId=' . $entry->id, ['form_params' => ['_method' => 'GET'], 'headers' => ['Origin' => SIGN_IN_FOREIGN]]);
    // A read turned into a write still needs the CSRF token. Yii never lets a form turn a write into
    // a read, and a write the header turns into a read is refused as a write sent the wrong way.
    $headerWrite = $browser->get('/actions/comments/api/create', [], ['X-Http-Method-Override' => 'POST']);
    $formWriteAsRead = $browser->request('POST', '/actions/comments/api/create', ['form_params' => ['_method' => 'GET', 'operationId' => signInOperation(), 'ownerId' => $entry->id]]);
    $headerWriteAsRead = $browser->postJson('/actions/comments/api/create', ['operationId' => signInOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Overridden']], ['X-Http-Method-Override' => 'GET']);

    expect($formRead['status'])->toBe(403)
        ->and(signInCode($formRead))->toBe('origin_not_allowed')
        ->and(signInCode($headerWrite))->toBe('csrf_failed')
        ->and(signInCode($formWriteAsRead))->toBe('csrf_failed')
        ->and(signInCode($headerWriteAsRead))->toBe('method_not_allowed')
        ->and((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(0);
});

it('limits session reads, mention searches, uploads and signed requests per address (L3)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in limits owner');
    $member = signInUser('signInLimited');
    $token = signInMint($member);
    $browser = signInBrowser();
    $api = verbb\comments\services\Api::class;

    signInExhaust('session', $api::SESSION_READS_PER_MINUTE);
    signInExhaust('mentions', $api::MENTION_SEARCHES_PER_MINUTE);
    signInExhaust('uploads', $api::UPLOADS_PER_MINUTE);
    signInExhaust('comments/api/member-token', $api::SIGNED_REQUESTS_PER_MINUTE);
    signInExhaust('comments/api/revoke-member-tokens', $api::SIGNED_REQUESTS_PER_MINUTE);

    $responses = [
        'session' => $browser->get('/actions/comments/api/session', [], signInBearer($token)),
        'mentions' => $browser->get('/actions/comments/api/mentions', ['context' => 'comments.reply', 'query' => 'Te', 'scope' => ['ownerId' => $entry->id, 'siteId' => $entry->siteId]], signInBearer($token)),
        'upload' => signInUpload($browser, 'notes.txt', 'Notes', signInBearer($token), $entry),
        'mint' => signInSigned('member-token', ['user' => ['uid' => $member->uid], 'origin' => SIGN_IN_TIER3]),
        'revoke' => signInSigned('revoke-member-tokens', ['user' => ['uid' => $member->uid]]),
    ];

    foreach ($responses as $label => $response) {
        expect($response['status'])->toBe(429, $label . ' ' . $response['body'])
            ->and(signInCode($response))->toBe('rate_limited')
            ->and(signInHeader($response, 'Retry-After'))->toBeString();
    }
});

it('doesn’t sign a member token in to GraphQL', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in GraphQL owner');
    $member = signInUser('signInGraphql');
    $token = signInMint($member);
    $response = signInBrowser()->postJson('/actions/graphql/api', ['query' => "query { commentsViewer(ownerId: {$entry->id}) { data } }"], ['Authorization' => 'Bearer ' . $token]);

    expect($response['body'])->not->toContain(verbb\comments\helpers\CommentsHelper::publicUserName($member))
        ->and($response['data']['data']['commentsViewer'] ?? null)->toBeNull();
});

it('finds a linked comment by its number as well as its UID, with the same rules', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in deep link owner');
    $root = signInPost($entry, 'Linked by number');
    $author = signInUser('signInDeepLinker');
    $waiting = signInPost($entry, 'Waiting by number', $author, held: true);
    $browser = signInBrowser();

    $byId = $browser->get('/actions/comments/api/comment', ['id' => $root->id]);
    $byUid = $browser->get('/actions/comments/api/comment', ['uid' => $root->uid]);
    $hidden = $browser->get('/actions/comments/api/comment', ['id' => $waiting->id]);
    $notAComment = $browser->get('/actions/comments/api/comment', ['id' => $entry->id]);

    expect($byId['status'])->toBe(200, $byId['body'])
        ->and($byId['data']['targetUid'])->toBe($root->uid)
        ->and(preg_replace('/"timeAgo":"[^"]*"/', '', $byId['body']))->toBe(preg_replace('/"timeAgo":"[^"]*"/', '', $byUid['body']))
        ->and(signInCode($hidden))->toBe('not_found')
        ->and(signInCode($notAComment))->toBe('not_found');

    // The author's own waiting comment comes through `viewer` by number; nobody else's does.
    $own = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id, 'ids' => [$waiting->id]], signInBearer(signInMint($author)));
    $someoneElse = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id, 'ids' => [$waiting->id]], signInBearer(signInMint(signInUser('signInNotTheAuthor'))));

    expect(array_column($own['data']['extraComments'], 'uid'))->toContain($waiting->uid)
        ->and(array_column($someoneElse['data']['extraComments'], 'uid'))->not->toContain($waiting->uid)
        ->and(signInCode($browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id, 'ids' => range(1, 201)], signInBearer(signInMint($author)))))->toBe('limit_exceeded');

    // The labels the renderer needs come with the thread.
    expect(array_keys($browser->get('/actions/comments/api/thread', ['ownerId' => $entry->id])['data']['labels']))
        ->toContain('comments', 'moderation', 'onThisPage', 'hold', 'removedNote', 'chooseOption');
});

it('answers someone Craft signed in without a cookie in full (L7)', function() {
    $entry = CommentsFixtureFactory::entry('Sign-in cookieless owner');
    $member = signInUser('signInCookieless');
    $request = Craft::$app->getRequest();
    $identity = Craft::$app->getUser()->getIdentity();
    $request->setIsConsoleRequest(false);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $request->setQueryParams(['ownerId' => $entry->id]);
    Craft::$app->getUser()->setIdentity($member);

    try {
        $response = (new verbb\comments\controllers\ApiController('api', Comments::$plugin))->runAction('viewer');

        expect($response->data['viewer']['isGuest'] ?? null)->toBeFalse(json_encode($response->data))
            ->and($response->data['viewer']['hasSession'] ?? null)->toBeTrue();
    } finally {
        Craft::$app->getUser()->setIdentity($identity);
        $request->setQueryParams([]);
        $request->setIsConsoleRequest(true);
        unset($_SERVER['REQUEST_METHOD']);
        Comments::$plugin->getSignIn()->reset();
    }
});

it('reuses the guest identity another request made at the same moment (L9)', function() {
    $secret = Comments::$plugin->getIdentities()->generateGuestToken();
    $hash = Comments::$plugin->getIdentities()->hashGuestToken($secret);
    $signIn = Comments::$plugin->getSignIn();
    (new ReflectionProperty($signIn, '_current'))->setValue($signIn, new RequestSignIn([
        'profile' => RequestProfile::CrossOriginPublic,
        'origin' => SIGN_IN_GUESTS,
        'guestSecret' => $secret,
    ]));
    // The other request commits its identity, on its own connection, just before this one inserts.
    $other = clone Craft::$app->getDb();
    $handler = static function() use ($other, $hash): void {
        $other->createCommand()->insert('{{%comments_identities}}', ['type' => 'guest', 'guestTokenHash' => $hash, 'name' => 'First'])->execute();
    };
    yii\base\Event::on(verbb\comments\records\Identity::class, yii\db\BaseActiveRecord::EVENT_BEFORE_INSERT, $handler);

    try {
        $resolved = Comments::$plugin->getIdentities()->resolveOrCreateGuest($secret, 'Second');
    } finally {
        yii\base\Event::off(verbb\comments\records\Identity::class, yii\db\BaseActiveRecord::EVENT_BEFORE_INSERT, $handler);
        $signIn->reset();
        $other->close();
    }

    expect($resolved['identity']->name)->toBe('First')
        ->and((int)(new craft\db\Query())->from('{{%comments_identities}}')->where(['guestTokenHash' => $hash])->count())->toBe(1);
});

it('warns on the settings page when uploads are served from the site’s own address (M2)', function() {
    $handle = 'signInOwnOrigin' . bin2hex(random_bytes(3));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => '@web/' . $handle]);
    Craft::$app->getFs()->saveFilesystem($filesystem);
    $volume = new craft\models\Volume(['name' => $handle, 'handle' => $handle, 'fsHandle' => $handle]);
    Craft::$app->getVolumes()->saveVolume($volume);
    [, $separate] = $GLOBALS['signInVolume'];
    $render = static function(int $volumeId): string {
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        try {
            return $view->renderTemplate('comments/settings/_panes/authoring', ['settings' => new Settings(['authoringMode' => 'rich', 'allowAttachments' => true, 'attachmentVolumeId' => $volumeId])]);
        } finally {
            $view->setTemplateMode($mode);
        }
    };

    try {
        $siteOrigin = AllowedOrigin::normalize((string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl());

        expect(verbb\comments\helpers\UploadPolicy::volumeSiteOrigin((int)$volume->id))->toBe($siteOrigin)
            ->and(verbb\comments\helpers\UploadPolicy::volumeSiteOrigin((int)$separate->id))->toBeNull()
            ->and($render((int)$volume->id))->toContain('nosniff')->toContain((string)$siteOrigin)
            ->and($render((int)$separate->id))->not->toContain('nosniff');
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        Craft::$app->getProjectConfig()->saveModifiedConfigData();
    }
});
