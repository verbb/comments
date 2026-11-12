<?php
namespace verbb\comments\controllers;

use Craft;
use craft\web\Controller;
use verbb\comments\Comments;
use verbb\comments\mcp\Access;
use verbb\comments\mcp\Protocol;
use verbb\comments\services\Mcp;
use yii\web\Response;

/** Stateless Streamable HTTP, JSON responses only, at /actions/comments/mcp. */
class McpController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        // Only this bearer-authenticated endpoint bypasses browser CSRF. Cookies never authorize it.
        if ($action->id === 'index') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        $this->response->getHeaders()->set('Cache-Control', 'no-store');
        $this->response->getHeaders()->set('X-Content-Type-Options', 'nosniff');
        try {
            return $this->handleRequest();
        } catch (\Throwable $e) {
            return $this->httpError(500, 'MCP request failed. Reference: ' . $this->logException($e));
        }
    }

    private function handleRequest(): Response
    {
        $settings = Comments::$plugin->getSettings();
        if (!$settings->mcpEnabled || $this->request->getIsCpRequest()) {
            return $this->httpError(404, 'MCP endpoint is not enabled.');
        }

        // Exact allow-list; no trust in Host, no wildcard origins, and no ambient cookie authentication.
        $origin = $this->request->getHeaders()->get('Origin');
        if ($origin !== null && (!is_string($origin) || !in_array($origin, $settings->mcpAllowedOrigins, true))) {
            return $this->httpError(403, 'Origin is not allowed.');
        }
        $access = Access::authenticate($settings, (string)$this->request->getHeaders()->get('Authorization', ''));
        if (!$access) {
            $this->response->getHeaders()->set('WWW-Authenticate', 'Bearer realm="comments-mcp"');
            return $this->httpError(401, 'Invalid token or the configured Craft user lacks MCP access.');
        }
        if (!$this->request->getIsPost()) {
            // Streamable HTTP explicitly permits 405 when no SSE stream/session deletion is offered.
            $this->response->getHeaders()->set('Allow', 'POST');
            return $this->httpError(405, 'Only POST is supported; this endpoint has no SSE stream or sessions.');
        }
        $contentType = strtolower(trim(explode(';', (string)$this->request->getHeaders()->get('Content-Type', ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->httpError(415, 'Content-Type must be application/json.');
        }
        $accept = strtolower((string)$this->request->getHeaders()->get('Accept', ''));
        $accepted = [];
        foreach (explode(',', $accept) as $range) {
            $parts = array_map('trim', explode(';', $range));
            $type = array_shift($parts);
            $quality = 1.0;
            foreach ($parts as $part) {
                if (str_starts_with($part, 'q=')) {
                    $quality = (float)substr($part, 2);
                }
            }
            if ($quality > 0) {
                $accepted[] = $type;
            }
        }
        if (!in_array('application/json', $accepted, true) || !in_array('text/event-stream', $accepted, true)) {
            return $this->httpError(406, 'Accept must include application/json and text/event-stream.');
        }
        $version = $this->request->getHeaders()->get('MCP-Protocol-Version');
        if ($version !== null && !in_array($version, Protocol::VERSIONS, true)) {
            return $this->httpError(400, 'Unsupported MCP-Protocol-Version.');
        }
        if ((int)$this->request->getHeaders()->get('Content-Length', 0) > 1048576) {
            return $this->httpError(413, 'Request exceeds 1 MiB.');
        }
        $body = $this->request->getRawBody();
        if (strlen($body) > 1048576) {
            return $this->httpError(413, 'Request exceeds 1 MiB.');
        }

        $service = new Mcp($access);
        $protocol = new Protocol(
            $service->tools(...),
            $service->call(...),
            $this->logException(...),
            Comments::$plugin->getVersion(),
        );
        // Request-only identity for hooks; never login(), save a session, or issue an auth cookie.
        $user = Craft::$app->getUser();
        $previousIdentity = $user->getIdentity(false);
        $user->setIdentity($access->user);
        try {
            $result = Comments::$plugin->getService()->withUser($access->user, fn() => $protocol->handle($body));
        } finally {
            $user->setIdentity($previousIdentity);
        }
        if ($result === null) {
            $this->response->setStatusCode(202);
            $this->response->format = Response::FORMAT_RAW;
            $this->response->data = null;
            $this->response->content = '';
            return $this->response;
        }
        return $this->asJson($result);
    }

    private function logException(\Throwable $e): string
    {
        $reference = bin2hex(random_bytes(8));
        // SQL errors can contain comment text and personal data; do not log raw messages/traces.
        Craft::error(json_encode([
            'reference' => $reference, 'exception' => get_class($e),
            'code' => $e->getCode(), 'file' => basename($e->getFile()), 'line' => $e->getLine(),
        ]), 'comments-mcp');
        return $reference;
    }

    private function httpError(int $status, string $message): Response
    {
        $this->response->setStatusCode($status);
        return $this->asJson(Protocol::error(null, -32000, $message));
    }
}
