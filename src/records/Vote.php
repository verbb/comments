<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;
use craft\records\User;

use yii\db\ActiveQueryInterface;

class Vote extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_votes}}';
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
