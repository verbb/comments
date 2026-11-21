<?php

declare(strict_types=1);

use Tests\Support\V3Site;
use verbb\comments\migrations\m400000_000004_cleanup;
use verbb\comments\models\Settings;

/**
 * Comments 3 had up and down voting unless a site turned it off. An upgraded site that never saved
 * `allowVoting` keeps up and down voting, rather than taking new installs' Like.
 */

/**
 * Run the cleanup migration's project config step against these Comments 3 settings, and give back what
 * it saved and the interaction mode a settings model reads from it, with `config/comments.php` holding
 * `$configFile`. Project config is put back as it was.
 */
function votingDefaultUpgrade(array $settings, array $configFile = []): array
{
    $projectConfig = Craft::$app->getProjectConfig();
    $originalSettings = $projectConfig->get('plugins.comments.settings');
    $configPath = Craft::$app->getConfig()->getConfigFilePath('comments');

    if (file_exists($configPath)) {
        throw new RuntimeException('The test app already has a config/comments.php.');
    }

    try {
        $projectConfig->remove('plugins.comments.settings');
        $projectConfig->set('plugins.comments.settings', ['structureUid' => '11111111-2222-4333-8444-555555555555'] + $settings);
        V3Site::writeProjectConfig();

        $step = new ReflectionMethod(m400000_000004_cleanup::class, '_updateProjectConfig');
        $step->invoke(new m400000_000004_cleanup(['db' => Craft::$app->getDb()]));
        V3Site::writeProjectConfig();

        $saved = $projectConfig->get('plugins.comments.settings') ?? [];

        if ($configFile !== []) {
            file_put_contents($configPath, '<?php return ' . var_export($configFile, true) . ';');
        }

        // As Craft loads plugin settings: what's saved, with the config file over it.
        return [$saved, (new Settings(array_merge($saved, $configFile)))->interactionMode];
    } finally {
        if ($configFile !== []) {
            @unlink($configPath);
        }

        // Craft doesn't save the removal of a `false`, so each key gets another value before it goes.
        foreach (array_keys($projectConfig->get('plugins.comments.settings') ?? []) as $key) {
            $projectConfig->set("plugins.comments.settings.{$key}", 'removed');
            $projectConfig->remove("plugins.comments.settings.{$key}");
        }

        $projectConfig->remove('plugins.comments.settings');

        if ($originalSettings !== null) {
            $projectConfig->set('plugins.comments.settings', $originalSettings, null, true, true);
        }

        V3Site::writeProjectConfig();
    }
}

it('keeps up and down voting for a Comments 3 site that never saved its settings', function() {
    [$saved, $mode] = votingDefaultUpgrade([]);

    expect($saved['interactionMode'] ?? null)->toBe('updown')
        ->and($mode)->toBe('updown');
});

it('leaves a saved voting choice or interaction mode as it was', function() {
    [$saved, $mode] = votingDefaultUpgrade(['allowVoting' => false]);

    expect($saved)->not->toHaveKey('interactionMode')
        ->and($mode)->toBe('none');

    [$saved, $mode] = votingDefaultUpgrade(['interactionMode' => 'like']);

    expect($saved['interactionMode'])->toBe('like')
        ->and($mode)->toBe('like');
});

it('lets a config file’s allowVoting win over the recorded mode, unless it sets interactionMode too', function() {
    [, $mode] = votingDefaultUpgrade([], ['allowVoting' => false]);

    expect($mode)->toBe('none');

    // A mode saved since, from the settings page, still gives way to the file.
    [, $mode] = votingDefaultUpgrade(['interactionMode' => 'updown'], ['allowVoting' => false]);

    expect($mode)->toBe('none');

    [, $mode] = votingDefaultUpgrade([], ['allowVoting' => false, 'interactionMode' => 'like']);

    expect($mode)->toBe('like');
});
