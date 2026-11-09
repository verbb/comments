<?php
namespace verbb\comments\helpers;

use verbb\comments\Comments;

use Craft;
use craft\elements\Asset;
use craft\elements\User;

use DateInterval;

class CommentsHelper
{
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

    /**
     * With `useEmailAsUsername` a user with no full name would otherwise be shown by their email address.
     */
    public static function publicUserName(User $user): string
    {
        $name = (string)$user->getName();

        return $name !== '' && strcasecmp($name, (string)$user->email) !== 0 ? $name : Craft::t('comments', 'Member');
    }

    /**
     * D1: commenters' addresses are admin-only, including as a search term, a filter or a sort.
     * Anyone else, a visitor or a console request with no user included, gets nothing that would
     * reveal one.
     */
    public static function canViewAddresses(): bool
    {
        $viewer = Craft::$app->getUser()->getIdentity();

        return $viewer instanceof User && $viewer->admin;
    }

    public static function getAvatar($user = null): string|Asset
    {
        $settings = Comments::$plugin->getSettings();

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

    private static function _assetExists($asset)
    {
        return $asset->getVolume()->getFs()->fileExists($asset->getPath());
    }
}
