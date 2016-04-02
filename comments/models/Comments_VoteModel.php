<?php
namespace Craft;

class Comments_VoteModel extends BaseModel
{
    // Public Methods
    // =========================================================================

    public function getComment()
    {
        return craft()->comments->getCommentById($this->commentId);
    }

    public function getUser()
    {
        return craft()->users->getUserById($this->userId);
    }
    

    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'id'            => array(AttributeType::Number),
            'commentId'     => array(AttributeType::Number),
            'userId'        => array(AttributeType::Number),
            'upvote'        => array(AttributeType::Bool),
            'downvote'      => array(AttributeType::Bool),
        );
    }
}