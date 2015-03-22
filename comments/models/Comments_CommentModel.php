<?php
namespace Craft;

class Comments_CommentModel extends BaseElementModel
{
    protected $elementType = 'Comments_Comment';

	const APPROVED	= 'approved';
	const PENDING	= 'pending';
	const SPAM		= 'spam';
	const TRASHED	= 'trashed';

	protected function defineAttributes()
	{
        return array_merge(parent::defineAttributes(), array(
			'id'			=> array(AttributeType::Number),
			'entryId'		=> array(AttributeType::Number),
			'userId'		=> array(AttributeType::Number),
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
			'comment'		=> array(AttributeType::String),

			// Just used for saving
			'parentId'      => AttributeType::Number,
        ));
	}

    public function isEditable()
    {
        return false;
    }

    public function hasTitles()
    {
        return false;
    }

    public function isLocalized()
    {
        return false;
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('comments/edit/' . $this->id);
    }

	public function getExcerpt($startPos=0, $maxLength=100) {
		if(strlen($this->comment) > $maxLength) {
			$excerpt   = substr($this->comment, $startPos, $maxLength-3);
			$lastSpace = strrpos($excerpt, ' ');
			$excerpt   = substr($excerpt, 0, $lastSpace);
			$excerpt  .= '...';
		} else {
			$excerpt = $this->comment;
		}
		
		return $excerpt;
	}


}