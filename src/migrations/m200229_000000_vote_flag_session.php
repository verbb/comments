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

class m200229_000000_vote_flag_session extends Migration
{
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%comments_flags}}', 'sessionId')) {
            $this->addColumn('{{%comments_flags}}', 'sessionId', $this->string(32)->after('userId'));
        }

        if (!$this->db->columnExists('{{%comments_flags}}', 'lastIp')) {
            $this->addColumn('{{%comments_flags}}', 'lastIp', $this->string(32)->after('sessionId'));
        }

        if (!$this->db->columnExists('{{%comments_votes}}', 'sessionId')) {
            $this->addColumn('{{%comments_votes}}', 'sessionId', $this->string()->after('userId'));
        }

        if (!$this->db->columnExists('{{%comments_votes}}', 'lastIp')) {
            $this->addColumn('{{%comments_votes}}', 'lastIp', $this->string()->after('sessionId'));
        }

        return true;
    }

    public function safeDown()
    {
        echo "m200229_000000_vote_flag_session cannot be reverted.\n";

        return false;
    }
}
