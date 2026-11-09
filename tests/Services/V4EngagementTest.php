<?php
use craft\db\Query;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\ReportStatus;
use verbb\comments\enums\RestrictionType;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\models\Vote;
use verbb\comments\services\Moderation;
use verbb\comments\services\Notifications;
use yii\base\UserException;

it('keeps the legacy GraphQL vote result model independent of retired vote records', function() {
    $vote = new Vote([
        'commentId' => 123,
        'userId' => 456,
        'upvote' => 1,
    ]);

    expect($vote->validate())->toBeTrue();
});

it('approves pending comments and records moderation actions', function() {
    $entry = CommentsFixtureFactory::entry('Moderation approve owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = true;
    Comments::$plugin->getSettings()->staffNotifications = [];
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->subscriptionTypes = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Needs approval',
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Pending);

    $approved = Comments::$plugin->getModeration()->approve($result->comment, $admin);
    expect($approved->moderationState)->toBe(ModerationState::Approved->value);
    expect($approved->status)->toBe(\verbb\comments\elements\Comment::STATUS_APPROVED);

    $actions = Comments::$plugin->getModeration()->getActions((int)$approved->id);
    expect($actions)->not->toBeEmpty();
    expect($actions[0]->action)->toBe(Moderation::ACTION_APPROVE);
    expect($actions[0]->fromState)->toBe(ModerationState::Pending->value);
    expect($actions[0]->toState)->toBe(ModerationState::Approved->value);
});

it('marks spam, restores via markNotSpam, and supports notes and restrictions', function() {
    $entry = CommentsFixtureFactory::entry('Moderation spam owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->staffNotifications = [];
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->subscriptionTypes = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Maybe spam',
        'userId' => (int)$admin->id,
    ]));

    $spam = Comments::$plugin->getModeration()->markSpam($result->comment, $admin, 'keyword');
    expect($spam->moderationState)->toBe(ModerationState::Spam->value);

    $clean = Comments::$plugin->getModeration()->markNotSpam($spam, $admin);
    expect($clean->moderationState)->toBe(ModerationState::Pending->value);

    $note = Comments::$plugin->getModeration()->addNote($clean, 'Looks fine after review', $admin);
    expect($note->note)->toBe('Looks fine after review');
    expect(Comments::$plugin->getModeration()->getNotes((int)$clean->id))->toHaveCount(1);

    $identityId = (int)$clean->identityId;
    $restriction = Comments::$plugin->getModeration()->blockIdentity(
        $identityId,
        (int)$clean->conversationId,
        $admin,
        'abuse',
    );
    expect($restriction->type)->toBe(RestrictionType::Blocked->value);
    expect(Comments::$plugin->getModeration()->isBlocked($identityId, (int)$clean->conversationId))->toBeTrue();

    Comments::$plugin->getModeration()->revokeRestriction((int)$restriction->id, $admin);
    expect(Comments::$plugin->getModeration()->isBlocked($identityId, (int)$clean->conversationId))->toBeFalse();
});

it('upserts interactions and batches totals', function() {
    $entry = CommentsFixtureFactory::entry('Interaction owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Vote me',
        'userId' => (int)$admin->id,
    ]));

    $commentId = (int)$result->comment->id;
    $identityId = (int)$result->comment->identityId;

    $up = Comments::$plugin->getInteractions()->set($commentId, $identityId, InteractionType::Upvote);
    expect($up->type)->toBe(InteractionType::Upvote->value);

    $down = Comments::$plugin->getInteractions()->set($commentId, $identityId, InteractionType::Downvote);
    expect($down->id)->toBe($up->id);
    expect($down->type)->toBe(InteractionType::Downvote->value);

    $totals = Comments::$plugin->getInteractions()->getTotals([$commentId]);
    expect($totals[$commentId]['downvote'])->toBe(1);
    expect($totals[$commentId]['upvote'])->toBe(0);

    expect(Comments::$plugin->getInteractions()->remove($commentId, $identityId))->toBeTrue();
    expect(Comments::$plugin->getInteractions()->getForIdentity($commentId, $identityId))->toBeNull();
});

it('creates unique open reports and can dismiss them', function() {
    $entry = CommentsFixtureFactory::entry('Report owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Report me',
        'userId' => (int)$admin->id,
    ]));

    $commentId = (int)$result->comment->id;
    $identityId = (int)$result->comment->identityId;

    $report = Comments::$plugin->getReports()->create($commentId, $identityId, null, 'legacy flag');
    expect($report->status)->toBe(ReportStatus::Open->value);

    expect(fn() => Comments::$plugin->getReports()->create($commentId, $identityId))
        ->toThrow(UserException::class);

    expect(Comments::$plugin->getReports()->getOpenCounts([$commentId])[$commentId])->toBe(1);

    $dismissed = Comments::$plugin->getReports()->dismiss((int)$report->id, $admin);
    expect($dismissed->status)->toBe(ReportStatus::Dismissed->value);
    expect(Comments::$plugin->getReports()->getOpenCounts([$commentId])[$commentId])->toBe(0);
});

it('subscribes and unsubscribes without deleting opt-out rows', function() {
    $entry = CommentsFixtureFactory::entry('Subscription owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Subscribe thread',
        'userId' => (int)$admin->id,
    ]));

    $conversationId = (int)$result->comment->conversationId;
    $identityId = (int)$result->comment->identityId;
    $commentId = (int)$result->comment->id;

    $sub = Comments::$plugin->getSubscriptions()->subscribeToConversation($conversationId, $identityId);
    expect($sub->subscribed)->toBeTruthy();
    expect($sub->type)->toBe(SubscriptionType::Conversation->value);

    $optOut = Comments::$plugin->getSubscriptions()->unsubscribe($conversationId, $identityId);
    expect($optOut->id)->toBe($sub->id);
    expect((bool)$optOut->subscribed)->toBeFalse();

    // Durable negative state remains as a row.
    $row = (new Query())
        ->from('{{%comments_subscriptions}}')
        ->where(['id' => $optOut->id])
        ->one();
    expect($row)->not->toBeNull();
    expect((bool)$row['subscribed'])->toBeFalse();

    $replySub = Comments::$plugin->getSubscriptions()->subscribeToReplies($conversationId, $identityId, $commentId);
    expect($replySub->type)->toBe(SubscriptionType::Replies->value);
    expect(Comments::$plugin->getSubscriptions()->isSubscribed(
        $conversationId,
        $identityId,
        $commentId,
        SubscriptionType::Replies,
    ))->toBeTrue();
});

it('applies the configured reply subscription default and preserves opt-outs', function() {
    $entry = CommentsFixtureFactory::entry('Default reply subscription owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [SubscriptionType::Replies->value];
    $settings->subscriptionDefault = Settings::SUBSCRIPTION_DEFAULT_REPLIES;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Subscribe me to replies by default',
        'userId' => (int)$admin->id,
    ]))->comment;

    $subscriptions = Comments::$plugin->getSubscriptions();
    $subscription = $subscriptions->get(
        (int)$comment->conversationId,
        (int)$comment->identityId,
        (int)$comment->id,
        SubscriptionType::Replies,
    );

    expect($subscription)->not->toBeNull();
    expect((bool)$subscription->subscribed)->toBeTrue();

    $subscriptions->unsubscribe(
        (int)$comment->conversationId,
        (int)$comment->identityId,
        (int)$comment->id,
        SubscriptionType::Replies,
    );
    $subscriptions->applyDefaultForComment($comment);

    expect((bool)$subscriptions->get(
        (int)$comment->conversationId,
        (int)$comment->identityId,
        (int)$comment->id,
        SubscriptionType::Replies,
    )->subscribed)->toBeFalse();
});

it('queues the dedicated staff event when an author edits a pending comment', function() {
    $entry = CommentsFixtureFactory::entry('Pending author edit owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->useQueueForNotifications = true;
    $settings->enableSpamChecks = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [Settings::NOTIFICATION_MODERATOR_EDITED];
    $settings->subscriptionTypes = [];

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pending before edit',
        'userId' => (int)$admin->id,
    ]))->comment;

    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();

    $comment->setScenario(\verbb\comments\elements\Comment::SCENARIO_FRONT_END);
    $comment->comment = 'Pending after author edit';
    $saved = Craft::$app->getElements()->saveElement($comment);

    if (!$saved) {
        throw new RuntimeException(json_encode($comment->getErrors(), JSON_THROW_ON_ERROR));
    }

    expect($saved)->toBeTrue();

    $types = [];
    foreach ((new Query())->from('{{%queue}}')->all() as $job) {
        $payload = unserialize($job['job']);
        if ($payload instanceof \verbb\comments\queue\jobs\SendNotification) {
            $types[] = $payload->type;
        }
    }

    expect($types)->toContain(Notifications::TYPE_MODERATOR_EDIT);
});

it('queues moderator notification for pending create but not participant types', function() {
    $entry = CommentsFixtureFactory::entry('Notification pending owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->useQueueForNotifications = true;
    $settings->participantNotifications = [
        Settings::NOTIFICATION_OWNER,
        Settings::NOTIFICATION_REPLY,
    ];
    $settings->staffNotifications = [Settings::NOTIFICATION_MODERATOR_PENDING];
    $settings->subscriptionTypes = [SubscriptionType::Conversation->value];

    // Clear any leftover jobs from prior tests.
    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Pending notify',
        'userId' => (int)$admin->id,
    ]));

    $jobs = (new Query())->from('{{%queue}}')->all();
    $types = [];
    foreach ($jobs as $job) {
        $payload = unserialize($job['job']);
        if ($payload instanceof \verbb\comments\queue\jobs\SendNotification) {
            $types[] = $payload->type;
        }
    }

    expect($types)->toContain(Notifications::TYPE_MODERATOR);
    expect($types)->not->toContain(Notifications::TYPE_AUTHOR);
    expect($types)->not->toContain(Notifications::TYPE_REPLY);
    expect($types)->not->toContain(Notifications::TYPE_SUBSCRIBE);
});

it('enforces active identity restrictions during canonical create', function() {
    $entry = CommentsFixtureFactory::entry('Restricted identity owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $first = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Initial participation',
        'userId' => (int)$admin->id,
    ]))->comment;

    $moderation = Comments::$plugin->getModeration();
    $moderation->moderateIdentity((int)$first->identityId, (int)$first->conversationId, $admin);

    $held = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Held by identity restriction',
        'userId' => (int)$admin->id,
    ]));

    expect($held->moderationState)->toBe(ModerationState::Pending);

    $moderation->blockIdentity((int)$first->identityId, (int)$first->conversationId, $admin);

    expect(function() use ($entry, $admin): void {
        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'Blocked participation',
            'userId' => (int)$admin->id,
        ]));
    })->toThrow(\yii\base\UserException::class);
});
