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
            'commentDate' => $this->dateTime()->notNull(),
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
            'commentDate' => $this->dateTime()->notNull(),
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
    }
    
    public function createIndexes()
    {
        $this->createIndex($this->db->getIndexName('{{%comments_comments}}', 'id', false), '{{%comments_comments}}', 'id', false);
        $this->createIndex($this->db->getIndexName('{{%comments_comments}}', 'ownerId', false), '{{%comments_comments}}', 'ownerId', false);
        $this->createIndex($this->db->getIndexName('{{%comments_comments}}', 'userId', false), '{{%comments_comments}}', 'userId', false);
        $this->createIndex($this->db->getIndexName('{{%comments_flags}}', 'commentId', false), '{{%comments_flags}}', 'commentId', false);
        $this->createIndex($this->db->getIndexName('{{%comments_flags}}', 'userId', false), '{{%comments_flags}}', 'userId', false);
        $this->createIndex($this->db->getIndexName('{{%comments_votes}}', 'commentId', false), '{{%comments_votes}}', 'commentId', false);
        $this->createIndex($this->db->getIndexName('{{%comments_votes}}', 'userId', false), '{{%comments_votes}}', 'userId', false);
    }

    public function addForeignKeys()
    {
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_comments}}', 'id'), '{{%comments_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_comments}}', 'ownerId'), '{{%comments_comments}}', 'ownerId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_comments}}', 'userId'), '{{%comments_comments}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_flags}}', 'commentId'), '{{%comments_flags}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_flags}}', 'userId'), '{{%comments_flags}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_votes}}', 'commentId'), '{{%comments_votes}}', 'commentId', '{{%comments_comments}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%comments_votes}}', 'userId'), '{{%comments_votes}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
    }
    
    public function dropForeignKeys()
    {
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['id'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['ownerId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_comments}}', ['userId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_flags}}', ['commentId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_flags}}', ['userId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_votes}}', ['commentId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%comments_votes}}', ['userId'], $this);
    }
}
