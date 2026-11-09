<?php
namespace verbb\comments\migrations\support;

use verbb\comments\Comments;
use verbb\comments\enums\ModerationState;

use craft\db\Connection;
use craft\db\Query;

/**
 * Proves the migrated data before anything is archived or removed.
 *
 * Each check compares the source tables with their v4 destination. A non-zero count stops the upgrade
 * with both still intact; there is no setting to skip this.
 */
final class V3Reconciler
{
    // Constants
    // =========================================================================

    private const STATE_BY_STATUS = [
        'approved' => ModerationState::Approved,
        'pending' => ModerationState::Pending,
        'spam' => ModerationState::Spam,
        'trashed' => ModerationState::Removed,
        // Cleanup has already rewritten this row if it stopped and is being run again.
        'removed' => ModerationState::Removed,
    ];


    // Public Methods
    // =========================================================================

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /** Failed checks and how many rows each affects. */
    public function failures(): array
    {
        return array_filter(array_merge(
            $this->_checkComments(),
            $this->_checkHierarchy(),
            $this->_checkEngagement(),
            $this->_checkAggregates(),
        ));
    }


    // Private Methods
    // =========================================================================

    private function _checkComments(): array
    {
        $comments = (new Query())->from(['c' => '{{%comments_comments}}']);
        $failures = [];

        // Nothing here adds or removes rows, so this catches anything else touching the table.
        $total = V3UpgradeLog::countFor($this->db, 'comments.total');
        $current = (int)(clone $comments)->count('*', $this->db);

        if ($total !== null && $total !== $current) {
            $failures['comments.countChanged'] = abs($total - $current);
        }

        $failures['comments.noIdentity'] = $this->_count((clone $comments)->where(['c.identityId' => null]));
        $failures['comments.noState'] = $this->_count((clone $comments)->where(['c.moderationState' => null]));
        $failures['comments.noFormat'] = $this->_count((clone $comments)->where(['c.contentFormat' => null]));

        $failures['comments.contentLost'] = $this->_count((clone $comments)
            ->where(['not', ['c.comment' => null]])
            ->andWhere(['or', ['c.content' => null], '[[c.content]] <> [[c.comment]]']));

        $failures['comments.noConversation'] = $this->_count((clone $comments)
            ->where(['c.conversationId' => null])
            ->andWhere(['not', ['c.ownerId' => null]])
            ->andWhere(['not', ['c.ownerSiteId' => null]]));

        $failures['comments.wrongConversation'] = $this->_count((clone $comments)
            ->innerJoin(['cv' => '{{%comments_conversations}}'], '[[cv.id]] = [[c.conversationId]]')
            ->where('[[cv.ownerId]] <> [[c.ownerId]] OR [[cv.siteId]] <> [[c.ownerSiteId]]'));

        $wrongState = ['or'];

        foreach (self::STATE_BY_STATUS as $status => $state) {
            $wrongState[] = ['and', ['c.status' => $status], ['not', ['c.moderationState' => $state->value]]];
        }

        $failures['comments.wrongState'] = $this->_count((clone $comments)->where($wrongState));

        return $failures;
    }

    private function _checkHierarchy(): array
    {
        $comments = (new Query())->from(['c' => '{{%comments_comments}}']);
        $withParent = (clone $comments)->innerJoin(['p' => '{{%comments_comments}}'], '[[p.id]] = [[c.parentId]]');

        return [
            'hierarchy.noRoot' => $this->_count((clone $comments)->where(['c.rootId' => null])),
            'hierarchy.invalidRoot' => $this->_count((clone $comments)
                ->where(['c.parentId' => null])
                ->andWhere('[[c.rootId]] <> [[c.id]] OR [[c.depth]] <> 0')),
            // Depth rising by exactly one along every parent link also rules out cycles.
            'hierarchy.parentMismatch' => $this->_count((clone $withParent)
                ->where('[[c.rootId]] <> [[p.rootId]] OR [[c.depth]] <> [[p.depth]] + 1')),
            'hierarchy.crossConversation' => $this->_count((clone $withParent)
                ->where('[[c.conversationId]] <> [[p.conversationId]]')
                ->orWhere(['and', ['c.conversationId' => null], ['not', ['p.conversationId' => null]]])
                ->orWhere(['and', ['p.conversationId' => null], ['not', ['c.conversationId' => null]]])),
        ];
    }

    private function _checkEngagement(): array
    {
        $failures = [];

        $votes = V3Schema::resolve(V3Schema::VOTES, $this->db);

        if ($votes !== null) {
            $directional = (new Query())
                ->from($votes)
                ->where(['not', ['commentId' => null]])
                ->andWhere(['or', ['upvote' => true], ['downvote' => true]]);

            $failures['votes.mismatch'] = abs($this->_expectedPerActor($directional)
                - (int)(new Query())->from('{{%comments_interactions}}')->count('*', $this->db));
        }

        $flags = V3Schema::resolve(V3Schema::FLAGS, $this->db);

        if ($flags !== null) {
            $source = (new Query())->from($flags)->where(['not', ['commentId' => null]]);

            $failures['flags.mismatch'] = abs($this->_expectedPerActor($source)
                - (int)(new Query())->from('{{%comments_reports}}')->count('*', $this->db));
        }

        $subscribe = V3Schema::resolve(V3Schema::SUBSCRIBE, $this->db);

        if ($subscribe !== null) {
            $deliverable = (new Query())
                ->from($subscribe)
                ->where(['not', ['userId' => null]])
                ->andWhere(['not', ['ownerId' => null]])
                ->andWhere(['not', ['ownerSiteId' => null]]);

            $expected = $this->_distinctCount((clone $deliverable), ['ownerId', 'ownerSiteId', 'userId', 'commentId']);

            $failures['subscriptions.mismatch'] = abs($expected
                - (int)(new Query())->from('{{%comments_subscriptions}}')->count('*', $this->db));

            // An upgrade must never email more people than Comments 3 would have.
            $failures['subscriptions.broadened'] = max(0, (int)(new Query())
                ->from('{{%comments_subscriptions}}')
                ->where(['subscribed' => true])
                ->count('*', $this->db) - $this->_count((clone $deliverable)->andWhere(['subscribed' => true])));
        }

        return $failures;
    }

    private function _checkAggregates(): array
    {
        $conversations = Comments::$plugin->getConversations();
        $drift = 0;

        foreach ((new Query())->select(['id', 'commentCount'])->from('{{%comments_conversations}}')->each(500, $this->db) as $row) {
            if ((int)$row['commentCount'] !== (int)$conversations->publicCommentQuery((int)$row['id'])->count('*', $this->db)) {
                $drift++;
            }
        }

        return ['conversations.aggregateDrift' => $drift];
    }

    /**
     * Votes and flags collapse to one per comment for each user or guest session, and a row with
     * neither stands alone.
     */
    private function _expectedPerActor(Query $source): int
    {
        return $this->_distinctCount((clone $source)->andWhere(['not', ['userId' => null]]), ['commentId', 'userId'])
            + $this->_distinctCount((clone $source)
                ->andWhere(['userId' => null])
                ->andWhere(['not', ['sessionId' => null]])
                ->andWhere(['not', ['sessionId' => '']]), ['commentId', 'sessionId'])
            + $this->_count((clone $source)
                ->andWhere(['userId' => null])
                ->andWhere(['or', ['sessionId' => null], ['sessionId' => '']]));
    }

    private function _distinctCount(Query $query, array $columns): int
    {
        return (int)(new Query())
            ->from(['d' => (clone $query)->select($columns)->distinct()])
            ->count('*', $this->db);
    }

    private function _count(Query $query): int
    {
        return (int)(clone $query)->count('*', $this->db);
    }
}
