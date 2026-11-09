<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\Site;
use craft\records\User;

use yii\db\ActiveQueryInterface;

class Comment extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_comments}}';
    }


    // Public Methods
    // =========================================================================

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getOwner(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'ownerId']);
    }

    public function getOwnerSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'ownerSiteId']);
    }

    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    public function getConversation(): ActiveQueryInterface
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversationId']);
    }

    public function getIdentity(): ActiveQueryInterface
    {
        return $this->hasOne(Identity::class, ['id' => 'identityId']);
    }

    public function getParentComment(): ActiveQueryInterface
    {
        return $this->hasOne(self::class, ['id' => 'parentId']);
    }

    public function getRootComment(): ActiveQueryInterface
    {
        return $this->hasOne(self::class, ['id' => 'rootId']);
    }
}
