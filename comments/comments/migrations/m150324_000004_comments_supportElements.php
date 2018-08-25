<?php
namespace Craft;

class m150324_000004_comments_supportElements extends BaseMigration
{
    public function safeUp()
    {
        craft()->db->createCommand()->dropForeignKey('comments', 'entryId');
        
        craft()->db->createCommand()->renameColumn('comments', 'entryId', 'elementId');

        craft()->db->createCommand()->addForeignKey('comments', 'elementId', 'elements', 'id', 'CASCADE', null);


        craft()->db->createCommand()->addColumnAfter('comments', 'elementType', ColumnType::Varchar, 'elementId');

        craft()->db->createCommand()->update('comments', array('elementType' => 'Entry'));



        return true;
    }
}