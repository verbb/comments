<?php
use craft\elements\User;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;

/** Run only against a disposable Craft installation; see docs/developers/mcp.md. */
$project = $argv[1] ?? '';
if (!is_file($project . '/.mcp-disposable')) {
    throw new RuntimeException('A disposable project marker is required.');
}
require $project . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
if (!str_starts_with($app->getDb()->createCommand('SELECT DATABASE()')->queryScalar(), 'comments_mcp_verify_')) {
    throw new RuntimeException('Refusing to modify a non-verification database.');
}
if (Comment::find()->status(null)->trashed(null)->count() || count($app->getSites()->getAllSites()) !== 1) {
    throw new RuntimeException('A fresh disposable database is required for each run.');
}
if (!$app->getMailer()->useFileTransport) {
    throw new RuntimeException('File-only mail transport is required.');
}


$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo "PASS $message\n";
}
function save($element): void
{
    if (!Craft::$app->getElements()->saveElement($element)) {
        throw new RuntimeException(json_encode($element->getErrors()));
    }
}
function rpc(string $token, string $method, array $params = [], array $headers = []): array
{
    $curl = curl_init('http://127.0.0.1:8877/actions/comments/mcp');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'MCP-Protocol-Version: 2025-06-18',
        ], $headers),
        CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object)$params]),
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException("Non-JSON HTTP $status: " . substr($body, 0, 500));
    }
    return [$status, $data];
}
function callTool(string $token, string $name, array $args = [], bool $error = false): array
{
    [$status, $data] = rpc($token, 'tools/call', ['name' => $name, 'arguments' => (object)$args]);
    check($status === 200 && isset($data['result']) && $data['result']['isError'] === $error, "$name " . ($error ? 'refused' : 'succeeded') . (isset($data['error']) ? ': ' . json_encode($data['error']) : ''));
    $text = $data['result']['content'][0]['text'];
    if (!$error && !is_array(json_decode($text, true))) {
        throw new RuntimeException($text);
    }
    return $error ? ['error' => $text] : json_decode($text, true);
}

check($app->edition === \craft\enums\CmsEdition::Pro, 'Craft Pro fixture edition');
$site = $app->getSites()->getPrimarySite();
$other = new \craft\models\Site([
    'groupId' => $site->groupId, 'name' => 'MCP second site', 'handle' => 'mcpSecond',
    'language' => 'en', 'hasUrls' => true, 'baseUrl' => 'http://localhost:8877/second/',
]);
check($app->getSites()->saveSite($other), 'second fixture site created');
$app->getProjectConfig()->writeYamlFiles();
$base = ['accessCp', 'accessPlugin-comments', 'comments-mcpRead', 'editSite:' . $site->uid];
$full = array_merge($base, ['comments-edit', 'comments-trash', 'comments-delete', 'comments-mcpModerate', 'comments-mcpManage']);
$users = $tokens = $configs = [];
foreach (['reader' => $base, 'writer' => $full, 'noTrash' => array_diff($full, ['comments-trash']), 'scopeOnly' => $full, 'permissionOnly' => $base, 'noAccess' => []] as $role => $permissions) {
    $user = new User(['username' => 'mcp-' . $role, 'email' => 'mcp-' . $role . '@example.invalid', 'pending' => false, 'active' => true]);
    save($user);
    check($app->getUserPermissions()->saveUserPermissions($user->id, array_values($permissions)), "$role permissions saved");
    $users[$role] = $user;
    $tokens[$role] = bin2hex(random_bytes(32));
    $configs[] = ['token' => $tokens[$role], 'userId' => $user->id, 'scopes' => in_array($role, ['reader', 'scopeOnly'], true) ? ['read'] : ['read', 'moderate', 'manage'], 'siteIds' => [$site->id, $other->id]];
}
$settings = ['mcpEnabled' => true, 'mcpTokens' => $configs, 'securityMaxLength' => 1000, 'useQueueForNotifications' => true];
file_put_contents($project . '/config/comments.php', '<?php return ' . var_export($settings, true) . ';');
chmod($project . '/config/comments.php', 0600);
Comments::$plugin->setSettings($settings);
$fixtures = [];
foreach (['approved', 'pending', 'spam', 'trashed'] as $status) {
    $comment = new Comment([
        'siteId' => $site->id, 'ownerId' => $users['writer']->id, 'ownerSiteId' => $site->id,
        'userId' => $users['reader']->id, 'status' => $status, 'comment' => "MCP fixture $status literal %_ needle",
    ]);
    $comment->setScenario(Comment::SCENARIO_CP);
    save($comment);
    $fixtures[$status] = $comment;
}
$cross = new Comment(['siteId' => $other->id, 'ownerSiteId' => $other->id, 'ownerId' => $users['writer']->id, 'status' => 'pending', 'comment' => 'Cross-site fixture']);
$cross->setScenario(Comment::SCENARIO_CP);
save($cross);
$reply = new Comment(['siteId' => $site->id, 'ownerSiteId' => $site->id, 'ownerId' => $users['writer']->id, 'status' => 'pending', 'comment' => 'Reply fixture', 'newParentId' => $fixtures['pending']->id]);
$reply->setScenario(Comment::SCENARIO_CP);
save($reply);
$flag = new \verbb\comments\records\Flag(['commentId' => $fixtures['pending']->id, 'userId' => $users['writer']->id]);
check($flag->save(), 'fixture flag saved');
check(rpc('invalid', 'tools/list')[0] === 401, 'invalid token rejected');
check(rpc($tokens['noAccess'], 'tools/list')[0] === 401, 'user without permissions rejected');
check(rpc($tokens['writer'], 'tools/list', [], ['Origin: https://untrusted.invalid'])[0] === 403, 'foreign origin rejected');
[$http, $init] = rpc($tokens['reader'], 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'clientInfo' => ['name' => 'live-test', 'version' => '1']]);
check($http === 200 && $init['result']['serverInfo']['name'] === 'craft-comments', 'HTTP initialization');
$caps = callTool($tokens['reader'], 'get_capabilities');
check($caps['siteIds'] === [$site->id] && count($caps['tools']) === 4, 'non-admin reader tools and site intersection');
$list = callTool($tokens['reader'], 'list_comments', ['search' => 'MCP fixture', 'limit' => 2]);
check(count($list['comments']) === 2 && $list['nextOffset'] === 2, 'pagination first page');
$page = callTool($tokens['reader'], 'list_comments', ['search' => 'MCP fixture', 'limit' => 2, 'offset' => 2]);
check(count($page['comments']) === 2 && $page['nextOffset'] === null && !array_intersect(array_column($list['comments'], 'id'), array_column($page['comments'], 'id')), 'pagination second page distinct');
$stats = callTool($tokens['reader'], 'get_comment_stats', ['search' => 'MCP fixture']);
check($stats['total'] === 4 && array_values($stats['counts']) === [1, 1, 1, 1], 'all moderation statuses counted');
$filtered = callTool($tokens['reader'], 'list_comments', ['status' => 'pending', 'flagged' => true, 'ownerId' => $users['writer']->id, 'userId' => $users['reader']->id, 'search' => '%_']);
check(count($filtered['comments']) === 1, 'combined literal text, owner, author, status and flag filters');
check(count(callTool($tokens['reader'], 'list_comments', ['search' => '%_absent'])['comments']) === 0, 'SQL wildcards are literal');
$args = ['commentId' => $fixtures['pending']->id, 'siteId' => $site->id];
$before = callTool($tokens['reader'], 'get_comment', $args)['comment'];
check($before['flagCount'] === 1 && !array_intersect(['email', 'ipAddress', 'url', 'userAgent'], array_keys($before)), 'flag count and private fields');
callTool($tokens['reader'], 'get_comment', ['commentId' => $cross->id, 'siteId' => $other->id], true);
callTool($tokens['reader'], 'get_comment', ['commentId' => $cross->id, 'siteId' => $site->id], true);
callTool($tokens['reader'], 'get_comment', ['commentId' => 9999999, 'siteId' => $site->id], true);
$edit = $args + ['expectedRevision' => $before['revision'], 'comment' => 'Edited fixture'];
callTool($tokens['reader'], 'update_comment', $edit, true);
callTool($tokens['scopeOnly'], 'update_comment', $edit, true);
callTool($tokens['permissionOnly'], 'update_comment', $edit, true);
callTool($tokens['scopeOnly'], 'moderate_comment', $args + ['expectedRevision' => $before['revision'], 'status' => 'approved'], true);
callTool($tokens['permissionOnly'], 'moderate_comment', $args + ['expectedRevision' => $before['revision'], 'status' => 'approved'], true);
$after = callTool($tokens['writer'], 'update_comment', $edit)['comment'];
check($after['comment'] === 'Edited fixture' && $after['userId'] === $before['userId'] && $after['status'] === $before['status'], 'edit preserves author and status');
$noop = callTool($tokens['writer'], 'update_comment', $args + ['expectedRevision' => $after['revision'], 'comment' => $after['comment']]);
check(!$noop['changed'] && $noop['comment']['revision'] === $after['revision'], 'no-op edit preserves revision');
$error = callTool($tokens['writer'], 'update_comment', $edit, true);
check(str_contains($error['error'], 'changed since'), 'stale revision rejected');
callTool($tokens['writer'], 'update_comment', $args + ['expectedRevision' => $after['revision'], 'comment' => str_repeat('x', 1001)], true);
callTool($tokens['noTrash'], 'moderate_comment', $args + ['expectedRevision' => $after['revision'], 'status' => 'trashed'], true);
$notificationLog = $app->getPath()->getRuntimePath() . '/mcp-notifications.jsonl';
file_put_contents($notificationLog, '');
foreach (['approved', 'spam', 'trashed', 'pending'] as $status) {
    $after = callTool($tokens['writer'], 'moderate_comment', $args + ['expectedRevision' => $after['revision'], 'status' => $status])['comment'];
    check($after['status'] === $status, "moderated to $status");
    if ($status === 'approved') {
        $jobs = array_map(fn($line) => json_decode($line, true), file($notificationLog, FILE_IGNORE_NEW_LINES));
        check(array_column($jobs, 'type') === ['author'], 'approval queues expected notifications');
        check(array_unique(array_column($jobs, 'actorId')) === [$users['writer']->id], 'notification hooks see MCP actor');
    }
    if ($status === 'trashed') {
        callTool($tokens['noTrash'], 'moderate_comment', $args + ['expectedRevision' => $after['revision'], 'status' => 'approved'], true);
    }
}
[$http, $badDelete] = rpc($tokens['writer'], 'tools/call', ['name' => 'delete_comment', 'arguments' => $args + ['expectedRevision' => $after['revision'], 'confirm' => false]]);
check($http === 200 && $badDelete['error']['code'] === -32602, 'delete requires explicit confirmation');
callTool($tokens['writer'], 'delete_comment', $args + ['expectedRevision' => $before['revision'], 'confirm' => true], true);
$deleted = callTool($tokens['writer'], 'delete_comment', $args + ['expectedRevision' => $after['revision'], 'confirm' => true]);
check($deleted['deleted'] && !$deleted['permanentlyDeleted'], 'soft deletion response');
callTool($tokens['reader'], 'get_comment', $args, true);
$trashed = Comment::find()->id($args['commentId'])->siteId($site->id)->status(null)->trashed(true)->one();
check($trashed !== null && $app->getElements()->restoreElement($trashed), 'Craft native restore after soft deletion');
callTool($tokens['reader'], 'get_comment', $args);
$child = Comment::find()->id($reply->id)->status(null)->trashed(null)->one();
check($child !== null && !$child->trashed, 'reply preserved after parent delete and restore');
$app->getUsers()->suspendUser($users['reader']);
check(rpc($tokens['reader'], 'tools/list')[0] === 401, 'suspended user rejected on next HTTP request');
$settings['mcpTokens'] = [];
file_put_contents($project . '/config/comments.php', '<?php return ' . var_export($settings, true) . ';');
check(rpc($tokens['writer'], 'tools/list')[0] === 401, 'revoked token rejected on next HTTP request');
echo "$checks live checks passed; mail stayed in file transport.\n";
