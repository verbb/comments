<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;

class ModeratorNote extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_moderatornotes}}';
    }
}
