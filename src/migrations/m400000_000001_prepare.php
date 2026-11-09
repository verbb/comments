<?php
namespace verbb\comments\migrations;

use verbb\comments\migrations\support\ResumableSchemaMigration;

use craft\db\Migration;

/**
 * Comments 4 prepare: additive Conversations, Identities, Comment v4 columns and satellites.
 *
 * Legacy v3 columns and Structure remain until a later validated cleanup migration.
 */
class m400000_000001_prepare extends Migration
{
    // Traits
    // =========================================================================

    use ResumableSchemaMigration;


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->_createConversations();
        $this->_createIdentities();
        $this->_widenStatus();
        $this->_alterComments();
        $this->_createInteractions();
        $this->_createReports();
        $this->_createSubscriptions();
        $this->_createMentions();
        $this->_createAssets();
        $this->_createModerationActions();
        $this->_createModeratorNotes();
        $this->_createRestrictions();

        return true;
    }

    public function safeDown(): bool
    {
        foreach ([
            '{{%comments_restrictions}}',
            '{{%comments_moderatornotes}}',
            '{{%comments_moderationactions}}',
            '{{%comments_assets}}',
            '{{%comments_mentions}}',
            '{{%comments_subscriptions}}',
            '{{%comments_reports}}',
            '{{%comments_interactions}}',
            '{{%comments_identities}}',
            '{{%comments_conversations}}',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->dropTable($table);
            }
        }

        foreach ([
            'conversationId',
            'identityId',
            'parentId',
            'rootId',
            'depth',
            'moderationState',
            'contentFormat',
            'content',
            'authorRemovedAt',
            'editedAt',
            'heldReason',
            'protection',
        ] as $column) {
            if ($this->db->columnExists('{{%comments_comments}}', $column)) {
                $this->dropColumn('{{%comments_comments}}', $column);
            }
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _createConversations(): void
    {
        if (!$this->db->tableExists('{{%comments_conversations}}')) {
            $this->createTable('{{%comments_conversations}}', [
                'id' => $this->primaryKey(),
                'ownerId' => $this->integer(),
                'siteId' => $this->integer(),
                'closed' => $this->boolean()->notNull()->defaultValue(false),
                'moderationMode' => $this->string(32),
                'allowGuests' => $this->boolean(),
                'sortOrder' => $this->string(32),
                'commentCount' => $this->integer()->notNull()->defaultValue(0),
                'lastActivityAt' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_conversations}}', ['ownerId', 'siteId'], true);
        $this->createIndexIfMissing('{{%comments_conversations}}', ['siteId', 'id']);
        $this->addForeignKeyIfMissing(null, '{{%comments_conversations}}', 'ownerId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_conversations}}', 'siteId', '{{%sites}}', 'id', 'SET NULL', 'CASCADE');
    }

    private function _createIdentities(): void
    {
        if (!$this->db->tableExists('{{%comments_identities}}')) {
            $this->createTable('{{%comments_identities}}', [
                'id' => $this->primaryKey(),
                'type' => $this->string(16)->notNull(),
                'userId' => $this->integer(),
                'guestTokenHash' => $this->string(128),
                'name' => $this->string(255),
                'email' => $this->string(255),
                'url' => $this->text(),
                'verifiedAt' => $this->dateTime(),
                'emailOptOutAt' => $this->dateTime(),
                'emailConfirmationSentAt' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_identities}}', 'userId', true);
        $this->createIndexIfMissing('{{%comments_identities}}', 'guestTokenHash', true);
        $this->createIndexIfMissing('{{%comments_identities}}', ['type', 'id']);
        $this->addForeignKeyIfMissing(null, '{{%comments_identities}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
    }

    private function _alterComments(): void
    {
        $table = '{{%comments_comments}}';

        if (!$this->db->columnExists($table, 'conversationId')) {
            $this->addColumn($table, 'conversationId', $this->integer()->after('id'));
        }

        if (!$this->db->columnExists($table, 'identityId')) {
            $this->addColumn($table, 'identityId', $this->integer()->after('conversationId'));
        }

        if (!$this->db->columnExists($table, 'parentId')) {
            $this->addColumn($table, 'parentId', $this->integer()->after('identityId'));
        }

        if (!$this->db->columnExists($table, 'rootId')) {
            $this->addColumn($table, 'rootId', $this->integer()->after('parentId'));
        }

        if (!$this->db->columnExists($table, 'depth')) {
            $this->addColumn($table, 'depth', $this->smallInteger()->unsigned()->after('rootId'));
        }

        if (!$this->db->columnExists($table, 'moderationState')) {
            $this->addColumn($table, 'moderationState', $this->string(16)->after('status'));
        }

        if (!$this->db->columnExists($table, 'contentFormat')) {
            $this->addColumn($table, 'contentFormat', $this->string(32)->after('comment'));
        }

        if (!$this->db->columnExists($table, 'content')) {
            $this->addColumn($table, 'content', $this->mediumText()->after('contentFormat'));
        }

        if (!$this->db->columnExists($table, 'authorRemovedAt')) {
            $this->addColumn($table, 'authorRemovedAt', $this->dateTime()->after('commentDate'));
        }

        if (!$this->db->columnExists($table, 'editedAt')) {
            $this->addColumn($table, 'editedAt', $this->dateTime()->after('commentDate'));
        }

        if (!$this->db->columnExists($table, 'heldReason')) {
            $this->addColumn($table, 'heldReason', $this->string(32)->after('moderationState'));
        }

        if (!$this->db->columnExists($table, 'protection')) {
            $this->addColumn($table, 'protection', $this->text()->after('heldReason'));
        }

        $this->createIndexIfMissing($table, ['conversationId', 'parentId', 'moderationState', 'commentDate', 'id']);
        $this->createIndexIfMissing($table, ['conversationId', 'rootId', 'moderationState', 'commentDate', 'id']);
        $this->createIndexIfMissing($table, ['conversationId', 'moderationState', 'commentDate', 'id']);
        $this->createIndexIfMissing($table, ['identityId', 'moderationState', 'commentDate', 'id']);
        $this->createIndexIfMissing($table, 'parentId');
        $this->createIndexIfMissing($table, 'rootId');

        // conversationId nullable for migrated orphans; RESTRICT once set.
        $this->addForeignKeyIfMissing(null, $table, 'conversationId', '{{%comments_conversations}}', 'id', 'RESTRICT', null);
        $this->addForeignKeyIfMissing(null, $table, 'identityId', '{{%comments_identities}}', 'id', 'RESTRICT', null);

        // Craft's garbage collection and site deletion remove rows in bulk without running element
        // hooks, and a root stores its own ID in `rootId`, so RESTRICT would block both for every
        // element type. `Conversations::repairDetachedReplies()` reattaches what this leaves behind.
        $this->addForeignKeyIfMissing(null, $table, 'parentId', $table, 'id', 'SET NULL', null);
        $this->addForeignKeyIfMissing(null, $table, 'rootId', $table, 'id', 'SET NULL', null);
    }

    /**
     * v3 limits `status` to its own four values, which v4's `removed` isn't one of. The cleanup
     * migration rewrites the values and adds NOT NULL once they've been verified.
     */
    private function _widenStatus(): void
    {
        $table = '{{%comments_comments}}';
        $column = $this->db->getTableSchema($table, true)?->getColumn('status');

        if ($this->db->getIsPgsql()) {
            $schema = $this->db->getSchema();

            foreach ($schema->getTableChecks($schema->getRawTableName($table), true) as $check) {
                if (in_array('status', $check->columnNames ?? [], true)) {
                    $this->dropCheck($check->name, $table);
                }
            }

            return;
        }

        if ($column && $column->enumValues) {
            $this->alterColumn($table, 'status', $this->string(16));
        }
    }

    private function _createInteractions(): void
    {
        if (!$this->db->tableExists('{{%comments_interactions}}')) {
            $this->createTable('{{%comments_interactions}}', [
                'id' => $this->primaryKey(),
                'commentId' => $this->integer()->notNull(),
                'identityId' => $this->integer()->notNull(),
                'type' => $this->string(32)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_interactions}}', ['commentId', 'identityId'], true);
        $this->createIndexIfMissing('{{%comments_interactions}}', ['commentId', 'type']);
        $this->addForeignKeyIfMissing(null, '{{%comments_interactions}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_interactions}}', 'identityId', '{{%comments_identities}}', 'id', 'RESTRICT', null);
    }

    private function _createReports(): void
    {
        if (!$this->db->tableExists('{{%comments_reports}}')) {
            $this->createTable('{{%comments_reports}}', [
                'id' => $this->primaryKey(),
                'commentId' => $this->integer()->notNull(),
                'identityId' => $this->integer(),
                'reason' => $this->string(64),
                'details' => $this->text(),
                'status' => $this->string(16)->notNull()->defaultValue('open'),
                'moderationActionId' => $this->integer(),
                'resolvedByUserId' => $this->integer(),
                'resolvedAt' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_reports}}', ['commentId', 'identityId']);
        $this->createIndexIfMissing('{{%comments_reports}}', ['status', 'dateCreated', 'id']);
        $this->addForeignKeyIfMissing(null, '{{%comments_reports}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_reports}}', 'identityId', '{{%comments_identities}}', 'id', 'RESTRICT', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_reports}}', 'resolvedByUserId', '{{%users}}', 'id', 'SET NULL', null);
    }

    private function _createSubscriptions(): void
    {
        if (!$this->db->tableExists('{{%comments_subscriptions}}')) {
            $this->createTable('{{%comments_subscriptions}}', [
                'id' => $this->primaryKey(),
                'conversationId' => $this->integer()->notNull(),
                'identityId' => $this->integer()->notNull(),
                'commentId' => $this->integer(),
                'type' => $this->string(32)->notNull(),
                // Durable opt-out: v3 subscribed=false must survive migration.
                'subscribed' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_subscriptions}}', ['conversationId', 'identityId', 'commentId', 'type'], true);
        $this->createIndexIfMissing('{{%comments_subscriptions}}', ['conversationId', 'subscribed']);
        $this->addForeignKeyIfMissing(null, '{{%comments_subscriptions}}', 'conversationId', '{{%comments_conversations}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_subscriptions}}', 'identityId', '{{%comments_identities}}', 'id', 'RESTRICT', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_subscriptions}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
    }

    private function _createMentions(): void
    {
        if (!$this->db->tableExists('{{%comments_mentions}}')) {
            $this->createTable('{{%comments_mentions}}', [
                'commentId' => $this->integer()->notNull(),
                'identityId' => $this->integer()->notNull(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_mentions}}', ['commentId', 'identityId'], true);
        $this->addForeignKeyIfMissing(null, '{{%comments_mentions}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_mentions}}', 'identityId', '{{%comments_identities}}', 'id', 'RESTRICT', null);
    }

    private function _createAssets(): void
    {
        if (!$this->db->tableExists('{{%comments_assets}}')) {
            $this->createTable('{{%comments_assets}}', [
                'commentId' => $this->integer()->notNull(),
                'assetId' => $this->integer()->notNull(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_assets}}', ['commentId', 'assetId'], true);
        $this->addForeignKeyIfMissing(null, '{{%comments_assets}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_assets}}', 'assetId', '{{%assets}}', 'id', 'CASCADE', null);
    }

    private function _createModerationActions(): void
    {
        if (!$this->db->tableExists('{{%comments_moderationactions}}')) {
            $this->createTable('{{%comments_moderationactions}}', [
                'id' => $this->primaryKey(),
                'commentId' => $this->integer()->notNull(),
                'action' => $this->string(64)->notNull(),
                'fromState' => $this->string(16),
                'toState' => $this->string(16),
                'userId' => $this->integer(),
                'actorType' => $this->string(16)->notNull(),
                'reason' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_moderationactions}}', ['commentId', 'dateCreated', 'id']);
        $this->addForeignKeyIfMissing(null, '{{%comments_moderationactions}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_moderationactions}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
    }

    private function _createModeratorNotes(): void
    {
        if (!$this->db->tableExists('{{%comments_moderatornotes}}')) {
            $this->createTable('{{%comments_moderatornotes}}', [
                'id' => $this->primaryKey(),
                'commentId' => $this->integer()->notNull(),
                'userId' => $this->integer(),
                'note' => $this->text()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_moderatornotes}}', ['commentId', 'dateCreated', 'id']);
        $this->addForeignKeyIfMissing(null, '{{%comments_moderatornotes}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_moderatornotes}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
    }

    private function _createRestrictions(): void
    {
        if (!$this->db->tableExists('{{%comments_restrictions}}')) {
            $this->createTable('{{%comments_restrictions}}', [
                'id' => $this->primaryKey(),
                'identityId' => $this->integer()->notNull(),
                'conversationId' => $this->integer(),
                'type' => $this->string(16)->notNull(),
                'reason' => $this->text(),
                'createdByUserId' => $this->integer(),
                'endsAt' => $this->dateTime(),
                'revokedByUserId' => $this->integer(),
                'revokedAt' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing('{{%comments_restrictions}}', ['identityId', 'type', 'conversationId']);
        $this->createIndexIfMissing('{{%comments_restrictions}}', ['conversationId', 'type']);
        $this->addForeignKeyIfMissing(null, '{{%comments_restrictions}}', 'identityId', '{{%comments_identities}}', 'id', 'RESTRICT', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_restrictions}}', 'conversationId', '{{%comments_conversations}}', 'id', 'CASCADE', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_restrictions}}', 'createdByUserId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKeyIfMissing(null, '{{%comments_restrictions}}', 'revokedByUserId', '{{%users}}', 'id', 'SET NULL', null);
    }
}
