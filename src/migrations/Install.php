<?php
namespace verbb\comments\migrations;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\services\Comments as CommentsService;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use craft\records\FieldLayout;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        // Keep the upgrade migration as the canonical v4 schema definition so
        // fresh installs and upgraded sites cannot drift apart.
        $v4Schema = new m400000_000001_prepare(['db' => $this->db]);

        if (!$v4Schema->safeUp()) {
            return false;
        }

        // Preserve layouts supplied by project config, but don't let an existing plugin entry suppress the default layout.
        $configExists = Craft::$app->getProjectConfig()->get(CommentsService::CONFIG_FIELDLAYOUT_KEY, true) !== null;

        if (!$configExists) {
            Comments::$plugin->getComments()->saveFieldLayout();
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropProjectConfig();
        $this->dropForeignKeys();
        $this->dropTables();

        $this->delete(FieldLayout::tableName(), ['type' => Comment::class]);

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%comments_comments}}');
        $this->createTable('{{%comments_comments}}', [
            'id' => $this->primaryKey(),
            'ownerId' => $this->integer(),
            'ownerSiteId' => $this->integer(),
            'userId' => $this->integer(),
            'status' => $this->string(16)->notNull(),
            'comment' => $this->text(),
            'gifUrl' => $this->text(),
            'name' => $this->string(),
            'email' => $this->string(),
            'url' => $this->text(),
            'ipAddress' => $this->string(),
            'userAgent' => $this->string(),
            'commentDate' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%comments_comments}}', 'id', false);
        $this->createIndex(null, '{{%comments_comments}}', 'ownerId', false);
        $this->createIndex(null, '{{%comments_comments}}', 'ownerSiteId', false);
        $this->createIndex(null, '{{%comments_comments}}', 'userId', false);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%comments_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%comments_comments}}', 'ownerId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%comments_comments}}', 'ownerSiteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%comments_comments}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
    }

    public function dropTables(): void
    {
        foreach ([
            V3Schema::LOG,
            V3Schema::MAP,
            V3Schema::ARCHIVE_STRUCTURE,
            ...array_values(V3Schema::ARCHIVE_TABLES),
            '{{%comments_assets}}',
            '{{%comments_mentions}}',
            '{{%comments_restrictions}}',
            '{{%comments_moderatornotes}}',
            '{{%comments_moderationactions}}',
            '{{%comments_subscriptions}}',
            '{{%comments_reports}}',
            '{{%comments_interactions}}',
            '{{%comments_flags}}',
            '{{%comments_votes}}',
            '{{%comments_subscribe}}',
            '{{%comments_comments}}',
            '{{%comments_identities}}',
            '{{%comments_conversations}}',
        ] as $table) {
            $this->dropTableIfExists($table);
        }
    }

    public function dropForeignKeys(): void
    {
        foreach ([
            '{{%comments_assets}}',
            '{{%comments_mentions}}',
            '{{%comments_restrictions}}',
            '{{%comments_moderatornotes}}',
            '{{%comments_moderationactions}}',
            '{{%comments_subscriptions}}',
            '{{%comments_reports}}',
            '{{%comments_interactions}}',
            '{{%comments_flags}}',
            '{{%comments_votes}}',
            '{{%comments_subscribe}}',
            '{{%comments_comments}}',
            '{{%comments_identities}}',
            '{{%comments_conversations}}',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->dropAllForeignKeysToTable($table);
            }
        }
    }

    public function dropProjectConfig(): void
    {
        Craft::$app->getProjectConfig()->remove('comments');
    }
}
