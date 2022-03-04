<?php
namespace verbb\comments\events;

use verbb\comments\models\Subscribe;

use yii\base\Event;

class SubscribeEvent extends Event
{
    // Properties
    // =========================================================================

    public bool $isNew = false;
    public Subscribe $subscribe;
}
