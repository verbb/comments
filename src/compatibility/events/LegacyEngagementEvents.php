<?php
namespace verbb\comments\compatibility\events;

use verbb\comments\enums\InteractionType;
use verbb\comments\events\FlagEvent;
use verbb\comments\events\ReportChangeEvent;
use verbb\comments\events\SubscribeEvent;
use verbb\comments\events\SubscriptionChangeEvent;
use verbb\comments\events\VoteChangeEvent;
use verbb\comments\events\VoteEvent;
use verbb\comments\models\Flag;
use verbb\comments\models\Subscribe;
use verbb\comments\models\Vote;
use verbb\comments\services\Compatibility;
use verbb\comments\services\Flags;
use verbb\comments\services\Subscribe as SubscribeService;
use verbb\comments\services\Votes;

use yii\base\Event;

/**
 * Comments 3's vote, flag and subscribe events, fired beside Comments 4's own in compatibility mode, so
 * a module listening on `Votes::class`, `Flags::class` or `Subscribe::class` keeps hearing about them.
 * They carry Comments 3's models, built from what changed. Like Comments 3's, they're notices: a
 * listener can't stop the change through them. Each logs a deprecation when something is listening.
 */
final class LegacyEngagementEvents
{
    // Static Methods
    // =========================================================================

    public static function vote(VoteChangeEvent $event, bool $before): void
    {
        $saving = $event->type !== null;
        $name = $saving
            ? ($before ? Votes::EVENT_BEFORE_SAVE_VOTE : Votes::EVENT_AFTER_SAVE_VOTE)
            : ($before ? Votes::EVENT_BEFORE_DELETE_VOTE : Votes::EVENT_AFTER_DELETE_VOTE);

        // A vote taken back is described by the one it was.
        $type = $event->type ?? $event->previousType;

        self::_trigger(Votes::class, $name, 'Votes', static fn() => new VoteEvent([
            'isNew' => $saving && $event->previousType === null,
            'vote' => new Vote([
                'commentId' => (int)$event->comment->id,
                'userId' => $event->user?->id,
                'upvote' => $type === InteractionType::Downvote ? 0 : 1,
                'downvote' => $type === InteractionType::Downvote ? 1 : 0,
            ]),
        ]), 'Use `Interactions::EVENT_BEFORE_VOTE` or `Interactions::EVENT_AFTER_VOTE`.');
    }

    public static function report(ReportChangeEvent $event, bool $before): void
    {
        $name = $event->reported
            ? ($before ? Flags::EVENT_BEFORE_SAVE_FLAG : Flags::EVENT_AFTER_SAVE_FLAG)
            : ($before ? Flags::EVENT_BEFORE_DELETE_FLAG : Flags::EVENT_AFTER_DELETE_FLAG);

        self::_trigger(Flags::class, $name, 'Flags', static fn() => new FlagEvent([
            'isNew' => $event->reported,
            'flag' => new Flag([
                'id' => $event->report?->id,
                'commentId' => (int)$event->comment->id,
                'userId' => $event->user?->id,
            ]),
        ]), 'Use `Reports::EVENT_BEFORE_REPORT`, `Reports::EVENT_AFTER_REPORT` or `Reports::EVENT_AFTER_WITHDRAW_REPORT`.');
    }

    public static function subscription(SubscriptionChangeEvent $event, ?int $ownerId, ?int $ownerSiteId): void
    {
        $name = $event->subscribed ? SubscribeService::EVENT_AFTER_SAVE_SUBSCRIBE : SubscribeService::EVENT_AFTER_DELETE_SUBSCRIBE;

        self::_trigger(SubscribeService::class, $name, 'Subscribe', static fn() => new SubscribeEvent([
            'isNew' => $event->subscribed,
            'subscribe' => new Subscribe([
                'id' => (int)$event->subscription->id,
                'ownerId' => $ownerId,
                'ownerSiteId' => $ownerSiteId,
                'userId' => $event->user?->id,
                'commentId' => $event->commentId,
                'subscribed' => $event->subscribed,
            ]),
        ]), 'Use `Subscriptions::EVENT_AFTER_CHANGE_SUBSCRIPTION`.');
    }


    // Private Methods
    // =========================================================================

    private static function _trigger(string $class, string $name, string $service, callable $build, string $replacement): void
    {
        if (!Compatibility::enabled() || !Event::hasHandlers($class, $name)) {
            return;
        }

        Compatibility::deprecated("events.{$name}", "Comments 3’s `{$service}::{$name}` event has been replaced. {$replacement}");
        Event::trigger($class, $name, $build());
    }
}
