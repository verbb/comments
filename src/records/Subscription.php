<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

/**
 * Canonical subscription row.
 *
 * Includes a durable `subscribed` boolean: v3 opt-out evidence requires
 * persisting negative state, not only the presence of a row.
 */
class Subscription extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_subscriptions}}';
    }


    // Public Methods
    // =========================================================================

    public function getConversation(): ActiveQueryInterface
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversationId']);
    }

    public function getIdentity(): ActiveQueryInterface
    {
        return $this->hasOne(Identity::class, ['id' => 'identityId']);
    }

    public function getComment(): ActiveQueryInterface
    {
        return $this->hasOne(Comment::class, ['id' => 'commentId']);
    }
}
