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

    /**
     * How long ago something happened, such as "3 hours ago", in the language of the current site.
     * Craft's formatter does the wording, which Yii translates. The interval is read as a time in
     * the past whichever way round it was worked out, so a clock slightly ahead never says "in 2 seconds".
     */
    public static function humanDurationFromInterval(DateInterval $dateInterval): string
    {
        $interval = clone $dateInterval;
        $interval->invert = 0;

        return Craft::$app->getFormatter()->asRelativeTime($interval);
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

        // An admin, or a GraphQL request on a private schema allowed to read addresses (SD-57).
        return ($viewer instanceof User && $viewer->admin) || Gql::canReadCommentEmails();
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
