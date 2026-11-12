<?php
namespace verbb\comments\services;

use verbb\comments\helpers\Plugin;

use Craft;
use craft\base\Component;
use craft\elements\User;

use yii\web\IdentityInterface;

use jamesedmonston\graphqlauthentication\GraphqlAuthentication;

class Service extends Component
{
    private ?User $_userOverride = null;

    /** Run authenticated integrations without interpreting their bearer token as a GraphQL JWT. */
    public function withUser(User $user, callable $callback): mixed
    {
        $previous = $this->_userOverride;
        $this->_userOverride = $user;
        try {
            return $callback();
        } finally {
            $this->_userOverride = $previous;
        }
    }

    // Public Methods
    // =========================================================================

    public function getUser(): bool|User|IdentityInterface|null
    {
        if ($this->_userOverride !== null) {
            return $this->_userOverride;
        }

        // Add support for https://plugins.craftcms.com/graphql-authentication
        if (Plugin::isPluginInstalledAndEnabled('graphql-authentication')) {
            if (GraphqlAuthentication::$tokenService?->getHeaderToken()) {
                return GraphqlAuthentication::$tokenService->getUserFromToken();
            }
        }

        return Craft::$app->getUser()->getIdentity();
    }

}
