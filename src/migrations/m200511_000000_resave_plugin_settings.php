<?php
namespace verbb\comments\migrations;

use Craft;
use craft\db\Migration;

class m200511_000000_resave_plugin_settings extends Migration
{
    public function safeUp(): bool
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.comments.schemaVersion', true);
        
        if (version_compare($schemaVersion, '1.1.5', '>=')) {
            return true;
        }

        $plugin = Craft::$app->getPlugins()->getPlugin('comments');

        if ($plugin === null) {
            return true;
        }

        $settings = $plugin->getSettings()->toArray();

        Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200511_000000_resave_plugin_settings cannot be reverted.\n";

        return false;
    }
}
