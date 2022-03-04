<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\queue\jobs\ResaveElements;

class m190220_000000_resave_search_indexes extends Migration
{
    public function safeUp(): bool
    {
        Craft::$app->getQueue()->push(new ResaveElements([
            'elementType' => Comment::class,
        ]));

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190220_000000_resave_search_indexes cannot be reverted.\n";

        return false;
    }
}
