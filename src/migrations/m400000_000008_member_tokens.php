<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

/**
 * Member tokens for front ends on another site (tier 3), stored only as hashes, and the nonces of the
 * signed requests that mint and revoke them, remembered for ten minutes so a request can't be replayed.
 * Fresh installs create both through `Install`.
 */
class m400000_000008_member_tokens extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%comments_member_tokens}}')) {
            $this->createTable('{{%comments_member_tokens}}', [
                'id' => $this->primaryKey(),
                'tokenHash' => $this->char(64)->notNull(),
                'userId' => $this->integer()->notNull(),
                'origin' => $this->string(255)->notNull(),
                'dateExpires' => $this->dateTime()->notNull(),
                'dateRevoked' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%comments_member_tokens}}', ['tokenHash'], true);
            $this->createIndex(null, '{{%comments_member_tokens}}', ['userId', 'dateRevoked'], false);
            $this->createIndex(null, '{{%comments_member_tokens}}', ['dateExpires'], false);
            $this->addForeignKey(null, '{{%comments_member_tokens}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', null);
        }

        if (!$this->db->tableExists('{{%comments_member_token_nonces}}')) {
            $this->createTable('{{%comments_member_token_nonces}}', [
                'nonceHash' => $this->char(64)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'PRIMARY KEY([[nonceHash]])',
            ]);

            $this->createIndex(null, '{{%comments_member_token_nonces}}', ['dateCreated'], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%comments_member_token_nonces}}');
        $this->dropTableIfExists('{{%comments_member_tokens}}');

        return true;
    }
}
