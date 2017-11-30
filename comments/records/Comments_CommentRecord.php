<?php
namespace Craft;

class Comments_CommentRecord extends BaseRecord
{
    // Public Methods
    // =========================================================================

    public function getTableName()
    {
        return 'comments';
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
			'parentElement'  => array(static::BELONGS_TO, 'ElementRecord', 'elementId', 'required' => true, 'onDelete' => static::CASCADE),
            'user' => array(static::BELONGS_TO, 'UserRecord', 'onDelete' => static::CASCADE),
        );
    }

	/**
	 * Prepares the model's attribute values to be saved to the database.
	 *
	 * @return null
	 */
	public function prepAttributesForSave()
	{
		parent::prepAttributesForSave();
		// Populate commentDate if this is a new record
		if ($this->isNewRecord() && empty($this->commentDate))
		{
			$this->commentDate = DateTimeHelper::currentTimeForDb();
		}
	}
    

    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'elementType'   => array(AttributeType::String),
            'structureId'   => array(AttributeType::Number),
            'status'        => array(AttributeType::Enum, 'values' => array(
                Comments_CommentModel::APPROVED,
                Comments_CommentModel::PENDING,
                Comments_CommentModel::SPAM,
                Comments_CommentModel::TRASHED,
            )),
            'name'          => array(AttributeType::String),
            'email'         => array(AttributeType::Email),
            'url'           => array(AttributeType::Url),
            'ipAddress'     => array(AttributeType::String),
            'userAgent'     => array(AttributeType::String),
            'comment'       => array(AttributeType::Mixed),
            'commentDate'   => array(AttributeType::DateTime),
        );
    }
}
