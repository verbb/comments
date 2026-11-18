<?php
namespace verbb\comments\migrations\support;

use verbb\comments\migrations\m400000_000009_form_fields;
use verbb\comments\migrations\m400000_000010_template_overrides;

use Craft;
use craft\db\Connection;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * Aggregated record of what the upgrade found, kept after it finishes for support diagnosis.
 *
 * One row per code with a bounded sample of IDs, so a site with thousands of orphaned Comments gets a
 * small report. It only ever holds identifiers, counts and the paths of the site's own template files,
 * never contact details or request data.
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
            // Comment and element IDs, or the template paths a template notice lists.
            'samples' => array_map(static fn(mixed $sample): int|string => is_int($sample) || ctype_digit((string)$sample) ? (int)$sample : (string)$sample, Json::decode($row['samples'] ?? '[]') ?? []),
        ], $rows);
    }

    /**
     * The table, for a migration with something to record on a site that has no Upgrade Report yet,
     * such as a Comments 4 development build that never came from Comments 3.
     */
    public static function createTable(Migration $migration): void
    {
        if ($migration->db->tableExists(V3Schema::LOG)) {
            return;
        }

        $migration->createTable(V3Schema::LOG, [
            'id' => $migration->primaryKey(),
            'code' => $migration->string(64)->notNull(),
            'severity' => $migration->string(16)->notNull(),
            'count' => $migration->integer()->notNull(),
            'samples' => $migration->text(),
            'dateCreated' => $migration->dateTime()->notNull(),
            'dateUpdated' => $migration->dateTime()->notNull(),
            'uid' => $migration->uid(),
        ]);

        $migration->createIndex(null, V3Schema::LOG, ['code'], true);
    }

    /**
     * Anything other than a passing note, in words for whoever runs the site.
     */
    public static function issues(Connection $db): array
    {
        $issues = [];

        foreach (self::all($db) as $entry) {
            if ($entry['severity'] === self::INFO) {
                continue;
            }

            $issue = ['code' => $entry['code'], 'label' => self::describe($entry['code']), 'count' => $entry['count']];

            // The overridden template files, each listed, with the guide that says where its changes go.
            if (in_array($entry['code'], [m400000_000010_template_overrides::NOTICE, m400000_000010_template_overrides::NOTICE_CONVENTIONAL], true)) {
                $issue['items'] = array_values(array_filter($entry['samples'], 'is_string'));
                $issue['url'] = m400000_000010_template_overrides::GUIDE_URL;
            }

            $issues[] = $issue;
        }

        return $issues;
    }

    public static function describe(string $code): string
    {
        if (str_starts_with($code, 'settings.retired.')) {
            return Craft::t('comments', 'The “{setting}” setting was in use and no longer exists.', ['setting' => substr($code, strlen('settings.retired.'))]);
        }

        return match ($code) {
            V3SettingsReview::LOAD_MODE_NOTICE => Craft::t('comments', 'Comments now load as a visitor scrolls near them, rather than with the page. Choose when they load with the Load Comments setting.'),
            V3SettingsReview::FORM_FIELDS_NOTICE => Craft::t('comments', 'Custom fields are no longer on the site’s comment form until you choose them. Choose each one visitors should fill in under The Comment Form, in Comments’ Comment Fields settings.'),
            V3SettingsReview::GIPHY_NOTICE => Craft::t('comments', 'GIF search now runs in each visitor’s browser, as GIPHY’s API terms require, so your GIPHY API key is included in the page for anyone who can comment. Check it’s a key for the web, and that GIPHY’s limit on it suits your site.'),
            V3SettingsReview::GRAVATAR_NOTICE => Craft::t('comments', 'Gravatar is now only used for signed-in users and guests who’ve confirmed their email, after a user’s own photo. Your site fetches each image and serves it itself, so avatars appear once they’ve been fetched in the queue.'),
            m400000_000010_template_overrides::NOTICE => Craft::t('comments', 'Your site overrides the thread’s templates. Comments 4 draws every thread with JavaScript and no longer uses them, so each change needs to move to its new place, as the upgrade guide explains.'),
            m400000_000010_template_overrides::NOTICE_CONVENTIONAL => Craft::t('comments', 'Your site has templates in templates/_comments, where Comments 3’s docs suggested overriding the thread. Comments 4 no longer uses thread templates, so check whether these were overrides, which need moving as the upgrade guide explains. Your own forms that post to Comments’ form actions keep working.'),
            m400000_000009_form_fields::PUBLIC_FIELDS_NOTICE => Craft::t('comments', 'Fields ticked under Public Fields are now asked for on the site’s form with their answers not shown, so what visitors enter is kept private. Choose where any should appear on comments under Answers on Comments, in Comments’ Comment Fields settings.'),
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
    private const ITEM_LIMIT = 200;
    private const SAMPLES_MAX_BYTES = 60000;


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

    /**
     * Names rather than IDs, such as template paths, all of them up to a larger limit, since a report
     * that lists files is only useful when it lists them.
     */
    public function addItems(string $code, string $severity, array $items): void
    {
        $items = array_values(array_unique(array_map('strval', $items)));

        if ($items === []) {
            return;
        }

        $samples = array_slice($items, 0, self::ITEM_LIMIT);

        // The samples column is TEXT, so long paths are cut from the end of the list rather than overflow it.
        while ($samples !== [] && strlen(Json::encode($samples)) > self::SAMPLES_MAX_BYTES) {
            array_pop($samples);
        }

        $this->entries[$code] = [
            'severity' => $severity,
            'count' => count($items),
            'samples' => $samples,
        ];
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
