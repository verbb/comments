<?php
namespace verbb\comments\migrations\support;

use verbb\comments\Comments;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\IdentityType;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\ReportStatus;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\services\Moderation;
use verbb\comments\services\Reports;

use craft\db\Connection;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use yii\base\Exception;
use yii\db\Expression;

use DateTime;

/**
 * Migration-private v3 → v4 data migrator.
 *
 * Not a normal plugin service. Preserves Comment element IDs, maps Structure →
 * parentId/rootId/depth, and never merges historical guests by email.
 *
 * Every stage is safe to repeat: each one works out what's left from the data itself and commits in
 * small batches, so a timeout or failure on a large site loses at most one batch and the next
 * `craft up` carries on. Source tables are only read here; they're archived by the cleanup migration
 * once `V3Reconciler` has confirmed everything arrived.
 */
final class V3ToV4Migrator
{
    // Constants
    // =========================================================================

    private const BATCH_SIZE = 500;

    private const MAP_SESSION = 'session';
    private const MAP_VOTE = 'vote';
    private const MAP_FLAG = 'flag';


    // Properties
    // =========================================================================

    private readonly V3UpgradeLog $log;


    // Public Methods
    // =========================================================================

    public function __construct(
        private readonly Connection $db,
    ) {
        $this->log = new V3UpgradeLog($db);
    }

    public function run(): void
    {
        $this->log->reset();
        $this->_preflight();

        $this->_migrateConversations();
        $this->_migrateUserIdentities();
        $this->_migrateComments();
        $this->_migrateHierarchy();
        $this->_migrateInteractions();
        $this->_migrateReports();
        $this->_migrateSubscriptions();
        $this->_rebuildConversationAggregates();

        $this->log->flush();
    }


    // Private Methods
    // =========================================================================

    private function _preflight(): void
    {
        $report = V3IntegrityScanner::scan($this->db);

        foreach ($report->issues as $issue) {
            $this->log->addIssue($issue);
        }

        // Taken before anything changes, so the cleanup can prove no Comment went missing.
        $this->log->add('comments.total', V3UpgradeLog::INFO, null, $report->summary['comments'] ?? 0);
        $this->log->flush();

        if ($report->hasErrors()) {
            throw new Exception('This database doesn’t have the expected Comments 3 schema, so nothing was changed. Update to the latest Comments 3 release first.');
        }
    }

    private function _migrateConversations(): void
    {
        // Subscribers can follow an element nobody has commented on yet.
        foreach (['{{%comments_comments}}', V3Schema::SUBSCRIBE] as $source) {
            $query = (new Query())
                ->select(['s.ownerId', 's.ownerSiteId'])
                ->distinct()
                ->from(['s' => $source])
                ->leftJoin(['c' => '{{%comments_conversations}}'], '[[c.ownerId]] = [[s.ownerId]] AND [[c.siteId]] = [[s.ownerSiteId]]')
                ->where(['c.id' => null])
                ->andWhere(['not', ['s.ownerId' => null]])
                ->andWhere(['not', ['s.ownerSiteId' => null]])
                ->limit(self::BATCH_SIZE);

            while ($pairs = $query->all($this->db)) {
                Db::batchInsert('{{%comments_conversations}}', ['ownerId', 'siteId'], array_map(
                    static fn(array $pair): array => [(int)$pair['ownerId'], (int)$pair['ownerSiteId']],
                    $pairs,
                ), $this->db);
            }
        }
    }

    private function _migrateUserIdentities(): void
    {
        // Voters, flaggers and subscribers need an Identity even if they never commented.
        foreach (['{{%comments_comments}}', V3Schema::VOTES, V3Schema::FLAGS, V3Schema::SUBSCRIBE] as $source) {
            $query = (new Query())
                ->select(['u.id', 'u.fullName', 'u.username', 'u.email'])
                ->distinct()
                ->from(['s' => $source])
                ->innerJoin(['u' => Table::USERS], '[[u.id]] = [[s.userId]]')
                ->leftJoin(['i' => '{{%comments_identities}}'], '[[i.userId]] = [[s.userId]]')
                ->where(['i.id' => null])
                ->limit(self::BATCH_SIZE);

            while ($users = $query->all($this->db)) {
                Db::batchInsert('{{%comments_identities}}', ['type', 'userId', 'name', 'email'], array_map(
                    static fn(array $user): array => [
                        IdentityType::User->value,
                        (int)$user['id'],
                        $user['fullName'] ?: ($user['username'] ?: null),
                        $user['email'],
                    ],
                    $users,
                ), $this->db);
            }
        }
    }

    private function _migrateComments(): void
    {
        $query = (new Query())
            ->select([
                'c.id',
                'c.userId',
                'c.status',
                'c.comment',
                'c.name',
                'c.email',
                'conversationId' => 'cv.id',
                'userIdentityId' => 'i.id',
            ])
            ->from(['c' => '{{%comments_comments}}'])
            ->leftJoin(['cv' => '{{%comments_conversations}}'], '[[cv.ownerId]] = [[c.ownerId]] AND [[cv.siteId]] = [[c.ownerSiteId]]')
            ->leftJoin(['i' => '{{%comments_identities}}'], '[[i.userId]] = [[c.userId]]')
            ->where(['c.identityId' => null]);

        $this->_eachBatch($query, 'c.id', function(array $row) {
            $state = $this->_mapStatus($row['status']);

            $this->db->createCommand()->update('{{%comments_comments}}', [
                'conversationId' => $row['conversationId'],
                // One historical guest Identity per comment — never merge by email/name.
                'identityId' => $row['userIdentityId'] ?? $this->_createGuestIdentity($row['name'], $row['email']),
                'content' => $row['comment'],
                'contentFormat' => ContentFormat::Plain->value,
                'moderationState' => $state->value,
            ], ['id' => $row['id']], [], false)->execute();

            // Records how a held comment came to be held; it isn't a fabricated moderation history.
            if ($state !== ModerationState::Approved) {
                $this->db->createCommand()->insert('{{%comments_moderationactions}}', [
                    'commentId' => $row['id'],
                    'action' => Moderation::ACTION_IMPORT,
                    'toState' => $state->value,
                    'actorType' => Moderation::ACTOR_SYSTEM,
                    'dateCreated' => Db::prepareDateForDb(new DateTime()),
                ])->execute();
            }
        });
    }

    private function _migrateHierarchy(): void
    {
        $structureIds = V3Schema::structureIdsHoldingComments($this->db);
        $configuredId = V3Schema::configuredStructureId();

        // A comment in more than one Structure takes its hierarchy from the configured one.
        usort($structureIds, static fn(int $a, int $b): int => ($a === $configuredId) <=> ($b === $configuredId));

        foreach ($structureIds as $structureId) {
            $this->_walkStructure($structureId);
        }

        // Comments with no node can't be placed, so they keep their content as new threads.
        $this->db->createCommand()->update('{{%comments_comments}}', [
            'rootId' => new Expression('[[id]]'),
            'parentId' => null,
            'depth' => 0,
        ], ['rootId' => null], [], false)->execute();
    }

    private function _walkStructure(int $structureId): void
    {
        $ancestors = [];
        $treeRoot = null;
        $cursor = null;

        do {
            $query = (new Query())
                ->select(['nodeId' => 'se.id', 'se.root', 'se.lft', 'se.rgt', 'se.level', 'c.id', 'c.conversationId'])
                ->from(['se' => Table::STRUCTUREELEMENTS])
                ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[se.elementId]]')
                ->where(['se.structureId' => $structureId])
                ->andWhere(['not', ['se.root' => null]])
                ->orderBy(['se.root' => SORT_ASC, 'se.lft' => SORT_ASC, 'se.id' => SORT_ASC])
                ->limit(self::BATCH_SIZE);

            if ($cursor) {
                $query->andWhere(['or',
                    ['>', 'se.root', $cursor['root']],
                    ['and', ['se.root' => $cursor['root']], ['>', 'se.lft', $cursor['lft']]],
                    ['and', ['se.root' => $cursor['root']], ['se.lft' => $cursor['lft']], ['>', 'se.id', $cursor['nodeId']]],
                ]);
            }

            $rows = $query->all($this->db);

            $this->db->transaction(function() use ($rows, &$ancestors, &$treeRoot) {
                foreach ($rows as $row) {
                    if ($row['root'] !== $treeRoot) {
                        $treeRoot = $row['root'];
                        $ancestors = [];
                    }

                    $this->_placeNode($row, $ancestors);
                }
            });

            $cursor = $rows === [] ? null : $rows[array_key_last($rows)];
        } while (count($rows) === self::BATCH_SIZE);
    }

    private function _placeNode(array $row, array &$ancestors): void
    {
        $id = (int)$row['id'];
        $conversationId = $row['conversationId'] !== null ? (int)$row['conversationId'] : null;

        // Containment rather than level alone: v3 leaves gaps when an ancestor was hard-deleted.
        while ($ancestors !== [] && $ancestors[array_key_last($ancestors)]['rgt'] < (int)$row['lft']) {
            array_pop($ancestors);
        }

        $parent = $ancestors === [] ? null : $ancestors[array_key_last($ancestors)];

        if ($parent === null && (int)$row['level'] > 1) {
            $this->log->add('hierarchy.parentMissing', V3UpgradeLog::WARNING, $id);
        }

        if ($parent !== null && $parent['conversationId'] !== $conversationId) {
            // A reply can't outlive its Conversation, so it starts a new thread in its own.
            $this->log->add('hierarchy.crossOwnerParent', V3UpgradeLog::WARNING, $id);
            $parent = null;
        }

        $assignment = [
            'parentId' => $parent['id'] ?? null,
            'rootId' => $parent['rootId'] ?? $id,
            'depth' => $parent !== null ? $parent['depth'] + 1 : 0,
        ];

        $this->db->createCommand()->update('{{%comments_comments}}', $assignment, ['id' => $id], [], false)->execute();

        $ancestors[] = [
            'id' => $id,
            'rgt' => (int)$row['rgt'],
            'conversationId' => $conversationId,
            'rootId' => $assignment['rootId'],
            'depth' => $assignment['depth'],
        ];
    }

    private function _migrateInteractions(): void
    {
        $query = (new Query())
            ->select(['v.*', 'userIdentityId' => 'i.id'])
            ->from(['v' => V3Schema::VOTES])
            ->leftJoin(['i' => '{{%comments_identities}}'], '[[i.userId]] = [[v.userId]]')
            ->where(['not', ['v.commentId' => null]]);

        $this->_eachBatch($query, 'v.id', function(array $vote) {
            $type = match (true) {
                (bool)$vote['downvote'] => InteractionType::Downvote,
                (bool)$vote['upvote'] => InteractionType::Upvote,
                // v3 keeps the row when someone takes a vote back, so this isn't lost data.
                default => null,
            };

            if ($type === null) {
                $this->log->add('vote.withdrawn', V3UpgradeLog::INFO, (int)$vote['id']);

                return;
            }

            if ($vote['upvote'] && $vote['downvote']) {
                $this->log->add('vote.contradictory', V3UpgradeLog::WARNING, (int)$vote['id']);
            }

            $identityId = $vote['userIdentityId'] ?? $this->_legacyGuestIdentity(self::MAP_VOTE, $vote);

            $existing = (new Query())
                ->select(['id', 'dateUpdated'])
                ->from('{{%comments_interactions}}')
                ->where(['commentId' => $vote['commentId'], 'identityId' => $identityId])
                ->one($this->db);

            // Rows arrive in ID order, so a tie on date resolves to the highest ID.
            if ($existing) {
                if ($vote['dateUpdated'] >= $existing['dateUpdated']) {
                    $this->db->createCommand()->update('{{%comments_interactions}}', [
                        'type' => $type->value,
                        'dateUpdated' => $vote['dateUpdated'],
                    ], ['id' => $existing['id']], [], false)->execute();
                }

                return;
            }

            $this->db->createCommand()->insert('{{%comments_interactions}}', [
                'commentId' => $vote['commentId'],
                'identityId' => $identityId,
                'type' => $type->value,
                'dateCreated' => $vote['dateCreated'],
                'dateUpdated' => $vote['dateUpdated'],
                'uid' => $vote['uid'] ?: StringHelper::UUID(),
            ])->execute();
        });
    }

    private function _migrateReports(): void
    {
        $query = (new Query())
            ->select(['f.*', 'userIdentityId' => 'i.id'])
            ->from(['f' => V3Schema::FLAGS])
            ->leftJoin(['i' => '{{%comments_identities}}'], '[[i.userId]] = [[f.userId]]')
            ->where(['not', ['f.commentId' => null]]);

        $this->_eachBatch($query, 'f.id', function(array $flag) {
            $identityId = $flag['userIdentityId'] ?? $this->_legacyGuestIdentity(self::MAP_FLAG, $flag);

            $exists = (new Query())
                ->from('{{%comments_reports}}')
                ->where(['commentId' => $flag['commentId'], 'identityId' => $identityId])
                ->exists($this->db);

            if ($exists) {
                return;
            }

            $this->db->createCommand()->insert('{{%comments_reports}}', [
                'commentId' => $flag['commentId'],
                'identityId' => $identityId,
                'status' => ReportStatus::Open->value,
                'reason' => Reports::REASON_LEGACY,
                'dateCreated' => $flag['dateCreated'],
                'dateUpdated' => $flag['dateUpdated'],
                'uid' => $flag['uid'] ?: StringHelper::UUID(),
            ])->execute();
        });
    }

    private function _migrateSubscriptions(): void
    {
        $query = (new Query())
            ->select([
                's.id',
                's.commentId',
                's.subscribed',
                's.dateCreated',
                's.dateUpdated',
                's.uid',
                'conversationId' => 'cv.id',
                'identityId' => 'i.id',
            ])
            ->from(['s' => V3Schema::SUBSCRIBE])
            ->leftJoin(['cv' => '{{%comments_conversations}}'], '[[cv.ownerId]] = [[s.ownerId]] AND [[cv.siteId]] = [[s.ownerSiteId]]')
            ->leftJoin(['i' => '{{%comments_identities}}'], '[[i.userId]] = [[s.userId]]');

        $this->_eachBatch($query, 's.id', function(array $row) {
            // A deleted user can't receive mail, so there's nothing to carry forward.
            if ($row['identityId'] === null || $row['conversationId'] === null) {
                $this->log->add('subscription.undeliverable', V3UpgradeLog::INFO, (int)$row['id']);

                return;
            }

            $commentId = $row['commentId'] !== null ? (int)$row['commentId'] : null;

            $key = [
                'conversationId' => $row['conversationId'],
                'identityId' => $row['identityId'],
                'commentId' => $commentId,
                'type' => ($commentId === null ? SubscriptionType::Conversation : SubscriptionType::Replies)->value,
            ];

            $existing = (new Query())
                ->select(['id', 'dateUpdated'])
                ->from('{{%comments_subscriptions}}')
                ->where($key)
                ->one($this->db);

            if ($existing) {
                if ($row['dateUpdated'] >= $existing['dateUpdated']) {
                    $this->db->createCommand()->update('{{%comments_subscriptions}}', [
                        'subscribed' => (bool)$row['subscribed'],
                        'dateUpdated' => $row['dateUpdated'],
                    ], ['id' => $existing['id']], [], false)->execute();
                }

                return;
            }

            // Preserve subscribed=false as durable opt-out — never drop negative rows.
            $this->db->createCommand()->insert('{{%comments_subscriptions}}', $key + [
                'subscribed' => (bool)$row['subscribed'],
                'dateCreated' => $row['dateCreated'],
                'dateUpdated' => $row['dateUpdated'],
                'uid' => $row['uid'] ?: StringHelper::UUID(),
            ])->execute();
        });
    }

    private function _rebuildConversationAggregates(): void
    {
        $conversations = Comments::$plugin->getConversations();
        $query = (new Query())->select(['id'])->from('{{%comments_conversations}}');

        $this->_eachBatch($query, 'id', static function(array $row) use ($conversations) {
            $conversations->rebuildAggregates((int)$row['id']);
        });

        $conversations->clearCaches();
    }

    /**
     * Walk a query by ascending ID, committing each batch.
     */
    private function _eachBatch(Query $query, string $idColumn, callable $handler): void
    {
        $cursor = 0;

        do {
            $rows = (clone $query)
                ->andWhere(['>', $idColumn, $cursor])
                ->orderBy([$idColumn => SORT_ASC])
                ->limit(self::BATCH_SIZE)
                ->all($this->db);

            $this->db->transaction(static function() use ($rows, $handler) {
                foreach ($rows as $row) {
                    $handler($row);
                }
            });

            $cursor = $rows === [] ? $cursor : (int)$rows[array_key_last($rows)]['id'];
        } while (count($rows) === self::BATCH_SIZE);
    }

    private function _createGuestIdentity(?string $name, ?string $email): int
    {
        $this->db->createCommand()->insert('{{%comments_identities}}', [
            'type' => IdentityType::Guest->value,
            'name' => $name !== '' ? $name : null,
            'email' => $email !== '' ? $email : null,
        ])->execute();

        return (int)$this->db->getLastInsertID('{{%comments_identities}}');
    }

    /**
     * Guest votes and flags group by their real v3 session, never by IP. A row with neither a user nor
     * a session (its voter's account was since deleted) keeps its own Identity so totals still match.
     */
    private function _legacyGuestIdentity(string $rowKind, array $row): int
    {
        $kind = $row['sessionId'] ? self::MAP_SESSION : $rowKind;
        $key = (string)($row['sessionId'] ?: $row['id']);

        $identityId = (new Query())
            ->select(['identityId'])
            ->from(V3Schema::MAP)
            ->where(['kind' => $kind, 'sourceKey' => $key])
            ->scalar($this->db);

        if ($identityId) {
            return (int)$identityId;
        }

        $identityId = $this->_createGuestIdentity(null, null);

        $this->db->createCommand()->insert(V3Schema::MAP, [
            'kind' => $kind,
            'sourceKey' => $key,
            'identityId' => $identityId,
        ])->execute();

        return $identityId;
    }

    private function _mapStatus(?string $status): ModerationState
    {
        return match ($status) {
            'approved' => ModerationState::Approved,
            'spam' => ModerationState::Spam,
            'trashed' => ModerationState::Removed,
            // Holding is the safe reading of anything unrecognised; preflight has reported it.
            default => ModerationState::Pending,
        };
    }
}
