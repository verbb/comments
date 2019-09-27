<?php
namespace verbb\comments\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
    }

    public function safeDown()
    {
        $this->dropForeignKeys();
        $this->dropTables();
    }

    public function createTables()
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
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%comments_votes}}', [
            'id' => $this->primaryKey(),
            'commentId' => $this->integer(),
            'userId' => $this->integer(),
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
    
    public function dropTables()
    {
        $this->dropTable('{{%comments_comments}}');
        $this->dropTable('{{%comments_flags}}');
        $this->dropTable('{{%comments_votes}}');
        $this->dropTable('{{%comments_subscribe}}');
    }
    
    public function createIndexes()
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

    public function addForeignKeys()
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
    
    public function dropForeignKeys()
    {
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['id'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['ownerId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['ownerSiteId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['userId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_flags}}', ['commentId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_flags}}', ['userId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_votes}}', ['commentId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_votes}}', ['userId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_subscribe}}', ['ownerId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_subscribe}}', ['ownerSiteId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_subscribe}}', ['userId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_subscribe}}', ['commentId'], $this);
    }
}
