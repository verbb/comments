<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

class m190123_000000_fix_comment_date_column extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%comments_flags}}', 'commentDate')) {
            $this->dropColumn('{{%comments_flags}}', 'commentDate');
        }

        if ($this->db->columnExists('{{%comments_votes}}', 'commentDate')) {
            $this->dropColumn('{{%comments_votes}}', 'commentDate');
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190123_000000_fix_comment_date_column cannot be reverted.\n";

        return false;
    }
}
