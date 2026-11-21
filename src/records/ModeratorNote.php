<?php
namespace verbb\comments\records;

use verbb\comments\base\EmojiColumnsTrait;

use craft\db\ActiveRecord;

class ModeratorNote extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_moderatornotes}}';
    }

    protected static function emojiColumns(): array
    {
        return ['note'];
    }


    // Traits
    // =========================================================================

    use EmojiColumnsTrait;
}
