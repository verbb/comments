<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\compatibility\events\LegacyEngagementEvents;
use verbb\comments\elements\Comment;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\events\VoteChangeEvent;
use verbb\comments\exceptions\RefusedException;
use verbb\comments\records\Interaction as InteractionRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\StringHelper;

use yii\base\Event;

use yii\db\IntegrityException;

use InvalidArgumentException;

/**
 * One current Interaction per Identity/Comment. Upsert on unique (commentId, identityId).
 */
class Interactions extends Component
{
    // Constants
    // =========================================================================

    /** Before someone's vote changes. Setting `$isValid` to false stops it. */
    public const EVENT_BEFORE_VOTE = 'beforeVote';

    /** After someone's vote changes: given, switched, or taken back when `$type` is null. */
    public const EVENT_AFTER_VOTE = 'afterVote';


    // Properties
    // =========================================================================

    // Comments whose interactions changed during this request, so totals a query loaded earlier are stale.
    private array $_changedCommentIds = [];

    // Authors' scores loaded during this request, by user ID.
    private array $_authorScores = [];


    // Public Methods
    // =========================================================================

    public function set(int $commentId, int $identityId, InteractionType $type): InteractionRecord
    {
        $previous = InteractionType::tryFrom((string)$this->getForIdentity($commentId, $identityId)?->type);
        $event = $previous === $type ? null : $this->_voteEvent($commentId, $identityId, $type, $previous);

        if ($event) {
            $this->_beforeVote($event);
        }

        // Votes are public in the data API's cached responses, and they aren't element saves, so those
        // are cleared once the vote is written.
        try {
            $record = $this->_set($commentId, $identityId, $type);
        } finally {
            CommentsPlugin::$plugin->getApi()->invalidateCaches();
        }

        if ($event) {
            $this->_afterVote($event);
        }

        return $record;
    }

    public function remove(int $commentId, int $identityId): bool
    {
        $previous = InteractionType::tryFrom((string)$this->getForIdentity($commentId, $identityId)?->type);
        $event = $previous ? $this->_voteEvent($commentId, $identityId, null, $previous) : null;

        if ($event) {
            $this->_beforeVote($event);
        }

        try {
            $removed = $this->_remove($commentId, $identityId);
        } finally {
            CommentsPlugin::$plugin->getApi()->invalidateCaches();
        }

        if ($event && $removed) {
            $this->_afterVote($event);
        }

        return $removed;
    }

    /**
     * Loads the scores of these comments' signed-in authors at once, for a thread that shows them.
     *
     * @param Comment[] $comments
     */
    public function primeAuthorScores(array $comments): void
    {
        $userIds = array_values(array_unique(array_filter(array_map(static fn(Comment $comment): int => (int)$comment->userId, $comments))));
        $missing = array_values(array_diff($userIds, array_keys($this->_authorScores)));

        if ($missing === []) {
            return;
        }

        $rows = (new Query())
            ->select(['c.userId', 'type' => 'i.type', 'total' => 'COUNT(*)'])
            ->from(['i' => '{{%comments_interactions}}'])
            ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[i.commentId]]')
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]')
            ->where(['c.userId' => $missing, 'c.moderationState' => ModerationState::Approved->value, 'c.authorRemovedAt' => null, 'e.dateDeleted' => null])
            ->groupBy(['c.userId', 'i.type'])
            ->all();

        foreach ($missing as $userId) {
            $this->_authorScores[$userId] = 0;
        }

        foreach ($rows as $row) {
            $sign = match ((string)$row['type']) {
                InteractionType::Downvote->value => -1,
                InteractionType::Upvote->value => 1,
                default => 0,
            };
            $this->_authorScores[(int)$row['userId']] += $sign * (int)$row['total'];
        }
    }

    /**
     * A signed-in author's net votes across their approved comments: upvotes and likes, less downvotes.
     */
    public function getAuthorScore(int $userId): int
    {
        if (!array_key_exists($userId, $this->_authorScores)) {
            $this->primeAuthorScores([new Comment(['userId' => $userId])]);
        }

        return $this->_authorScores[$userId] ?? 0;
    }

    public function getForIdentity(int $commentId, int $identityId): ?InteractionRecord
    {
        return InteractionRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
        ]);
    }

    /** The viewer's interaction for each comment, keyed by comment ID. */
    public function getViewerState(array $commentIds, int $identityId): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        $result = array_fill_keys($commentIds, null);

        if ($commentIds === []) {
            return $result;
        }

        /** @var InteractionRecord[] $rows */
        $rows = InteractionRecord::find()
            ->where([
                'commentId' => $commentIds,
                'identityId' => $identityId,
            ])
            ->all();

        foreach ($rows as $row) {
            $result[(int)$row->commentId] = $row;
        }

        return $result;
    }

    public function getTotals(array $commentIds): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        $result = [];

        foreach ($commentIds as $id) {
            $result[$id] = ['upvote' => 0, 'downvote' => 0];
        }

        if ($commentIds === []) {
            return $result;
        }

        $rows = (new Query())
            ->select(['commentId', 'type', 'total' => 'COUNT(*)'])
            ->from('{{%comments_interactions}}')
            ->where(['commentId' => $commentIds])
            ->groupBy(['commentId', 'type'])
            ->all();

        foreach ($rows as $row) {
            $commentId = (int)$row['commentId'];
            $type = (string)$row['type'];

            if (isset($result[$commentId][$type])) {
                $result[$commentId][$type] = (int)$row['total'];
            }
        }

        return $result;
    }

    public function getForUser(int $userId, ?InteractionType $type = null): array
    {
        $identityId = (new Query())
            ->select(['id'])
            ->from('{{%comments_identities}}')
            ->where(['userId' => $userId])
            ->scalar();

        if (!$identityId) {
            return [];
        }

        $query = InteractionRecord::find()->where(['identityId' => (int)$identityId]);

        if ($type) {
            $query->andWhere(['type' => $type->value]);
        }

        return $query->all();
    }

    /**
     * Toggle an interaction: re-applying the active type clears it, applying the
     * opposite type switches to it (relevant only in up/down mode).
     *
     * Returns the current interaction type, or null when cleared.
     */
    public function toggle(int $commentId, int $identityId, InteractionType $requested): ?InteractionType
    {
        $existing = $this->getForIdentity($commentId, $identityId);
        $current = $existing ? InteractionType::tryFrom((string)$existing->type) : null;

        if ($current === $requested) {
            $this->remove($commentId, $identityId);

            return null;
        }

        $this->set($commentId, $identityId, $requested);

        return $requested;
    }

    /**
     * Toggle with v3 front-end semantics, where the caller only knew about a boolean upvote flag.
     */
    public function toggleVote(int $commentId, int $identityId, bool $upvote): ?InteractionType
    {
        return $this->toggle(
            $commentId,
            $identityId,
            $upvote ? InteractionType::Upvote : InteractionType::Downvote,
        );
    }

    /**
     * Whether votes on a comment changed during this request, so totals a query loaded before are stale.
     */
    public function hasChangedInRequest(int $commentId): bool
    {
        return isset($this->_changedCommentIds[$commentId]);
    }

    /**
     * Batched presentation state for a set of comments: totals plus the viewer's own interaction.
     *
     * A comment query loads totals with the comments, so only comments without them, or whose votes
     * changed during this request, are counted again. An ID finds the comment this request loaded through
     * the render cache. Identity may be null for anonymous viewers, in which case only totals are populated.
     */
    public function getPresentationState(array $comments, ?int $identityId): array
    {
        $totals = [];
        $countIds = [];

        foreach ($comments as $comment) {
            $commentId = (int)($comment instanceof Comment ? $comment->id : $comment);

            if (!$commentId) {
                continue;
            }

            $comment = $comment instanceof Comment ? $comment : CommentsPlugin::$plugin->getRenderCache()->getComment($commentId);

            if ($comment instanceof Comment && $comment->hasInteractionTotals()) {
                $totals[$commentId] = ['upvote' => $comment->getUpvotes(), 'downvote' => $comment->getDownvotes()];
            } else {
                $totals[$commentId] = null;
                $countIds[] = $commentId;
            }
        }

        // One query for whatever wasn't already loaded, keeping the order the comments came in.
        $counted = $countIds !== [] ? $this->getTotals($countIds) : [];
        $viewerState = $identityId !== null && $totals !== []
            ? $this->getViewerState(array_keys($totals), $identityId)
            : [];

        $result = [];

        foreach ($totals as $commentId => $counts) {
            $counts ??= $counted[$commentId];
            $viewer = $viewerState[$commentId] ?? null;

            $result[$commentId] = [
                'upvotes' => $counts['upvote'],
                'downvotes' => $counts['downvote'],
                'score' => $counts['upvote'] - $counts['downvote'],
                'viewer' => $viewer ? (string)$viewer->type : null,
            ];
        }

        return $result;
    }


    // Private Methods
    // =========================================================================

    private function _set(int $commentId, int $identityId, InteractionType $type): InteractionRecord
    {
        $this->_changedCommentIds[$commentId] = true;
        $this->_authorScores = [];
        $existing = InteractionRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
        ]);

        if ($existing) {
            if ($existing->type === $type->value) {
                return $existing;
            }

            $existing->type = $type->value;
            $existing->save(false);

            return $existing;
        }

        $record = new InteractionRecord([
            'commentId' => $commentId,
            'identityId' => $identityId,
            'type' => $type->value,
            'uid' => StringHelper::UUID(),
        ]);

        try {
            $record->save(false);
        } catch (IntegrityException) {
            $existing = InteractionRecord::findOne([
                'commentId' => $commentId,
                'identityId' => $identityId,
            ]);

            if (!$existing) {
                throw new InvalidArgumentException('Failed to save Interaction after unique-index race.');
            }

            $existing->type = $type->value;
            $existing->save(false);

            return $existing;
        }

        return $record;
    }

    /**
     * The event for a vote changing, or null when nothing is listening, so a vote costs no lookups for it.
     */
    private function _voteEvent(int $commentId, int $identityId, ?InteractionType $type, ?InteractionType $previous): ?VoteChangeEvent
    {
        $listening = $this->hasEventHandlers(self::EVENT_BEFORE_VOTE)
            || $this->hasEventHandlers(self::EVENT_AFTER_VOTE)
            || (Compatibility::enabled() && array_filter([Votes::EVENT_BEFORE_SAVE_VOTE, Votes::EVENT_AFTER_SAVE_VOTE, Votes::EVENT_BEFORE_DELETE_VOTE, Votes::EVENT_AFTER_DELETE_VOTE], static fn(string $name): bool => Event::hasHandlers(Votes::class, $name)) !== []);

        $comment = $listening ? Comment::find()->id($commentId)->siteId('*')->status(null)->one() : null;

        if (!$comment) {
            return null;
        }

        $userId = CommentsPlugin::$plugin->getIdentities()->getById($identityId)?->userId;

        return new VoteChangeEvent([
            'comment' => $comment,
            'identityId' => $identityId,
            'user' => $userId ? User::find()->id((int)$userId)->status(null)->one() : null,
            'type' => $type,
            'previousType' => $previous,
        ]);
    }

    private function _beforeVote(VoteChangeEvent $event): void
    {
        $this->trigger(self::EVENT_BEFORE_VOTE, $event);
        LegacyEngagementEvents::vote($event, true);

        if (!$event->isValid) {
            throw new RefusedException(Craft::t('comments', 'Your vote couldn’t be saved.'));
        }
    }

    private function _afterVote(VoteChangeEvent $event): void
    {
        $this->trigger(self::EVENT_AFTER_VOTE, $event);
        LegacyEngagementEvents::vote($event, false);
    }

    private function _remove(int $commentId, int $identityId): bool
    {
        $this->_changedCommentIds[$commentId] = true;
        $this->_authorScores = [];
        $existing = InteractionRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
        ]);

        if (!$existing) {
            return false;
        }

        return (bool)$existing->delete();
    }
}
