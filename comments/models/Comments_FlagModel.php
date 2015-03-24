<?php
namespace Craft;

class Comments_FlagModel extends BaseModel
{
	protected function defineAttributes()
	{
        return array(
			'id'			=> array(AttributeType::Number),
			'commentId'		=> array(AttributeType::Number),
			'userId'		=> array(AttributeType::Number),
        );
	}
}