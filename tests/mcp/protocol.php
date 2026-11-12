<?php
require __DIR__ . '/../../src/mcp/ToolException.php';
require __DIR__ . '/../../src/mcp/InvalidParamsException.php';
require __DIR__ . '/../../src/mcp/ToolDefinitions.php';
require __DIR__ . '/../../src/mcp/Protocol.php';

use verbb\comments\mcp\Protocol;
use verbb\comments\mcp\ToolDefinitions;
use verbb\comments\mcp\ToolException;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$called = 0;
$logged = 0;
$protocol = new Protocol(
    fn() => ToolDefinitions::all(),
    function(string $name, array $arguments) use (&$called): array {
        $called++;
        if ($name === 'denied') {
            throw new ToolException('Permission denied.');
        }
        if ($name === 'invalid-internal') {
            throw new InvalidArgumentException('SECRET INTERNAL ARGUMENT');
        }
        if ($name === 'crash') {
            throw new RuntimeException('SECRET SQL COMMENT DATA');
        }
        $tools = array_column(ToolDefinitions::all(), null, 'name');
        if (!isset($tools[$name])) {
            throw new ToolException('Unknown tool.');
        }
        ToolDefinitions::validate($tools[$name], $arguments);
        return ['ok' => true];
    },
    function(Throwable $e) use (&$logged): string { $logged++; return 'test-reference'; },
    '3.0.24',
);
function rpc(string $method, mixed $params = null, int|string $id = 1): array {
    global $protocol;
    return $protocol->handle(json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params ?? new stdClass()]));
}
check($protocol->handle('{')['error']['code'] === -32700, 'Malformed JSON');
foreach (['[]', 'null', '1', '[{"jsonrpc":"2.0","id":1,"method":"ping"}]', '{"jsonrpc":"1.0","id":1,"method":"ping"}'] as $body) {
    check($protocol->handle($body)['error']['code'] === -32600, 'Invalid envelope or batch');
}
check($protocol->handle('{"jsonrpc":"2.0","id":null,"method":"ping"}')['error']['code'] === -32600, 'Null request id');
check(rpc('ping', id: 0)['id'] === 0, 'Zero id preserved');
check(rpc('ping', id: 'abc')['id'] === 'abc', 'String id preserved');
check(rpc('ping', [])['error']['code'] === -32602, 'Array params rejected');
check(rpc('unknown')['error']['code'] === -32601, 'Unknown method');
check(rpc('initialize')['error']['code'] === -32602, 'Initialize validation');
foreach (['2025-03-26', '2025-06-18', 'future'] as $version) {
    $result = rpc('initialize', ['protocolVersion' => $version, 'clientInfo' => ['name' => 'tests', 'version' => '1'], 'capabilities' => new stdClass()])['result'];
    check($result['protocolVersion'] === ($version === 'future' ? '2025-06-18' : $version), 'Version negotiation');
    check($result['capabilities']['tools']['listChanged'] === false, 'Capabilities');
}
check(count(rpc('tools/list')['result']['tools']) === 7, 'Tool list');
check(rpc('tools/list', ['cursor' => 'bad'])['error']['code'] === -32602, 'No unsupported cursors');
$before = $called;
foreach (['notifications/initialized', 'notifications/cancelled', 'tools/call'] as $method) {
    check($protocol->handle(json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => ['name' => 'delete_comment']])) === null, 'Notifications are silent');
}
check($called === $before, 'Notifications NEVER execute tools');
check($protocol->handle('{"jsonrpc":"2.0","id":1,"result":{}}') === null, 'Client response accepted');
check(rpc('tools/call', ['name' => 'get_capabilities', 'arguments' => new stdClass()])['result']['isError'] === false, 'Successful call');
check(rpc('tools/call', ['name' => 'get_capabilities', 'arguments' => ['surprise' => true]])['error']['code'] === -32602, 'Unknown arguments on empty schema');
check(rpc('tools/call', ['name' => 'get_comment', 'arguments' => []])['error']['code'] === -32602, 'Array arguments rejected');
check(rpc('tools/call', ['name' => 'denied'])['result']['isError'] === true, 'Expected tool errors');
$crash = rpc('tools/call', ['name' => 'crash']);
check(!str_contains(json_encode($crash), 'SECRET') && str_contains(json_encode($crash), 'test-reference') && $logged === 1, 'Internal errors redacted and correlated');

$internal = rpc('tools/call', ['name' => 'invalid-internal']);
check($internal['result']['isError'] === true && !str_contains(json_encode($internal), 'SECRET'), 'Internal argument exceptions are redacted');

$tools = array_column(ToolDefinitions::all(), null, 'name');
$revision = str_repeat('a', 64);
$target = ['commentId' => 1, 'siteId' => 2, 'expectedRevision' => $revision];
$invalid = [
    ['get_comment', ['commentId' => '1', 'siteId' => 2]],
    ['get_comment', ['commentId' => true, 'siteId' => 2]],
    ['get_comment', ['commentId' => 0, 'siteId' => 2]],
    ['get_comment', ['commentId' => 1]],
    ['list_comments', ['limit' => 101]],
    ['list_comments', ['offset' => -1]],
    ['list_comments', ['siteId' => '*']],
    ['list_comments', ['search' => str_repeat('x', 201)]],
    ['list_comments', ['flagged' => 'true']],
    ['list_comments', ['email' => 'secret@example.com']],
    ['moderate_comment', $target + ['status' => 'published']],
    ['moderate_comment', ['commentId' => 1, 'siteId' => 2, 'status' => 'approved']],
    ['update_comment', $target + ['comment' => str_repeat('x', 20001)]],
    ['update_comment', array_merge($target, ['expectedRevision' => 'old', 'comment' => 'text'])],
    ['delete_comment', $target + ['confirm' => false]],
    ['delete_comment', $target + ['confirm' => true, 'hardDelete' => true]],
];
foreach ($invalid as [$name, $arguments]) {
    try {
        ToolDefinitions::validate($tools[$name], $arguments);
        throw new RuntimeException('Accepted invalid arguments: ' . $name);
    } catch (InvalidArgumentException) {
        check(true, 'Schema rejects invalid input');
    }
}
foreach (ToolDefinitions::STATUSES as $status) {
    ToolDefinitions::validate($tools['moderate_comment'], $target + ['status' => $status]);
    check(true, 'Valid moderation status');
}
ToolDefinitions::validate($tools['delete_comment'], $target + ['confirm' => true]);
ToolDefinitions::validate($tools['list_comments'], ['limit' => 100, 'offset' => 0, 'flagged' => false, 'search' => '100%_literal']);
check(true, 'Valid boundaries');
echo "Protocol/schema checks: $checks passed\n";
