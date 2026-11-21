<?php
namespace verbb\comments\records;

use verbb\comments\base\EmojiColumnsTrait;

use craft\db\ActiveRecord;
use craft\records\User;

use yii\db\ActiveQueryInterface;

class Identity extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_identities}}';
    }

    protected static function emojiColumns(): array
    {
        return ['name'];
    }


    // Traits
    // =========================================================================

    use EmojiColumnsTrait;


    // Public Methods
    // =========================================================================

    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
