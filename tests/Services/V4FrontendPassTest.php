<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\models\CommentSubmission;

/**
 * The public frontend from the 2 November 2026 review, stage 6. Its Twig thread and no-JavaScript
 * checks went with the switch-over; what's left is about comments and visitors, whatever draws them.
 */

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->allowGuest = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
});

afterEach(function() {
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    $request = Craft::$app->getRequest();
    $request->setQueryParams([]);
    $request->setBodyParams([]);
    $request->setIsConsoleRequest(true);
    unset($_SERVER['REQUEST_METHOD']);
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getIdentities()->clearCaches();
});

function passComment($entry, string $content = 'Comment', ?int $parentId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
        'parentId' => $parentId,
    ], static fn($v) => $v !== null)))->comment;
}

it('says plainly what’s wrong with a comment, field by field', function() {
    $entry = CommentsFixtureFactory::entry('Validation owner');

    Craft::$app->getUser()->setIdentity(null);

    try {
        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'No name given',
            'email' => 'a@example.test',
        ]));
        $this->fail('Expected a validation error.');
    } catch (CommentValidationException $e) {
        expect($e->getMessage())->toBe('Name is required.')->not->toContain('{')
            ->and($e->fieldErrors)->toBe(['fields[name]' => ['Name is required.']]);
    }

    $translated = CommentValidationException::fromErrors(['field:city' => ['City cannot be blank.'], 'comment' => ['Too long.', 'Also wrong.']]);

    expect(array_keys($translated->fieldErrors))->toBe(['fields[city]', 'fields[comment]'])
        ->and($translated->getMessage())->toBe('City cannot be blank. Too long.');
});

it('stops offering Reply at the deepest level comments can go', function() {
    $entry = CommentsFixtureFactory::entry('Reply depth owner');
    $root = passComment($entry, 'Root');
    $reply = passComment($entry, 'Reply', (int)$root->id);
    Comments::$plugin->getSettings()->maxReplyDepth = 1;

    $states = Comments::$plugin->getFrontend()->viewerStatesFor([$root, $reply]);

    expect($states[(int)$root->id]['canReply'])->toBeTrue()
        ->and($states[(int)$reply->id]['canReply'])->toBeFalse();
});

it('recognises a guest in the same request that first gave them a cookie', function() {
    $identities = Comments::$plugin->getIdentities();
    Craft::$app->getUser()->setIdentity(null);
    Craft::$app->getRequest()->setIsConsoleRequest(false);

    expect($identities->findGuestForRequest())->toBeNull();

    $identity = $identities->resolveForCurrentRequest('Sam', 'first-visit@example.test');

    // Their vote has to come back pressed, though their browser hasn't sent the cookie back yet.
    expect((int)$identities->findGuestForRequest()?->id)->toBe((int)$identity->id);

    $identities->clearCaches();
});
