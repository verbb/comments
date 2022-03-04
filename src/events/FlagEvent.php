<?php
namespace verbb\comments\events;

use verbb\comments\models\Flag;

use yii\base\Event;

class FlagEvent extends Event
{
    // Properties
    // =========================================================================

    public Flag $flag;
    public bool $isNew = false;
}
