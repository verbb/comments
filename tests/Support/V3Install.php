<?php
namespace Tests\Support;

use craft\db\Migration;

/**
 * The tables and foreign keys from the v3 `Install` migration, as of the final 3.0.x release.
 */
final class V3Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%comments_comments}}', [
            'id' => $this->primaryKey(),
            'ownerId' => $this->integer(),
            'ownerSiteId' => $this->integer(),
            'userId' => $this->integer(),
            'status' => $this->enum('status', ['approved', 'pending', 'spam', 'trashed']),
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

        return true;
    }
}
