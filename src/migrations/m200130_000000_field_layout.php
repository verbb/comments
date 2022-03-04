<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use craft\db\Migration;
use craft\records\FieldLayout;

class m200130_000000_field_layout extends Migration
{
    public function safeUp(): bool
    {
        $this->insert(FieldLayout::tableName(), ['type' => Comment::class]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200130_000000_field_layout cannot be reverted.\n";

        return false;
    }
}
