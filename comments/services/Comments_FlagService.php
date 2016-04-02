<?php
namespace Craft;

class Comments_FlagService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

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
        $threshold = craft()->comments->getSettings()->flaggedCommentLimit;
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

        // Fire an 'onBeforeFlagComment' event
        $event = new Event($this, array('flag' => $model));
        $this->onBeforeFlagComment($event);

        // Allow event to cancel comment saving
        if (!$event->performAction) {
            return false;
        }

        if ($record->save()) {
            $model->id = $record->id;

            // Fire an 'onFlagComment' event
            $this->onFlagComment(new Event($this, array('flag' => $model)));

            return true;
        } else {
            $model->addErrors($record->getErrors());
            return false;
        }
    }



    // Event Handlers
    // =========================================================================

    public function onBeforeFlagComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['flag']) || !($params['flag'] instanceof Comments_FlagModel)) {
            throw new Exception('onBeforeFlagComment event requires "flag" param with FlagModel instance');
        }

        $this->raiseEvent('onBeforeFlagComment', $event);
    }

    public function onFlagComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['flag']) || !($params['flag'] instanceof Comments_FlagModel)) {
            throw new Exception('onFlagComment event requires "flag" param with FlagModel instance');
        }

        $this->raiseEvent('onFlagComment', $event);
    }
}