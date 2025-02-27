<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

class m250227_000000_comments_url extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->alterColumn('{{%comments_comments}}', 'url', $this->text());

        return true;
    }

    public function safeDown(): bool
    {
        echo "m220420_000000_stencil_add_entryid cannot be reverted.\n";
        return false;
    }
}
