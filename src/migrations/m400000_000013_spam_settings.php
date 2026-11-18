<?php
namespace verbb\comments\migrations;

use verbb\comments\models\Settings;
use verbb\comments\services\SpamSettings;

use Craft;
use craft\db\Migration;
use craft\helpers\Json;

/**
 * The Spam settings move out of project config into this installation's database, so they can be
 * changed on production. Each environment starts with what its project config held. Project config
 * isn't written here, and its leftover keys are ignored, then dropped the next time settings are saved.
 *
 * Fresh installs create the table through `Install`.
 */
class m400000_000013_spam_settings extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createSchema();

        if ((int)$this->db->createCommand('SELECT COUNT(*) FROM ' . $this->db->quoteTableName(SpamSettings::TABLE))->queryScalar() > 0) {
            return true;
        }

        $saved = Craft::$app->getProjectConfig()->get('plugins.comments.settings') ?? [];

        if (!is_array($saved)) {
            return true;
        }

        // Read through the model, so Comments 3's names become today's.
        $settings = new Settings($saved);
        $values = [];

        foreach (SpamSettings::KEYS as $key) {
            $legacy = match ($key) {
                'challengeProvider' => array_key_exists('recaptchaEnabled', $saved) || array_key_exists('turnstileEnabled', $saved),
                'spamClassifier' => array_key_exists('akismetEnabled', $saved),
                'spamFeedbackEnabled' => array_key_exists('akismetFeedbackEnabled', $saved),
                default => false,
            };

            if (array_key_exists($key, $saved) || $legacy) {
                $values[$key] = $settings->$key;
            }
        }

        $this->insert(SpamSettings::TABLE, ['settings' => Json::encode($values)]);

        return true;
    }

    /**
     * The table, which `Install` creates too.
     */
    public function createSchema(): void
    {
        if (!$this->db->tableExists(SpamSettings::TABLE)) {
            $this->createTable(SpamSettings::TABLE, [
                'id' => $this->primaryKey(),
                'settings' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(SpamSettings::TABLE);

        return true;
    }
}
