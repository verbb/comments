<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;

use craft\base\ElementInterface;
use craft\elements\User;

use yii\base\Event;

/**
 * Everyone a notification was for, after Comments has emailed them. Guests and Notification Recipients
 * addresses are users without an ID.
 */
class NotifyEvent extends Event
{
    // Properties
    // =========================================================================

    /** What happened, as one of the `Notifications::TYPE_*` constants. */
    public string $type;
    public Comment $comment;
    /** The element the comment is on. */
    public ?ElementInterface $owner = null;

    /** @var User[] */
    public array $recipients = [];
}
