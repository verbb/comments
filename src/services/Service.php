<?php
namespace verbb\comments\services;

use verbb\comments\Comments;

use Craft;
use craft\base\Component;

use jamesedmonston\graphqlauthentication\GraphqlAuthentication;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function getUser()
    {
        // Add support for https://plugins.craftcms.com/graphql-authentication
        if (class_exists(GraphqlAuthentication::class) && GraphqlAuthentication::$tokenService->getHeaderToken()) {
            return GraphqlAuthentication::$tokenService->getUserFromToken();
        }

        return Craft::$app->getUser()->getIdentity();
    } 

}
