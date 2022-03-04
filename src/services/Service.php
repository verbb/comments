<?php
namespace verbb\comments\services;

use Craft;
use craft\base\Component;
use craft\elements\User;

use jamesedmonston\graphqlauthentication\GraphqlAuthentication;
use yii\web\IdentityInterface;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function getUser(): bool|User|IdentityInterface|null
    {
        // Add support for https://plugins.craftcms.com/graphql-authentication
        if (class_exists(GraphqlAuthentication::class) && GraphqlAuthentication::$tokenService->getHeaderToken()) {
            return GraphqlAuthentication::$tokenService->getUserFromToken();
        }

        return Craft::$app->getUser()->getIdentity();
    }

}
