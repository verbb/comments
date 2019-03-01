<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\queue\jobs\ResaveElements;

use yii\db\Expression;

class m190301_000000_check_craft2_elements extends Migration
{
    public function safeUp()
    {
        $this->update('{{%elements}}', ['type' => Comment::class], ['type' => 'Comments_Comment']);
    }

    public function safeDown()
    {
        echo "m190301_000000_check_craft2_elements cannot be reverted.\n";

        return false;
    }
}
