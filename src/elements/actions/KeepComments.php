<?php
namespace verbb\comments\elements\actions;

use verbb\comments\services\Moderation;

use Craft;

class KeepComments extends ModerateComments
{
    // Static Methods
    // =========================================================================

    public static function isDestructive(): bool
    {
        return false;
    }


    // Public Methods
    // =========================================================================

    public function decision(): string
    {
        return Moderation::DECISION_KEEP;
    }

    public function getTriggerLabel(): string
    {
        return Craft::t('comments', 'Keep');
    }
}
