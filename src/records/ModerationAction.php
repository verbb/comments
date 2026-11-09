<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;

class ModerationAction extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_moderationactions}}';
    }
}
