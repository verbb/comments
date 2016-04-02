<?php
namespace Craft;

class Comments_FlagRecord extends BaseRecord
{
    // Public Methods
    // =========================================================================

    public function getTableName()
    {
        return 'comments_flags';
    }

    public function defineRelations()
    {
        return array(
            'comment' => array(static::BELONGS_TO, 'Comments_CommentRecord', 'onDelete' => static::CASCADE),
            'user' => array(static::BELONGS_TO, 'UserRecord', 'onDelete' => static::CASCADE),
        );
    }
    

    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array();
    }
}
