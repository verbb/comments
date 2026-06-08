<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

class m260607_000000_add_gif_url extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%comments_comments}}', 'gifUrl')) {
            $this->addColumn('{{%comments_comments}}', 'gifUrl', $this->text()->after('comment'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%comments_comments}}', 'gifUrl')) {
            $this->dropColumn('{{%comments_comments}}', 'gifUrl');
        }

        return true;
    }
}
