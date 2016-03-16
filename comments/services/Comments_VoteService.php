<?php
namespace Craft;

class Comments_VoteService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getAllVotes()
    {
        $records = Comments_VoteRecord::model()->findAll();
        return Comments_VoteModel::populateModels($records, 'id');
    }

    public function getVotesByCommentId($commentId)
    {
        $records = Comments_VoteRecord::model()->findAllByAttributes(array('commentId' => $commentId));
        return Comments_VoteModel::populateModels($records, 'id');
    }

    public function getDownvotesByCommentId($commentId)
    {
        $records = Comments_VoteRecord::model()->findAllByAttributes(array('commentId' => $commentId, 'downvote' => '1'));
        return Comments_VoteModel::populateModels($records, 'id');
    }

    public function getUpvotesByCommentId($commentId)
    {
        $records = Comments_VoteRecord::model()->findAllByAttributes(array('commentId' => $commentId, 'upvote' => '1'));
        return Comments_VoteModel::populateModels($records, 'id');
    }

    public function hasVoted($comment, $user)
    {
        $record = Comments_VoteRecord::model()->findByAttributes(array('commentId' => $comment->id, 'userId' => $user->id));

        return ($record) ? true : false;
    }

    public function hasDownVoted($comment, $user)
    {
        $record = Comments_VoteRecord::model()->findByAttributes(array('commentId' => $comment->id, 'userId' => $user->id, 'downvote' => '1'));

        return ($record) ? true : false;
    }

    public function hasUpVoted($comment, $user)
    {
        $record = Comments_VoteRecord::model()->findByAttributes(array('commentId' => $comment->id, 'userId' => $user->id, 'upvote' => '1'));

        return ($record) ? true : false;
    }

    public function saveVote(Comments_VoteModel $model)
    {
        $record = new Comments_VoteRecord();

        $record->setAttributes($model->getAttributes(), false);

        // Fire an 'onBeforeVoteComment' event
        $event = new Event($this, array('vote' => $model));
        $this->onBeforeVoteComment($event);

        // Allow event to cancel comment saving
        if (!$event->performAction) {
            return false;
        }

        if ($record->save()) {
            $model->id = $record->id;

            // Fire an 'onVoteComment' event
            $this->onVoteComment(new Event($this, array('vote' => $model)));

            $comment = craft()->comments->getCommentById($model->commentId);

            return array('success' => true, 'votes' => $comment->voteCount());
        } else {
            $model->addErrors($record->getErrors());
            return array('error' => $model->getErrors());
        }
    }

    public function isOverDownvoteThreshold($comment)
    {
        $threshold = craft()->comments->getSettings()->downvoteCommentLimit;
        $downvotes = $this->getDownvotesByCommentId($comment->id);

        if (count($downvotes) >= $threshold) {
            return true;
        } else {
            return false;
        }
    }



    // Event Handlers
    // =========================================================================

    public function onBeforeVoteComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['vote']) || !($params['vote'] instanceof Comments_VoteModel)) {
            throw new Exception('onBeforeVoteComment event requires "vote" param with VoteModel instance');
        }

        $this->raiseEvent('onBeforeVoteComment', $event);
    }

    public function onVoteComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['vote']) || !($params['vote'] instanceof Comments_VoteModel)) {
            throw new Exception('onVoteComment event requires "vote" param with VoteModel instance');
        }

        $this->raiseEvent('onVoteComment', $event);
    }


}