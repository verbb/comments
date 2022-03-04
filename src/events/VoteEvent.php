<?php
namespace verbb\comments\events;

use verbb\comments\models\Vote;

use yii\base\Event;

class VoteEvent extends Event
{
    // Properties
    // =========================================================================

    public bool $isNew = false;
    public Vote $vote;
}
