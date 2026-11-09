<?php
namespace verbb\comments\elements\actions;

use verbb\comments\services\Moderation;

use Craft;

class MarkCommentsNotSpam extends ModerateComments
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
        return Moderation::DECISION_NOT_SPAM;
    }

    public function getTriggerLabel(): string
    {
        return Craft::t('comments', 'Not spam');
    }
}
