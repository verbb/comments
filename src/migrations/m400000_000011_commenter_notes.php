<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

/**
 * Moderators' notes about a commenter, shown on their Activity tab. A note goes with its commenter's
 * identity, and keeps its text if the moderator who wrote it is deleted. Fresh installs create the table
 * through `Install`.
 */
class m400000_000011_commenter_notes extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%comments_commenternotes}}')) {
            $this->createTable('{{%comments_commenternotes}}', [
                'id' => $this->primaryKey(),
                'identityId' => $this->integer()->notNull(),
                'userId' => $this->integer(),
                'note' => $this->text()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%comments_commenternotes}}', ['identityId', 'dateCreated', 'id'], false);
            $this->addForeignKey(null, '{{%comments_commenternotes}}', ['identityId'], '{{%comments_identities}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%comments_commenternotes}}', ['userId'], '{{%users}}', ['id'], 'SET NULL', null);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%comments_commenternotes}}');

        return true;
    }
}
