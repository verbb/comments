<?php
namespace verbb\comments\compatibility\elements;

use verbb\comments\elements\Comment;
use verbb\comments\services\Compatibility;

use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\Db;

/**
 * Comments 3's comment query parameters, in compatibility mode: the `trashed` status, and Craft's structure
 * parameters, which Comments 3 answered from its structure. Comments 4 keeps each reply's parent and
 * depth, so `level`, `hasDescendants`, `leaves`, `ancestorOf` and `descendantOf` are answered from those.
 * Without compatibility mode, Craft treats the structure parameters as it would for any element without
 * a structure, and returns nothing.
 */
trait LegacyCommentQuery
{
    // Constants
    // =========================================================================

    private const LEGACY_STRUCTURE_PARAMS = ['level', 'hasDescendants', 'leaves', 'ancestorOf', 'ancestorDist', 'descendantOf', 'descendantDist'];


    // Properties
    // =========================================================================

    // The structure parameters held back from Craft while a query is prepared, restored afterwards.
    private ?array $_legacyStructureParams = null;


    // Protected Methods
    // =========================================================================

    /**
     * Comments 3's `trashed` status, which is `removed` now. Null for any other status.
     */
    protected function legacyStatusCondition(string $status): ?array
    {
        if ($status !== 'trashed' || !Compatibility::enabled()) {
            return null;
        }

        Compatibility::deprecated('status.trashed', 'The `trashed` comment status has been renamed `removed`. Use `.status(\'removed\')`.');

        return ['comments_comments.status' => Comment::STATUS_REMOVED];
    }

    /**
     * Adds conditions for Comments 3's structure parameters, then clears them so Craft doesn't refuse the
     * query for having no structure. `restoreLegacyStructureParams()` puts them back.
     */
    protected function applyLegacyStructureParams(): void
    {
        $this->restoreLegacyStructureParams();

        if (!Compatibility::enabled()) {
            return;
        }

        $params = [];

        foreach (self::LEGACY_STRUCTURE_PARAMS as $param) {
            $params[$param] = $this->$param;
        }

        $used = array_filter(['level', 'hasDescendants', 'leaves', 'ancestorOf', 'descendantOf'], fn(string $param): bool => !in_array($params[$param], [null, false], true));

        if ($used === []) {
            return;
        }

        Compatibility::deprecated('query.structure', 'Comments are no longer kept in a structure. Use `depth` in place of `level`, and `parentId` or `rootId` for a comment’s replies and the comments above it.');

        if ($params['level'] !== null) {
            $this->subQuery->andWhere(Db::parseNumericParam('comments_comments.depth', self::_levelToDepth($params['level'])));
        }

        // Comments 3 read `leaves` as having no replies.
        $hasDescendants = $params['hasDescendants'] ?? ($params['leaves'] ? false : null);

        if ($hasDescendants !== null) {
            $replies = (new Query())
                ->from('{{%comments_comments}} legacy_replies')
                ->where('[[legacy_replies.parentId]] = [[elements.id]]');

            $this->subQuery->andWhere($hasDescendants ? ['exists', $replies] : ['not exists', $replies]);
        }

        if ($params['descendantOf']) {
            $this->subQuery->andWhere(['elements.id' => self::_descendantIds(self::_id($params['descendantOf']), $params['descendantDist']) ?: [0]]);
        }

        if ($params['ancestorOf']) {
            $this->subQuery->andWhere(['elements.id' => self::_ancestorIds(self::_id($params['ancestorOf']), $params['ancestorDist']) ?: [0]]);
        }

        $this->_legacyStructureParams = $params;

        foreach (self::LEGACY_STRUCTURE_PARAMS as $param) {
            // `leaves` is a plain bool on Craft's query, and every other one is nullable.
            $this->$param = $param === 'leaves' ? false : null;
        }
    }

    protected function restoreLegacyStructureParams(): void
    {
        if ($this->_legacyStructureParams === null) {
            return;
        }

        foreach ($this->_legacyStructureParams as $param => $value) {
            $this->$param = $value;
        }

        $this->_legacyStructureParams = null;
    }


    // Private Methods
    // =========================================================================

    /**
     * Comments 3's `level` 1 is a top-level comment, which is `depth` 0. Comparisons such as `>= 2` keep
     * their operator.
     */
    private static function _levelToDepth(mixed $level): mixed
    {
        if (is_array($level)) {
            return array_map(self::_levelToDepth(...), $level);
        }

        if (is_int($level) || (is_string($level) && ctype_digit($level))) {
            return (int)$level - 1;
        }

        if (is_string($level) && preg_match('/^\s*(not\s+|!=|<=|>=|<|>|=)?\s*(\d+)\s*$/i', $level, $match)) {
            return $match[1] . ((int)$match[2] - 1);
        }

        return $level;
    }

    private static function _id(mixed $value): int
    {
        return (int)($value instanceof ElementInterface ? $value->id : $value);
    }

    /**
     * Every reply beneath a comment, or down to `$dist` levels, whatever its status. The query's own
     * status parameter then decides which are returned, as it did in Comments 3.
     */
    private static function _descendantIds(int $id, ?int $dist): array
    {
        $ids = [];
        $parentIds = [$id];
        $depth = 0;

        while ($parentIds !== [] && ($dist === null || $depth < $dist)) {
            $parentIds = array_map('intval', (new Query())->select(['id'])->from('{{%comments_comments}}')->where(['parentId' => $parentIds])->column());
            array_push($ids, ...$parentIds);
            $depth++;
        }

        return $ids;
    }

    /**
     * Every comment above a comment, or the nearest `$dist` of them.
     */
    private static function _ancestorIds(int $id, ?int $dist): array
    {
        $ids = [];
        $parentId = (new Query())->select(['parentId'])->from('{{%comments_comments}}')->where(['id' => $id])->scalar();

        while ($parentId && ($dist === null || count($ids) < $dist) && !in_array((int)$parentId, $ids, true)) {
            $ids[] = (int)$parentId;
            $parentId = (new Query())->select(['parentId'])->from('{{%comments_comments}}')->where(['id' => $parentId])->scalar();
        }

        return $ids;
    }
}
