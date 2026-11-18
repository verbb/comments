<?php
namespace verbb\comments\events;

use verbb\comments\models\Flag;

use yii\base\Event;

/**
 * Comments 3's event for its `Flags` service's events, fired in compatibility mode only.
 *
 * @deprecated in 4.0.0. Removed when compatibility mode is.
 */
class FlagEvent extends Event
{
    // Properties
    // =========================================================================

    public bool $isNew = false;
    public Flag $flag;
}
