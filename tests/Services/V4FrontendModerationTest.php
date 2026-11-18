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

    // The console harness can't drop the logged-in identity, so assert what a public read returns
    // directly: with no explicit state filter, only Approved Comments are ever returned.
    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId);
    $page = Comments::$plugin->getConversations()->getRootComments((int)$conversation->id);

    expect($page->comments)->toBeEmpty();
    expect(Comments::$plugin->getModeration()->canModerateComment(null))->toBeFalse();
});

it('describes pending comments in full, with their decisions, only for moderators', function() {
    $entry = CommentsFixtureFactory::entry('Frontend moderation moderator read');
    $author = CommentsFixtureFactory::user('frontend-mod-author-2');

    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pending comment for moderator',
        'userId' => (int)$author->id,
    ]))->comment;

    $frontend = Comments::$plugin->getFrontend();
    $admin = Craft::$app->getUser()->getIdentity();

    // Described in full only for the people allowed to see it, as the data API's `viewer` read does.
    expect($frontend->buildDetachedCommentViews([$comment], true)[(int)$comment->id]->state)->toBe(ModerationState::Pending->value)
        ->and($frontend->buildDetachedCommentViews([$comment])[(int)$comment->id]->placeholder)->toBeTrue()
        ->and($frontend->moderationDecisions($comment, $admin))->toContain('approve', 'spam')
        // Unmoderated comments are not votable, even in an enabled interaction mode.
        ->and($frontend->viewerStatesFor([$comment])[(int)$comment->id]['canVote'])->toBeFalse();
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

    expect(Comments::$plugin->getFrontend()->moderationDecisions($result->comment, $admin))->toBeEmpty();
});
