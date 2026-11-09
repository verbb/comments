<?php
namespace verbb\comments\elements\actions;

use verbb\comments\services\Moderation;

use Craft;

class RemoveComments extends ModerateComments
{
    // Static Methods
    // =========================================================================

    public static function isDestructive(): bool
    {
        return true;
    }


    // Public Methods
    // =========================================================================

    public function decision(): string
    {
        return Moderation::DECISION_REMOVE;
    }

    public function getTriggerLabel(): string
    {
        return Craft::t('comments', 'Remove');
    }
}
