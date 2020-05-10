<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\queue\jobs\ResaveElements;

use yii\db\Expression;

class m200511_000000_resave_plugin_settings extends Migration
{
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.comments.schemaVersion', true);
        
        if (version_compare($schemaVersion, '1.1.5', '>=')) {
            return;
        }

        $plugin = Craft::$app->getPlugins()->getPlugin('comments');

        if ($plugin === null) {
            return;
        }

        $settings = $plugin->getSettings()->toArray();

        Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);
    }

    public function safeDown()
    {
        echo "m200511_000000_resave_plugin_settings cannot be reverted.\n";

        return false;
    }
}
