<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use RuntimeException;

/**
 * Real requests against the test app, one browser per instance, so permission checks, sessions and
 * CSRF run as they do for a person rather than through direct dispatch.
 */
class HttpClient
{
    private static mixed $server = null;
    private static ?string $baseUrl = null;

    private Client $client;

    public static function startServer(): void
    {
        if (self::$server) {
            return;
        }

        $port = 18300 + (getmypid() % 500);
        self::$baseUrl = "http://127.0.0.1:$port";
        $log = CRAFT_STORAGE_PATH . '/logs/http-server.log';
        @mkdir(dirname($log), 0775, true);

        self::$server = proc_open(
            [PHP_BINARY, '-d', 'max_execution_time=0', '-S', "127.0.0.1:$port", '-t', CRAFT_WEB_ROOT, dirname(__DIR__) . '/Support/http-router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            dirname(__DIR__, 2),
            getenv(),
        );

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);

            if ($socket) {
                fclose($socket);
                register_shutdown_function([self::class, 'stopServer']);

                return;
            }

            usleep(100000);
        }

        throw new RuntimeException('The test HTTP server did not start.');
    }

    public static function stopServer(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::$baseUrl,
            'cookies' => new CookieJar(),
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => 60,
        ]);
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        $options = ['headers' => ['Accept' => 'application/json', ...$headers]];

        if ($query) {
            $options['query'] = $query;
        }

        return $this->_send('GET', $path, $options);
    }

    /**
     * A JSON body, as the comments script sends one, with the CSRF token in its header.
     */
    public function postJson(string $path, array $body, array $headers = [], ?string $csrfToken = null): array
    {
        $headers = ['Accept' => 'application/json', ...$headers];

        if ($csrfToken !== null) {
            $headers['X-CSRF-Token'] = $csrfToken;
        }

        return $this->_send('POST', $path, ['json' => $body, 'headers' => $headers]);
    }

    /**
     * Any request, with Guzzle's options, such as a preflight, an exact raw body to sign, or a
     * multipart upload.
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $options['headers'] = ['Accept' => 'application/json', ...($options['headers'] ?? [])];

        return $this->_send($method, $path, $options);
    }

    public static function baseUrl(): ?string
    {
        return self::$baseUrl;
    }

    /** The names of the cookies this browser holds. */
    public function cookieNames(): array
    {
        return array_map(static fn($cookie): string => (string)$cookie->getName(), iterator_to_array($this->client->getConfig('cookies')));
    }

    public function post(string $path, array $fields = [], bool $csrf = true): array
    {
        if ($csrf) {
            $fields['CRAFT_CSRF_TOKEN'] = $this->csrfToken();
        }

        return $this->_send('POST', $path, ['form_params' => $fields, 'headers' => ['Accept' => 'application/json']]);
    }

    public function csrfToken(): string
    {
        return (string)$this->get('/actions/users/session-info')['data']['csrfTokenValue'];
    }

    public function login(string $loginName, string $password): void
    {
        $response = $this->post('/actions/users/login', ['loginName' => $loginName, 'password' => $password]);

        if ($response['status'] !== 200) {
            throw new RuntimeException('Sign-in failed: ' . $response['body']);
        }
    }

    private function _send(string $method, string $path, array $options): array
    {
        $response = $this->client->request($method, $path, $options);
        $body = (string)$response->getBody();

        // Yii's debug error page runs to hundreds of KB, and only its heading matters in a failure.
        if (str_contains($body, 'call-stack-item') && preg_match('/<title>(.*?)<\/title>.*?<h2>(.*?)<\/h2>/s', $body, $matches)) {
            $body = 'ERROR PAGE: ' . $matches[1] . ' | ' . strip_tags($matches[2]);
        }

        return [
            'status' => $response->getStatusCode(),
            'body' => $body,
            'data' => json_decode($body, true),
            'headers' => array_change_key_case($response->getHeaders(), CASE_LOWER),
        ];
    }
}
