<?php
namespace verbb\comments\migrations\support;

use Craft;
use craft\db\Connection;
use craft\db\Query;
use craft\helpers\Json;

/**
 * Aggregated record of what the upgrade found, kept after it finishes for support diagnosis.
 *
 * One row per code with a bounded sample of IDs, so a site with thousands of orphaned Comments gets a
 * small report. It only ever holds identifiers and counts, never contact details or request data.
 */
final class V3UpgradeLog
{
    // Static Methods
    // =========================================================================

    public static function all(Connection $db): array
    {
        if (!$db->tableExists(V3Schema::LOG)) {
            return [];
        }

        $rows = (new Query())
            ->select(['code', 'severity', 'count', 'samples'])
            ->from(V3Schema::LOG)
            ->orderBy(['code' => SORT_ASC])
            ->all($db);

        return array_map(static fn(array $row): array => [
            'code' => $row['code'],
            'severity' => $row['severity'],
            'count' => (int)$row['count'],
            'samples' => array_map('intval', Json::decode($row['samples'] ?? '[]') ?? []),
        ], $rows);
    }

    /**
     * Anything other than a passing note, in words for whoever runs the site.
     */
    public static function issues(Connection $db): array
    {
        $issues = [];

        foreach (self::all($db) as $entry) {
            if ($entry['severity'] !== self::INFO) {
                $issues[] = ['code' => $entry['code'], 'label' => self::describe($entry['code']), 'count' => $entry['count']];
            }
        }

        return $issues;
    }

    public static function describe(string $code): string
    {
        if (str_starts_with($code, 'settings.retired.')) {
            return Craft::t('comments', 'The “{setting}” setting was in use and no longer exists.', ['setting' => substr($code, strlen('settings.retired.'))]);
        }

        return match ($code) {
            'status.unknown' => Craft::t('comments', 'Comments had a status Comments 3 doesn’t define, and were kept as pending.'),
            'owner.missing' => Craft::t('comments', 'Comments belonged to an element that no longer exists.'),
            'ownerSite.missing' => Craft::t('comments', 'Comments had no site recorded.'),
            'ownerSite.unsupported' => Craft::t('comments', 'Comments belonged to a site their element isn’t on.'),
            'hierarchy.structureMissing' => Craft::t('comments', 'Nothing recorded which comments replied to which, so all of them became top-level comments.'),
            'hierarchy.structureUnresolved' => Craft::t('comments', 'The configured comment structure couldn’t be found, so threads were read from the structures holding comments.'),
            'hierarchy.multipleStructures' => Craft::t('comments', 'Comments were spread across more than one structure.'),
            'hierarchy.nodeMissing' => Craft::t('comments', 'Comments had no place in their thread, and became top-level comments.'),
            'hierarchy.invalidBounds', 'hierarchy.duplicateBoundary' => Craft::t('comments', 'Thread data was damaged, so some replies may have moved.'),
            'hierarchy.parentMissing' => Craft::t('comments', 'Replies had lost the comment they replied to, and became top-level comments.'),
            'hierarchy.crossOwnerParent' => Craft::t('comments', 'Replies were on a different element from their parent, and became top-level comments.'),
            'vote.contradictory' => Craft::t('comments', 'Votes were recorded as both up and down, and were kept as downvotes.'),
            'permissions.groupFailed' => Craft::t('comments', 'A user group couldn’t be given the new comment permissions. Check it in its settings.'),
            default => $code,
        };
    }

    public static function countFor(Connection $db, string $code): ?int
    {
        if (!$db->tableExists(V3Schema::LOG)) {
            return null;
        }

        $count = (new Query())->select(['count'])->from(V3Schema::LOG)->where(['code' => $code])->scalar($db);

        return $count === false || $count === null ? null : (int)$count;
    }


    // Constants
    // =========================================================================

    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    private const SAMPLE_LIMIT = 25;


    // Properties
    // =========================================================================

    private array $entries = [];


    // Public Methods
    // =========================================================================

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Stages recompute everything they report on a retry, so a retry starts from an empty log.
     */
    public function reset(): void
    {
        if ($this->db->tableExists(V3Schema::LOG)) {
            $this->db->createCommand()->delete(V3Schema::LOG)->execute();
        }

        $this->entries = [];
    }

    public function add(string $code, string $severity, ?int $entityId = null, int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        $this->entries[$code] ??= ['severity' => $severity, 'count' => 0, 'samples' => []];
        $this->entries[$code]['count'] += $count;

        if ($entityId !== null && count($this->entries[$code]['samples']) < self::SAMPLE_LIMIT) {
            $this->entries[$code]['samples'][] = $entityId;
        }
    }

    public function addIssue(array $issue): void
    {
        $this->add($issue['code'], $issue['severity'], null, $issue['count']);
        $this->entries[$issue['code']]['samples'] = array_slice(
            array_merge($this->entries[$issue['code']]['samples'], $issue['samples']),
            0,
            self::SAMPLE_LIMIT,
        );
    }

    public function flush(): void
    {
        if (!$this->db->tableExists(V3Schema::LOG)) {
            return;
        }

        foreach ($this->entries as $code => $entry) {
            $this->db->createCommand()->upsert(V3Schema::LOG, [
                'code' => $code,
                'severity' => $entry['severity'],
                'count' => $entry['count'],
                'samples' => Json::encode($entry['samples']),
            ], [
                'severity' => $entry['severity'],
                'count' => $entry['count'],
                'samples' => Json::encode($entry['samples']),
            ])->execute();
        }

        $this->entries = [];
    }
}
