<?php
namespace verbb\comments\migrations;

use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\migrations\support\SavesProjectConfig;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\services\ProjectConfig;

use Throwable;

/**
 * Grant the new granular CP permissions to whoever already held the v3 comment permissions.
 *
 * `comments-view` now gates the CP section and `comments-moderate` gates the moderation
 * workspace, where previously `comments-edit` implied both. Without this backfill, existing
 * moderators would silently lose access to the section on upgrade.
 */
class m400000_000003_permissions extends Migration
{
    // Traits
    // =========================================================================

    use SavesProjectConfig;


    // Constants
    // =========================================================================

    /**
     * Any of these meant "this user works with comments in the CP" before 4.0.
     */
    private const LEGACY_PERMISSIONS = ['comments-edit', 'comments-trash', 'comments-delete'];

    /**
     * The v3 section only needed plugin access, so these users could already read comments.
     */
    private const LEGACY_VIEW_PERMISSIONS = ['accessplugin-comments', ...self::LEGACY_PERMISSIONS];


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->_backfillUsers();
        $this->_backfillGroups();

        return true;
    }

    public function safeDown(): bool
    {
        echo "m400000_000003_permissions cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _backfillGroups(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.comments.schemaVersion', true);

        // Group permissions live in project config, so an environment that already ran this upgrade has
        // delivered them through its YAML.
        if ($schemaVersion !== null && version_compare($schemaVersion, '4.0.0', '>=')) {
            return;
        }

        if ($projectConfig->readOnly) {
            echo "    > Skipping user group permissions because admin changes are disabled here. Run this upgrade where they’re enabled and deploy its project config.\n";

            return;
        }

        $userPermissions = Craft::$app->getUserPermissions();

        foreach (Craft::$app->getUserGroups()->getAllGroups() as $group) {
            $existing = $userPermissions->getPermissionsByGroupId($group->id);
            $granted = $this->_addGrants($existing);

            if ($granted === $existing) {
                continue;
            }

            sort($granted);

            try {
                // Straight to project config: saving through the service would also strip the
                // permissions of any plugin that happens to be disabled right now.
                $projectConfig->set(ProjectConfig::PATH_USER_GROUPS . '.' . $group->uid . '.permissions', $granted);
            } catch (Throwable $e) {
                // Not worth blocking the upgrade: a retry would hit the same error, and it's fixed in the group's settings.
                Craft::warning("Couldn’t backfill comment permissions for user group “{$group->handle}”: {$e->getMessage()}", __METHOD__);

                $log = new V3UpgradeLog($this->db);
                $log->add('permissions.groupFailed', V3UpgradeLog::WARNING, (int)$group->id);
                $log->flush();
            }
        }
    }

    /**
     * Per-user grants only exist in the database, so they have to be written in every environment,
     * whatever its project config allows. Group grants aren't copied onto the user.
     */
    private function _backfillUsers(): void
    {
        $this->_grantToUsersHolding(self::LEGACY_VIEW_PERMISSIONS, 'comments-view');
        $this->_grantToUsersHolding(['comments-edit'], 'comments-moderate');
    }

    private function _grantToUsersHolding(array $held, string $permission): void
    {
        $userIds = (new Query())
            ->select(['p_u.userId'])
            ->distinct()
            ->from(['p_u' => Table::USERPERMISSIONS_USERS])
            ->innerJoin(['p' => Table::USERPERMISSIONS], '[[p.id]] = [[p_u.permissionId]]')
            ->where(['p.name' => $held])
            ->column($this->db);

        if ($userIds === []) {
            return;
        }

        $permissionId = $this->_permissionId($permission);

        $alreadyGranted = (new Query())
            ->select(['userId'])
            ->from(Table::USERPERMISSIONS_USERS)
            ->where(['permissionId' => $permissionId, 'userId' => $userIds])
            ->column($this->db);

        $rows = array_map(
            static fn($userId): array => [$permissionId, (int)$userId],
            array_values(array_diff($userIds, $alreadyGranted)),
        );

        if ($rows !== []) {
            $this->batchInsert(Table::USERPERMISSIONS_USERS, ['permissionId', 'userId'], $rows);
        }
    }

    private function _permissionId(string $name): int
    {
        $id = (new Query())
            ->select(['id'])
            ->from(Table::USERPERMISSIONS)
            ->where(['name' => $name])
            ->scalar($this->db);

        if ($id) {
            return (int)$id;
        }

        $this->insert(Table::USERPERMISSIONS, ['name' => $name]);

        return (int)$this->db->getLastInsertID(Table::USERPERMISSIONS);
    }

    private function _addGrants(array $permissions): array
    {
        $lowered = array_map('strtolower', $permissions);
        $grants = [];

        if (array_intersect($lowered, self::LEGACY_VIEW_PERMISSIONS)) {
            $grants[] = 'comments-view';
        }

        // `comments-edit` was the de facto moderation permission, so those holders keep moderating.
        if (in_array('comments-edit', $lowered, true)) {
            $grants[] = 'comments-moderate';
        }

        $missing = array_diff($grants, $lowered);

        return $missing === [] ? $permissions : array_values(array_merge($permissions, $missing));
    }
}
