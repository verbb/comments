<?php
namespace verbb\comments\helpers;

use verbb\comments\Comments;

use Craft;
use craft\elements\Asset;

use DateInterval;
use Throwable;

class CommentsHelper
{
    // Static Methods
    // =========================================================================

    public static function humanDurationFromInterval(DateInterval $dateInterval): string
    {
        if ($dateInterval->y) {
            return $dateInterval->y == 1 ? self::_translate('1 year ago') : self::_translate('{num} years ago', ['num' => $dateInterval->y]);
        }

        if ($dateInterval->m) {
            return $dateInterval->m == 1 ? self::_translate('1 month ago') : self::_translate('{num} months ago', ['num' => $dateInterval->m]);
        }

        if ($dateInterval->d) {
            return $dateInterval->d == 1 ? self::_translate('1 day ago') : self::_translate('{num} days ago', ['num' => $dateInterval->d]);
        }

        if ($dateInterval->h) {
            return $dateInterval->h == 1 ? self::_translate('1 hour ago') : self::_translate('{num} hours ago', ['num' => $dateInterval->h]);
        }

        if ($dateInterval->i) {
            return $dateInterval->i == 1 ? self::_translate('1 minute ago') : self::_translate('{num} minutes ago', ['num' => $dateInterval->i]);
        }

        if ($dateInterval->s) {
            return $dateInterval->s == 1 ? self::_translate('1 second ago') : self::_translate('{num} seconds ago', ['num' => $dateInterval->s]);
        }

        return '';
    }

    public static function getAvatar($user = null): string|Asset
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->enableGravatar) {
            try {
                $gravatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=64&d=404';

                // Try to fetch the gravatar. It'll throw an error if not found, so we fall back.
                file_get_contents($gravatar);

                return $gravatar;
            } catch (Throwable $e) {
            }
        }

        if ($user && $photo = $user->getPhoto()) {
            if (self::_assetExists($photo)) {
                return $photo;
            }
        }

        if ($settings->getPlaceholderAvatar()) {
            if (self::_assetExists($settings->getPlaceholderAvatar())) {
                return $settings->getPlaceholderAvatar();
            }
        }

        return '';
    }


    // Private Methods
    // =========================================================================

    private static function _translate(string $message, array $params = []): string
    {
        // Craft has no translations for these strings, so use the plugin's own. Sites that already override them
        // in their `app` translations keep that override.
        $appTranslation = Craft::$app->getI18n()->getMessageSource('app')->translate('app', $message, Craft::$app->language);

        return Craft::t($appTranslation !== false ? 'app' : 'comments', $message, $params);
    }

    private static function _assetExists($asset)
    {
        return $asset->getVolume()->getFs()->fileExists($asset->getPath());
    }
}
