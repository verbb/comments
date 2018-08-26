<?php
namespace verbb\comments\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class m180825_000000_craft3_upgrade extends Migration
{
    public function safeUp()
    {
        // Cleanup columns
        $this->dropColumn('{{%comments_comments}}', 'structureId');
        $this->dropColumn('{{%comments_comments}}', 'elementType');

        // Rename elementId to ownerId
        MigrationHelper::renameColumn('{{%comments_comments}}', 'elementId', 'ownerId', $this);

        // Add correct foreign key to id (which is an element after all)
        $this->createIndex($this->db->getIndexName('{{%comments_comments}}', 'id', false), '{{%comments_comments}}', 'id', false);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_comments}}', 'id'), '{{%comments_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);

        return true;
    }

    public function safeDown()
    {
        echo "m180825_000000_craft3_upgrade cannot be reverted.\n";

        return false;
    }
}
