<?php

declare(strict_types=1);

use craft\helpers\ProjectConfig as ProjectConfigHelper;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use verbb\comments\services\SpamSettings;

/**
 * An upgraded site keeps Comments 3's settings in project config under their old names until they're next
 * saved. Saving any settings pane, through the real form, writes them under today's names rather than
 * dropping them.
 */

const LEGACY_SAVE_PASSWORD = 'Comments-Legacy-Save-1!';

const LEGACY_SAVE_V3_SETTINGS = [
    'pluginName' => 'Comments',
    'allowVoting' => true,
    'notificationAdminEnabled' => true,
    'notificationFlaggedEnabled' => true,
    'notificationAdmins' => [['email' => 'editor@example.test', 'enabled' => '1']],
    'notificationSubscribeEnabled' => true,
    'notificationModeratorApprovedEnabled' => true,
];

function legacySaveBrowser(): HttpClient
{
    $user = CommentsFixtureFactory::user('legacySaveAdmin');
    $user->admin = true;
    $user->newPassword = LEGACY_SAVE_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    // Craft 5.12 rate-limits sign-ins per address.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();
    $browser->login((string)$user->username, LEGACY_SAVE_PASSWORD);

    return $browser;
}

function legacySaveStore(array $settings): void
{
    $projectConfig = Craft::$app->getProjectConfig();
    $projectConfig->set('plugins.comments.settings', $settings);
    $projectConfig->saveModifiedConfigData();
}

/** What the web request saved, read afresh. */
function legacySaveStored(): array
{
    // The web request moved the config version on; load it again so this process can write afterwards.
    Craft::$app->getIsInstalled(true);
    Craft::$app->getProjectConfig()->reset();

    return Craft::$app->getProjectConfig()->get('plugins.comments.settings') ?? [];
}

function legacySaveGeneralPane(): array
{
    return [
        'pluginName' => 'Comments',
        'closed' => '',
        'autoCloseDays' => '',
        'sortDefaultKey' => 'commentDate',
        'sortDefaultDirection' => 'desc',
    ];
}

beforeAll(function() {
    Craft::$app->getProjectConfig()->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
});

beforeEach(function() {
    $this->storedSettings = Craft::$app->getProjectConfig()->get('plugins.comments.settings') ?? [];
});

afterEach(function() {
    legacySaveStored();
    legacySaveStore($this->storedSettings);
});

it('writes Comments 3 settings under today’s names when another pane is saved', function() {
    legacySaveStore(LEGACY_SAVE_V3_SETTINGS);

    $response = legacySaveBrowser()->post('/admin/actions/comments/settings/save-settings', ['settings' => legacySaveGeneralPane()]);
    $stored = legacySaveStored();

    expect($response['status'])->toBe(302, $response['body'])
        ->and($stored)->not->toHaveKey('allowVoting')
        ->and($stored)->not->toHaveKey('notificationAdmins')
        ->and($stored['interactionMode'] ?? null)->toBe('updown')
        ->and($stored['staffNotifications'] ?? null)->toBe(['staff-new', 'staff-reported'])
        ->and($stored['participantNotifications'] ?? null)->toBe(['owner', 'reply', 'mention', 'approved'])
        ->and($stored['subscriptionTypes'] ?? null)->toBe(['conversation'])
        ->and(ProjectConfigHelper::unpackAssociativeArrays($stored['notificationRecipients'] ?? []))->toBe([['email' => 'editor@example.test', 'enabled' => '1']]);
});

it('keeps what a pane posts over a translated Comments 3 setting', function() {
    legacySaveStore(LEGACY_SAVE_V3_SETTINGS);

    $response = legacySaveBrowser()->post('/admin/actions/comments/settings/save-settings', ['settings' => [
        'interactionMode' => 'none',
        'allowFlagging' => '1',
    ]]);
    $stored = legacySaveStored();

    expect($response['status'])->toBe(302, $response['body'])
        ->and($stored['interactionMode'] ?? null)->toBe('none')
        ->and($stored['staffNotifications'] ?? null)->toBe(['staff-new', 'staff-reported']);
});

it('leaves Comments 3 spam settings to the Spam Protection page', function() {
    legacySaveStore(LEGACY_SAVE_V3_SETTINGS + [
        'recaptchaEnabled' => true,
        'recaptchaKey' => 'site-key',
        'recaptchaSecret' => 'secret',
        'akismetEnabled' => true,
        'akismetFeedbackEnabled' => true,
        'akismetApiKey' => 'akismet-key',
    ]);

    $response = legacySaveBrowser()->post('/admin/actions/comments/settings/save-settings', ['settings' => legacySaveGeneralPane()]);
    $stored = legacySaveStored();

    expect($response['status'])->toBe(302, $response['body'])
        ->and(array_intersect_key($stored, array_flip([...SpamSettings::KEYS, 'recaptchaEnabled', 'akismetEnabled', 'akismetFeedbackEnabled'])))->toBe([])
        ->and($stored['interactionMode'] ?? null)->toBe('updown');
});
