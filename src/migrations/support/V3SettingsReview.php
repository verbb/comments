<?php
namespace verbb\comments\migrations\support;

use Craft;

/**
 * What became of each of the 70 settings Comments 3 had.
 *
 * Settings that carried over or were translated keep working by themselves. A retired one set to
 * something other than its default is recorded, because behaviour the site asked for has gone. Only the
 * setting's name is recorded, and never its value, because some of these hold API keys.
 */
final class V3SettingsReview
{
    // Static Methods
    // =========================================================================

    public static function dispositions(): array
    {
        return array_fill_keys(self::KEPT_SETTINGS, self::KEPT)
            + array_fill_keys(self::TRANSLATED_SETTINGS, self::TRANSLATED)
            + array_fill_keys(array_keys(self::RETIRED_SETTINGS), self::RETIRED);
    }

    /** The retired settings this site was using. */
    public static function retiredInUse(array $values): array
    {
        $inUse = [];

        foreach (self::RETIRED_SETTINGS as $setting => $unused) {
            if (in_array($setting, self::SILENT, true) || !array_key_exists($setting, $values)) {
                continue;
            }

            $value = $values[$setting];

            if ($value !== null && $value !== '' && $value != $unused) {
                $inUse[] = $setting;
            }
        }

        return $inUse;
    }

    public static function record(V3UpgradeLog $log): void
    {
        $values = array_merge(
            Craft::$app->getProjectConfig()->get('plugins.comments.settings', true) ?? [],
            Craft::$app->getConfig()->getConfigFromFile('comments'),
        );

        foreach (self::retiredInUse($values) as $setting) {
            $log->add('settings.retired.' . $setting, V3UpgradeLog::WARNING);
        }
    }


    // Constants
    // =========================================================================

    public const KEPT = 'kept';
    public const TRANSLATED = 'translated';
    public const RETIRED = 'retired';

    private const KEPT_SETTINGS = [
        'pluginName', 'closed', 'allowGuest', 'guestNotice', 'guestRequireEmailName', 'guestShowEmailName',
        'requireModeration', 'moderatorUserGroup', 'moderatorExcluded', 'autoCloseDays', 'maxReplyDepth',
        'maxUserComments', 'allowGuestVoting', 'allowFlagging', 'allowGuestFlagging', 'showAvatar',
        'placeholderAvatar', 'showTimeAgo', 'orderBy', 'outputDefaultCss', 'outputDefaultJs',
        'templateFolderOverride', 'templateEmail', 'enableSpamChecks', 'securityMaxLength', 'securityFlooding',
        'securityModeration', 'securitySpamlist', 'securityBanned', 'securityMatchExact', 'recaptchaEnabled',
        'recaptchaKey', 'recaptchaSecret', 'recaptchaMinScore', 'useQueueForNotifications', 'permissions',
        'sortDefaultKey', 'sortDefaultDirection',
    ];

    // Each of these is read once by `Settings` and written back in its v4 form.
    private const TRANSLATED_SETTINGS = [
        'allowVoting', 'notificationAuthorEnabled', 'notificationReplyEnabled', 'notificationSubscribeAuto',
        'notificationSubscribeDefault', 'notificationSubscribeEnabled', 'notificationSubscribeCommentEnabled',
        'notificationModeratorEnabled', 'notificationModeratorEditEnabled', 'notificationModeratorApprovedEnabled',
        'notificationAdminEnabled', 'notificationFlaggedEnabled',
    ];

    // With the value that means the setting wasn't being used.
    private const RETIRED_SETTINGS = [
        'structureUid' => null,
        'users' => null,
        'indexSidebarLimit' => null,
        'indexSidebarGroup' => null,
        'indexSidebarIndividualElements' => null,
        'defaultQueryStatus' => ['approved'],
        'downvoteCommentLimit' => 5,
        'hideVotingForThreshold' => false,
        'showAuthorScore' => true,
        'flaggedCommentLimit' => 5,
        'enableGravatar' => false,
        'assetBaseUrl' => null,
        'assetBasePath' => null,
        'giphyEnabled' => false,
        'giphyApiKey' => null,
        'giphyRating' => 'g',
        'giphyLimit' => 24,
        'showCustomFieldNames' => false,
        'showCustomFieldInstructions' => false,
        // Staff are told through an event rather than emailed.
        'notificationAdmins' => [],
    ];

    // These only ever affected how the site looked to the people running it, or were internal.
    private const SILENT = ['structureUid', 'users', 'indexSidebarLimit', 'indexSidebarGroup', 'indexSidebarIndividualElements'];
}
