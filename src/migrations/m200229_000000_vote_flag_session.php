<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

class m200229_000000_vote_flag_session extends Migration
{
    public function safeUp(): bool
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

    public function safeDown(): bool
    {
        echo "m200229_000000_vote_flag_session cannot be reverted.\n";

        return false;
    }
}
