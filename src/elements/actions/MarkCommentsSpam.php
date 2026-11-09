<?php
namespace verbb\comments\elements\actions;

use verbb\comments\services\Moderation;

use Craft;

class MarkCommentsSpam extends ModerateComments
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
        return Moderation::DECISION_SPAM;
    }

    public function getTriggerLabel(): string
    {
        return Craft::t('comments', 'Mark as spam');
    }
}
