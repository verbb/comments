<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

/**
 * Why a comment is waiting, and what spam protection found. Fresh installs and v3 upgrades get these
 * from `prepare`, so this only does anything on development sites that ran `prepare` before they existed.
 */
class m400000_000006_review_reasons extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%comments_comments}}', 'heldReason')) {
            $this->addColumn('{{%comments_comments}}', 'heldReason', $this->string(32)->after('moderationState'));
        }

        if (!$this->db->columnExists('{{%comments_comments}}', 'protection')) {
            $this->addColumn('{{%comments_comments}}', 'protection', $this->text()->after('heldReason'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m400000_000006_review_reasons cannot be reverted.\n";

        return false;
    }
}
