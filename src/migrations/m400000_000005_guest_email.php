<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

/**
 * Columns added to the v4 schema after `prepare` first shipped to development sites. Fresh installs and
 * v3 upgrades already have them from `prepare`, so this only ever does anything on those sites.
 */
class m400000_000005_guest_email extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%comments_comments}}', 'editedAt')) {
            $this->addColumn('{{%comments_comments}}', 'editedAt', $this->dateTime()->after('commentDate'));
        }

        if (!$this->db->columnExists('{{%comments_identities}}', 'emailOptOutAt')) {
            $this->addColumn('{{%comments_identities}}', 'emailOptOutAt', $this->dateTime()->after('verifiedAt'));
        }

        if (!$this->db->columnExists('{{%comments_identities}}', 'emailConfirmationSentAt')) {
            $this->addColumn('{{%comments_identities}}', 'emailConfirmationSentAt', $this->dateTime()->after('emailOptOutAt'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m400000_000005_guest_email cannot be reverted.\n";

        return false;
    }
}
