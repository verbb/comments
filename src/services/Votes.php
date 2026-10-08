<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\events\VoteEvent;
use verbb\comments\errors\VoteNotFoundException;
use verbb\comments\models\Vote as VoteModel;
use verbb\comments\records\Vote as VoteRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

use yii\base\Event;

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
    private array $_voteCounts = [];
    private array $_viewerVotes = [];


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        foreach ([Comment::EVENT_AFTER_SAVE, Comment::EVENT_AFTER_DELETE, Comment::EVENT_AFTER_RESTORE] as $event) {
            Event::on(Comment::class, $event, function(): void {
                $this->_invalidateVoteCaches();
            });
        }
    }

    public function getVoteByUser(int $commentId, $userId)
    {
        $votes = $this->_getViewerVotes($commentId, $userId);

        return $votes ? reset($votes) : null;
    }

    public function getVotesByCommentId(int $commentId): int
    {
        return $this->_getVoteCounts($commentId)['total'];
    }

    public function getUpvotesByCommentId(int $commentId): int
    {
        return $this->_getVoteCounts($commentId)['upvotes'];
    }

    public function getDownvotesByCommentId(int $commentId): int
    {
        return $this->_getVoteCounts($commentId)['downvotes'];
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

    // Net votes across approved comments, preserving the existing author-score scope.
    public function getScoreByAuthorId($userId): int
    {
        $userId = (int)$userId;

        if (!$userId) {
            return 0;
        }

        if (!array_key_exists($userId, $this->_authorScores)) {
            $authorIds = array_values(array_diff(array_unique(array_merge(
                Comments::$plugin->getRenderCache()->getAuthorIds(),
                [(int)$userId],
            )), array_keys($this->_authorScores)));

            $rows = (new Query())
                ->select([
                    'comments.userId',
                    'score' => 'SUM(CASE WHEN votes.upvote = 1 THEN 1 ELSE 0 END) - SUM(CASE WHEN votes.downvote = 1 THEN 1 ELSE 0 END)',
                ])
                ->from('{{%comments_comments}} comments')
                ->innerJoin('{{%comments_votes}} votes', '[[votes.commentId]] = [[comments.id]]')
                ->where(['comments.userId' => $authorIds, 'comments.status' => Comment::STATUS_APPROVED])
                ->groupBy('comments.userId')
                ->all();

            foreach ($authorIds as $authorId) {
                $this->_authorScores[$authorId] = 0;
            }

            foreach ($rows as $row) {
                $this->_authorScores[$row['userId']] = (int)$row['score'];
            }
        }

        return $this->_authorScores[$userId];
    }

    public function hasDownVoted($comment, $user): bool
    {
        foreach ($this->_getViewerVotes($comment->id, $user->id ?? null) as $vote) {
            if ($vote->downvote == 1) {
                return true;
            }
        }

        return false;
    }

    public function hasUpVoted($comment, $user): bool
    {
        foreach ($this->_getViewerVotes($comment->id, $user->id ?? null) as $vote) {
            if ($vote->upvote == 1) {
                return true;
            }
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
        $this->_invalidateVoteCaches();

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

        $this->_invalidateVoteCaches();

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

    private function _getVoteCounts(int $commentId): array
    {
        if (!array_key_exists($commentId, $this->_voteCounts)) {
            $commentIds = $this->_getUncachedCommentIds($commentId, $this->_voteCounts);
            $rows = (new Query())
                ->select([
                    'commentId',
                    'total' => 'COUNT(*)',
                    'upvotes' => 'SUM(CASE WHEN upvote = 1 THEN 1 ELSE 0 END)',
                    'downvotes' => 'SUM(CASE WHEN downvote = 1 THEN 1 ELSE 0 END)',
                ])
                ->from('{{%comments_votes}}')
                ->where(['commentId' => $commentIds])
                ->groupBy('commentId')
                ->all();

            foreach ($commentIds as $id) {
                $this->_voteCounts[$id] = ['total' => 0, 'upvotes' => 0, 'downvotes' => 0];
            }

            foreach ($rows as $row) {
                $this->_voteCounts[$row['commentId']] = [
                    'total' => (int)$row['total'],
                    'upvotes' => (int)$row['upvotes'],
                    'downvotes' => (int)$row['downvotes'],
                ];
            }
        }

        return $this->_voteCounts[$commentId];
    }

    private function _getViewerVotes(int $commentId, $userId): array
    {
        $identity = $userId ? ['userId' => $userId] : ['sessionId' => $this->_getSessionId()];
        $key = key($identity) . ':' . reset($identity);
        $cached = $this->_viewerVotes[$key] ?? [];

        if (!array_key_exists($commentId, $cached)) {
            $commentIds = $this->_getUncachedCommentIds($commentId, $cached);
            $rows = $this->_createVotesQuery()
                ->where(['commentId' => $commentIds])
                ->andWhere($identity)
                ->all();

            foreach ($commentIds as $id) {
                $cached[$id] = [];
            }

            foreach ($rows as $row) {
                $cached[$row['commentId']][] = new VoteModel($row);
            }

            $this->_viewerVotes[$key] = $cached;
        }

        return $cached[$commentId];
    }

    private function _getUncachedCommentIds(int $commentId, array $cached): array
    {
        return array_values(array_diff(array_unique(array_merge(
            Comments::$plugin->getRenderCache()->getCommentIds(),
            [$commentId],
        )), array_keys($cached)));
    }

    private function _invalidateVoteCaches(): void
    {
        $this->_voteCounts = [];
        $this->_viewerVotes = [];
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
