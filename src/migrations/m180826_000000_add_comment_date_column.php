<?php
namespace verbb\comments\migrations;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\fields\Customer;
use craft\commerce\fields\Products;
use craft\commerce\widgets\Orders;
use craft\commerce\widgets\Revenue;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class m180826_000000_add_comment_date_column extends Migration
{
    public function safeUp()
    {   
        // First create the new column
        $this->addColumn('{{%comments_comments}}', 'commentDate', $this->dateTime()->after('userAgent')->notNull());

        // For existing records, backfill the new column with the existing values from dateCreated
        Craft::$app->getDb()->createCommand()
            ->update('{{%comments_comments}}', ['commentDate' => 'dateCreated'])
            ->execute();

        return true;
    }

    public function safeDown()
    {
        echo "m180826_000000_add_comment_date_column cannot be reverted.\n";

        return false;
    }
}
