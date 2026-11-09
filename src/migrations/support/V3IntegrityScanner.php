<?php
namespace verbb\comments\migrations\support;

use craft\db\Connection;
use craft\db\Query;
use craft\db\Table;

/**
 * Read-only preflight for a Comments 3 database.
 *
 * Only a database the upgrade can't read is an error. Damaged data is a warning, because it can't be
 * repaired by retrying and refusing would leave the site unable to upgrade at all: orphans survive
 * without a Conversation, unplaceable replies become roots, and the archive keeps the original.
 */
final class V3IntegrityScanner
{
    // Static Methods
    // =========================================================================

    public static function scan(Connection $db): V3IntegrityReport
    {
        $issues = self::_scanSchema($db);

        if ($issues !== []) {
            return new V3IntegrityReport(summary: [], issues: $issues);
        }

        self::_scanStatuses($db, $issues);
        self::_scanOwners($db, $issues);
        self::_scanHierarchy($db, $issues);

        return new V3IntegrityReport(
            summary: [
                'comments' => (int)(new Query())->from('{{%comments_comments}}')->count('*', $db),
                'votes' => (int)(new Query())->from(V3Schema::VOTES)->count('*', $db),
                'flags' => (int)(new Query())->from(V3Schema::FLAGS)->count('*', $db),
                'subscriptions' => (int)(new Query())->from(V3Schema::SUBSCRIBE)->count('*', $db),
            ],
            issues: $issues,
        );
    }

    private static function _scanSchema(Connection $db): array
    {
        $issues = [];

        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (!$db->tableExists($table)) {
                self::_add($issues, 'schema.tableMissing', 'error', 1);

                continue;
            }

            foreach ($columns as $column) {
                if (!$db->columnExists($table, $column)) {
                    self::_add($issues, 'schema.columnMissing', 'error', 1);
                }
            }
        }

        return $issues;
    }

    private static function _scanStatuses(Connection $db, array &$issues): void
    {
        $query = (new Query())
            ->from('{{%comments_comments}}')
            ->where(['or', ['status' => null], ['not', ['status' => ['approved', 'pending', 'spam', 'trashed']]]]);

        self::_addFromQuery($db, $issues, 'status.unknown', $query);
    }

    private static function _scanOwners(Connection $db, array &$issues): void
    {
        $comments = (new Query())->from(['c' => '{{%comments_comments}}']);

        self::_addFromQuery($db, $issues, 'owner.missing', (clone $comments)->where(['c.ownerId' => null]), 'c.id');

        self::_addFromQuery($db, $issues, 'ownerSite.missing', (clone $comments)
            ->where(['c.ownerSiteId' => null])
            ->andWhere(['not', ['c.ownerId' => null]]), 'c.id');

        self::_addFromQuery($db, $issues, 'ownerSite.unsupported', (clone $comments)
            ->leftJoin(['es' => Table::ELEMENTS_SITES], '[[es.elementId]] = [[c.ownerId]] AND [[es.siteId]] = [[c.ownerSiteId]]')
            ->where(['es.id' => null])
            ->andWhere(['not', ['c.ownerId' => null]])
            ->andWhere(['not', ['c.ownerSiteId' => null]]), 'c.id');
    }

    private static function _scanHierarchy(Connection $db, array &$issues): void
    {
        $comments = (int)(new Query())->from('{{%comments_comments}}')->count('*', $db);

        if ($comments === 0) {
            return;
        }

        $structureIds = V3Schema::structureIdsHoldingComments($db);
        $configuredId = V3Schema::configuredStructureId();

        if ($structureIds === []) {
            // Without a single node nothing says which comment replied to which.
            self::_add($issues, 'hierarchy.structureMissing', 'warning', $comments);

            return;
        }

        if ($configuredId === null) {
            self::_add($issues, 'hierarchy.structureUnresolved', 'warning', 1);
        }

        if (count($structureIds) > 1) {
            self::_add($issues, 'hierarchy.multipleStructures', 'warning', count($structureIds));
        }

        // Craft takes a trashed element out of its Structure, moving its children up a level, so a
        // trashed comment without a node is normal and already has nothing beneath it.
        self::_addFromQuery($db, $issues, 'hierarchy.nodeMissing', (new Query())
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[c.id]]')
            ->leftJoin(['se' => Table::STRUCTUREELEMENTS], '[[se.elementId]] = [[c.id]]')
            ->where(['se.id' => null, 'e.dateDeleted' => null]), 'c.id');

        $nodes = (new Query())
            ->from(['se' => Table::STRUCTUREELEMENTS])
            ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[se.elementId]]');

        self::_addFromQuery($db, $issues, 'hierarchy.invalidBounds', (clone $nodes)
            ->where('[[se.lft]] >= [[se.rgt]]')
            ->orWhere(['<', 'se.level', 1]), 'c.id');

        $duplicates = (int)(new Query())
            ->from(['d' => (new Query())
                ->select(['se.structureId', 'se.root', 'se.lft'])
                ->from(['se' => Table::STRUCTUREELEMENTS])
                ->where(['se.structureId' => $structureIds])
                ->groupBy(['se.structureId', 'se.root', 'se.lft'])
                ->having('COUNT(*) > 1')])
            ->count('*', $db);

        if ($duplicates > 0) {
            self::_add($issues, 'hierarchy.duplicateBoundary', 'warning', $duplicates);
        }
    }

    private static function _addFromQuery(Connection $db, array &$issues, string $code, Query $query, string $idColumn = 'id'): void
    {
        $count = (int)(clone $query)->count('*', $db);

        if ($count === 0) {
            return;
        }

        $samples = (clone $query)
            ->select([$idColumn])
            ->orderBy([$idColumn => SORT_ASC])
            ->limit(self::SAMPLE_LIMIT)
            ->column($db);

        self::_add($issues, $code, 'warning', $count, array_map('intval', $samples));
    }

    private static function _add(array &$issues, string $code, string $severity, int $count, array $samples = []): void
    {
        foreach ($issues as &$issue) {
            if ($issue['code'] === $code) {
                $issue['count'] += $count;

                return;
            }
        }

        $issues[] = [
            'code' => $code,
            'severity' => $severity,
            'count' => $count,
            'samples' => $samples,
        ];
    }


    // Constants
    // =========================================================================

    private const SAMPLE_LIMIT = 25;

    private const REQUIRED_COLUMNS = [
        '{{%comments_comments}}' => ['ownerId', 'ownerSiteId', 'userId', 'status', 'comment', 'name', 'email'],
        V3Schema::VOTES => ['commentId', 'userId', 'sessionId', 'upvote', 'downvote'],
        V3Schema::FLAGS => ['commentId', 'userId', 'sessionId'],
        V3Schema::SUBSCRIBE => ['ownerId', 'ownerSiteId', 'userId', 'commentId', 'subscribed'],
    ];
}
