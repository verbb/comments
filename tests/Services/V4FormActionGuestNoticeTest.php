<?php

declare(strict_types=1);

use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use Tests\Support\SpamSettingsStore;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;

/**
 * Guest Notice on the Comments 3 form action, through real requests: while guests can't comment, a
 * signed-out visitor's post to `comments/comments/save` gets Guest Notice, or "Must be logged in to
 * comment." without one, before the element's own rules, as the GraphQL mutation does. A member is
 * still held to those rules.
 */

const FAGN_PASSWORD = 'Comments-Guest-Notice-Http-1!';
const FAGN_NOTICE = 'Sign in to join this conversation.';

function fagnUser(string $prefix): User
{
    $user = CommentsFixtureFactory::user($prefix);
    $user->newPassword = FAGN_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    return $user;
}

function fagnBrowser(?User $user = null): HttpClient
{
    // Craft limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();

    if ($user) {
        $browser->login((string)$user->username, FAGN_PASSWORD);
    }

    return $browser;
}

/** Run `$callback` with these settings in project config, which the web app reads, then put them back. */
function fagnWithSettings(array $values, callable $callback): mixed
{
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = [];

    foreach ($values as $name => $value) {
        $previous[$name] = $projectConfig->get('plugins.comments.settings.' . $name);
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
    }

    $projectConfig->saveModifiedConfigData();

    try {
        return $callback();
    } finally {
        foreach ($previous as $name => $value) {
            // A value Craft would treat as unchanged is changed first, or its return isn't saved.
            $projectConfig->set('plugins.comments.settings.' . $name, 'guest-notice');
            $projectConfig->set('plugins.comments.settings.' . $name, $value);
        }

        $projectConfig->saveModifiedConfigData();
    }
}

function fagnSave(HttpClient $browser, $entry, string $text = 'Through the form action'): array
{
    return $browser->post('/actions/comments/comments/save', [
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => $text, 'name' => 'Guest Poster', 'email' => 'guest-poster@example.test'],
    ]);
}

function fagnRefusal(array $response): ?string
{
    return $response['data']['errors']['comment'][0] ?? null;
}

beforeAll(function() {
    $projectConfig = Craft::$app->getProjectConfig();
    $GLOBALS['fagnConfig'] = $projectConfig->get('plugins.comments.settings');
    $GLOBALS['fagnSpam'] = SpamSettingsStore::snapshot();
    $GLOBALS['fagnSettings'] = Comments::$plugin->getSettings()->getAttributes();

    foreach ([
        'enableSpamChecks' => false,
        'requireModeration' => false,
        'allowGuest' => false,
        'guestNotice' => FAGN_NOTICE,
        'guestRequireEmailName' => false,
        'participantNotifications' => [],
        'staffNotifications' => [],
    ] as $name => $value) {
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
        SpamSettingsStore::apply([$name => $value]);
    }

    $projectConfig->saveModifiedConfigData();

    // A site page that shows Craft's flash messages and `errors`, as a Comments 3 template would.
    craft\helpers\FileHelper::writeToFile(
        Craft::$app->getPath()->getSiteTemplatesPath() . '/guest-notice-form.twig',
        'notice={{ craft.app.session.getFlash(\'notice\') }}|error={{ craft.app.session.getFlash(\'error\') }}|errors={{ (errors ?? null) | json_encode | raw }}',
    );

    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
    SpamSettingsStore::restore($GLOBALS['fagnSpam']);
    @unlink(Craft::$app->getPath()->getSiteTemplatesPath() . '/guest-notice-form.twig');
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = $GLOBALS['fagnConfig'];

    if ($previous === null) {
        $projectConfig->remove('plugins.comments.settings');
    } else {
        $projectConfig->set('plugins.comments.settings', $previous);
    }

    $projectConfig->saveModifiedConfigData();
    Comments::$plugin->getSettings()->setAttributes($GLOBALS['fagnSettings'], false);
});

beforeEach(function() {
    Craft::$app->getCache()->flush();
});

it('gives a signed-out visitor Guest Notice from the form action while guests can’t comment', function() {
    $entry = CommentsFixtureFactory::entry('Guest notice open owner');
    $closed = CommentsFixtureFactory::entry('Guest notice closed owner');
    Comments::$plugin->getConversations()->setClosed((int)$closed->id, (int)$closed->siteId, true);
    $browser = fagnBrowser();

    $open = fagnSave($browser, $entry);
    $onClosed = fagnSave($browser, $closed);

    expect($open['status'])->toBe(200, $open['body'])
        ->and($open['data']['success'] ?? null)->toBeFalse()
        ->and(fagnRefusal($open))->toBe(FAGN_NOTICE)
        // Before the element's own rules, as for GraphQL.
        ->and(fagnRefusal($onClosed))->toBe(FAGN_NOTICE)
        ->and(Comment::find()->ownerId([$entry->id, $closed->id])->status(null)->count())->toEqual(0);
});

it('falls back to “Must be logged in to comment.” without a Guest Notice', function() {
    $entry = CommentsFixtureFactory::entry('Guest notice blank owner');
    $response = fagnWithSettings(['guestNotice' => null], fn() => fagnSave(fagnBrowser(), $entry));

    expect($response['data']['success'] ?? null)->toBeFalse($response['body'])
        ->and(fagnRefusal($response))->toBe('Must be logged in to comment.')
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(0);
});

it('gives a plain form post Guest Notice as Craft’s error, with the page rendered again', function() {
    $entry = CommentsFixtureFactory::entry('Guest notice plain form owner');
    $browser = fagnBrowser();
    $response = $browser->request('POST', '/guest-notice-form', [
        'headers' => ['Accept' => 'text/html'],
        'form_params' => [
            'CRAFT_CSRF_TOKEN' => $browser->csrfToken(),
            'action' => 'comments/comments/save',
            'elementId' => $entry->id,
            'siteId' => $entry->siteId,
            'fields' => ['comment' => 'Posted without JavaScript'],
        ],
    ]);

    expect($response['status'])->toBe(200, $response['body'])
        ->and($response['body'])->toBe('notice=|error=' . FAGN_NOTICE . '|errors=' . json_encode(['comment' => [FAGN_NOTICE]]))
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(0);
});

it('still holds members to the element’s rules, and still takes guests when they’re allowed', function() {
    $entry = CommentsFixtureFactory::entry('Guest notice member owner');
    $closed = CommentsFixtureFactory::entry('Guest notice member closed owner');
    Comments::$plugin->getConversations()->setClosed((int)$closed->id, (int)$closed->siteId, true);
    $member = fagnBrowser(fagnUser('fagnMember'));

    $posted = fagnSave($member, $entry, 'From a member');
    $refused = fagnSave($member, $closed, 'Onto a closed thread');

    expect($posted['data']['success'] ?? null)->toBeTrue($posted['body'])
        ->and(fagnRefusal($refused))->toBe('Comment not allowed.');

    [$guestPosted, $guestClosed] = fagnWithSettings(['allowGuest' => true], fn() => [
        fagnSave(fagnBrowser(), $entry, 'From a guest'),
        fagnSave(fagnBrowser(), $closed, 'From a guest, closed'),
    ]);

    expect($guestPosted['data']['success'] ?? null)->toBeTrue($guestPosted['body'])
        ->and(fagnRefusal($guestClosed))->toBe('Comment not allowed.')
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(2)
        ->and(Comment::find()->ownerId($closed->id)->status(null)->count())->toEqual(0);
});
