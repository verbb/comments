<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\queue\jobs\ResaveElements;

use yii\db\Expression;

class m190220_000000_resave_search_indexes extends Migration
{
    public function safeUp()
    {
        Craft::$app->getQueue()->push(new ResaveElements([
            'elementType' => Comment::class,
        ]));
    }

    public function safeDown()
    {
        echo "m190220_000000_resave_search_indexes cannot be reverted.\n";

        return false;
    }
}
