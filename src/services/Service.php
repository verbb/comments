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
    // Public Methods
    // =========================================================================

    public function getUser(): bool|User|IdentityInterface|null
    {
        // Add support for https://plugins.craftcms.com/graphql-authentication
        if (Plugin::isPluginInstalledAndEnabled('graphql-authentication')) {
            if (GraphqlAuthentication::$tokenService?->getHeaderToken()) {
                return GraphqlAuthentication::$tokenService->getUserFromToken();
            }
        }

        return Craft::$app->getUser()->getIdentity();
    }

}
