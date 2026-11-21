<?php

declare(strict_types=1);

use craft\db\Query;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\services\SpamSettings;

/**
 * The settings pages don't show the Spam settings, so Spam settings that can't be used (a challenge with no
 * keys, or Comments 3 spam flags with no keys) don't stop them saving. Their own settings still validate.
 */

const SPAM_ISOLATION_PASSWORD = 'Comments-Spam-Isolation-1!';

function spamIsolationBrowser(): HttpClient
{
    $user = CommentsFixtureFactory::user('spamIsolationAdmin');
    $user->admin = true;
    $user->newPassword = SPAM_ISOLATION_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    // Craft 5.12 rate-limits sign-ins per address.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();
    $browser->login((string)$user->username, SPAM_ISOLATION_PASSWORD);

    return $browser;
}

function spamIsolationStoreSettings(array $settings): void
{
    $projectConfig = Craft::$app->getProjectConfig();
    $projectConfig->set('plugins.comments.settings', $settings);
    $projectConfig->saveModifiedConfigData();
}

/** What the web request saved, read afresh. */
function spamIsolationStored(): array
{
    Craft::$app->getIsInstalled(true);
    Craft::$app->getProjectConfig()->reset();

    return Craft::$app->getProjectConfig()->get('plugins.comments.settings') ?? [];
}

/** Turnstile switched on with no keys: the Spam settings fail their own rules. */
function spamIsolationBreakSpamSettings(): void
{
    $values = Comments::$plugin->getSpamSettings()->getStoredValues() ?? [];
    Comments::$plugin->getSpamSettings()->saveValues(array_replace($values, [
        'enableSpamChecks' => true,
        'challengeProvider' => 'turnstile',
        'turnstileKey' => '',
        'turnstileSecret' => '',
    ]));
}

function spamIsolationGeneralPane(array $overrides = []): array
{
    return array_replace([
        'pluginName' => 'Comments',
        'closed' => '',
        'autoCloseDays' => '',
        'sortDefaultKey' => 'commentDate',
        'sortDefaultDirection' => 'desc',
    ], $overrides);
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
    $this->storedSpam = (new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar();
});

afterEach(function() {
    spamIsolationStored();
    spamIsolationStoreSettings($this->storedSettings);
    Craft::$app->getDb()->createCommand()->delete(SpamSettings::TABLE)->execute();

    if (is_string($this->storedSpam)) {
        Craft::$app->getDb()->createCommand()->insert(SpamSettings::TABLE, ['settings' => $this->storedSpam])->execute();
    }

    (new ReflectionProperty(SpamSettings::class, '_stored'))->setValue(Comments::$plugin->getSpamSettings(), null);
});

it('saves a settings pane while the Spam settings are invalid', function() {
    spamIsolationBreakSpamSettings();

    $response = spamIsolationBrowser()->post('/admin/actions/comments/settings/save-settings', [
        'settings' => spamIsolationGeneralPane(['pluginName' => 'Comments Renamed']),
    ]);

    expect($response['status'])->toBe(302, $response['body'])
        ->and(spamIsolationStored()['pluginName'] ?? null)->toBe('Comments Renamed');
});

it('still refuses an invalid value on the pane itself', function() {
    spamIsolationBreakSpamSettings();

    $response = spamIsolationBrowser()->post('/admin/actions/comments/settings/save-settings', [
        'settings' => spamIsolationGeneralPane(['pluginName' => 'Comments Renamed', 'autoCloseDays' => '0']),
    ]);

    expect($response['status'])->not->toBe(302)
        ->and(spamIsolationStored()['pluginName'] ?? null)->not->toBe('Comments Renamed');
});

it('saves a settings pane while Comments 3 spam flags have no keys', function() {
    spamIsolationStoreSettings([
        'pluginName' => 'Comments',
        'recaptchaEnabled' => true,
        'akismetEnabled' => true,
    ]);

    $response = spamIsolationBrowser()->post('/admin/actions/comments/settings/save-settings', [
        'settings' => spamIsolationGeneralPane(['pluginName' => 'Comments Upgraded']),
    ]);

    expect($response['status'])->toBe(302, $response['body'])
        ->and(spamIsolationStored()['pluginName'] ?? null)->toBe('Comments Upgraded');
});
