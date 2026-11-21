<?php
namespace verbb\comments\records;

use verbb\comments\base\EmojiColumnsTrait;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

class Report extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_reports}}';
    }

    protected static function emojiColumns(): array
    {
        return ['details'];
    }


    // Traits
    // =========================================================================

    use EmojiColumnsTrait;


    // Public Methods
    // =========================================================================

    public function getComment(): ActiveQueryInterface
    {
        return $this->hasOne(Comment::class, ['id' => 'commentId']);
    }

    public function getIdentity(): ActiveQueryInterface
    {
        return $this->hasOne(Identity::class, ['id' => 'identityId']);
    }
}
