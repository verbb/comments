<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\records\FieldLayout;

use yii\db\Expression;

class m201124_100000_ensure_field_layout extends Migration
{
    public function safeUp()
    {
        $fieldLayout = (new Query())
            ->select(['id'])
            ->from([FieldLayout::tableName()])
            ->where(['type' => Comment::class, 'dateDeleted' => null])
            ->one();

        if (!$fieldLayout) {
            $this->insert(FieldLayout::tableName(), ['type' => Comment::class]);
        }
    }

    public function safeDown()
    {
        echo "m201124_100000_ensure_field_layout cannot be reverted.\n";

        return false;
    }
}
