<?php
namespace verbb\comments\events;

use yii\base\Event;

class FlagEvent extends Event
{
    // Properties
    // =========================================================================

    public $flag;

    public $isNew = false;
}
