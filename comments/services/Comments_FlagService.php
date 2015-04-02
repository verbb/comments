<?php
namespace Craft;

class Comments_FlagService extends BaseApplicationComponent
{
    public function getAllFlags()
    {
        $records = Comments_FlagRecord::model()->findAll();
        return Comments_FlagModel::populateModels($records, 'id');
    }

    public function getFlagsByCommentId($commentId)
    {
		$records = Comments_FlagRecord::model()->findAllByAttributes(array('commentId' => $commentId));
        return Comments_FlagModel::populateModels($records, 'id');
    }

    public function hasFlagged($comment, $user)
    {
		$record = Comments_FlagRecord::model()->findByAttributes(array('commentId' => $comment->id, 'userId' => $user->id));

		return ($record) ? true : false;
    }

    public function isOverFlagThreshold($comment)
    {
    	$threshold = craft()->plugins->getPlugin('comments')->getSettings()->flaggedCommentLimit;
		$flags = $this->getFlagsByCommentId($comment->id);

    	if (count($flags) >= $threshold) {
    		return true;
    	} else {
    		return false;
    	}
    }

    public function saveFlag(Comments_FlagModel $model)
    {
        $record = new Comments_FlagRecord();

		$record->setAttributes($model->getAttributes(), false);

		if ($record->save()) {
			$model->setAttribute('id', $record->getAttribute('id'));

			return true;
		} else {
			$model->addErrors($record->getErrors());
			return false;
		}
    }

}