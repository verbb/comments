<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationMode;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use verbb\comments\records\Conversation as ConversationRecord;

it('resolves ConversationConfig from global settings', function() {
    $entry = CommentsFixtureFactory::entry('Config global owner');
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->allowGuest = true;
    $settings->orderBy = 'asc';
    $settings->closed = false;

    $config = Comments::$plugin->getConversations()->getConfigForOwner($entry->id, $entry->siteId);

    expect($config->enabled)->toBeTrue();
    expect($config->closed)->toBeFalse();
    expect($config->moderationMode)->toBe(ModerationMode::Pre);
    expect($config->allowGuests)->toBeTrue();
    expect($config->sortOrder)->toBe(CommentSort::Oldest);
    expect($config->requiresModeration())->toBeTrue();
});

it('applies Conversation overrides to resolved config', function() {
    $entry = CommentsFixtureFactory::entry('Config override owner');
    Comments::$plugin->getSettings()->requireModeration = false;

    $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner($entry->id, $entry->siteId);
    $conversation->closed = true;
    $conversation->moderationMode = ModerationMode::Pre->value;
    $conversation->allowGuests = false;
    $conversation->sortOrder = CommentSort::Oldest->value;
    $conversation->save(false);
    Comments::$plugin->getConversations()->clearCaches();

    $config = Comments::$plugin->getConversations()->getConfigForOwner($entry->id, $entry->siteId);

    expect($config->conversationId)->toBe((int)$conversation->id);
    expect($config->isCommentingEnabled())->toBeFalse();
    expect($config->closed)->toBeTrue();
    expect($config->moderationMode)->toBe(ModerationMode::Pre);
    expect($config->allowGuests)->toBeFalse();
    expect($config->sortOrder)->toBe(CommentSort::Oldest);
});

it('resolves source permission settings into the effective conversation config', function() {
    $entry = CommentsFixtureFactory::entry('Config disabled source owner');
    Comments::$plugin->getSettings()->permissions = [
        \craft\elements\Entry::class => ['not-the-owner-section-uid'],
    ];
    Comments::$plugin->getConversations()->clearCaches();

    $config = Comments::$plugin->getConversations()->getConfigForOwner($entry->id, $entry->siteId);

    expect($config->enabled)->toBeFalse();
    expect($config->isCommentingEnabled())->toBeFalse();
});

it('honours a conversation guest override during canonical create validation', function() {
    $entry = CommentsFixtureFactory::entry('Config guest override owner');
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = false;
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner($entry->id, $entry->siteId);
    $conversation->allowGuests = true;
    $conversation->save(false);
    Comments::$plugin->getConversations()->clearCaches();
    Craft::$app->getUser()->setIdentity(null);

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Guest override comment',
        'name' => 'Guest Author',
        'email' => 'guest-override@example.test',
    ]));

    expect($result->comment->userId)->toBeNull();
    expect($result->comment->identityId)->not->toBeNull();
});

it('blocks create when Conversation config is closed', function() {
    $entry = CommentsFixtureFactory::entry('Config closed owner');
    Comments::$plugin->getSettings()->requireModeration = false;

    $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner($entry->id, $entry->siteId);
    $conversation->closed = true;
    $conversation->save(false);
    Comments::$plugin->getConversations()->clearCaches();

    $admin = Craft::$app->getUser()->getIdentity();

    expect(fn() => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Should fail',
        'userId' => (int)$admin->id,
    ])))->toThrow(\yii\base\UserException::class);
});

it('uses ConversationConfig moderation mode for create', function() {
    $entry = CommentsFixtureFactory::entry('Config pre-mod owner');
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner($entry->id, $entry->siteId);
    $conversation->moderationMode = ModerationMode::Pre->value;
    $conversation->save(false);
    Comments::$plugin->getConversations()->clearCaches();

    $admin = Craft::$app->getUser()->getIdentity();

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pre moderated via config',
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Pending);
});

it('toggles votes with v3 semantics', function() {
    $entry = CommentsFixtureFactory::entry('Toggle vote owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Toggle me',
        'userId' => (int)$admin->id,
    ]));

    $commentId = (int)$result->comment->id;
    $identityId = (int)$result->comment->identityId;
    $interactions = Comments::$plugin->getInteractions();

    // Re-applying the active type clears it; applying the opposite type switches to it.
    expect($interactions->toggleVote($commentId, $identityId, true))->toBe(InteractionType::Upvote);
    expect($interactions->toggleVote($commentId, $identityId, true))->toBeNull();
    expect($interactions->toggleVote($commentId, $identityId, false))->toBe(InteractionType::Downvote);
    expect($interactions->toggleVote($commentId, $identityId, true))->toBe(InteractionType::Upvote);
    expect($interactions->toggleVote($commentId, $identityId, true))->toBeNull();
});

it('toggles reports open and withdrawn', function() {
    $entry = CommentsFixtureFactory::entry('Toggle report owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->staffNotifications = [];
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Flag me',
        'userId' => (int)$admin->id,
    ]));

    $commentId = (int)$result->comment->id;
    $identityId = (int)$result->comment->identityId;
    $reports = Comments::$plugin->getReports();

    expect($reports->toggle($commentId, $identityId))->toBeTrue();
    expect($reports->toggle($commentId, $identityId))->toBeFalse();
    expect($reports->getOpenCounts([$commentId])[$commentId])->toBe(0);
});
