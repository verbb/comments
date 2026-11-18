<?php
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\compatibility\gql\LegacyGql;
use verbb\comments\elements\Comment;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\events\FlagEvent;
use verbb\comments\events\ReportChangeEvent;
use verbb\comments\events\SubscribeEvent;
use verbb\comments\events\SubscriptionChangeEvent;
use verbb\comments\events\VoteChangeEvent;
use verbb\comments\events\VoteEvent;
use verbb\comments\exceptions\RefusedException;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Flags;
use verbb\comments\services\Interactions;
use verbb\comments\services\Reports;
use verbb\comments\services\Subscribe;
use verbb\comments\services\Subscriptions;
use verbb\comments\services\Votes;
use verbb\protect\checks\ReplayProtection;
use yii\base\Event;

/**
 * Events for votes, reports and subscriptions, which Comments 3 fired from its `Votes`, `Flags` and
 * `Subscribe` services, and the other Comments 3 behaviour compatibility mode keeps: those events under
 * their old names, the `searchTextAndNameOnly` query parameter and Comments 3's GraphQL clients.
 */

function engagementComment(): Comment
{
    $entry = CommentsFixtureFactory::entry('Engagement events owner');
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Something to vote on',
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
    ]))->comment;
}

function engagementIdentity(?User $user = null): int
{
    return (int)Comments::$plugin->getIdentities()->getOrCreateForUser($user ?? CommentsFixtureFactory::user('engagement'))->id;
}

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);

    $plugin = Comments::$plugin;
    $plugin->getInteractions()->off(Interactions::EVENT_BEFORE_VOTE);
    $plugin->getInteractions()->off(Interactions::EVENT_AFTER_VOTE);
    $plugin->getReports()->off(Reports::EVENT_BEFORE_REPORT);
    $plugin->getReports()->off(Reports::EVENT_AFTER_REPORT);
    $plugin->getReports()->off(Reports::EVENT_AFTER_WITHDRAW_REPORT);
    $plugin->getSubscriptions()->off(Subscriptions::EVENT_AFTER_CHANGE_SUBSCRIPTION);

    foreach ([
        Votes::class => [Votes::EVENT_BEFORE_SAVE_VOTE, Votes::EVENT_AFTER_SAVE_VOTE, Votes::EVENT_BEFORE_DELETE_VOTE, Votes::EVENT_AFTER_DELETE_VOTE],
        Flags::class => [Flags::EVENT_BEFORE_SAVE_FLAG, Flags::EVENT_AFTER_SAVE_FLAG, Flags::EVENT_BEFORE_DELETE_FLAG, Flags::EVENT_AFTER_DELETE_FLAG],
        Subscribe::class => [Subscribe::EVENT_BEFORE_SAVE_SUBSCRIBE, Subscribe::EVENT_AFTER_SAVE_SUBSCRIBE, Subscribe::EVENT_BEFORE_DELETE_SUBSCRIBE, Subscribe::EVENT_AFTER_DELETE_SUBSCRIBE],
    ] as $class => $names) {
        foreach ($names as $name) {
            Event::off($class, $name);
        }
    }
});

// Votes
// =========================================================================

it('tells listeners about a vote given, switched and taken back, with who voted', function() {
    $comment = engagementComment();
    $voter = CommentsFixtureFactory::user('engagementVoter');
    $identityId = engagementIdentity($voter);
    $seen = [];
    $interactions = Comments::$plugin->getInteractions();
    $interactions->on(Interactions::EVENT_AFTER_VOTE, function(VoteChangeEvent $event) use (&$seen) {
        $seen[] = [$event->previousType?->value, $event->type?->value, $event->user?->id, (int)$event->comment->id];
    });

    $interactions->set((int)$comment->id, $identityId, InteractionType::Upvote);
    // The same vote again changes nothing, so nothing is told.
    $interactions->set((int)$comment->id, $identityId, InteractionType::Upvote);
    $interactions->set((int)$comment->id, $identityId, InteractionType::Downvote);
    $interactions->remove((int)$comment->id, $identityId);

    expect($seen)->toBe([
        [null, 'upvote', (int)$voter->id, (int)$comment->id],
        ['upvote', 'downvote', (int)$voter->id, (int)$comment->id],
        ['downvote', null, (int)$voter->id, (int)$comment->id],
    ]);
});

it('lets a listener stop a vote', function() {
    $comment = engagementComment();
    $identityId = engagementIdentity();
    Comments::$plugin->getInteractions()->on(Interactions::EVENT_BEFORE_VOTE, fn(VoteChangeEvent $event) => $event->isValid = false);

    expect(fn() => Comments::$plugin->getInteractions()->set((int)$comment->id, $identityId, InteractionType::Upvote))->toThrow(RefusedException::class);
    expect(Comments::$plugin->getInteractions()->getForIdentity((int)$comment->id, $identityId))->toBeNull();
});

it('fires Comments 3’s vote events in compatibility mode only', function() {
    $comment = engagementComment();
    $voter = CommentsFixtureFactory::user('engagementLegacyVoter');
    $identityId = engagementIdentity($voter);
    $seen = [];
    Event::on(Votes::class, Votes::EVENT_AFTER_SAVE_VOTE, function(VoteEvent $event) use (&$seen) {
        $seen[] = ['save', $event->vote->commentId, $event->vote->userId, $event->vote->upvote, $event->vote->downvote, $event->isNew];
    });
    Event::on(Votes::class, Votes::EVENT_AFTER_DELETE_VOTE, function(VoteEvent $event) use (&$seen) {
        $seen[] = ['delete', $event->vote->commentId];
    });

    Comments::$plugin->getInteractions()->set((int)$comment->id, $identityId, InteractionType::Downvote);
    Comments::$plugin->getInteractions()->remove((int)$comment->id, $identityId);

    expect($seen)->toBe([
        ['save', (int)$comment->id, (int)$voter->id, 0, 1, true],
        ['delete', (int)$comment->id],
    ]);

    Comments::$plugin->getSettings()->compatibilityMode = false;
    Comments::$plugin->getInteractions()->set((int)$comment->id, $identityId, InteractionType::Upvote);

    expect($seen)->toHaveCount(2);
});

// Reports
// =========================================================================

it('tells listeners about a report and its withdrawal, and lets one stop a report', function() {
    $comment = engagementComment();
    $identityId = engagementIdentity();
    $reports = Comments::$plugin->getReports();
    $seen = [];
    $reports->on(Reports::EVENT_AFTER_REPORT, function(ReportChangeEvent $event) use (&$seen) {
        $seen[] = ['report', $event->reason, $event->report?->id !== null];
    });
    $reports->on(Reports::EVENT_AFTER_WITHDRAW_REPORT, function(ReportChangeEvent $event) use (&$seen) {
        $seen[] = ['withdraw', $event->reported];
    });
    $legacy = [];
    Event::on(Flags::class, Flags::EVENT_AFTER_SAVE_FLAG, function(FlagEvent $event) use (&$legacy) {
        $legacy[] = $event->flag->commentId;
    });

    $reports->create((int)$comment->id, $identityId, 'spam');
    $reports->withdrawOpen((int)$comment->id, $identityId);

    expect($seen)->toBe([['report', 'spam', true], ['withdraw', false]])
        ->and($legacy)->toBe([(int)$comment->id]);

    $reports->on(Reports::EVENT_BEFORE_REPORT, fn(ReportChangeEvent $event) => $event->isValid = false);

    expect(fn() => $reports->create((int)$comment->id, $identityId, 'spam'))->toThrow(RefusedException::class);
    expect($reports->getOpenForIdentity((int)$comment->id, $identityId))->toBeNull();
});

// Subscriptions
// =========================================================================

it('tells listeners when someone starts or stops following, but not when nothing changed', function() {
    $comment = engagementComment();
    $identityId = engagementIdentity();
    $conversationId = (int)$comment->conversationId;
    $subscriptions = Comments::$plugin->getSubscriptions();
    $seen = [];
    $subscriptions->on(Subscriptions::EVENT_AFTER_CHANGE_SUBSCRIPTION, function(SubscriptionChangeEvent $event) use (&$seen) {
        $seen[] = [$event->type->value, $event->subscribed];
    });
    $legacy = [];
    Event::on(Subscribe::class, Subscribe::EVENT_AFTER_SAVE_SUBSCRIBE, function(SubscribeEvent $event) use (&$legacy) {
        $legacy[] = [$event->subscribe->ownerId, $event->subscribe->subscribed];
    });

    $subscriptions->subscribeToConversation($conversationId, $identityId);
    $subscriptions->subscribeToConversation($conversationId, $identityId);
    $subscriptions->unsubscribe($conversationId, $identityId, null, SubscriptionType::Conversation);

    expect($seen)->toBe([['conversation', true], ['conversation', false]])
        ->and($legacy)->toBe([[(int)$comment->ownerId, true]]);
});

// Queries
// =========================================================================

it('takes Comments 3’s searchTextAndNameOnly parameter', function() {
    $query = Comment::find()->searchTextAndNameOnly(true)->search('anything');

    expect($query->searchTextAndNameOnly)->toBeTrue()
        ->and($query->count())->toBeInt();
});

// GraphQL
// =========================================================================

it('accepts and ignores Comments 3’s userId and commentDate in compatibility mode only', function() {
    $arguments = LegacyGql::mutationArguments();

    expect($arguments)->toHaveKeys(['newParentId', 'userId', 'commentDate'])
        ->and(LegacyGql::withoutIgnoredArguments(['comment' => 'Hi', 'userId' => 1, 'commentDate' => '2020-01-01']))->toBe(['comment' => 'Hi']);

    Comments::$plugin->getSettings()->compatibilityMode = false;

    expect(LegacyGql::mutationArguments())->toBe([]);
});

it('checks a Comments 3 GraphQL write without a preparation in compatibility mode, and every other check still applies', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->enableSpamChecks = true;
    $settings->replayProtectionEnabled = true;
    $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
    $replay = static function() use ($siteId): bool {
        foreach (Comments::$plugin->getProtection()->plan($siteId, false, true)->checks() as $check) {
            if ($check instanceof ReplayProtection) {
                return $check->enabled;
            }
        }

        return false;
    };

    expect($replay())->toBeTrue()
        ->and(LegacyGql::write([], $replay))->toBeFalse()
        // Evidence sent, or compatibility mode off, keeps replay protection.
        ->and(LegacyGql::write(['protection' => ['version' => 1]], $replay))->toBeTrue();

    $settings->compatibilityMode = false;

    expect(LegacyGql::write([], $replay))->toBeTrue()
        ->and($replay())->toBeTrue();
});
