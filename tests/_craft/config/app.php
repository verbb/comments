<?php

use craft\helpers\App;

// Browser review and runtime maintenance commands use Craft's normal
// applications. The custom request/session components below are only needed
// while Pest is exercising controller-like service paths from the console.
if (PHP_SAPI !== 'cli' || getenv('VERBB_INTEGRATION_TESTS') !== 'true') {
    return [];
}

return [
    'class' => Tests\Support\ConsoleApplication::class,
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS-CommentsTests',
    'components' => [
        'projectConfig' => function() {
            $config = craft\helpers\App::projectConfigConfig();
            $config['writeYamlAutomatically'] = false;

            return Craft::createObject($config);
        },
        'session' => [
            'class' => craft\web\Session::class,
            'name' => 'comments-test',
            'useCookies' => false,
        ],
        'request' => [
            'class' => craft\web\Request::class,
            'enableCsrfValidation' => false,
            'enableCookieValidation' => false,
            'hostInfo' => 'https://comments-test.test',
            'url' => '/',
        ],
        'response' => [
            'class' => craft\web\Response::class,
        ],
    ],
];
