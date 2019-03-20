<?php
namespace verbb\comments\migrations;

use craft\db\Migration;
use craft\db\Query;

use yii\db\Expression;

class m180826_000000_add_comment_date_column extends Migration
{
    public function safeUp()
    {   
        // First create the new column
        if (!$this->db->columnExists('{{%comments_comments}}', 'commentDate')) {
            $this->addColumn('{{%comments_comments}}', 'commentDate', $this->dateTime()->after('userAgent')->notNull());
        }

        // For existing records, backfill the new column with the existing values from dateCreated
        $this->update('{{%comments_comments}}', ['commentDate' => new Expression('dateCreated')]);

        return true;
    }

    public function safeDown()
    {
        echo "m180826_000000_add_comment_date_column cannot be reverted.\n";

        return false;
    }
}
