<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\events\VoteEvent;
use verbb\comments\errors\VoteNotFoundException;
use verbb\comments\models\Vote as VoteModel;
use verbb\comments\records\Vote as VoteRecord;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
use craft\db\Query;

class Votes extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_VOTE = 'beforeSaveVote';
    public const EVENT_AFTER_SAVE_VOTE = 'afterSaveVote';
    public const EVENT_BEFORE_DELETE_VOTE = 'beforeDeleteVote';
    public const EVENT_AFTER_DELETE_VOTE = 'afterDeleteVote';


    // Properties
    // =========================================================================
    protected string $sessionName = 'comments_vote';

    private array $_authorScores = [];
    private array $_votesByComment = [];


    // Public Methods
    // =========================================================================

    public function getVoteByUser(int $commentId, $userId)
    {
        // Try and fetch votes for a user, if not, use their sessionId
        $votes = $this->_votes($commentId);
        $criteria = ['commentId' => $commentId];

        if ($userId) {
            $criteria['userId'] = $userId;
        } else {
            $criteria['sessionId'] = $this->_getSessionId();
        }

        if ($items = ArrayHelper::whereMultiple($votes, $criteria)) {
            return reset($items);
        }

        return null;
    }

    public function getVotesByCommentId(int $commentId): int
    {
        return count($this->_votes($commentId));
    }

    public function getUpvotesByCommentId(int $commentId): int
    {
        return count(ArrayHelper::whereMultiple($this->_votes($commentId), ['commentId' => $commentId, 'upvote' => '1']));
    }

    public function getDownvotesByCommentId(int $commentId): int
    {
        return count(ArrayHelper::whereMultiple($this->_votes($commentId), ['commentId' => $commentId, 'downvote' => '1']));
    }

    public function getVotesByUserId($userId): array
    {
        $votes = [];

        $query = $this->_createVotesQuery()
            ->where(['userId' => $userId]);

        foreach ($query->all() as $result) {
            $votes[] = new VoteModel($result);
        }

        return $votes;
    }

    public function getUpvotesByUserId($userId): array
    {
        $votes = [];

        $query = $this->_createVotesQuery()
            ->where(['userId' => $userId, 'upvote' => 1]);

        foreach ($query->all() as $result) {
            $votes[] = new VoteModel($result);
        }

        return $votes;
    }

    public function getDownvotesByUserId($userId): array
    {
        $votes = [];

        $query = $this->_createVotesQuery()
            ->where(['userId' => $userId, 'downvote' => 1]);

        foreach ($query->all() as $result) {
            $votes[] = new VoteModel($result);
        }

        return $votes;
    }

    // The total reputation for an author: net votes (upvotes - downvotes) across all of their
    // approved comments. Cached per-request so rendering many comments by the same author is cheap.
    public function getScoreByAuthorId($userId): int
    {
        // Only registered users have a stable identity to aggregate a score against
        if (!$userId) {
            return 0;
        }

        if (array_key_exists($userId, $this->_authorScores)) {
            return $this->_authorScores[$userId];
        }

        // All approved comments authored by this user (spam/pending/trashed don't count)
        $commentIds = (new Query())
            ->select(['id'])
            ->from('{{%comments_comments}}')
            ->where(['userId' => $userId, 'status' => 'approved']);

        $upvotes = (new Query())
            ->from('{{%comments_votes}}')
            ->where(['commentId' => $commentIds, 'upvote' => 1])
            ->count();

        $downvotes = (new Query())
            ->from('{{%comments_votes}}')
            ->where(['commentId' => $commentIds, 'downvote' => 1])
            ->count();

        return $this->_authorScores[$userId] = (int)$upvotes - (int)$downvotes;
    }

    public function hasDownVoted($comment, $user): bool
    {
        // Try and fetch votes for a user, if not, use their sessionId
        $votes = $this->_votes($comment->id);
        $criteria = ['commentId' => $comment->id, 'downvote' => '1'];

        if ($user->id) {
            $criteria['userId'] = $user->id;
        } else {
            $criteria['sessionId'] = $this->_getSessionId();
        }

        if ($items = ArrayHelper::whereMultiple($votes, $criteria)) {
            reset($items);

            return true;
        }

        return false;
    }

    public function hasUpVoted($comment, $user): bool
    {
        // Try and fetch votes for a user, if not, use their sessionId
        $votes = $this->_votes($comment->id);
        $criteria = ['commentId' => $comment->id, 'upvote' => '1'];

        if ($user->id) {
            $criteria['userId'] = $user->id;
        } else {
            $criteria['sessionId'] = $this->_getSessionId();
        }

        if ($items = ArrayHelper::whereMultiple($votes, $criteria)) {
            reset($items);

            return true;
        }

        return false;
    }

    public function isOverDownvoteThreshold($comment): bool
    {
        $threshold = Comments::$plugin->getSettings()->downvoteCommentLimit;
        $downvotes = $this->getDownvotesByCommentId($comment->id);

        return $downvotes >= $threshold;
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
        $voteRecord->sessionId = $this->_getSessionId();
        $voteRecord->upvote = $vote->upvote;
        $voteRecord->downvote = $vote->downvote;

        if (Craft::$app->getConfig()->getGeneral()->storeUserIps) {
            $voteRecord->lastIp = Craft::$app->getRequest()->userIP;
        }

        // Save the record
        $voteRecord->save(false);

        // Bust the request caches so any later read in this request sees the new vote
        $this->_invalidateVoteCaches($voteRecord->commentId);

        // Now that we have an ID, save it on the model
        if ($isNewVote) {
            $vote->id = $voteRecord->id;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_VOTE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_VOTE, new VoteEvent([
                'vote' => $vote,
                'isNew' => $isNewVote,
            ]));
        }

        return true;
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

        $this->_invalidateVoteCaches($vote->commentId);

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_VOTE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_VOTE, new VoteEvent([
                'vote' => $vote,
            ]));
        }

        return true;
    }

    public function generateSessionId(): string
    {
        return md5(uniqid(mt_rand(), true));
    }


    // Private Methods
    // =========================================================================

    private function _votes($commentId = null): array
    {
        // Memoize per-comment lookups for the request. The plugin calls this multiple times per
        // comment (count, upvotes, downvotes, hasUpVoted, hasDownVoted), so without this each
        // call re-queries. Votes don't change mid-render; saveVote/deleteVote bust the entry.
        if ($commentId !== null && array_key_exists($commentId, $this->_votesByComment)) {
            return $this->_votesByComment[$commentId];
        }

        $votes = [];

        $query = $this->_createVotesQuery();

        if ($commentId) {
            $query->where(['commentId' => $commentId]);
        }

        foreach ($query->all() as $result) {
            $votes[] = new VoteModel($result);
        }

        if ($commentId !== null) {
            $this->_votesByComment[$commentId] = $votes;
        }

        return $votes;
    }

    // Clears the request caches for a comment's votes (and all author scores, since a vote
    // changes an author's total). Called whenever a vote is saved or deleted.
    private function _invalidateVoteCaches($commentId): void
    {
        unset($this->_votesByComment[$commentId]);
        $this->_authorScores = [];
    }

    private function _getSessionId()
    {
        $session = Craft::$app->getSession();
        $sessionId = $session[$this->sessionName];

        if (!$sessionId) {
            $sessionId = $this->generateSessionId();
            $session->set($this->sessionName, $sessionId);
        }

        return $sessionId;
    }

    private function _getVoteRecordById(int $voteId = null): ?VoteRecord
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
