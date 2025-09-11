<?php
namespace verbb\comments\helpers;

use verbb\comments\Comments;

use Craft;
use craft\elements\Asset;

use DateInterval;
use Throwable;

class CommentsHelper
{
    // Properties
    // =========================================================================

    private static array $_resolvedGravatars = [];


    // Static Methods
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

    public static function getAvatar($user = null): string|Asset
    {
        $settings = Comments::$plugin->getSettings();

        if ($user && $user->email && $settings->enableGravatar) {
            $url = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=64&d=404';

            // Only use the Gravatar if it doesn't throw a 404
            if (!self::_check404($url)) {
                return $url;
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

    private static function _assetExists($asset)
    {
        return $asset->getVolume()->getFs()->fileExists($asset->getPath());
    }

    private static function _check404(string $url): bool
    {
        // If we've already resolved this URL, return the cached result
        if (isset(self::$_resolvedGravatars[$url])) {
            return self::$_resolvedGravatars[$url];
        }

        // Perform a lightweight HEAD request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY        => true,  // Only headers
            CURLOPT_RETURNTRANSFER => true, // Don't output
            CURLOPT_FOLLOWLOCATION => true, // Follow redirects
            CURLOPT_TIMEOUT        => 5,    // Short timeout
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Store in cache for subsequent lookups in the same request
        self::$_resolvedGravatars[$url] = ($httpCode === 404);

        return self::$_resolvedGravatars[$url];
    }
}
