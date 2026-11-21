<?php

declare(strict_types=1);

use craft\helpers\ProjectConfig as ProjectConfigHelper;
use verbb\comments\Comments;
use verbb\comments\models\Settings;

/**
 * Project config leaves out an empty list, so an emptied list setting with a non-empty default must still
 * read back empty after it's saved, rather than bringing its default back.
 */

/** Saves `$values` as the settings page would, and reads them back as a new request would. */
function emptyListsRoundTrip(array $values): array
{
    $plugin = Comments::$plugin;
    $stored = Craft::$app->getPlugins()->getStoredPluginInfo('comments')['settings'] ?? [];

    expect(Craft::$app->getPlugins()->savePluginSettings($plugin, array_replace($stored, $values)))->toBeTrue();

    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    Craft::$app->getProjectConfig()->reset();
    $saved = Craft::$app->getProjectConfig()->get('plugins.comments.settings') ?? [];

    // Craft unpacks rows such as report reasons when it loads plugin settings.
    return [$saved, new Settings(ProjectConfigHelper::unpackAssociativeArrays($saved))];
}

beforeEach(function() {
    $this->storedSettings = Craft::$app->getProjectConfig()->get('plugins.comments.settings') ?? [];
});

afterEach(function() {
    Craft::$app->getProjectConfig()->reset();
    Craft::$app->getProjectConfig()->set('plugins.comments.settings', $this->storedSettings);
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
});

it('keeps emptied participant notifications, report reasons and file types empty', function() {
    // Start from saved lists, so the empty ones below have something to replace.
    emptyListsRoundTrip([
        'participantNotifications' => [Settings::NOTIFICATION_OWNER],
        'reportReasons' => [['label' => 'Rude', 'description' => '', 'value' => 'rude']],
        'attachmentExtensions' => 'png',
    ]);

    // As the panes post them: an unticked checkbox group, a table with no rows, a cleared text input.
    [$saved, $settings] = emptyListsRoundTrip([
        'participantNotifications' => '',
        'reportReasons' => '',
        'attachmentExtensions' => '',
    ]);

    expect($saved)->toHaveKey('participantNotifications')
        ->and($saved)->toHaveKey('reportReasons')
        ->and($saved)->toHaveKey('attachmentExtensions')
        ->and($settings->participantNotifications)->toBe([])
        ->and($settings->reportReasons)->toBe([])
        ->and($settings->attachmentExtensions)->toBe([]);
});

it('saves non-empty lists as lists', function() {
    [$saved, $settings] = emptyListsRoundTrip([
        'participantNotifications' => [Settings::NOTIFICATION_OWNER, Settings::NOTIFICATION_APPROVED],
        'reportReasons' => [['label' => 'Rude', 'description' => '', 'value' => 'rude']],
        'attachmentExtensions' => 'png, .PDF',
    ]);

    expect($saved['participantNotifications'])->toBe([Settings::NOTIFICATION_OWNER, Settings::NOTIFICATION_APPROVED])
        ->and($saved['attachmentExtensions'])->toBe(['png', 'pdf'])
        ->and($settings->participantNotifications)->toBe([Settings::NOTIFICATION_OWNER, Settings::NOTIFICATION_APPROVED])
        ->and(array_column($settings->reportReasons, 'value'))->toBe(['rude'])
        ->and($settings->attachmentExtensions)->toBe(['png', 'pdf']);
});
