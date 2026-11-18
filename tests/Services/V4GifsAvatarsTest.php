<?php
use craft\db\Query;
use craft\elements\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\migrations\support\V3SettingsReview;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\models\Settings;
use verbb\comments\services\Avatars;

/**
 * GIFs from GIPHY beside a comment's text (SD-52) and commenters' Gravatar images served by the site
 * (SD-53), both brought back from Comments 3.
 */

const GIF_URL = 'https://media2.giphy.com/media/v1.Y2lkPTc5MGI3NjEx/abc123/giphy.webp?cid=790b7611&rid=giphy.webp&ct=g';

// The smallest valid PNG: one transparent pixel.
const GRAVATAR_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7\x35\x81\x84\x00\x00\x00\x00IEND\xaeB`\x82";

function gifsAs(?User $user): void
{
    Craft::$app->getUser()->setIdentity($user);
    Comments::$plugin->getIdentities()->clearCaches();
    Comments::$plugin->getConversations()->clearCaches();
}

function gifsCreate($entry, array $content): array
{
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getApi()->create(['ownerId' => $entry->id, 'content' => $content]);
}

function gifsError(callable $callback): verbb\comments\exceptions\ApiException
{
    try {
        $callback();
    } catch (Throwable $e) {
        return Comments::$plugin->getApi()->errorFor($e);
    }

    throw new RuntimeException('Expected the data API to refuse this.');
}

/** Gravatar's answers, in order, and the requests made for them. */
function gravatarResponds(Response ...$responses): ArrayObject
{
    $requests = new ArrayObject();
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($requests));
    Comments::$plugin->getAvatars()->httpOptions = ['handler' => $stack];

    return $requests;
}

function avatarRow(int $identityId): ?array
{
    return (new Query())->from(Avatars::TABLE)->where(['identityId' => $identityId])->one() ?: null;
}

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->giphyEnabled = true;
    $settings->giphyApiKey = 'test-web-key';
    $settings->showAvatar = true;
    $settings->enableGravatar = false;
    Comments::$plugin->getApi()->invalidateCaches();
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Comments::$plugin->getAvatars()->httpOptions = [];
    gifsAs($this->admin);
});

// Settings
// =========================================================================

it('keeps Comments 3’s GIPHY and Gravatar settings', function() {
    $settings = new Settings(['giphyEnabled' => true, 'giphyApiKey' => '$GIPHY_KEY', 'giphyRating' => 'pg-13', 'giphyLimit' => 12, 'enableGravatar' => true]);

    expect($settings->toArray())->toMatchArray(['giphyEnabled' => true, 'giphyApiKey' => '$GIPHY_KEY', 'giphyRating' => 'pg-13', 'giphyLimit' => 12, 'enableGravatar' => true])
        // An unset variable is no key at all.
        ->and($settings->getGiphyApiKey())->toBeNull();

    foreach (['enableGravatar', 'giphyEnabled', 'giphyApiKey', 'giphyRating', 'giphyLimit'] as $setting) {
        expect(V3SettingsReview::dispositions()[$setting])->toBe(V3SettingsReview::KEPT, $setting);
    }

    expect(V3SettingsReview::retiredInUse(['giphyEnabled' => true, 'giphyApiKey' => 'key', 'enableGravatar' => true]))->toBe([])
        ->and(V3UpgradeLog::describe(V3SettingsReview::GIPHY_NOTICE))->toContain('browser')
        ->and(V3UpgradeLog::describe(V3SettingsReview::GRAVATAR_NOTICE))->toContain('confirmed');
});

it('needs a key, a GIPHY rating and at most 50 results', function() {
    $settings = new Settings(['giphyEnabled' => true, 'giphyRating' => 'nc-17', 'giphyLimit' => 51]);
    $settings->validate(['giphyApiKey', 'giphyRating', 'giphyLimit']);

    expect(array_keys($settings->getErrors()))->toEqualCanonicalizing(['giphyApiKey', 'giphyRating', 'giphyLimit']);
});

// GIFs
// =========================================================================

it('gives the GIF picker GIPHY’s key only when it’s on and the viewer can comment', function() {
    $entry = CommentsFixtureFactory::entry('GIF viewer owner');
    $settings = Comments::$plugin->getSettings();

    expect(Comments::$plugin->getApi()->viewer($entry)['editor']['gifs'])->toBe(['apiKey' => 'test-web-key', 'rating' => 'g', 'limit' => 24, 'language' => 'en']);

    $settings->allowGuest = false;
    gifsAs(null);
    expect(Comments::$plugin->getApi()->viewer($entry)['editor']['gifs'])->toBeNull();

    gifsAs($this->admin);
    $settings->giphyEnabled = false;
    expect(Comments::$plugin->getApi()->viewer($entry)['editor']['gifs'])->toBeNull();

    // The public thread never carries it.
    $settings->giphyEnabled = true;
    expect(json_encode(Comments::$plugin->getApi()->thread($entry)))->not->toContain('test-web-key');
});

it('posts a comment with a GIF, and a GIF on its own', function() {
    $entry = CommentsFixtureFactory::entry('GIF post owner');

    $created = gifsCreate($entry, ['format' => 'plain', 'text' => 'Look', 'gif' => ['url' => GIF_URL, 'width' => '480', 'height' => 270]]);

    expect($created['comment']['content'])->toMatchArray(['text' => 'Look', 'gifUrl' => GIF_URL, 'gifWidth' => 480, 'gifHeight' => 270]);

    $only = gifsCreate($entry, ['format' => 'plain', 'text' => '', 'gif' => ['url' => GIF_URL]]);

    expect($only['comment']['content'])->toMatchArray(['text' => '', 'gifUrl' => GIF_URL, 'gifWidth' => null, 'gifHeight' => null]);

    $thread = Comments::$plugin->getApi()->thread($entry);
    expect(array_column(array_column($thread['comments'], 'content'), 'gifUrl'))->toBe([GIF_URL, GIF_URL]);
});

it('refuses a GIF that isn’t from GIPHY, or while GIPHY is off', function() {
    $entry = CommentsFixtureFactory::entry('GIF refusal owner');

    foreach (['http://media.giphy.com/media/x/giphy.gif', 'https://giphy.com.example.com/x.gif', 'https://example.com/giphy.com/x.gif', 'javascript:alert(1)', 'https://media.giphy.com/x.gif" onerror="x'] as $url) {
        $error = gifsError(fn() => gifsCreate($entry, ['format' => 'plain', 'text' => 'Bad', 'gif' => ['url' => $url]]));
        expect($error->errorCode)->toBe('validation_failed', $url)
            ->and($error->fieldErrors)->toHaveKey('content');
    }

    Comments::$plugin->getSettings()->giphyEnabled = false;

    expect(gifsError(fn() => gifsCreate($entry, ['format' => 'plain', 'text' => 'Off', 'gif' => ['url' => GIF_URL]]))->errorCode)->toBe('validation_failed')
        ->and(Comment::find()->ownerId($entry->id)->count())->toBe(0);
});

it('keeps a GIF through an edit that doesn’t mention it, and takes it off with null', function() {
    $entry = CommentsFixtureFactory::entry('GIF edit owner');
    $created = gifsCreate($entry, ['format' => 'plain', 'text' => 'Before', 'gif' => ['url' => GIF_URL, 'width' => 200, 'height' => 100]]);
    $uid = $created['comment']['uid'];
    $api = Comments::$plugin->getApi();

    expect($api->edit($uid)['comment']['content']['gif'])->toBe(['url' => GIF_URL, 'width' => 200, 'height' => 100])
        ->and($api->edit($uid)['editor']['gifs']['apiKey'])->toBe('test-web-key');

    $kept = $api->update(['uid' => $uid, 'content' => ['format' => 'plain', 'text' => 'After']]);
    expect($kept['comment']['content'])->toMatchArray(['text' => 'After', 'gifUrl' => GIF_URL]);

    // Even with GIPHY off, an author can take a GIF off.
    Comments::$plugin->getSettings()->giphyEnabled = false;
    $removed = $api->update(['uid' => $uid, 'content' => ['format' => 'plain', 'text' => 'After', 'gif' => null]]);

    expect($removed['comment']['content']['gifUrl'])->toBeNull()
        ->and($removed['comment']['editedAt'])->not->toBeNull();
});

it('marks a comment edited when only its GIF changes', function() {
    $entry = CommentsFixtureFactory::entry('GIF edited owner');
    $created = gifsCreate($entry, ['format' => 'plain', 'text' => 'Same text']);
    $uid = $created['comment']['uid'];

    expect($created['comment']['editedAt'])->toBeNull();

    $updated = Comments::$plugin->getApi()->update(['uid' => $uid, 'content' => ['format' => 'plain', 'text' => 'Same text', 'gif' => ['url' => GIF_URL]]]);

    expect($updated['comment']['content']['gifUrl'])->toBe(GIF_URL)
        ->and($updated['comment']['editedAt'])->not->toBeNull();
});

it('takes a GIF off from the control panel without changing the text', function() {
    $entry = CommentsFixtureFactory::entry('GIF CP owner');
    $created = gifsCreate($entry, ['format' => 'plain', 'text' => 'Keep me', 'gif' => ['url' => GIF_URL]]);
    $comment = Comment::find()->uid($created['comment']['uid'])->one();

    Comments::$plugin->getGiphy()->apply($comment, null);
    expect(Craft::$app->getElements()->saveElement($comment, false))->toBeTrue();

    $saved = Comment::find()->id($comment->id)->one();
    expect($saved->gifUrl)->toBeNull()
        ->and($saved->gifWidth)->toBeNull()
        ->and($saved->comment)->toBe('Keep me');
});

// Gravatar
// =========================================================================

it('only looks up addresses known to be the commenter’s', function() {
    $avatars = Comments::$plugin->getAvatars();
    $member = CommentsFixtureFactory::user('gravatarMember');
    $identities = Comments::$plugin->getIdentities();

    expect($avatars->verifiedEmail($identities->getOrCreateForUser($member)))->toBe($member->email);

    $guest = $identities->createHistoricalGuest('Guest', 'guest-gravatar@example.test');
    expect($avatars->verifiedEmail($guest))->toBeNull();

    $guest->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $guest->save(false);
    expect($avatars->verifiedEmail($guest))->toBe('guest-gravatar@example.test');

    Craft::$app->getUsers()->suspendUser($member);
    expect($avatars->verifiedEmail($identities->getOrCreateForUser($member)))->toBeNull();
});

it('fetches a G-rated image by SHA-256 hash and keeps it, and remembers who has none', function() {
    Comments::$plugin->getSettings()->enableGravatar = true;
    $member = CommentsFixtureFactory::user('gravatarFetch');
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($member);
    $other = Comments::$plugin->getIdentities()->getOrCreateForUser(CommentsFixtureFactory::user('gravatarNone'));
    $requests = gravatarResponds(new Response(200, ['Content-Type' => 'image/png'], GRAVATAR_PNG), new Response(404));

    expect(Comments::$plugin->getAvatars()->refresh([(int)$identity->id, (int)$other->id]))->toBeTrue();

    $uri = $requests[0]['request']->getUri();
    parse_str($uri->getQuery(), $query);

    expect($uri->getHost())->toBe('gravatar.com')
        ->and($uri->getPath())->toBe('/avatar/' . hash('sha256', strtolower($member->email)))
        ->and($query)->toBe(['s' => '128', 'd' => '404', 'r' => 'g']);

    $row = avatarRow((int)$identity->id);
    expect((bool)$row['found'])->toBeTrue()
        ->and($row['mimeType'])->toBe('image/png')
        ->and(Comments::$plugin->getAvatars()->imageByUid($row['uid']))->toBe(['mimeType' => 'image/png', 'image' => GRAVATAR_PNG])
        ->and((bool)avatarRow((int)$other->id)['found'])->toBeFalse();
});

it('never keeps something that isn’t an image, and keeps the old copy when Gravatar is down', function() {
    Comments::$plugin->getSettings()->enableGravatar = true;
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser(CommentsFixtureFactory::user('gravatarBad'));
    gravatarResponds(new Response(200, ['Content-Type' => 'image/png'], '<svg onload="alert(1)"></svg>'));

    expect(Comments::$plugin->getAvatars()->refresh([(int)$identity->id]))->toBeFalse()
        ->and(avatarRow((int)$identity->id))->toBeNull();

    gravatarResponds(new Response(200, [], GRAVATAR_PNG), new Response(503));
    Comments::$plugin->getAvatars()->refresh([(int)$identity->id]);
    Comments::$plugin->getAvatars()->refresh([(int)$identity->id]);

    expect((bool)avatarRow((int)$identity->id)['found'])->toBeTrue();
});

it('forgets a copy once the address isn’t confirmed, and when the user is deleted', function() {
    Comments::$plugin->getSettings()->enableGravatar = true;
    $identities = Comments::$plugin->getIdentities();
    $guest = $identities->createHistoricalGuest('Guest', 'forget-gravatar@example.test');
    $guest->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $guest->save(false);
    gravatarResponds(new Response(200, [], GRAVATAR_PNG));
    Comments::$plugin->getAvatars()->refresh([(int)$guest->id]);

    expect(avatarRow((int)$guest->id))->not->toBeNull();

    $guest->verifiedAt = null;
    $guest->save(false);
    Comments::$plugin->getAvatars()->refresh([(int)$guest->id]);
    expect(avatarRow((int)$guest->id))->toBeNull();

    $member = CommentsFixtureFactory::user('gravatarDeleted');
    $identity = $identities->getOrCreateForUser($member);
    gravatarResponds(new Response(200, [], GRAVATAR_PNG));
    Comments::$plugin->getAvatars()->refresh([(int)$identity->id]);
    Craft::$app->getElements()->deleteElement($member);

    expect(avatarRow((int)$identity->id))->toBeNull();
});

it('shows the site’s copy after a user’s photo and before the placeholder, with no hash in the page', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->enableGravatar = true;
    $entry = CommentsFixtureFactory::entry('Gravatar thread owner');
    $member = CommentsFixtureFactory::user('gravatarThread');
    gifsAs($member);
    gifsCreate($entry, ['format' => 'plain', 'text' => 'Hello']);
    gifsAs($this->admin);
    $identity = Comments::$plugin->getIdentities()->findForUser((int)$member->id);

    gravatarResponds(new Response(200, [], GRAVATAR_PNG));
    Comments::$plugin->getAvatars()->refresh([(int)$identity->id]);

    $thread = Comments::$plugin->getApi()->thread($entry);
    $avatarUrl = $thread['comments'][0]['author']['avatarUrl'];
    $row = avatarRow((int)$identity->id);

    expect($avatarUrl)->toContain('comments/avatars/image')
        ->and($avatarUrl)->toContain($row['uid'])
        ->and(json_encode($thread))->not->toContain($row['emailHash'])
        ->and(json_encode($thread))->not->toContain('gravatar.com');

    // Off again, and the thread doesn't use it.
    $settings->enableGravatar = false;
    Comments::$plugin->getApi()->invalidateCaches();
    expect(Comments::$plugin->getApi()->thread($entry)['comments'][0]['author']['avatarUrl'] ?? '')->not->toContain('comments/avatars/image');
});
