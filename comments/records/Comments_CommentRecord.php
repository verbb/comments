<?php
namespace Craft;

class Comments_CommentRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'comments';
	}

	protected function defineAttributes()
	{
		return array(
			'structureId'   => array(AttributeType::Number),
			'status'		=> array(AttributeType::Enum, 'values' => array(
			    Comments_CommentModel::APPROVED,
			    Comments_CommentModel::PENDING,
			    Comments_CommentModel::SPAM,
			    Comments_CommentModel::TRASHED,
			)),
			'name'			=> array(AttributeType::String),
			'email'			=> array(AttributeType::Email),
			'url'			=> array(AttributeType::Url),
			'ipAddress'		=> array(AttributeType::String),
			'userAgent'		=> array(AttributeType::String),
			'comment'		=> array(AttributeType::Mixed),
		);
	}

	public function scopes()
	{
		return array(
			'ordered' => array('order' => 'dateCreated'),
		);
	}

	public function defineRelations()
	{
		return array(
			'element'  => array(static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE),
			'entry'  => array(static::BELONGS_TO, 'EntryRecord', 'onDelete' => static::CASCADE),
			'user' => array(static::BELONGS_TO, 'UserRecord', 'onDelete' => static::CASCADE),
		);
	}
}
