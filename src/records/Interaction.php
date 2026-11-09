<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

class Interaction extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_interactions}}';
    }


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
