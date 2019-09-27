<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\MigrationHelper;

use yii\db\Expression;

class m190918_000000_add_subscribe extends Migration
{
    public function safeUp()
    {
        if (!$this->db->tableExists('{{%comments_subscribe}}')) {
            $this->createTable('{{%comments_subscribe}}', [
                'id' => $this->primaryKey(),
                'ownerId' => $this->integer(),
                'ownerSiteId' => $this->integer(),
                'userId' => $this->integer(),
                'commentId' => $this->integer(),
                'subscribed' => $this->boolean(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
    }

    public function safeDown()
    {
        echo "m190918_000000_add_subscribe cannot be reverted.\n";

        return false;
    }
}
