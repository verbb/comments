<?php
namespace verbb\comments\records;

use verbb\comments\base\EmojiColumnsTrait;

use craft\db\ActiveRecord;

class ModerationAction extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_moderationactions}}';
    }

    protected static function emojiColumns(): array
    {
        return ['reason'];
    }


    // Traits
    // =========================================================================

    use EmojiColumnsTrait;
}
