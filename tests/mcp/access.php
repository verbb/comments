<?php
// Focused doubles for token/permission logic, NOT a substitute for Craft integration tests.
namespace craft\elements {
    class User {
        public const STATUS_ACTIVE = 'active';
        public bool $locked = false;
        public string $status = 'active';
        public array $permissions = ['accessCp', 'accessPlugin-comments', 'comments-mcpRead', 'editSite:one'];
        public function getStatus(): string { return $this->status; }
        public function can(string $permission): bool { return in_array($permission, $this->permissions, true); }
    }
}
namespace craft\helpers {
    class App {
        public static function parseEnv(string $value): string|false { return str_starts_with($value, '$') ? getenv(substr($value, 1)) : $value; }
    }
}
namespace verbb\comments\models {
    class Settings { public array $mcpTokens = []; }
}
namespace {
    class Craft { public static object $app; }
    require __DIR__ . '/../../src/mcp/ToolException.php';
    require __DIR__ . '/../../src/mcp/Access.php';
    use verbb\comments\mcp\Access;
    use verbb\comments\mcp\ToolException;
    use verbb\comments\models\Settings;
    $user = new \craft\elements\User();
    Craft::$app = new class($user) {
        public function __construct(public object $user) {}
        public function getUsers(): object { return $this; }
        public function getUserById(int $id): ?object { return $id === 7 ? $this->user : null; }
        public function getSites(): object { return $this; }
        public function getAllSites(): array { return [(object)['id' => 1, 'uid' => 'one'], (object)['id' => 2, 'uid' => 'two']]; }
        public function getIsMultiSite(): bool { return true; }
    };
    $checks = 0;
    function check(bool $result, string $message): void {
        global $checks;
        $checks++;
        if (!$result) { throw new \RuntimeException($message); }
    }
    $secret = str_repeat('a', 48);
    putenv('COMMENTS_TEST_TOKEN=' . $secret);
    $settings = new Settings();
    $valid = ['token' => '$COMMENTS_TEST_TOKEN', 'userId' => 7, 'siteIds' => [1, 2], 'scopes' => ['read', 'moderate', 'manage']];
    $settings->mcpTokens = [$valid];
    $auth = 'Bearer ' . $secret;
    $access = Access::authenticate($settings, $auth);
    check($access !== null && $access->siteIds === [1], 'Site permissions narrow configured sites');
    check($access->allows('read') && !$access->allows('moderate') && !$access->allows('manage'), 'Scopes cannot grant Craft permissions');
    $user->permissions[] = 'comments-mcpModerate';
    check(!$access->allows('moderate'), 'Moderation also requires comments-edit');
    $user->permissions[] = 'comments-edit';
    check($access->allows('moderate'), 'Moderation with both permissions');
    $readOnly = new Access($user, ['read'], [1]);
    check(!$readOnly->allows('moderate'), 'Craft permissions cannot grant missing token scope');
    foreach (['', $secret, 'Basic ' . $secret, 'Bearer wrong', 'Bearer ' . $secret . ', extra'] as $header) {
        check(Access::authenticate($settings, $header) === null, 'Invalid authentication header');
    }
    foreach (['pending', 'suspended', 'inactive', 'disabled', 'archived'] as $status) {
        $user->status = $status;
        check(Access::authenticate($settings, $auth) === null, 'Inactive user denied');
    }
    $user->status = 'active';
    $user->locked = true;
    check(Access::authenticate($settings, $auth) === null, 'Locked user denied');
    $user->locked = false;
    foreach ([
        ['siteIds' => []], ['siteIds' => ['1']], ['siteIds' => [2]], ['siteIds' => ['*']],
        ['userId' => 99], ['userId' => '7'], ['scopes' => ['manage']], ['scopes' => ['read', 'admin']],
        ['token' => '$MISSING_MCP_ENV'], ['token' => 'short'],
    ] as $override) {
        $settings->mcpTokens = [array_merge($valid, $override)];
        check(Access::authenticate($settings, $auth) === null, 'Bad or inaccessible token config fails closed');
    }
    $settings->mcpTokens = [$valid];
    foreach (['accessCp', 'accessPlugin-comments', 'comments-mcpRead'] as $permission) {
        $previous = $user->permissions;
        $user->permissions = array_values(array_diff($previous, [$permission]));
        check(Access::authenticate($settings, $auth) === null, 'Required Craft permission');
        $user->permissions = $previous;
    }
    try { $access->requireSite(2); throw new \RuntimeException('Cross-site access allowed'); } catch (ToolException) { check(true, 'Cross-site denied'); }
    echo "Access checks: $checks passed\n";
}
