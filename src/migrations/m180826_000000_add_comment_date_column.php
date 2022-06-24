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
            $this->addColumn('{{%comments_comments}}', 'commentDate', $this->dateTime()->null()->after('userAgent')->notNull());
        }

        $comments = (new Query())
            ->from('{{%comments_comments}}')
            ->all();

        // For existing records, backfill the new column with the existing values from dateCreated
        foreach ($comments as $comment) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%comments_comments}}', ['commentDate' => $comment['dateCreated']], ['id' => $comment['id']])
                ->execute();
        }

        return true;
    }

    public function safeDown()
    {
        echo "m180826_000000_add_comment_date_column cannot be reverted.\n";

        return false;
    }
}
