<?php
namespace verbb\comments\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class m180825_000000_craft3_upgrade extends Migration
{
    public function safeUp()
    {
        // Rename table
        if ($this->db->tableExists('{{%comments}}') && !$this->db->tableExists('{{%comments_comments}}')) {
            MigrationHelper::renameTable('{{%comments}}', '{{%comments_comments}}', $this);
        }

        // Cleanup columns
        if ($this->db->columnExists('{{%comments_comments}}', 'structureId')) {
            $this->dropColumn('{{%comments_comments}}', 'structureId');
        }

        if ($this->db->columnExists('{{%comments_comments}}', 'elementType')) {
            $this->dropColumn('{{%comments_comments}}', 'elementType');
        }

        // Rename elementId to ownerId
        if ($this->db->columnExists('{{%comments_comments}}', 'elementId') && !$this->db->columnExists('{{%comments_comments}}', 'ownerId')) {
            MigrationHelper::renameColumn('{{%comments_comments}}', 'elementId', 'ownerId', $this);
        }

        // Add correct foreign key to id (which is an element after all)
        if (!MigrationHelper::doesIndexExist('{{%comments_comments}}', 'id')) {
            $this->createIndex($this->db->getIndexName('{{%comments_comments}}', 'id', false), '{{%comments_comments}}', 'id', false);
        }
        
        if (!MigrationHelper::findForeignKey('{{%comments_comments}}', 'id')) {
            // Disable FK checks
            $queryBuilder = $this->db->getSchema()->getQueryBuilder();
            $this->execute($queryBuilder->checkIntegrity(false));

            $this->addForeignKey($this->db->getForeignKeyName('{{%comments_comments}}', 'id'), '{{%comments_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        
            // Re-enable FK checks
            $this->execute($queryBuilder->checkIntegrity(true));
        }

        return true;
    }

    public function safeDown()
    {
        echo "m180825_000000_craft3_upgrade cannot be reverted.\n";

        return false;
    }
}
