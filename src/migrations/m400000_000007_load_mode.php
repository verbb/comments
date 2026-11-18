<?php
namespace verbb\comments\migrations;

use verbb\comments\enums\LoadMode;

use Craft;
use craft\db\Migration;

/**
 * Development builds of Comments 4 had a `lazyLoad` switch, which is now `loadMode`. Comments 3 never had
 * either, so a site upgrading from it keeps the new default and is told about it in the Upgrade Report.
 */
class m400000_000007_load_mode extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Applying YAML from the environment that ran this already brings the new setting with it.
        if ($projectConfig->readOnly || version_compare($projectConfig->get('plugins.comments.schemaVersion', true) ?? '', '4.0.5', '>=')) {
            return true;
        }

        $settings = $projectConfig->get('plugins.comments.settings') ?? [];

        if (!array_key_exists('lazyLoad', $settings)) {
            return true;
        }

        // A `lazyLoad` in `config/comments.php` overrides the saved one, and `Settings` reads it from there,
        // so writing a `loadMode` from the saved value would quietly replace what the file asks for.
        $file = Craft::$app->getConfig()->getConfigFromFile('comments');
        $fileSetsLazyLoad = is_array($file) && array_key_exists('lazyLoad', $file);

        if (($settings['loadMode'] ?? null) === null && !$fileSetsLazyLoad) {
            $projectConfig->set('plugins.comments.settings.loadMode', LoadMode::fromLazyLoad($settings['lazyLoad'])->value);
        }

        // Craft doesn't save the removal of a value that's `false`, so the key is given another value first,
        // or a site that had lazy loading off would find `lazyLoad` back in its project config.
        $projectConfig->set('plugins.comments.settings.lazyLoad', 'removed');
        $projectConfig->remove('plugins.comments.settings.lazyLoad');

        return true;
    }

    public function safeDown(): bool
    {
        echo "m400000_000007_load_mode cannot be reverted.\n";

        return false;
    }
}
