<?php
namespace craft\base { class Component {} }
namespace craft\elements { class User {} }
namespace verbb\comments\helpers {
    class Plugin { public static function isPluginInstalledAndEnabled(string $handle): bool { return true; } }
}
namespace jamesedmonston\graphqlauthentication {
    class GraphqlAuthentication { public static object $tokenService; }
}
namespace {
    require __DIR__ . '/../../src/services/Service.php';
    $graphqlUser = new \craft\elements\User();
    \jamesedmonston\graphqlauthentication\GraphqlAuthentication::$tokenService = new class($graphqlUser) {
        public function __construct(public object $user) {}
        public function getHeaderToken(): string { return 'graphql-token'; }
        public function getUserFromToken(): object { return $this->user; }
    };
    $service = new \verbb\comments\services\Service();
    $actor = new \craft\elements\User();
    $nested = new \craft\elements\User();
    $checks = 0;
    function check(bool $result): void {
        global $checks;
        $checks++;
        if (!$result) { throw new RuntimeException('Scoped actor failed'); }
    }
    check($service->getUser() === $graphqlUser);
    $result = $service->withUser($actor, function() use ($service, $actor, $nested): int {
        check($service->getUser() === $actor);
        try {
            $service->withUser($nested, function() use ($service, $nested): void {
                check($service->getUser() === $nested);
                throw new RuntimeException('deliberate failure');
            });
        } catch (RuntimeException $e) {
            check($e->getMessage() === 'deliberate failure');
        }
        check($service->getUser() === $actor);
        return 42;
    });
    check($result === 42);
    check($service->getUser() === $graphqlUser);
    echo "Scoped actor checks: $checks passed\n";
}
