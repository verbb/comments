<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

/**
 * Phase 6.8 — moderator controls exposed through the public frontend surface.
 */

it('hides unmoderated comments from the public conversation view', function() {
    $entry = CommentsFixtureFactory::entry('Frontend moderation public read');
    $author = CommentsFixtureFactory::user('frontend-mod-author');

    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pending comment awaiting review',
        'userId' => (int)$author->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Pending);

    // The console harness can't drop the logged-in identity, so assert the public read contract
    // directly: with no explicit state filter, only Approved Comments are ever returned.
    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId);
    $page = Comments::$plugin->getConversations()->getRootComments((int)$conversation->id);

    expect($page->comments)->toBeEmpty();
    expect(Comments::$plugin->getModeration()->canModerateComment(null))->toBeFalse();
});

it('surfaces pending comments with inline moderation actions for moderators', function() {
    $entry = CommentsFixtureFactory::entry('Frontend moderation moderator read');
    $author = CommentsFixtureFactory::user('frontend-mod-author-2');

    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pending comment for moderator',
        'userId' => (int)$author->id,
    ]));

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);

    expect($view->isModerator)->toBeTrue();
    expect($view->roots)->not->toBeEmpty();

    $row = $view->roots[0];
    expect($row->state)->toBe(ModerationState::Pending->value);
    expect($row->moderatorOnly)->toBeTrue();
    expect($row->moderationActions)->toContain('moderate-approve');
    expect($row->moderationActions)->toContain('moderate-spam');

    // Unmoderated rows are not votable, even in an enabled interaction mode.
    expect($row->interaction)->toBeNull();
});

it('never lets a moderator moderate their own comment from the public surface', function() {
    $entry = CommentsFixtureFactory::entry('Frontend moderation self');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'My own comment',
        'userId' => (int)$admin->id,
    ]));

    $moderation = Comments::$plugin->getModeration();

    expect($moderation->canModerateComment($admin))->toBeTrue();
    expect($moderation->canModerateComment($admin, $result->comment))->toBeFalse();

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);
    expect($view->roots[0]->moderationActions)->toBeEmpty();
});

it('keeps moderator affordances out of shared-cache shells', function() {
    $entry = CommentsFixtureFactory::entry('Frontend moderation shared cache');
    $author = CommentsFixtureFactory::user('frontend-mod-author-3');

    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pending comment, shared cache',
        'userId' => (int)$author->id,
    ]));

    $view = Comments::$plugin->getFrontend()->buildConversationView(
        (int)$entry->id,
        (int)$entry->siteId,
        null,
        20,
        null,
        true,
    );

    expect($view->isModerator)->toBeFalse();
    expect($view->roots)->toBeEmpty();
});
