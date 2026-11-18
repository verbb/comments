<?php
namespace verbb\comments\migrations\support;

use verbb\comments\elements\Comment;

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
            if (in_array($setting, self::SILENT, true) || in_array($setting, self::REPORTED_ELSEWHERE, true) || !array_key_exists($setting, $values)) {
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
        $values = self::currentValues();

        foreach (self::retiredInUse($values) as $setting) {
            $log->add('settings.retired.' . $setting, V3UpgradeLog::WARNING);
        }

        // Comments 3 put every comment in the page's HTML. Comments 4 loads them in the browser as a
        // visitor scrolls near, which changes what search engines that don't run scripts can read, so
        // a site hears about it and where to choose otherwise, unless it has already chosen.
        if (!array_key_exists('loadMode', $values)) {
            $log->add(self::LOAD_MODE_NOTICE, V3UpgradeLog::WARNING);
        }

        // Comments 3 took every custom field in the Comment layout from the site's form. Comments 4 takes
        // only those set to Collect or Collect and show, and none are until the site chooses them.
        if (self::customFieldCount() > 0) {
            $log->add(self::FORM_FIELDS_NOTICE, V3UpgradeLog::WARNING);
        }

        // Both carry over, but work differently: GIPHY's terms now have visitors search GIPHY themselves,
        // with the site's key in the page, and Gravatar is only used for confirmed addresses, served by the site.
        if (filter_var($values['giphyEnabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            $log->add(self::GIPHY_NOTICE, V3UpgradeLog::WARNING);
        }

        if (filter_var($values['enableGravatar'] ?? false, FILTER_VALIDATE_BOOL)) {
            $log->add(self::GRAVATAR_NOTICE, V3UpgradeLog::WARNING);
        }
    }

    /**
     * How many custom fields the Comment field layout has.
     */
    public static function customFieldCount(): int
    {
        return count(Craft::$app->getFields()->getLayoutByType(Comment::class)->getCustomFieldElements());
    }

    /** The site's Comments 3 settings, with `config/comments.php` over project config, as Craft reads them. */
    public static function currentValues(): array
    {
        return array_merge(
            Craft::$app->getProjectConfig()->get('plugins.comments.settings', true) ?? [],
            Craft::$app->getConfig()->getConfigFromFile('comments'),
        );
    }


    // Constants
    // =========================================================================

    public const KEPT = 'kept';
    public const TRANSLATED = 'translated';
    public const RETIRED = 'retired';
    public const LOAD_MODE_NOTICE = 'settings.loadMode';
    public const FORM_FIELDS_NOTICE = 'settings.formFields';
    public const GIPHY_NOTICE = 'settings.giphy';
    public const GRAVATAR_NOTICE = 'settings.gravatar';

    private const KEPT_SETTINGS = [
        'pluginName', 'closed', 'allowGuest', 'guestNotice', 'guestRequireEmailName', 'guestShowEmailName',
        'requireModeration', 'moderatorUserGroup', 'moderatorExcluded', 'autoCloseDays', 'maxReplyDepth',
        'maxUserComments', 'allowGuestVoting', 'allowFlagging', 'allowGuestFlagging', 'showAvatar',
        'placeholderAvatar', 'showTimeAgo', 'orderBy', 'outputDefaultCss', 'outputDefaultJs',
        'templateEmail', 'enableSpamChecks', 'securityMaxLength', 'securityFlooding',
        'securityModeration', 'securitySpamlist', 'securityBanned', 'securityMatchExact',
        'recaptchaKey', 'recaptchaSecret', 'recaptchaMinScore', 'useQueueForNotifications', 'permissions',
        'sortDefaultKey', 'sortDefaultDirection', 'enableGravatar', 'giphyEnabled', 'giphyApiKey', 'giphyRating',
        'giphyLimit', 'downvoteCommentLimit', 'showAuthorScore', 'flaggedCommentLimit',
    ];

    // Each of these is read once by `Settings` and written back in its v4 form.
    private const TRANSLATED_SETTINGS = [
        'allowVoting', 'notificationAuthorEnabled', 'notificationReplyEnabled', 'notificationSubscribeAuto',
        'notificationSubscribeDefault', 'notificationSubscribeEnabled', 'notificationSubscribeCommentEnabled',
        'notificationModeratorEnabled', 'notificationModeratorEditEnabled', 'notificationModeratorApprovedEnabled',
        'notificationAdminEnabled', 'notificationFlaggedEnabled', 'notificationAdmins', 'recaptchaEnabled',
    ];

    // With the value that means the setting wasn't being used.
    private const RETIRED_SETTINGS = [
        'structureUid' => null,
        'users' => null,
        'indexSidebarLimit' => null,
        'indexSidebarGroup' => null,
        'indexSidebarIndividualElements' => null,
        'defaultQueryStatus' => ['approved'],
        'hideVotingForThreshold' => false,
        'assetBaseUrl' => null,
        'assetBasePath' => null,
        'showCustomFieldNames' => false,
        'showCustomFieldInstructions' => false,
        // Threads are drawn with JavaScript, so there are no thread templates to override.
        'templateFolderOverride' => null,
    ];

    // These only ever affected how the site looked to the people running it, or were internal.
    private const SILENT = ['structureUid', 'users', 'indexSidebarLimit', 'indexSidebarGroup', 'indexSidebarIndividualElements'];

    // Reported by their own migration, which says more than that the setting has gone:
    // `m400000_000010_template_overrides` lists the overridden files.
    private const REPORTED_ELSEWHERE = ['templateFolderOverride'];
}
