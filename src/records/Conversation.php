<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\Site;

use yii\db\ActiveQueryInterface;

class Conversation extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_conversations}}';
    }


    // Public Methods
    // =========================================================================

    public function getOwner(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'ownerId']);
    }

    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }
}
