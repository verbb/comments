<?php
namespace verbb\comments\services;

use verbb\comments\Comments;

use Craft;
use craft\base\Component;
use craft\elements\User;

use yii\web\IdentityInterface;

use jamesedmonston\graphqlauthentication\GraphqlAuthentication;
use verbb\base\helpers\Plugin;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function getUser(): bool|User|IdentityInterface|null
    {
        // A data API request signed in by a member token, or by a guest's session token, is that
        // member or nobody, whatever else came with it, including another plugin's bearer token.
        $signIn = Comments::$plugin?->getSignIn()->current();

        if ($signIn && $signIn->usesHeaderToken()) {
            return $signIn->user;
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
