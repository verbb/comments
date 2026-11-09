<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;

use craft\elements\User;

use yii\base\Event;

class ModerationEvent extends Event
{
    // Properties
    // =========================================================================

    public Comment $comment;
    public ModerationState $previousState;
    public ModerationState $state;
    public ?User $actor = null;
}
