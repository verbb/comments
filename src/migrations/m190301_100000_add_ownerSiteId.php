<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\MigrationHelper;

use yii\db\Expression;

class m190301_100000_add_ownerSiteId extends Migration
{
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%comments_comments}}', 'ownerSiteId')) {
            $this->addColumn('{{%comments_comments}}', 'ownerSiteId', $this->integer()->after('ownerId'));
        }

        if (!MigrationHelper::doesForeignKeyExist('{{%comments_comments}}', 'ownerSiteId')) {
            $this->addForeignKey(null, '{{%comments_comments}}', 'ownerSiteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        }

        $ownerSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        $this->update('{{%comments_comments}}', ['ownerSiteId' => $ownerSiteId]);
    }

    public function safeDown()
    {
        echo "m190301_100000_add_ownerSiteId cannot be reverted.\n";

        return false;
    }
}
