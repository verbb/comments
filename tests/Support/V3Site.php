<?php
namespace Tests\Support;

use Craft;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\Structure;
use DateTime;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\migrations\Install;
use verbb\comments\migrations\support\V3Schema;

/**
 * Turns the test database into a Comments 3 site, so an upgrade test goes through Craft's migrator
 * against the schema a real site has, then puts everything back.
 */
final class V3Site
{
    private const ALL_TABLES = [
        '{{%comments_v3_log}}',
        '{{%comments_v3_map}}',
        '{{%comments_v3_structure}}',
        '{{%comments_v3_votes}}',
        '{{%comments_v3_flags}}',
        '{{%comments_v3_subscribe}}',
        '{{%comments_assets}}',
        '{{%comments_mentions}}',
        '{{%comments_restrictions}}',
        '{{%comments_moderatornotes}}',
        '{{%comments_moderationactions}}',
        '{{%comments_subscriptions}}',
        '{{%comments_reports}}',
        '{{%comments_interactions}}',
        '{{%comments_flags}}',
        '{{%comments_votes}}',
        '{{%comments_subscribe}}',
        '{{%comments_comments}}',
        '{{%comments_identities}}',
        '{{%comments_conversations}}',
    ];

    private static ?int $structureId = null;

    public static function downgrade(): void
    {
        self::dropAllTables();
        self::quietly(fn() => (new V3Install())->safeUp());

        $structure = new Structure();
        Craft::$app->getStructures()->saveStructure($structure);
        self::$structureId = (int)$structure->id;

        $db = Craft::$app->getDb();
        $db->createCommand()
            ->delete(Table::MIGRATIONS, ['and', ['track' => 'plugin:comments'], ['like', 'name', 'm400000_%', false]])
            ->execute();

        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->set('plugins.comments.schemaVersion', '1.2.2');
        $projectConfig->set('plugins.comments.settings.structureUid', Db::uidById(Table::STRUCTURES, self::$structureId));

        // Migrations read the schema version from the YAML, as they would on a real site.
        self::writeProjectConfig();

        $db->getSchema()->refresh();
    }

    public static function restore(): void
    {
        $db = Craft::$app->getDb();
        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->readOnly = false;

        $db->createCommand('DROP TRIGGER IF EXISTS comments_test_fault')->execute();

        if ($db->tableExists('{{%comments_comments}}', true)) {
            foreach (V3Schema::structureIdsHoldingComments($db) as $structureId) {
                $db->createCommand()->delete(Table::STRUCTURES, ['id' => $structureId])->execute();
            }
        }

        if (self::$structureId !== null) {
            $db->createCommand()->delete(Table::STRUCTURES, ['id' => self::$structureId])->execute();
            self::$structureId = null;
        }

        $db->createCommand()->delete(Table::ELEMENTS, ['type' => Comment::class])->execute();

        self::dropAllTables();
        self::quietly(fn() => (new Install())->safeUp());

        $plugin = Comments::$plugin;
        $migrator = $plugin->getMigrator();

        foreach ($migrator->getNewMigrations() as $name) {
            $migrator->addMigrationHistory($name);
        }

        $projectConfig->remove('plugins.comments.settings.structureUid');
        Craft::$app->getPlugins()->updatePluginVersionInfo($plugin);
        self::writeProjectConfig();

        $db->getSchema()->refresh();
        $plugin->getConversations()->clearCaches();
        $plugin->getIdentities()->clearCaches();
    }

    /**
     * What `craft up` does: apply every pending migration in order, then record the new schema version.
     */
    public static function upgrade(int $limit = 0): void
    {
        $plugin = Comments::$plugin;

        self::quietly(fn() => $plugin->getMigrator()->up($limit));

        if ($limit === 0) {
            Craft::$app->getPlugins()->updatePluginVersionInfo($plugin);
        }

        Craft::$app->getDb()->getSchema()->refresh();
        $plugin->getConversations()->clearCaches();
        $plugin->getIdentities()->clearCaches();
    }

    public static function writeProjectConfig(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->saveModifiedConfigData();
        $projectConfig->writeYamlFiles(true);

        // The YAML is only read once per request, and this whole test run is one request.
        $projectConfig->reset();
    }

    public static function structureId(): int
    {
        return self::$structureId;
    }

    /**
     * Insert a comment the way v3 stored it. Raw, because the v4 element can't save to this schema.
     */
    public static function comment(array $attributes = [], ?string $dateDeleted = null): int
    {
        $db = Craft::$app->getDb();
        $now = Db::prepareDateForDb(new DateTime());

        $db->createCommand()->insert(Table::ELEMENTS, [
            'type' => Comment::class,
            'enabled' => true,
            'archived' => false,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'dateDeleted' => $dateDeleted,
        ])->execute();

        $id = (int)$db->getLastInsertID(Table::ELEMENTS);
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $db->createCommand()->insert(Table::ELEMENTS_SITES, [
            'elementId' => $id,
            'siteId' => $siteId,
            'enabled' => true,
        ])->execute();

        $db->createCommand()->insert('{{%comments_comments}}', array_merge([
            'id' => $id,
            'ownerSiteId' => $siteId,
            'status' => 'approved',
            'comment' => 'Comment ' . $id,
            'commentDate' => '2024-01-15 10:00:00',
            'dateCreated' => '2024-01-15 10:00:00',
            'dateUpdated' => '2024-01-16 11:00:00',
        ], $attributes))->execute();

        return $id;
    }

    /**
     * Write a nested set for a tree given as `[id => [childId => [...]]]`.
     */
    public static function tree(array $tree, ?int $structureId = null, int $rootElementLft = 1): void
    {
        $db = Craft::$app->getDb();
        $structureId ??= self::$structureId;

        $db->createCommand()->insert(Table::STRUCTUREELEMENTS, [
            'structureId' => $structureId,
            'lft' => $rootElementLft,
            'rgt' => $rootElementLft + 1,
            'level' => 0,
        ])->execute();

        $rootId = (int)$db->getLastInsertID(Table::STRUCTUREELEMENTS);
        $cursor = $rootElementLft + 1;
        self::writeNodes($tree, $structureId, $rootId, 1, $cursor);

        $db->createCommand()->update(Table::STRUCTUREELEMENTS, ['root' => $rootId, 'rgt' => $cursor], ['id' => $rootId])->execute();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function row(string $table, array $attributes): void
    {
        Craft::$app->getDb()->createCommand()->insert($table, array_merge([
            'dateCreated' => '2024-02-01 09:00:00',
            'dateUpdated' => '2024-02-01 09:00:00',
            'uid' => StringHelper::UUID(),
        ], $attributes))->execute();
    }

    private static function writeNodes(array $nodes, int $structureId, int $rootId, int $level, int &$cursor): void
    {
        $db = Craft::$app->getDb();

        foreach ($nodes as $elementId => $children) {
            $lft = $cursor++;
            self::writeNodes($children, $structureId, $rootId, $level + 1, $cursor);
            $rgt = $cursor++;

            $db->createCommand()->insert(Table::STRUCTUREELEMENTS, [
                'structureId' => $structureId,
                'elementId' => $elementId,
                'root' => $rootId,
                'lft' => $lft,
                'rgt' => $rgt,
                'level' => $level,
            ])->execute();
        }
    }

    private static function dropAllTables(): void
    {
        $db = Craft::$app->getDb();

        foreach (self::ALL_TABLES as $table) {
            if ($db->tableExists($table, true)) {
                Db::dropAllForeignKeysToTable($table, $db);
                $db->createCommand()->dropTable($table)->execute();
            }
        }

        $db->getSchema()->refresh();
    }

    private static function quietly(callable $callback): void
    {
        ob_start();

        try {
            $callback();
        } finally {
            ob_end_clean();
        }
    }
}
