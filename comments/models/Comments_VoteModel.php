<?php
namespace Craft;

class Comments_VoteModel extends BaseModel
{
	protected function defineAttributes()
	{
        return array(
			'id'			=> array(AttributeType::Number),
			'commentId'		=> array(AttributeType::Number),
			'userId'		=> array(AttributeType::Number),
			'upvote'		=> array(AttributeType::Bool),
			'downvote'		=> array(AttributeType::Bool),
        );
	}
}