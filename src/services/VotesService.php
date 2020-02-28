<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\events\VoteEvent;
use verbb\comments\errors\VoteNotFoundException;
use verbb\comments\models\Vote as VoteModel;
use verbb\comments\records\Vote as VoteRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

class VotesService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_VOTE = 'beforeSaveVote';
    const EVENT_AFTER_SAVE_VOTE = 'afterSaveVote';
    const EVENT_BEFORE_DELETE_VOTE = 'beforeDeleteVote';
    const EVENT_AFTER_DELETE_VOTE = 'afterDeleteVote';


    // Properties
    // =========================================================================

    private $_votesById;


    // Public Methods
    // =========================================================================

    public function getVoteByCommentId(int $commentId)
    {
        $result = $this->_createVotesQuery()
            ->where(['commentId' => $commentId])
            ->one();

        return $result ? new VoteModel($result) : null;
    }

    public function getVoteByUser(int $commentId, $userId)
    {
        $result = $this->_createVotesQuery()
            ->where(['commentId' => $commentId, 'userId' => $userId])
            ->one();

        return $result ? new VoteModel($result) : null;
    }

    public function getVotesByCommentId(int $commentId)
    {
        return $this->_createVotesQuery()
            ->where(['commentId' => $commentId])
            ->count();
    }

    public function getUpvotesByCommentId(int $commentId)
    {
        return $this->_createVotesQuery()
            ->where(['commentId' => $commentId, 'upvote' => '1'])
            ->count();
    }

    public function getDownvotesByCommentId(int $commentId)
    {
        return $this->_createVotesQuery()
            ->where(['commentId' => $commentId, 'downvote' => '1'])
            ->count();
    }

    public function hasDownVoted($comment, $user)
    {
        return $this->_createVotesQuery()
            ->where(['commentId' => $comment->id, 'userId' => $user->id, 'downvote' => '1'])
            ->exists();
    }

    public function hasUpVoted($comment, $user)
    {
        return $this->_createVotesQuery()
            ->where(['commentId' => $comment->id, 'userId' => $user->id, 'upvote' => '1'])
            ->exists();
    }

    public function isOverDownvoteThreshold($comment)
    {
        $threshold = Comments::$plugin->getSettings()->downvoteCommentLimit;
        $downvotes = $this->getDownvotesByCommentId($comment->id);

        if ($downvotes >= $threshold) {
            return true;
        }

        return false;
    }

    public function saveVote(VoteModel $vote, bool $runValidation = true): bool
    {
        $isNewVote = !$vote->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_VOTE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_VOTE, new VoteEvent([
                'vote' => $vote,
                'isNew' => $isNewVote,
            ]));
        }

        if ($runValidation && !$vote->validate()) {
            Craft::info('Vote not saved due to validation error.', __METHOD__);
            return false;
        }

        $voteRecord = $this->_getVoteRecordById($vote->id);

        $voteRecord->commentId = $vote->commentId;
        $voteRecord->userId = $vote->userId;
        $voteRecord->upvote = $vote->upvote;
        $voteRecord->downvote = $vote->downvote;

        // Save the record
        $voteRecord->save(false);

        // Now that we have a ID, save it on the model
        if ($isNewVote) {
            $vote->id = $voteRecord->id;
        }

        // Might as well update our cache of the model while we have it.
        $this->_votesById[$vote->id] = $vote;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_VOTE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_VOTE, new VoteEvent([
                'vote' => $vote,
                'isNew' => $isNewVote,
            ]));
        }

        return true;
    }

    public function deleteVoteById(int $voteId): bool
    {
        $vote = $this->getVoteById($voteId);

        if (!$vote) {
            return false;
        }

        return $this->deleteVote($vote);
    }

    public function deleteVote(VoteModel $vote): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_VOTE)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_VOTE, new VoteEvent([
                'vote' => $vote,
            ]));
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%comments_votes}}', ['id' => $vote->id])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_VOTE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_VOTE, new VoteEvent([
                'vote' => $vote,
            ]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _getVoteRecordById(int $voteId = null): VoteRecord
    {
        if ($voteId !== null) {
            $voteRecord = VoteRecord::findOne($voteId);

            if (!$voteRecord) {
                throw new VoteNotFoundException("No vote exists with the ID '{$voteId}'");
            }
        } else {
            $voteRecord = new VoteRecord();
        }

        return $voteRecord;
    }

    private function _createVotesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'commentId',
                'userId',
                'upvote',
                'downvote',
            ])
            ->from(['{{%comments_votes}}']);
    }

}
