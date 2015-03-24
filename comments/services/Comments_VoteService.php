<?php
namespace Craft;

class Comments_VoteService extends BaseApplicationComponent
{
    public function getAllVotes()
    {
        $records = Comments_VoteRecord::model()->findAll();
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

    public function saveVote(Comments_VoteModel $model)
    {
        $record = new Comments_VoteRecord();

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