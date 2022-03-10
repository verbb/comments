<?php
namespace verbb\comments\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;

class m190301_100000_add_ownerSiteId extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%comments_comments}}', 'ownerSiteId')) {
            $this->addColumn('{{%comments_comments}}', 'ownerSiteId', $this->integer()->after('ownerId'));
        }

        if (!Db::doesForeignKeyExist('{{%comments_comments}}', 'ownerSiteId')) {
            $this->addForeignKey(null, '{{%comments_comments}}', 'ownerSiteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        }

        $ownerSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        $this->update('{{%comments_comments}}', ['ownerSiteId' => $ownerSiteId]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190301_100000_add_ownerSiteId cannot be reverted.\n";

        return false;
    }
}
