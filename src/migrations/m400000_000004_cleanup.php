<?php
namespace verbb\comments\migrations;

use verbb\comments\migrations\support\RunsOutsideTransaction;
use verbb\comments\migrations\support\V3Reconciler;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\migrations\support\V3SettingsReview;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\services\Comments as CommentsService;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;

use yii\base\Exception;

/**
 * Retire the v3 storage, but only after proving the migrated data, and archive rather than drop it.
 *
 * Each step checks whether it's already been done, because on MySQL the schema changes commit as they
 * go and a run that stopped part way has to be able to pick up from where it was.
 */
class m400000_000004_cleanup extends Migration
{
    // Traits
    // =========================================================================

    use RunsOutsideTransaction;


    // Public Methods
    // =========================================================================

    public function safeDown(): bool
    {
        echo "m400000_000004_cleanup cannot be reverted.\n";

        return false;
    }


    // Protected Methods
    // =========================================================================

    protected function runUpgrade(): void
    {
        if (V3Schema::resolve(V3Schema::VOTES, $this->db) === null) {
            return;
        }

        $this->_reconcile();
        $this->_finaliseStatus();
        $this->_archiveSourceTables();
        $this->_archiveStructures();

        $this->dropTableIfExists(V3Schema::MAP);
        $this->_recordRetiredSettings();
        $this->_updateProjectConfig();
        $this->_printReport();
    }


    // Private Methods
    // =========================================================================

    private function _reconcile(): void
    {
        $failures = (new V3Reconciler($this->db))->failures();
        $log = new V3UpgradeLog($this->db);

        foreach ($failures as $code => $count) {
            $log->add('reconcile.' . $code, V3UpgradeLog::ERROR, null, $count);
        }

        $log->flush();

        if ($failures !== []) {
            $summary = implode(', ', array_map(
                static fn(string $code, int $count): string => "{$code} ({$count})",
                array_keys($failures),
                $failures,
            ));

            throw new Exception("The Comments 3 upgrade couldn’t verify the migrated data, so nothing has been removed: {$summary}.");
        }
    }

    private function _finaliseStatus(): void
    {
        $this->update('{{%comments_comments}}', ['status' => 'removed'], ['status' => 'trashed'], [], false);
        $this->update('{{%comments_comments}}', ['status' => 'pending'], ['status' => null], [], false);
        $this->alterColumn('{{%comments_comments}}', 'status', $this->string(16)->notNull());
    }

    private function _archiveSourceTables(): void
    {
        foreach (V3Schema::ARCHIVE_TABLES as $table => $archive) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            // Without this, deleting a comment or user would quietly edit the archive.
            foreach ($this->db->getTableSchema($table, true)->foreignKeys as $name => $foreignKey) {
                $this->dropForeignKey((string)$name, $table);
            }

            // Nothing in the upgrade reads these, and an archive shouldn't keep visitors' addresses.
            if ($this->db->columnExists($table, 'lastIp')) {
                $this->dropColumn($table, 'lastIp');
            }

            $this->renameTable($table, $archive);
        }
    }

    private function _archiveStructures(): void
    {
        if (!$this->db->tableExists(V3Schema::ARCHIVE_STRUCTURE)) {
            $this->createTable(V3Schema::ARCHIVE_STRUCTURE, [
                'structureId' => $this->integer()->notNull(),
                'elementId' => $this->integer(),
                'root' => $this->integer(),
                'lft' => $this->integer(),
                'rgt' => $this->integer(),
                'level' => $this->smallInteger(),
            ]);
        }

        $structureIds = V3Schema::structureIdsHoldingComments($this->db);
        $configuredId = V3Schema::configuredStructureId();

        if ($configuredId !== null && !in_array($configuredId, $structureIds, true)) {
            $structureIds[] = $configuredId;
        }

        foreach ($structureIds as $structureId) {
            // A Structure holding anything else belongs to something else, so it isn't ours to remove.
            $foreign = (new Query())
                ->from(['se' => Table::STRUCTUREELEMENTS])
                ->leftJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[se.elementId]]')
                ->where(['se.structureId' => $structureId])
                ->andWhere(['not', ['se.elementId' => null]])
                ->andWhere(['c.id' => null])
                ->exists($this->db);

            if ($foreign) {
                continue;
            }

            $nodes = (new Query())
                ->select(['structureId', 'elementId', 'root', 'lft', 'rgt', 'level'])
                ->from(Table::STRUCTUREELEMENTS)
                ->where(['structureId' => $structureId]);

            $this->delete(V3Schema::ARCHIVE_STRUCTURE, ['structureId' => $structureId]);

            foreach ($nodes->batch(1000, $this->db) as $rows) {
                $this->db->createCommand()->batchInsert(
                    V3Schema::ARCHIVE_STRUCTURE,
                    ['structureId', 'elementId', 'root', 'lft', 'rgt', 'level'],
                    array_map('array_values', $rows),
                )->execute();
            }

            $this->delete(Table::STRUCTUREELEMENTS, ['structureId' => $structureId]);
            $this->delete(Table::STRUCTURES, ['id' => $structureId]);
        }
    }

    private function _updateProjectConfig(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Writing to read-only project config would throw. An environment with admin changes disabled
        // receives these through the YAML of the one that ran the upgrade.
        if ($projectConfig->readOnly) {
            return;
        }

        $projectConfig->remove('plugins.comments.settings.structureUid');

        // Comments 3 only had plain text. New installs default to rich authoring, so an upgraded site
        // records the mode it has always had rather than switching to Editor by itself.
        if ($projectConfig->get('plugins.comments.settings.authoringMode') === null) {
            $projectConfig->set('plugins.comments.settings.authoringMode', 'plain');
        }

        // Comments 3 emailed the moderator group about comments waiting for review. Comments 4 only names
        // staff in an event unless Email the Moderator Group is on, so a site that relied on that email
        // keeps it. Checked before the settings are next saved, which drops the Comments 3 names.
        if (
            $projectConfig->get('plugins.comments.settings.emailModeratorGroup') === null
            && V3SettingsReview::emailedModeratorGroup(V3SettingsReview::currentValues())
        ) {
            $projectConfig->set('plugins.comments.settings.emailModeratorGroup', true);
        }

        // "Comment Form" read as the public form now that this tab sits in the control panel's editor, which opens on Content.
        $layoutsKey = CommentsService::CONFIG_FIELDLAYOUT_KEY;

        foreach ($projectConfig->get($layoutsKey) ?? [] as $layoutUid => $layout) {
            foreach ($layout['tabs'] ?? [] as $index => $tab) {
                if (($tab['name'] ?? null) === 'Comment Form') {
                    $projectConfig->set("{$layoutsKey}.{$layoutUid}.tabs.{$index}.name", 'Content');
                }
            }
        }
    }

    private function _recordRetiredSettings(): void
    {
        $log = new V3UpgradeLog($this->db);
        V3SettingsReview::record($log);
        $log->flush();
    }

    private function _printReport(): void
    {
        $issues = array_filter(
            V3UpgradeLog::all($this->db),
            static fn(array $entry): bool => $entry['severity'] !== V3UpgradeLog::INFO,
        );

        if ($issues === []) {
            echo "    > The Comments 3 upgrade verified with no warnings.\n";

            return;
        }

        echo "    > The Comments 3 upgrade verified with warnings (recorded in " . Db::rawTableShortName(V3Schema::LOG) . "):\n";

        foreach ($issues as $issue) {
            echo "    >   {$issue['code']}: {$issue['count']}\n";
        }
    }
}
