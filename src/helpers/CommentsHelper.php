<?php
namespace verbb\comments\helpers;

use verbb\comments\Comments;

use Craft;
use craft\helpers\FileHelper;
use craft\i18n\Locale;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;

class CommentsHelper
{
    // Public Methods
    // =========================================================================

    public static function humanDurationFromInterval(DateInterval $dateInterval): string
    {
        if ($dateInterval->y) {
            return $dateInterval->y == 1 ? Craft::t('app', '1 year ago') : Craft::t('app', '{num} years ago', ['num' => $dateInterval->y]);
        }

        if ($dateInterval->m) {
            return $dateInterval->m == 1 ? Craft::t('app', '1 month ago') : Craft::t('app', '{num} months ago', ['num' => $dateInterval->m]);
        }

        if ($dateInterval->d) {
            return $dateInterval->d == 1 ? Craft::t('app', '1 day ago') : Craft::t('app', '{num} days ago', ['num' => $dateInterval->d]);
        }

        if ($dateInterval->h) {
            return $dateInterval->h == 1 ? Craft::t('app', '1 hour ago') : Craft::t('app', '{num} hours ago', ['num' => $dateInterval->h]);
        }

        if ($dateInterval->i) {
            return $dateInterval->i == 1 ? Craft::t('app', '1 minute ago') : Craft::t('app', '{num} minutes ago', ['num' => $dateInterval->i]);
        }

        if ($dateInterval->s) {
            return $dateInterval->s == 1 ? Craft::t('app', '1 second ago') : Craft::t('app', '{num} seconds ago', ['num' => $dateInterval->s]);
        }

        return '';
    }

    public static function getAvatar($user = null)
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->enableGravatar) {
            try {
                $gravatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=64&d=404';

                // Try to fetch the gravatar. It'll throw an error if not found, so we fall back.
                file_get_contents($gravatar);

                return $gravatar;
            } catch (\Throwable $e) {}
        }

        if ($user) {
            if ($photo = $user->getPhoto()) {
                if (self::_assetExists($photo)) {
                    return $photo;
                }
            }
        }

        if ($settings->getPlaceholderAvatar()) {
            if (self::_assetExists($settings->getPlaceholderAvatar())) {
                return $settings->getPlaceholderAvatar();
            }
        }

        return null;
    }


    // Private Methods
    // =========================================================================

    private static function _assetExists($asset)
    {
        return $asset->getVolume()->fileExists($asset->getPath());
    }
}
