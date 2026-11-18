<?php
namespace verbb\comments\services;

use verbb\comments\models\Settings;
use verbb\comments\records\SpamSettings as SpamSettingsRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Json;

/**
 * The Spam settings live in this installation's database, not project config, so anyone with the
 * **Manage spam settings** permission can change them on production, where admin changes are usually
 * off. Each environment keeps its own. `config/comments.php` still wins over what's saved, so a
 * developer can fix a setting in place.
 */
class SpamSettings extends Component
{
    // Constants
    // =========================================================================

    public const TABLE = '{{%comments_spamsettings}}';

    // What a saved secret shows as. Posting it back keeps the saved value.
    public const SECRET_MASK = '••••••••';

    public const KEYS = [
        'enableSpamChecks',
        'honeypotEnabled',
        'minimumSubmitTime',
        'replayProtectionEnabled',
        'securityFlooding',
        'rateLimitEnabled',
        'rateLimitCount',
        'rateLimitWindow',
        'challengeProvider',
        'turnstileKey',
        'turnstileSecret',
        'recaptchaKey',
        'recaptchaSecret',
        'recaptchaMinScore',
        'hcaptchaKey',
        'hcaptchaSecret',
        'friendlyCaptchaKey',
        'friendlyCaptchaSecret',
        'friendlyCaptchaEndpoint',
        'challengeAuthenticated',
        'challengeSendIp',
        'spamClassifier',
        'akismetApiKey',
        'akismetBlogUrl',
        'akismetTestMode',
        'cleantalkApiKey',
        'oopspamApiKey',
        'oopspamSpamThreshold',
        'spamFeedbackEnabled',
        'saveSpam',
        'spamRetentionDays',
        'protectionFailClosed',
        'securityModeration',
        'securitySpamlist',
        'securityBanned',
        'securityMatchExact',
        'maxLinks',
        'blockedEmailDomains',
    ];

    // Shown masked when saved as plain text, so people who manage spam settings can replace them
    // without reading them. An environment variable reference is shown as it is.
    public const SECRET_KEYS = [
        'turnstileSecret',
        'recaptchaSecret',
        'hcaptchaSecret',
        'friendlyCaptchaSecret',
        'akismetApiKey',
        'cleantalkApiKey',
        'oopspamApiKey',
    ];


    // Properties
    // =========================================================================

    private array|false|null $_stored = null;


    // Public Methods
    // =========================================================================

    /**
     * Puts the saved Spam settings on the plugin's settings, over anything project config still holds.
     * A key `config/comments.php` sets is left as the file has it, and a key with nothing saved goes
     * back to its default.
     */
    public function hydrate(Settings $settings): void
    {
        $stored = $this->getStoredValues() ?? [];
        $file = self::_configFile();
        $defaults = new Settings();
        $values = [];

        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $file)) {
                $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : $defaults->$key;
            }
        }

        $settings->setAttributes($values, false);
    }

    /**
     * What's saved, or null before anything has been.
     */
    public function getStoredValues(): ?array
    {
        if ($this->_stored === null) {
            $this->_stored = false;

            if (Craft::$app->getDb()->tableExists(self::TABLE)) {
                $json = (new Query())->select(['settings'])->from(self::TABLE)->orderBy(['id' => SORT_ASC])->scalar();

                if (is_string($json)) {
                    $decoded = Json::decodeIfJson($json);
                    $this->_stored = is_array($decoded) ? $decoded : false;
                }
            }
        }

        return $this->_stored ?: null;
    }

    /**
     * Applies posted Spam settings to `$settings`, keeping a saved secret whose mask came back.
     */
    public function applyPosted(Settings $settings, array $posted): void
    {
        $values = array_intersect_key($posted, array_flip(self::KEYS));

        foreach (self::SECRET_KEYS as $key) {
            if (($values[$key] ?? null) === self::SECRET_MASK) {
                unset($values[$key]);
            }
        }

        $settings->setAttributes($values, false);
    }

    /**
     * Saves the Spam settings from `$settings`. Keys `config/comments.php` sets keep what was saved
     * before, so taking the line out of the file brings that back.
     */
    public function save(Settings $settings): bool
    {
        if (!$settings->validate(self::KEYS)) {
            return false;
        }

        $file = self::_configFile();
        $values = $this->getStoredValues() ?? [];

        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $file)) {
                $values[$key] = $settings->$key;
            }
        }

        $this->saveValues($values);

        return true;
    }

    /**
     * Writes `$values` as the saved Spam settings, replacing what was there.
     */
    public function saveValues(array $values): void
    {
        $values = array_intersect_key($values, array_flip(self::KEYS));
        $record = SpamSettingsRecord::find()->orderBy(['id' => SORT_ASC])->one() ?? new SpamSettingsRecord();
        $record->settings = Json::encode($values);
        $record->save(false);

        $this->_stored = null;
    }

    /**
     * How a setting appears on the Spam settings page: a secret saved as plain text shows as a mask.
     */
    public function displayValue(Settings $settings, string $key): mixed
    {
        $value = $settings->$key;

        if (in_array($key, self::SECRET_KEYS, true) && is_string($value) && trim($value) !== '' && !str_starts_with(trim($value), '$')) {
            return self::SECRET_MASK;
        }

        return $value;
    }

    public function canManage(): bool
    {
        $user = Craft::$app->getUser();

        return $user->getIsAdmin() || $user->checkPermission('comments-manageSpamSettings');
    }


    // Private Methods
    // =========================================================================

    private static function _configFile(): array
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('comments');

        return is_array($config) ? $config : [];
    }
}
