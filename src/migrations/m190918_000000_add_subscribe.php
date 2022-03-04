<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

class m190918_000000_add_subscribe extends Migration
{
    public function safeUp(): bool
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

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190918_000000_add_subscribe cannot be reverted.\n";

        return false;
    }
}
