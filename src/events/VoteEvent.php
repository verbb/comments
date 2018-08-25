<?php
namespace verbb\comments\events;

use yii\base\Event;

class VoteEvent extends Event
{
    // Properties
    // =========================================================================

    public $vote;

    public $isNew = false;
}
