<?php
namespace verbb\comments\events;

use verbb\comments\enums\SubscriptionType;
use verbb\comments\records\Subscription;

use craft\elements\User;

use yii\base\Event;

/**
 * Someone following or no longer following a discussion, or the replies to a comment.
 */
class SubscriptionChangeEvent extends Event
{
    // Properties
    // =========================================================================

    public Subscription $subscription;
    public int $identityId;
    /** The person, or null for a guest. */
    public ?User $user = null;
    public SubscriptionType $type;
    public int $conversationId;
    /** The comment whose replies are followed, for a `replies` subscription. */
    public ?int $commentId = null;
    public bool $subscribed = true;
}
