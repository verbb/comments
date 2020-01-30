<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;
use craft\records\FieldLayout;

use yii\db\Expression;

class m200130_000000_field_layout extends Migration
{
    public function safeUp()
    {
        $this->insert(FieldLayout::tableName(), ['type' => Comment::class]);

        return true;
    }

    public function safeDown()
    {
        echo "m200130_000000_field_layout cannot be reverted.\n";

        return false;
    }
}
