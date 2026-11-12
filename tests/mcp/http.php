<?php
namespace yii\web {
    class Response {
        public const FORMAT_RAW = 'raw';
        public int $statusCode = 200;
        public mixed $data = null;
        public string $content = '';
        public string $format = 'json';
        public object $headers;
        public function __construct() { $this->headers = new \TestHeaders(); }
        public function getHeaders(): object { return $this->headers; }
        public function setStatusCode(int $code): void { $this->statusCode = $code; }
    }
}
namespace craft\web {
    class Controller {
        public bool $enableCsrfValidation = true;
        public object $request;
        public object $response;
        public function beforeAction($action): bool { return true; }
        public function asJson(array $data): \yii\web\Response { $this->response->data = $data; return $this->response; }
    }
}
namespace verbb\comments {
    class Comments { public static object $plugin; }
}
namespace verbb\comments\services {
    class Mcp {
        public function __construct($access) {}
        public function tools(): array { return []; }
        public function call(string $name, array $arguments): array { return ['ok' => true]; }
    }
}
namespace {
    class TestHeaders {
        public function __construct(public array $values = []) {}
        public function get(string $name, mixed $default = null): mixed { return $this->values[$name] ?? $default; }
        public function set(string $name, mixed $value): void { $this->values[$name] = $value; }
    }
    require __DIR__ . '/access.php';
    require __DIR__ . '/../../src/mcp/InvalidParamsException.php';
    require __DIR__ . '/../../src/mcp/Protocol.php';
    require __DIR__ . '/../../src/controllers/McpController.php';
    $checks = 0;
    $settings = new class extends \verbb\comments\models\Settings {
        public bool $mcpEnabled = true;
        public array $mcpAllowedOrigins = [];
    };
    $settings->mcpTokens = [$valid];
    \verbb\comments\Comments::$plugin = new class($settings) {
        public function __construct(public object $settings) {}
        public function getSettings(): object { return $this->settings; }
        public function getVersion(): string { return '3.0.24'; }
        public function getService(): object {
            return new class {
                public function withUser($user, callable $callback): mixed { return $callback(); }
            };
        }
    };
    $previousApp = Craft::$app;
    Craft::$app = new class($previousApp) {
        public object $session;
        public function __construct(public object $previous) {
            $this->session = new class {
                public mixed $identity = null;
                public function getIdentity(bool $renew): mixed { return $this->identity; }
                public function setIdentity(mixed $identity): void { $this->identity = $identity; }
            };
        }
        public function __call(string $name, array $arguments): mixed { return $this->previous->$name(...$arguments); }
        public function getUser(): object { return $this->session; }
    };
    function request(array $headers = [], string $method = 'POST', ?string $body = null, bool $cp = false): \yii\web\Response {
        global $auth;
        $controller = new \verbb\comments\controllers\McpController();
        $controller->response = new \yii\web\Response();
        $controller->request = new class($headers, $method, $body, $cp, $auth) {
            public object $headers;
            public function __construct(array $headers, public string $method, public ?string $body, public bool $cp, string $auth) {
                $this->headers = new TestHeaders(array_merge(['Authorization' => $auth, 'Accept' => 'application/json, text/event-stream', 'Content-Type' => 'application/json'], $headers));
            }
            public function getHeaders(): object { return $this->headers; }
            public function getIsCpRequest(): bool { return $this->cp; }
            public function getIsPost(): bool { return $this->method === 'POST'; }
            public function getRawBody(): string { return $this->body ?? '{"jsonrpc":"2.0","id":1,"method":"ping"}'; }
        };
        $controller->beforeAction((object)['id' => 'index']);
        check(!$controller->enableCsrfValidation, 'Only MCP endpoint bypasses browser CSRF');
        return $controller->actionIndex();
    }
    $settings->mcpEnabled = false;
    check(request()->statusCode === 404, 'Disabled endpoint');
    $settings->mcpEnabled = true;
    check(request(cp: true)->statusCode === 404, 'CP route denied');
    check(request(['Authorization' => ''])->statusCode === 401, 'No bearer token');
    Craft::$app->session->identity = $user;
    check(request(['Authorization' => ''])->statusCode === 401, 'Browser identity cannot authorize MCP');
    check(request(['Origin' => 'https://evil.test'])->statusCode === 403, 'Origin validation');
    check(request(['Origin' => 'null'])->statusCode === 403, 'Opaque origin denied');
    check(request(method: 'GET')->statusCode === 405, 'No SSE stream');
    check(request(method: 'DELETE')->statusCode === 405, 'No server sessions');
    check(request(['Content-Type' => 'text/plain'])->statusCode === 415, 'JSON required');
    check(request(['Accept' => 'application/json'])->statusCode === 406, 'MCP Accept types required');
    check(request(['Accept' => 'application/json-evil, text/event-stream'])->statusCode === 406, 'Exact media types required');
    check(request(['Accept' => 'application/json;q=0, text/event-stream'])->statusCode === 406, 'Zero-quality JSON refused');
    check(request(['MCP-Protocol-Version' => '2099-01-01'])->statusCode === 400, 'Unsupported transport version');
    check(request(['Content-Length' => '1048577'])->statusCode === 413, 'Declared body limit');
    check(request(body: str_repeat(' ', 1048577))->statusCode === 413, 'Actual body limit');
    $response = request();
    check($response->statusCode === 200 && $response->data['result'] instanceof \stdClass, 'Ping round trip');
    check($response->headers->get('Cache-Control') === 'no-store', 'No response caching');
    check(Craft::$app->session->identity === $user, 'Original identity restored');
    $settings->mcpAllowedOrigins = ['https://client.test'];
    check(request(['Origin' => 'https://client.test'])->statusCode === 200, 'Explicit origin allowed');
    check(request(['Origin' => 'https://client.test.evil.test'])->statusCode === 403, 'Origin is exact, not prefix');
    $response = request(body: '{"jsonrpc":"2.0","method":"notifications/initialized"}');
    check($response->statusCode === 202 && $response->data === null && $response->content === '', 'Notification acknowledgement has no body');
    check(request(body: '{broken')->data['error']['code'] === -32700, 'JSON parse errors are JSON-RPC');
    echo "HTTP controller checks: $checks passed\n";
}
