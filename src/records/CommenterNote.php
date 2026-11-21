<?php
namespace verbb\comments\records;

use verbb\comments\base\EmojiColumnsTrait;

use craft\db\ActiveRecord;

/**
 * A moderator's note about a commenter, on their Activity tab. Notes about a single comment are
 * `ModeratorNote`s.
 */
class CommenterNote extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_commenternotes}}';
    }

    protected static function emojiColumns(): array
    {
        return ['note'];
    }


    // Traits
    // =========================================================================

    use EmojiColumnsTrait;
}
