<?php
namespace verbb\comments\events;

use yii\base\Event;

class SubscribeEvent extends Event
{
    // Properties
    // =========================================================================

    public $subscribe;

    public $isNew = false;
}
