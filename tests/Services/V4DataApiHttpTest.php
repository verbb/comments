<?php

declare(strict_types=1);

use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\SpamSettingsStore;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;

/**
 * `comments/api/*` through real requests: sessions, cookies, CSRF, cache headers, the API version
 * header, operation replays and error statuses, as a guest, a member, an author, another member, a
 * moderator and a blocked commenter.
 */

const DATA_API_HTTP_PASSWORD = 'Comments-DataApi-Http-1!';

function dataApiHttpUser(string $prefix, array $permissions = []): User
{
    $user = CommentsFixtureFactory::user($prefix);
    $user->newPassword = DATA_API_HTTP_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    if ($permissions) {
        Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);
    }

    return $user;
}

function dataApiHttpBrowser(?User $user = null): HttpClient
{
    // Craft 5.12 limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();

    if ($user) {
        $browser->login((string)$user->username, DATA_API_HTTP_PASSWORD);
    }

    return $browser;
}

function dataApiHttpPost($entry, string $text, ?User $user = null, ?Comment $parent = null, bool $held = false): Comment
{
    Comments::$plugin->getSettings()->requireModeration = $held;
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'parentId' => $parent?->id,
        'content' => $text,
        'userId' => $user ? (int)$user->id : (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')->id,
    ]))->comment;
}

/** A header's value, or null. */
function dataApiHeader(array $response, string $name): ?string
{
    $values = $response['headers'][strtolower($name)] ?? null;

    return $values ? implode(', ', $values) : null;
}

/** A browser's CSRF token, from the API itself as the comments script gets it. */
function dataApiCsrf(HttpClient $browser, $entry): string
{
    $response = $browser->get('/actions/comments/api/protection', ['ownerId' => $entry->id]);
    expect($response['status'])->toBe(200, $response['body']);

    return (string)$response['data']['csrf']['value'];
}

function dataApiOperation(): string
{
    return craft\helpers\StringHelper::UUID();
}

beforeAll(function() {
    // The web app reads the edition and settings from project config, not from this process. Only the
    // settings these requests need are changed there, and the whole of it is put back afterwards, so
    // nothing leaks into the tests that read it next.
    $projectConfig = Craft::$app->getProjectConfig();
    $projectConfig->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    $GLOBALS['dataApiHttpConfig'] = $projectConfig->get('plugins.comments.settings');
    $GLOBALS['dataApiHttpSpam'] = SpamSettingsStore::snapshot();
    $GLOBALS['dataApiHttpSettings'] = Comments::$plugin->getSettings()->getAttributes();

    foreach ([
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
    ] as $name => $value) {
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
        SpamSettingsStore::apply([$name => $value]);
    }

    $projectConfig->saveModifiedConfigData();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
    SpamSettingsStore::restore($GLOBALS['dataApiHttpSpam']);
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = $GLOBALS['dataApiHttpConfig'];

    if ($previous === null) {
        $projectConfig->remove('plugins.comments.settings');
    } else {
        $projectConfig->set('plugins.comments.settings', $previous);
    }

    $projectConfig->saveModifiedConfigData();
    Comments::$plugin->getSettings()->setAttributes($GLOBALS['dataApiHttpSettings'], false);
});

beforeEach(function() {
    // Rate limits are per address, and every request here comes from the same one.
    Craft::$app->getCache()->flush();
    Comments::$plugin->getSettings()->enableSpamChecks = false;
});

it('answers public reads the same with or without a session, cacheable, and sets no cookie', function() {
    $entry = CommentsFixtureFactory::entry('HTTP public owner');
    $root = dataApiHttpPost($entry, 'Public over HTTP');
    dataApiHttpPost($entry, 'Waiting over HTTP', null, $root, true);
    $member = dataApiHttpUser('httpApiReader');
    $guest = dataApiHttpBrowser();
    $signedIn = dataApiHttpBrowser($member);

    foreach ([
        ['/actions/comments/api/thread', ['ownerId' => $entry->id]],
        ['/actions/comments/api/replies', ['parentUid' => $root->uid]],
        ['/actions/comments/api/comment', ['uid' => $root->uid]],
        ['/actions/comments/api/structured-data', ['ownerId' => $entry->id]],
    ] as [$path, $query]) {
        $anonymous = $guest->get($path, $query);
        $personal = $signedIn->get($path, $query);

        expect($anonymous['status'])->toBe(200, $path . ' ' . $anonymous['body'])
            ->and($personal['status'])->toBe(200)
            ->and(dataApiHeader($anonymous, 'Cache-Control'))->toBe('public, max-age=0, s-maxage=60, must-revalidate')
            ->and(dataApiHeader($personal, 'Cache-Control'))->toBe('public, max-age=0, s-maxage=60, must-revalidate')
            ->and(dataApiHeader($anonymous, 'CDN-Cache-Control'))->toBe('max-age=60, stale-while-revalidate=300')
            ->and(dataApiHeader($anonymous, 'ETag'))->toBeString()
            // Any origin may read public data, never with credentials, so one cached copy serves them all.
            ->and(dataApiHeader($anonymous, 'Vary'))->toBe('X-Comments-Api-Version')
            ->and(dataApiHeader($anonymous, 'Access-Control-Allow-Origin'))->toBe('*')
            ->and(dataApiHeader($anonymous, 'Access-Control-Allow-Credentials'))->toBeNull()
            ->and(dataApiHeader($anonymous, 'Set-Cookie'))->toBeNull()
            ->and(dataApiHeader($personal, 'Set-Cookie'))->toBeNull()
            ->and(dataApiHeader($anonymous, 'Pragma'))->toBeNull()
            ->and(dataApiHeader($personal, 'Pragma'))->toBeNull()
            ->and(dataApiHeader($anonymous, 'Expires'))->toBeNull()
            ->and(dataApiHeader($personal, 'Expires'))->toBeNull()
            ->and(dataApiHeader($anonymous, 'X-Comments-Api-Version'))->toBe('1')
            ->and($anonymous['data']['apiVersion'])->toBe(1)
            ->and(preg_replace('/"timeAgo":"[^"]*"/', '', $personal['body']))->toBe(preg_replace('/"timeAgo":"[^"]*"/', '', $anonymous['body']))
            ->and($anonymous['body'])->not->toContain('Waiting over HTTP');
    }

    expect($guest->cookieNames())->toBe([]);

});

it('answers a browser revalidating any public read with 304 and no body, signed in or not', function() {
    $entry = CommentsFixtureFactory::entry('HTTP revalidate owner');
    $root = dataApiHttpPost($entry, 'Revalidate me');
    // A comment posted seconds ago says so to the second, which changes its ETag as it should.
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['commentDate' => '2026-01-01 00:00:00'], ['id' => $root->id])->execute();
    Comments::$plugin->getApi()->invalidateCaches();
    $guest = dataApiHttpBrowser();
    $signedIn = dataApiHttpBrowser(dataApiHttpUser('httpApiRevalidator'));

    foreach ([
        ['/actions/comments/api/thread', ['ownerId' => $entry->id]],
        ['/actions/comments/api/replies', ['parentUid' => $root->uid]],
        ['/actions/comments/api/comment', ['uid' => $root->uid]],
        ['/actions/comments/api/structured-data', ['ownerId' => $entry->id]],
    ] as [$path, $query]) {
        foreach (['guest' => $guest, 'signed in' => $signedIn] as $who => $browser) {
            $first = $browser->get($path, $query);
            $etag = (string)dataApiHeader($first, 'ETag');

            // As the browser sends it, and as it sends it after nginx's gzip has made the ETag weak.
            foreach ([$etag, 'W/' . $etag, '"other", ' . $etag] as $ifNoneMatch) {
                $again = $browser->get($path, $query, ['If-None-Match' => $ifNoneMatch]);

                expect($again['status'])->toBe(304, "{$path} {$who} {$ifNoneMatch}")
                    ->and($again['body'])->toBe('')
                    ->and(dataApiHeader($again, 'ETag'))->toBe($etag)
                    ->and(dataApiHeader($again, 'Set-Cookie'))->toBeNull();
            }

            expect($browser->get($path, $query, ['If-None-Match' => '"stale"'])['status'])->toBe(200);
        }
    }
});

it('never builds a forged host into a public response', function() {
    $entry = CommentsFixtureFactory::entry('HTTP host owner');
    dataApiHttpPost($entry, 'Host check');
    $guest = dataApiHttpBrowser();
    $forged = ['X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https', 'Host' => 'evil.example'];

    foreach ([
        ['/actions/comments/api/thread', ['ownerId' => $entry->id]],
        ['/actions/comments/api/structured-data', ['ownerId' => $entry->id]],
    ] as [$path, $query]) {
        $poisoned = $guest->get($path, $query, $forged);
        $clean = $guest->get($path, $query);

        expect($poisoned['status'])->toBe(200, $poisoned['body'])
            ->and($poisoned['body'])->not->toContain('evil.example')
            ->and($clean['body'])->not->toContain('evil.example');
    }
});

it('gives a guest without a session a slim viewer answer, starting nothing until they write', function() {
    $entry = CommentsFixtureFactory::entry('HTTP slim owner');
    dataApiHttpPost($entry, 'Read me');
    $guest = dataApiHttpBrowser();

    $viewer = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);

    expect($viewer['status'])->toBe(200, $viewer['body'])
        ->and(dataApiHeader($viewer, 'Cache-Control'))->toBe('private, no-store')
        ->and(dataApiHeader($viewer, 'Set-Cookie'))->toBeNull()
        ->and($viewer['data']['viewer'])->toMatchArray(['isGuest' => true, 'hasSession' => false, 'canComment' => true], $viewer['body'])
        ->and($viewer['data']['csrf'])->toBeNull()
        ->and($viewer['data']['editor']['protection'])->toBeNull()
        ->and($guest->cookieNames())->toBe([]);

    // Starting to write is when a guest gets a CSRF token and protection.
    $protection = $guest->get('/actions/comments/api/protection', ['ownerId' => $entry->id]);

    expect($protection['status'])->toBe(200)
        ->and(dataApiHeader($protection, 'Cache-Control'))->toBe('private, no-store')
        ->and($protection['data']['csrf']['value'])->toBeString()
        ->and($protection['data']['protection'])->toHaveKeys(['version', 'manifest', 'expiresAt']);
});

it('refuses a client asking for an API version this site doesn’t serve', function() {
    $entry = CommentsFixtureFactory::entry('HTTP version owner');
    $browser = dataApiHttpBrowser();

    foreach (['thread', 'viewer'] as $action) {
        $refused = $browser->get('/actions/comments/api/' . $action, ['ownerId' => $entry->id], ['X-Comments-Api-Version' => '2']);

        expect($refused['status'])->toBe(400)
            ->and($refused['data']['errors'][0]['code'])->toBe('unsupported_api_version')
            ->and($refused['data']['apiVersion'])->toBe(1)
            ->and(dataApiHeader($refused, 'Cache-Control'))->toBe('private, no-store');
    }

    $csrf = dataApiCsrf($browser, $entry);
    $write = $browser->postJson('/actions/comments/api/create', ['operationId' => dataApiOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'x']], ['X-Comments-Api-Version' => '0'], $csrf);

    expect($write['status'])->toBe(400)
        ->and($write['data']['errors'][0]['code'])->toBe('unsupported_api_version')
        ->and($browser->get('/actions/comments/api/thread', ['ownerId' => $entry->id], ['X-Comments-Api-Version' => '1'])['status'])->toBe(200);
});

it('lets a guest post once per operation, replaying a repeat, and refuses a write without CSRF', function() {
    $entry = CommentsFixtureFactory::entry('HTTP guest write owner');
    $guest = dataApiHttpBrowser();
    $csrf = dataApiCsrf($guest, $entry);
    $body = ['operationId' => dataApiOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Guest over HTTP'], 'guest' => ['name' => 'Gale']];

    $first = $guest->postJson('/actions/comments/api/create', $body, csrfToken: $csrf);
    $repeat = $guest->postJson('/actions/comments/api/create', $body, csrfToken: $csrf);

    expect($first['status'])->toBe(200, $first['body'])
        ->and($first['data'])->toMatchArray(['apiVersion' => 1, 'status' => 'ok', 'moderationState' => 'approved', 'replayed' => false])
        ->and(dataApiHeader($first, 'Cache-Control'))->toBe('private, no-store')
        ->and($repeat['status'])->toBe(200)
        ->and($repeat['data']['replayed'])->toBeTrue()
        ->and($repeat['data']['comment']['uid'])->toBe($first['data']['comment']['uid'])
        ->and((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(1);

    $noCsrf = $guest->postJson('/actions/comments/api/create', [...$body, 'operationId' => dataApiOperation()]);
    $noOperation = $guest->postJson('/actions/comments/api/create', array_diff_key($body, ['operationId' => true]), csrfToken: $csrf);
    $wrongMethod = $guest->get('/actions/comments/api/create');

    expect($noCsrf['status'])->toBe(403)
        ->and($noCsrf['data']['errors'][0]['code'])->toBe('csrf_failed')
        ->and($noOperation['status'])->toBe(422)
        ->and($noOperation['data']['errors'][0]['code'])->toBe('validation_failed')
        ->and($noOperation['data']['errors'][0]['fieldErrors'])->toHaveKey('operationId')
        ->and($wrongMethod['status'])->toBe(405)
        ->and($wrongMethod['data']['errors'][0]['code'])->toBe('method_not_allowed')
        ->and((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(1);

    // The guest's own comment is theirs on the next viewer read, through the cookie posting gave them.
    $viewer = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);

    expect($viewer['data']['viewer']['hasSession'])->toBeTrue()
        ->and($viewer['data']['comments'][$first['data']['comment']['uid']]['isAuthor'])->toBeTrue()
        ->and($viewer['data']['csrf']['value'])->toBeString();
});

it('lets a member write, vote, report, edit and remove, and another member none of it', function() {
    $entry = CommentsFixtureFactory::entry('HTTP member owner');
    $author = dataApiHttpUser('httpApiAuthor');
    $other = dataApiHttpUser('httpApiOther');
    $browser = dataApiHttpBrowser($author);
    $csrf = dataApiCsrf($browser, $entry);

    $created = $browser->postJson('/actions/comments/api/create', ['operationId' => dataApiOperation(), 'ownerUri' => $entry->uri, 'content' => ['format' => 'plain', 'text' => 'Member over HTTP']], csrfToken: $csrf);
    $uid = $created['data']['comment']['uid'] ?? null;

    expect($created['status'])->toBe(200, $created['body'])
        ->and($created['data']['comment']['viewer']['canEdit'])->toBeTrue();

    $viewer = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);

    expect($viewer['status'])->toBe(200)
        ->and($viewer['data']['viewer'])->toMatchArray(['isGuest' => false, 'hasSession' => true])
        ->and($viewer['data']['comments'][$uid])->toMatchArray(['isAuthor' => true, 'canEdit' => true, 'canDelete' => true])
        ->and($viewer['data']['csrf']['value'])->toBeString()
        ->and($viewer['data']['editor']['protection'])->toHaveKey('manifest');

    $edit = $browser->get('/actions/comments/api/edit', ['uid' => $uid]);
    $updated = $browser->postJson('/actions/comments/api/update', ['operationId' => dataApiOperation(), 'uid' => $uid, 'content' => ['format' => 'plain', 'text' => 'Edited over HTTP']], csrfToken: $csrf);

    expect($edit['status'])->toBe(200)
        ->and($edit['data']['comment']['content']['text'])->toBe('Member over HTTP')
        ->and(dataApiHeader($edit, 'Cache-Control'))->toBe('private, no-store')
        ->and($updated['status'])->toBe(200, $updated['body'])
        ->and($updated['data']['comment']['content']['text'])->toBe('Edited over HTTP');

    // Someone else can't touch it, but can vote on it and report it.
    $intruder = dataApiHttpBrowser($other);
    $intruderCsrf = dataApiCsrf($intruder, $entry);
    $hijack = $intruder->postJson('/actions/comments/api/update', ['operationId' => dataApiOperation(), 'uid' => $uid, 'content' => ['format' => 'plain', 'text' => 'Hijacked']], csrfToken: $intruderCsrf);
    $peek = $intruder->get('/actions/comments/api/edit', ['uid' => $uid]);
    $delete = $intruder->postJson('/actions/comments/api/delete', ['operationId' => dataApiOperation(), 'uid' => $uid], csrfToken: $intruderCsrf);
    $vote = $intruder->postJson('/actions/comments/api/vote', ['operationId' => dataApiOperation(), 'uid' => $uid, 'vote' => 'upvote'], csrfToken: $intruderCsrf);
    $report = $intruder->postJson('/actions/comments/api/report', ['operationId' => dataApiOperation(), 'uid' => $uid, 'reason' => 'spam'], csrfToken: $intruderCsrf);

    expect($hijack['status'])->toBe(403)
        ->and($hijack['data']['errors'][0]['code'])->toBe('forbidden')
        ->and($peek['status'])->toBe(403)
        ->and($delete['status'])->toBe(403)
        ->and($vote['status'])->toBe(200, $vote['body'])
        ->and($vote['data']['comment']['interaction']['upvotes'])->toBe(1)
        ->and($vote['data']['comment']['viewer']['vote'])->toBe('upvote')
        ->and($report['status'])->toBe(200)
        ->and($report['data']['comment']['viewer']['reported'])->toBeTrue();

    $removed = $browser->postJson('/actions/comments/api/delete', ['operationId' => dataApiOperation(), 'uid' => $uid], csrfToken: $csrf);

    expect($removed['status'])->toBe(200, $removed['body'])
        ->and($removed['data']['uid'])->toBe($uid)
        ->and($browser->get('/actions/comments/api/thread', ['ownerId' => $entry->id])['data']['comments'])->toBe([]);
});

it('shows a moderator what’s held and lets them decide, once', function() {
    $entry = CommentsFixtureFactory::entry('HTTP moderator owner');
    $author = dataApiHttpUser('httpApiHeldAuthor');
    $held = dataApiHttpPost($entry, 'Held over HTTP', $author, held: true);
    $moderator = dataApiHttpBrowser(dataApiHttpUser('httpApiModerator', ['comments-view', 'comments-moderate', 'comments-trash']));
    $member = dataApiHttpBrowser(dataApiHttpUser('httpApiNotModerator'));

    $viewer = $moderator->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);
    $extra = array_column($viewer['data']['extraComments'] ?? [], null, 'uid');

    expect($viewer['status'])->toBe(200, $viewer['body'])
        ->and($viewer['data']['viewer']['isModerator'])->toBeTrue()
        ->and($extra[$held->uid]['state'])->toBe('held')
        ->and($extra[$held->uid]['moderation']['actions'])->toContain('approve')
        ->and($viewer['data']['moderation']['heldCount'])->toBe(1)
        ->and($member->get('/actions/comments/api/viewer', ['ownerId' => $entry->id])['data']['extraComments'])->toBe([]);

    $memberCsrf = dataApiCsrf($member, $entry);
    $refused = $member->postJson('/actions/comments/api/moderate', ['operationId' => dataApiOperation(), 'uid' => $held->uid, 'action' => 'approve'], csrfToken: $memberCsrf);
    $csrf = dataApiCsrf($moderator, $entry);
    $approved = $moderator->postJson('/actions/comments/api/moderate', ['operationId' => dataApiOperation(), 'uid' => $held->uid, 'action' => 'approve'], csrfToken: $csrf);
    $again = $moderator->postJson('/actions/comments/api/moderate', ['operationId' => dataApiOperation(), 'uid' => $held->uid, 'action' => 'approve'], csrfToken: $csrf);

    expect($refused['status'])->toBe(403)
        ->and($approved['status'])->toBe(200, $approved['body'])
        ->and($approved['data']['comment']['state'])->toBe('approved')
        ->and($again['status'])->toBe(409)
        ->and($again['data']['errors'][0]['code'])->toBe('conflict')
        ->and($moderator->get('/actions/comments/api/thread', ['ownerId' => $entry->id])['body'])->toContain('Held over HTTP');
});

it('refuses a blocked commenter and answers errors with their codes and statuses', function() {
    $entry = CommentsFixtureFactory::entry('HTTP blocked owner');
    $blockedUser = dataApiHttpUser('httpApiBlocked');
    $root = dataApiHttpPost($entry, 'Before the block', $blockedUser);
    Comments::$plugin->getModeration()->blockIdentity((int)$root->identityId, null, Craft::$app->getUsers()->getUserByUsernameOrEmail('admin'), 'Enough');
    $blocked = dataApiHttpBrowser($blockedUser);
    $csrf = dataApiCsrf($blocked, $entry);

    $viewer = $blocked->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);
    $post = $blocked->postJson('/actions/comments/api/create', ['operationId' => dataApiOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Again']], csrfToken: $csrf);

    expect($viewer['data']['viewer'])->toMatchArray(['canComment' => false, 'blocked' => true])
        ->and($post['status'])->toBe(403)
        ->and($post['data']['errors'][0]['code'])->toBe('blocked');

    $guest = dataApiHttpBrowser();
    $errors = [
        [$guest->get('/actions/comments/api/thread', ['ownerId' => 999999999]), 404, 'not_found'],
        [$guest->get('/actions/comments/api/thread', ['ownerId' => $entry->id, 'limit' => 51]), 400, 'limit_exceeded'],
        [$guest->get('/actions/comments/api/thread', ['ownerId' => $entry->id, 'cursor' => 'forged']), 400, 'invalid_cursor'],
        [$guest->get('/actions/comments/api/replies', ['parentUid' => $root->uid, 'limit' => 101]), 400, 'limit_exceeded'],
        [$guest->get('/actions/comments/api/edit', ['uid' => $root->uid]), 401, 'sign_in_required'],
        [$guest->get('/actions/comments/api/comment', ['uid' => 'nope']), 404, 'not_found'],
    ];

    foreach ($errors as [$response, $status, $code]) {
        expect($response['status'])->toBe($status, $response['body'])
            ->and($response['data']['status'])->toBe('error')
            ->and($response['data']['errors'][0]['code'])->toBe($code)
            ->and($response['data']['errors'][0])->toHaveKeys(['category', 'message', 'fieldErrors', 'recoverable', 'retryAfter'])
            ->and(dataApiHeader($response, 'Cache-Control'))->toBe('private, no-store');
    }
});

it('limits public reads that have to be built, but not ones the cache answers', function() {
    $entry = CommentsFixtureFactory::entry('HTTP rate owner');
    dataApiHttpPost($entry, 'Rate check');
    $guest = dataApiHttpBrowser();

    // The default page size is cached, so a server rendering many pages from one address isn't refused.
    for ($i = 0; $i < 130; $i++) {
        $cached = $guest->get('/actions/comments/api/thread', ['ownerId' => $entry->id]);

        expect($cached['status'])->toBe(200, $cached['body']);
    }

    // Another page size is built every time, and 120 a minute are allowed. A minute can end part way
    // through, which starts a fresh allowance.
    $statuses = [];

    for ($i = 0; $i < 250 && !in_array(429, $statuses, true); $i++) {
        $response = $guest->get('/actions/comments/api/thread', ['ownerId' => $entry->id, 'limit' => 5]);
        $statuses[] = $response['status'];
    }

    expect($statuses)->toContain(429)
        ->and(count(array_filter($statuses, static fn(int $status): bool => $status === 200)))->toBeGreaterThanOrEqual(110)
        ->and($response['data']['errors'][0]['code'])->toBe('rate_limited')
        ->and((int)dataApiHeader($response, 'Retry-After'))->toBeGreaterThan(0)
        ->and(dataApiHeader($response, 'X-Comments-Api-Version'))->toBe('1');
});

it('shows a guest their own waiting and spam comments as waiting, through their guest cookie, and no other guest', function() {
    $entry = CommentsFixtureFactory::entry('HTTP guest pending owner');
    $author = dataApiHttpBrowser();
    $csrf = dataApiCsrf($author, $entry);
    $posted = [];

    foreach (['Guest waiting', 'Guest spam'] as $text) {
        $response = $author->postJson('/actions/comments/api/create', ['operationId' => dataApiOperation(), 'ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => $text], 'guest' => ['name' => 'Gale']], csrfToken: $csrf);
        expect($response['status'])->toBe(200, $response['body']);
        $posted[$text] = $response['data']['comment']['uid'];
    }

    $moderation = Comments::$plugin->getModeration();
    $moderation->setState(Comment::find()->uid($posted['Guest waiting'])->one(), verbb\comments\enums\ModerationState::Pending);
    $moderation->markSpam(Comment::find()->uid($posted['Guest spam'])->one());

    $mine = $author->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);
    $extra = array_column($mine['data']['extraComments'] ?? [], 'state', 'uid');
    $other = dataApiHttpBrowser();
    dataApiCsrf($other, $entry);
    $theirs = $other->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);

    expect($mine['status'])->toBe(200, $mine['body'])
        ->and($extra)->toEqualCanonicalizing([$posted['Guest waiting'] => 'pending', $posted['Guest spam'] => 'pending'])
        ->and($theirs['data']['extraComments'])->toBe([])
        ->and($theirs['body'])->not->toContain('Guest spam')
        ->and($author->get('/actions/comments/api/thread', ['ownerId' => $entry->id])['body'])->not->toContain('Guest waiting');
});

it('refuses GraphQL mutations over GET, and without the CSRF token when a cookie says who’s asking', function() {
    $entry = CommentsFixtureFactory::entry('HTTP GraphQL CSRF owner');
    $comment = dataApiHttpPost($entry, 'GraphQL target', dataApiHttpUser('httpGqlTarget'));
    // Craft's public schema, which needs no token, turned on for the length of the test.
    $gql = Craft::$app->getGql();
    $token = $gql->getPublicToken();
    $schema = $token->getSchema();
    $previousScope = $schema->scope;
    $previousEnabled = $token->enabled;
    $schema->scope = array_values(array_unique([...$schema->scope, 'sites.' . Craft::$app->getSites()->getPrimarySite()->uid . ':read', 'comments:read', 'comments:edit', 'comments:save', 'comments:delete']));
    $gql->saveSchema($schema);
    $token->enabled = true;
    $gql->saveToken($token);
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    $mutations = [
        'create' => "mutation { createComment(ownerId: {$entry->id}, comment: \"Cross-site\") { id } }",
        'save' => "mutation { saveComment(uid: \"{$comment->uid}\", comment: \"Changed\") { id } }",
        'vote' => "mutation { voteComment(uid: \"{$comment->uid}\", vote: \"upvote\") { upvote } }",
        'flag' => "mutation { flagComment(uid: \"{$comment->uid}\", withdraw: false) { commentId } }",
        'delete' => "mutation { deleteComment(uid: \"{$comment->uid}\") }",
    ];

    try {
        $member = dataApiHttpBrowser(dataApiHttpUser('httpGqlVictim'));
        $csrf = dataApiCsrf($member, $entry);

        foreach ($mutations as $name => $query) {
            $overGet = $member->get('/actions/graphql/api', ['query' => $query]);
            $noToken = $member->postJson('/actions/graphql/api', ['query' => $query]);
            $badToken = $member->postJson('/actions/graphql/api', ['query' => $query], csrfToken: 'forged-token');

            expect($overGet['data']['errors'][0]['extensions']['code'] ?? null)->toBe('method_not_allowed', $name . ' ' . $overGet['body'])
                ->and($noToken['data']['errors'][0]['extensions']['code'] ?? null)->toBe('csrf_failed', $name . ' ' . $noToken['body'])
                ->and($badToken['data']['errors'][0]['extensions']['code'] ?? null)->toBe('csrf_failed', $name . ' ' . $badToken['body']);
        }

        expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(1)
            ->and(Comments::$plugin->getInteractions()->getTotals([(int)$comment->id])[(int)$comment->id]['upvote'])->toBe(0);

        // With the page's token, the same request works.
        $voted = $member->postJson('/actions/graphql/api', ['query' => $mutations['vote']], csrfToken: $csrf);

        expect($voted['data']['errors'] ?? null)->toBeNull($voted['body'])
            ->and($voted['data']['data']['voteComment']['upvote'])->toBeTruthy();

        // A server calling with no cookies isn't anyone's browser, so needs no token.
        $server = dataApiHttpBrowser();
        $served = $server->postJson('/actions/graphql/api', ['query' => "query { commentsThread(ownerId: {$entry->id}) { apiVersion } }"]);

        expect($served['data']['data']['commentsThread']['apiVersion'] ?? null)->toBe(1, $served['body']);
    } finally {
        $schema->scope = $previousScope;
        $gql->saveSchema($schema);
        $token->enabled = $previousEnabled;
        $gql->saveToken($token);
        Craft::$app->getProjectConfig()->saveModifiedConfigData();
    }
});

it('answers each site’s thread for that site alone', function() {
    // Through real requests, because this test process's list of sites can be out of date after
    // earlier tests delete one.
    $sites = Tests\Support\Fixtures\MultiSiteFixtureFactory::sameOwnerDifferentSites('HTTP other site owner');
    dataApiHttpPost($sites['primary'], 'Primary site comment');
    $guest = dataApiHttpBrowser();
    $primary = $guest->get('/actions/comments/api/thread', ['ownerId' => $sites['primary']->id, 'siteId' => $sites['primary']->siteId]);
    $secondary = $guest->get('/actions/comments/api/thread', ['ownerId' => $sites['secondary']->id, 'siteId' => $sites['secondary']->siteId]);

    expect($primary['status'])->toBe(200, $primary['body'])
        ->and($primary['data']['comments'])->toHaveCount(1)
        ->and($secondary['status'])->toBe(200, $secondary['body'])
        ->and($secondary['data']['conversation']['siteId'])->toBe((int)$sites['secondary']->siteId)
        ->and($secondary['data']['comments'])->toBe([]);
});
