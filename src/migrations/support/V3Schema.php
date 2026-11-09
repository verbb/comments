<?php
namespace verbb\comments\migrations\support;

use Craft;
use craft\db\Connection;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;

/**
 * Where the Comments 3 data lives during an upgrade, and where it goes afterwards.
 *
 * Source tables are renamed to their archive name rather than dropped, so anything reading them has
 * to work with either name for a cleanup that stopped part way through to be safe to run again.
 */
final class V3Schema
{
    // Static Methods
    // =========================================================================

    public static function hasSourceData(Connection $db): bool
    {
        foreach (array_keys(self::ARCHIVE_TABLES) as $table) {
            if ($db->tableExists($table)) {
                return true;
            }
        }

        return false;
    }

    public static function resolve(string $table, Connection $db): ?string
    {
        if ($db->tableExists($table)) {
            return $table;
        }

        $archive = self::ARCHIVE_TABLES[$table];

        return $db->tableExists($archive) ? $archive : null;
    }

    public static function configuredStructureId(): ?int
    {
        $structureUid = Craft::$app->getProjectConfig()->get('plugins.comments.settings.structureUid');
        $structureId = $structureUid ? Db::idByUid(Table::STRUCTURES, $structureUid) : null;

        return $structureId ? (int)$structureId : null;
    }

    /**
     * Comments 3 repairs a stale `structureUid` by moving on to a new Structure, so the configured
     * Structure can't be relied on to hold every Comment. The nodes themselves are the evidence.
     */
    public static function structureIdsHoldingComments(Connection $db): array
    {
        return array_map('intval', (new Query())
            ->select(['se.structureId'])
            ->distinct()
            ->from(['se' => Table::STRUCTUREELEMENTS])
            ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[se.elementId]]')
            ->column($db));
    }


    // Constants
    // =========================================================================

    public const VOTES = '{{%comments_votes}}';
    public const FLAGS = '{{%comments_flags}}';
    public const SUBSCRIBE = '{{%comments_subscribe}}';

    public const ARCHIVE_TABLES = [
        self::VOTES => '{{%comments_v3_votes}}',
        self::FLAGS => '{{%comments_v3_flags}}',
        self::SUBSCRIBE => '{{%comments_v3_subscribe}}',
    ];

    public const ARCHIVE_STRUCTURE = '{{%comments_v3_structure}}';

    /** Maps a guest session or anonymous source row to its Identity while the upgrade runs. */
    public const MAP = '{{%comments_v3_map}}';

    public const LOG = '{{%comments_v3_log}}';
}
