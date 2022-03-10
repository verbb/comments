<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

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

        // Don't make the same config changes twice
        $installed = (Craft::$app->projectConfig->get('plugins.comments', true) !== null);
        $configExists = (Craft::$app->projectConfig->get('comments', true) !== null);

        if (!$installed && !$configExists) {
            $this->insert(FieldLayout::tableName(), ['type' => Comment::class]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();
        $this->dropProjectConfig();

        $this->delete(FieldLayout::tableName(), ['type' => Comment::class]);

        return true;
    }

    public function createTables(): void
    {
        $this->createTable('{{%comments_comments}}', [
            'id' => $this->primaryKey(),
            'ownerId' => $this->integer(),
            'ownerSiteId' => $this->integer(),
            'userId' => $this->integer(),
            'status' => $this->enum('status', ['approved', 'pending', 'spam', 'trashed']),
            'comment' => $this->text(),
            'name' => $this->string(),
            'email' => $this->string(),
            'url' => $this->string(),
            'ipAddress' => $this->string(),
            'userAgent' => $this->string(),
            'commentDate' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%comments_flags}}', [
            'id' => $this->primaryKey(),
            'commentId' => $this->integer(),
            'userId' => $this->integer(),
            'sessionId' => $this->string(32),
            'lastIp' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%comments_votes}}', [
            'id' => $this->primaryKey(),
            'commentId' => $this->integer(),
            'userId' => $this->integer(),
            'sessionId' => $this->string(32),
            'lastIp' => $this->string(),
            'upvote' => $this->boolean(),
            'downvote' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%comments_subscribe}}', [
            'id' => $this->primaryKey(),
            'ownerId' => $this->integer(),
            'ownerSiteId' => $this->integer(),
            'userId' => $this->integer(),
            'commentId' => $this->integer(),
            'subscribed' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function dropTables(): void
    {
        $this->dropTable('{{%comments_comments}}');
        $this->dropTable('{{%comments_flags}}');
        $this->dropTable('{{%comments_votes}}');
        $this->dropTable('{{%comments_subscribe}}');
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%comments_comments}}', 'id', false);
        $this->createIndex(null, '{{%comments_comments}}', 'ownerId', false);
        $this->createIndex(null, '{{%comments_comments}}', 'ownerSiteId', false);
        $this->createIndex(null, '{{%comments_comments}}', 'userId', false);
        $this->createIndex(null, '{{%comments_flags}}', 'commentId', false);
        $this->createIndex(null, '{{%comments_flags}}', 'userId', false);
        $this->createIndex(null, '{{%comments_votes}}', 'commentId', false);
        $this->createIndex(null, '{{%comments_votes}}', 'userId', false);
        $this->createIndex(null, '{{%comments_subscribe}}', 'ownerId', false);
        $this->createIndex(null, '{{%comments_subscribe}}', 'ownerSiteId', false);
        $this->createIndex(null, '{{%comments_subscribe}}', 'userId', false);
        $this->createIndex(null, '{{%comments_subscribe}}', 'commentId', false);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%comments_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%comments_comments}}', 'ownerId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%comments_comments}}', 'ownerSiteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%comments_comments}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%comments_flags}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%comments_flags}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%comments_votes}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%comments_votes}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%comments_subscribe}}', 'ownerId', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%comments_subscribe}}', 'ownerSiteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%comments_subscribe}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%comments_subscribe}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
    }

    public function dropForeignKeys(): void
    {
        Db::dropForeignKeyIfExists('{{%comments_comments}}', ['id'], $this);
        Db::dropForeignKeyIfExists('{{%comments_comments}}', ['ownerId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_comments}}', ['ownerSiteId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_comments}}', ['userId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_flags}}', ['commentId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_flags}}', ['userId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_votes}}', ['commentId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_votes}}', ['userId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_subscribe}}', ['ownerId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_subscribe}}', ['ownerSiteId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_subscribe}}', ['userId'], $this);
        Db::dropForeignKeyIfExists('{{%comments_subscribe}}', ['commentId'], $this);
    }

    public function dropProjectConfig(): void
    {
        Craft::$app->projectConfig->remove('comments');
    }
}
