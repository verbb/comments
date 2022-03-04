<?php
namespace verbb\comments\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\Db;

class m200106_000000_fix_project_config extends Migration
{
    public function safeUp(): bool
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.comments.schemaVersion', true);
        
        if (version_compare($schemaVersion, '1.1.2', '>=')) {
            return true;
        }

        // Swap from ID to UID
        $structureId = $projectConfig->get('plugins.comments.settings.structureId');

        if ($structureId) {
            $structureUid = Db::uidById(Table::STRUCTURES, $structureId);

            if ($structureUid) {
                $projectConfig->set('plugins.comments.settings.structureUid', $structureUid);
                $projectConfig->remove('plugins.comments.settings.structureId');
            }
        }

        // Do the same for all permissions
        $allPermissions = $projectConfig->get('plugins.comments.settings.permissions');

        $tables = [
            Asset::class => Table::VOLUMES,
            Category::class => Table::CATEGORYGROUPS,
            Entry::class => Table::SECTIONS,
            User::class => Table::USERGROUPS,
        ];

        if ($allPermissions) {
            foreach ($allPermissions as $elementType => $permissions) {
                if (!is_array($permissions)) {
                    continue;
                }

                $table = $tables[$elementType] ?? '';

                if (!$table) {
                    continue;
                }

                foreach ($permissions as $index => $permissionId) {
                    if (is_string($permissionId)) {
                        continue;
                    }

                    $uid = Db::uidById($table, $permissionId);

                    $allPermissions[$elementType][$index] = $uid;
                }
            }

            $projectConfig->set('plugins.comments.settings.permissions', $allPermissions);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200106_000000_fix_project_config cannot be reverted.\n";

        return false;
    }
}
