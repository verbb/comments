<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;

use craft\base\ElementInterface;
use craft\elements\User;

use yii\base\Event;

/**
 * People with an account who should hear about a comment. Comments emails only guests, so delivering
 * this is left to the site or a notifications plugin.
 */
class NotifyEvent extends Event
{
    // Properties
    // =========================================================================

    /** What happened, as one of the `Notifications::TYPE_*` constants. */
    public string $type;
    public Comment $comment;
    public ?ElementInterface $element = null;

    public array $recipients = [];
}
