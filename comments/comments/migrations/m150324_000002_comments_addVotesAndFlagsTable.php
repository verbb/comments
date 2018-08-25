<?php
namespace Craft;

class m150324_000002_comments_addVotesAndFlagsTable extends BaseMigration
{
    public function safeUp()
    {
        $tableName = 'comments_flags';
        if (!craft()->db->tableExists($tableName)) {
            Craft::log('Creating the `$tableName` table.', LogLevel::Info, true);

            craft()->db->createCommand()->createTable($tableName, array(
                'id' => array('column' => 'pk'),
                'commentId' => array('column' => 'integer', 'required' => true),
                'userId' => array('column' => 'integer', 'required' => true),
            ), null, false);
        
            craft()->db->createCommand()->addForeignKey($tableName, 'commentId', 'comments', 'id', 'CASCADE', null);
            craft()->db->createCommand()->addForeignKey($tableName, 'userId', 'users', 'id', 'CASCADE', null);
        }

        $tableName = 'comments_votes';
        if (!craft()->db->tableExists($tableName)) {
            Craft::log('Creating the `$tableName` table.', LogLevel::Info, true);

            craft()->db->createCommand()->createTable($tableName, array(
                'id' => array('column' => 'pk'),
                'commentId' => array('column' => 'integer', 'required' => true),
                'userId' => array('column' => 'integer', 'required' => true),
                'upvote' => array('column' => 'boolean'),
                'downvote' => array('column' => 'boolean'),
            ), null, false);
        
            craft()->db->createCommand()->addForeignKey($tableName, 'commentId', 'comments', 'id', 'CASCADE', null);
            craft()->db->createCommand()->addForeignKey($tableName, 'userId', 'users', 'id', 'CASCADE', null);
        }

        return true;
    }
}