<?php
namespace verbb\comments\mcp;

use Craft;
use craft\elements\User;
use craft\helpers\App;
use verbb\comments\models\Settings;

/** Token scope AND Craft permissions AND explicit site restrictions must all allow access. */
class Access
{
    public function __construct(
        public readonly User $user,
        public readonly array $scopes,
        public readonly array $siteIds,
    ) {
    }

    public static function authenticate(Settings $settings, string $authorization): ?self
    {
        if (!preg_match('/^Bearer ([A-Za-z0-9._~+\/-]+=*)$/iD', $authorization, $matches)) {
            return null;
        }
        foreach ($settings->mcpTokens as $config) {
            if (!is_array($config) || !is_string($config['token'] ?? null)) {
                continue;
            }
            $secret = App::parseEnv($config['token']);
            if (!is_string($secret) || strlen($secret) < 32 || str_starts_with($secret, '$') || !hash_equals($secret, $matches[1])) {
                continue;
            }
            if (!is_int($config['userId'] ?? null) || !is_array($config['siteIds'] ?? null)
                || !$config['siteIds'] || !is_array($config['scopes'] ?? null)
                || !in_array('read', $config['scopes'], true)) {
                return null;
            }
            foreach ($config['siteIds'] as $siteId) {
                if (!is_int($siteId) || $siteId < 1) {
                    return null;
                }
            }
            foreach ($config['scopes'] as $scope) {
                if (!in_array($scope, ['read', 'moderate', 'manage'], true)) {
                    return null;
                }
            }
            $user = Craft::$app->getUsers()->getUserById($config['userId']);
            if (!$user || $user->getStatus() !== User::STATUS_ACTIVE || $user->locked
                || !$user->can('accessCp') || !$user->can('accessPlugin-comments') || !$user->can('comments-mcpRead')) {
                return null;
            }
            $siteIds = [];
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                if (in_array($site->id, $config['siteIds'], true)
                    && (!Craft::$app->getIsMultiSite() || $user->can('editSite:' . $site->uid))) {
                    $siteIds[] = $site->id;
                }
            }
            return $siteIds ? new self($user, $config['scopes'], $siteIds) : null;
        }
        return null;
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes, true) && match ($scope) {
            'read' => $this->user->can('comments-mcpRead'),
            'moderate' => $this->user->can('comments-mcpModerate') && $this->user->can('comments-edit'),
            'manage' => $this->user->can('comments-mcpManage'),
            default => false,
        };
    }

    public function requireSite(int $siteId): void
    {
        if (!in_array($siteId, $this->siteIds, true)) {
            throw new ToolException('Access denied for this site.');
        }
    }
}
