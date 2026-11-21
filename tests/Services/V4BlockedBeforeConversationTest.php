<?php
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\models\CommentSubmission;

/**
 * A block everywhere applies on an element nobody has commented on yet: `viewer` and `session` say the
 * commenter can't comment or follow, as posting does. A block on one conversation stays there.
 */

function blockedFirstAs(?User $user): void
{
    Craft::$app->getUser()->setIdentity($user);
    Comments::$plugin->getIdentities()->clearCaches();
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getApi()->invalidateCaches();
}

function blockedFirstCode(callable $callback): ?string
{
    try {
        $callback();
    } catch (Throwable $e) {
        return Comments::$plugin->getApi()->errorFor($e)->errorCode;
    }

    return null;
}

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = ['conversation'];
    $this->member = CommentsFixtureFactory::user('blockedFirst' . bin2hex(random_bytes(3)));
    $this->identity = Comments::$plugin->getIdentities()->getOrCreateForUser($this->member);
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
    blockedFirstAs($this->admin);
});

it('tells a commenter blocked everywhere they can’t comment or follow on an element with no conversation yet', function() {
    $fresh = CommentsFixtureFactory::entry('Blocked first ' . uniqid());
    Comments::$plugin->getModeration()->blockIdentity((int)$this->identity->id, null, $this->admin, 'Everywhere');
    blockedFirstAs($this->member);

    expect(Comments::$plugin->getConversations()->findForOwner((int)$fresh->id, (int)$fresh->siteId))->toBeNull();

    $viewer = Comments::$plugin->getApi()->viewer($fresh);
    $session = Comments::$plugin->getApi()->session($fresh, null);

    expect($viewer['viewer'])->toMatchArray(['canComment' => false, 'blocked' => true])
        ->and($viewer['viewer']['follow']['available'])->toBeFalse()
        ->and($session['viewer'])->toMatchArray(['canComment' => false, 'blocked' => true])
        ->and($session['viewer']['follow']['available'])->toBeFalse()
        ->and(blockedFirstCode(fn() => Comments::$plugin->getApi()->create(['ownerId' => $fresh->id, 'content' => ['format' => 'plain', 'text' => 'First']])))->toBe('blocked');
});

it('keeps a block on one conversation to that conversation', function() {
    $blockedHere = CommentsFixtureFactory::entry('Blocked here ' . uniqid());
    $fresh = CommentsFixtureFactory::entry('Free there ' . uniqid());
    Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $blockedHere->id, 'siteId' => $blockedHere->siteId, 'content' => 'Before the block', 'userId' => (int)$this->member->id]));
    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$blockedHere->id, (int)$blockedHere->siteId);
    Comments::$plugin->getModeration()->blockIdentity((int)$this->identity->id, (int)$conversation->id, $this->admin, 'Here only');
    blockedFirstAs($this->member);

    expect(Comments::$plugin->getApi()->viewer($blockedHere)['viewer'])->toMatchArray(['canComment' => false, 'blocked' => true])
        ->and(Comments::$plugin->getApi()->viewer($fresh)['viewer'])->toMatchArray(['canComment' => true, 'blocked' => false])
        ->and(Comments::$plugin->getApi()->viewer($fresh)['viewer']['follow']['available'])->toBeTrue();
});
