<?php
namespace Tests\Support;

use verbb\comments\Comments;
use verbb\comments\services\SpamSettings;

use Craft;
use craft\db\Query;

/**
 * The web app reads the Spam settings from the database, not project config, so tests that set them
 * for requests over HTTP write them here as well.
 */
final class SpamSettingsStore
{
    // Static Methods
    // =========================================================================

    /**
     * What's saved now, to hand back to `restore()`.
     */
    public static function snapshot(): ?string
    {
        $json = (new Query())->select(['settings'])->from(SpamSettings::TABLE)->orderBy(['id' => SORT_ASC])->scalar();

        return is_string($json) ? $json : null;
    }

    /**
     * Saves the Spam settings among `$settings` over what's saved. Others are ignored.
     */
    public static function apply(array $settings): void
    {
        $values = array_intersect_key($settings, array_flip(SpamSettings::KEYS));

        if ($values === []) {
            return;
        }

        $service = Comments::$plugin->getSpamSettings();
        self::_reset();
        $service->saveValues(array_merge($service->getStoredValues() ?? [], $values));
    }

    public static function restore(?string $snapshot): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand()->delete(SpamSettings::TABLE)->execute();

        if ($snapshot !== null) {
            $db->createCommand()->insert(SpamSettings::TABLE, ['settings' => $snapshot])->execute();
        }

        self::_reset();
    }


    // Private Methods
    // =========================================================================

    private static function _reset(): void
    {
        (new \ReflectionProperty(SpamSettings::class, '_stored'))->setValue(Comments::$plugin->getSpamSettings(), null);
    }
}
