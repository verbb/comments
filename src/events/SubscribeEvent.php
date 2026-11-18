<?php
namespace verbb\comments\events;

use verbb\comments\models\Subscribe;

use yii\base\Event;

/**
 * Comments 3's event for its `Subscribe` service's events, fired in compatibility mode only.
 *
 * @deprecated in 4.0.0. Removed when compatibility mode is.
 */
class SubscribeEvent extends Event
{
    // Properties
    // =========================================================================

    public bool $isNew = false;
    public Subscribe $subscribe;
}
