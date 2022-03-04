<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use craft\db\Migration;

class m190301_000000_check_craft2_elements extends Migration
{
    public function safeUp(): bool
    {
        $this->update('{{%elements}}', ['type' => Comment::class], ['type' => 'Comments_Comment']);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190301_000000_check_craft2_elements cannot be reverted.\n";

        return false;
    }
}
