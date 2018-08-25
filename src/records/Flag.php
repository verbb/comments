<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;
use craft\records\User;

use yii\db\ActiveQueryInterface;

class Flag extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_flags}}';
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Comment::class, ['id' => 'commentId']);
    }

    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
