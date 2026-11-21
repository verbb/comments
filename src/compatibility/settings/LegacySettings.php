<?php
namespace verbb\comments\compatibility\settings;

use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\LoadMode;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\services\Compatibility;

use Closure;

/**
 * Comments 3's settings, and those from Comments 4's development builds, translated into today's when the
 * settings model is made. This runs whatever `compatibilityMode` is, because it's how an upgraded site's
 * saved settings carry over until they're next saved. A key that `config/comments.php` still sets is
 * logged as a deprecation, so the site can find it.
 */
trait LegacySettings
{
    // Constants
    // =========================================================================

    // Settings Comments 3 stored that have no meaning now. Project config can still hold them until the settings are next saved.
    private const RETIRED_SETTINGS = [
        'structureUid',
        'users',
        'defaultQueryStatus',
        'hideVotingForThreshold',
        'assetBaseUrl',
        'assetBasePath',
        'showCustomFieldNames',
        'showCustomFieldInstructions',
        'indexSidebarLimit',
        'indexSidebarGroup',
        'indexSidebarIndividualElements',
    ];

    // Comments 3's settings that are read as today's settings, and what each became.
    private const TRANSLATED_SETTINGS = [
        'allowVoting' => 'interactionMode',
        'notificationAuthorEnabled' => 'participantNotifications',
        'notificationReplyEnabled' => 'participantNotifications',
        'notificationMentionEnabled' => 'participantNotifications',
        'notificationModeratorApprovedEnabled' => 'participantNotifications',
        'notificationModeratorEnabled' => 'staffNotifications',
        'notificationModeratorEditEnabled' => 'staffNotifications',
        'notificationAdminEnabled' => 'staffNotifications',
        'notificationFlaggedEnabled' => 'staffNotifications',
        'notificationSubscribeAuto' => 'subscriptionDefault',
        'notificationSubscribeDefault' => 'subscriptionDefault',
        'notificationSubscribeEnabled' => 'subscriptionTypes',
        'notificationSubscribeCommentEnabled' => 'subscriptionTypes',
        'notificationAdmins' => 'notificationRecipients',
        'recaptchaEnabled' => 'challengeProvider',
        'turnstileEnabled' => 'challengeProvider',
        'akismetEnabled' => 'spamClassifier',
        'akismetFeedbackEnabled' => 'spamFeedbackEnabled',
    ];


    // Properties
    // =========================================================================

    // **Public Fields** from development builds of Comments 4, as handles, until it's next saved.
    private ?array $_legacyPublicFields = null;


    // Public Methods
    // =========================================================================

    /**
     * **Public Fields** from a development build of Comments 4, as field handles, when it's still what the
     * site has: in project config before `m400000_000009_form_fields` has run, or in `config/comments.php`.
     * `FormFields::settings()` reads each one as Collect, on comments and replies, which is what it did.
     */
    public function getLegacyPublicFields(): ?array
    {
        return $this->_legacyPublicFields;
    }

    /**
     * `publicFields` is read as Collect for each field it lists, in place of **Comment Form Fields**.
     */
    public function setPublicFields(mixed $handles): void
    {
        $this->_legacyPublicFields = self::_handles($handles);
        $this->formFields = [];
    }


    // Protected Methods
    // =========================================================================

    /**
     * Translates the legacy keys in a set of settings values, and removes them.
     */
    protected function normalizeLegacySettings(array $values): array
    {
        $this->_logLegacyConfigKeys();

        // Threads are drawn by the comments script, so there are no thread templates to override.
        // `m400000_000010_template_overrides` lists a site's overridden files in the Upgrade Report and
        // drops the saved setting, and a config file that still sets it is told so.
        if (array_key_exists('templateFolderOverride', $values)) {
            if (array_key_exists('templateFolderOverride', self::_configFile())) {
                Compatibility::deprecated('templateFolderOverride', 'The `templateFolderOverride` setting in `config/comments.php` has been removed. Comments draws every thread with JavaScript, so Twig templates for the thread aren’t used, and the upgrade guide explains where each change goes instead.');
            }

            unset($values['templateFolderOverride']);
        }

        foreach (self::RETIRED_SETTINGS as $retired) {
            unset($values[$retired]);
        }

        // v3 stored voting as a boolean. Translate it at the model boundary so only the
        // explicit v4 interaction mode is written back to project config.
        $hasInteractionMode = array_key_exists('interactionMode', $values)
            && $values['interactionMode'] !== null
            && $values['interactionMode'] !== '';

        // A config file overrides what's saved, so its `allowVoting` wins over a saved `interactionMode`
        // unless the file sets `interactionMode` too, as with `lazyLoad` below.
        $file = self::_configFile();
        $votingFromFile = ($file['allowVoting'] ?? null) !== null && !array_key_exists('interactionMode', $file);

        if (($votingFromFile || !$hasInteractionMode) && array_key_exists('allowVoting', $values)) {
            // v3 voting always offered both directions, so up/down is what preserves behaviour.
            $values['interactionMode'] = (bool)$values['allowVoting']
                ? InteractionMode::UpDown->value
                : InteractionMode::None->value;
        } elseif (!$hasInteractionMode) {
            unset($values['interactionMode']);
        }

        unset($values['allowVoting']);

        // Development builds of Comments 4 had a `lazyLoad` switch, which `m400000_000007_load_mode` turns
        // into `loadMode` in project config. A config file can still set it, and a file overrides what's
        // saved, so its `lazyLoad` wins over a saved `loadMode` unless the file sets `loadMode` too.
        if (array_key_exists('lazyLoad', $values)) {
            $file = self::_configFile();
            $fromFile = array_key_exists('lazyLoad', $file) && !array_key_exists('loadMode', $file);

            if ($fromFile || ($values['loadMode'] ?? null) === null || $values['loadMode'] === '') {
                $values['loadMode'] = LoadMode::fromLazyLoad($values['lazyLoad'])->value;
            }

            if ($fromFile) {
                Compatibility::deprecated('lazyLoad', 'The `lazyLoad` setting in `config/comments.php` has been replaced by `loadMode`, which takes `visible`, `button` or `eager`.');
            }

            unset($values['lazyLoad']);
        }

        if (($values['sortDefaultKey'] ?? null) === 'structure') {
            $values['sortDefaultKey'] = 'commentDate';
            $values['sortDefaultDirection'] = 'desc';
        }

        // Development builds of Comments 4 had **Public Fields**, a list of handles, which
        // `m400000_000009_form_fields` turns into Comment Form Fields set to Collect in project config. A
        // config file can still set it, and a file overrides what's saved, so its `publicFields` wins
        // unless the file sets `formFields` too.
        if (array_key_exists('publicFields', $values)) {
            $file = self::_configFile();
            $fromFile = array_key_exists('publicFields', $file) && !array_key_exists('formFields', $file);

            if ($fromFile || empty($values['formFields'])) {
                $this->_legacyPublicFields = self::_handles($values['publicFields']);
                unset($values['formFields']);
                $this->formFields = [];
            }

            if ($fromFile) {
                Compatibility::deprecated('publicFields', 'The `publicFields` setting in `config/comments.php` has been replaced by `formFields`, which sets each field to Collect or Collect and show. Each field it lists is collected, and not shown.');
            }

            unset($values['publicFields']);
        } elseif (array_key_exists('formFields', $values)) {
            $this->_legacyPublicFields = null;
        }

        $values = $this->_normalizeLegacyNotifications($values);

        // Comments 3's `recaptchaEnabled`, and development builds' `turnstileEnabled`, became one choice of
        // challenge provider. Turnstile wins where a development build had both on.
        if (!array_key_exists('challengeProvider', $values) && (array_key_exists('recaptchaEnabled', $values) || array_key_exists('turnstileEnabled', $values))) {
            $values['challengeProvider'] = match (true) {
                self::_flag($values['turnstileEnabled'] ?? false) => self::CHALLENGE_TURNSTILE,
                self::_flag($values['recaptchaEnabled'] ?? false) => self::CHALLENGE_RECAPTCHA,
                default => self::CHALLENGE_NONE,
            };
        }

        unset($values['recaptchaEnabled'], $values['turnstileEnabled']);

        // Development builds had Akismet as the only classifier, with its own switches.
        if (!array_key_exists('spamClassifier', $values) && array_key_exists('akismetEnabled', $values)) {
            $values['spamClassifier'] = self::_flag($values['akismetEnabled']) ? self::CLASSIFIER_AKISMET : self::CLASSIFIER_NONE;
        }

        if (!array_key_exists('spamFeedbackEnabled', $values) && array_key_exists('akismetFeedbackEnabled', $values)) {
            $values['spamFeedbackEnabled'] = self::_flag($values['akismetFeedbackEnabled']);
        }

        unset($values['akismetEnabled'], $values['akismetFeedbackEnabled']);

        // Settings from Comments 4's development: the staff settings replaced the first, and the second
        // checked a flag any client can send.
        unset($values['emailModeratorGroup'], $values['javascriptCheckEnabled']);

        return $values;
    }


    // Private Methods
    // =========================================================================

    /**
     * Logs each retired or translated key `config/comments.php` still sets. Saved settings don't need it,
     * because saving them on the settings page writes only today's keys.
     */
    private function _logLegacyConfigKeys(): void
    {
        $file = self::_configFile();

        foreach (self::RETIRED_SETTINGS as $key) {
            if (array_key_exists($key, $file)) {
                Compatibility::deprecated("settings.{$key}", "The `{$key}` setting in `config/comments.php` has been removed and is ignored. Remove it from the file.");
            }
        }

        foreach (self::TRANSLATED_SETTINGS as $key => $replacement) {
            if (array_key_exists($key, $file) && !array_key_exists($replacement, $file)) {
                Compatibility::deprecated("settings.{$key}", "The `{$key}` setting in `config/comments.php` has been replaced by `{$replacement}`. It's still read for now, so set `{$replacement}` in its place.");
            }
        }
    }

    /**
     * Translate v3 project-config keys once at the model boundary. Runtime code only uses the
     * canonical v4 event lists, so saving settings naturally removes the legacy boolean matrix.
     */
    private function _normalizeLegacyNotifications(array $values): array
    {
        $legacyKeys = [
            'notificationAuthorEnabled',
            'notificationReplyEnabled',
            'notificationMentionEnabled',
            'notificationSubscribeAuto',
            'notificationSubscribeDefault',
            'notificationSubscribeEnabled',
            'notificationSubscribeCommentEnabled',
            'notificationModeratorEnabled',
            'notificationModeratorEditEnabled',
            'notificationModeratorApprovedEnabled',
            'notificationAdmins',
            'notificationAdminEnabled',
            'notificationFlaggedEnabled',
        ];

        $hasLegacyValues = array_intersect($legacyKeys, array_keys($values)) !== [];

        if ($hasLegacyValues && !array_key_exists('participantNotifications', $values)) {
            $participantNotifications = [];

            if ((bool)($values['notificationAuthorEnabled'] ?? true)) {
                $participantNotifications[] = self::NOTIFICATION_OWNER;
            }

            if ((bool)($values['notificationReplyEnabled'] ?? true)) {
                $participantNotifications[] = self::NOTIFICATION_REPLY;
            }

            if ((bool)($values['notificationMentionEnabled'] ?? true)) {
                $participantNotifications[] = self::NOTIFICATION_MENTION;
            }

            if ((bool)($values['notificationModeratorApprovedEnabled'] ?? false)) {
                $participantNotifications[] = self::NOTIFICATION_APPROVED;
            }

            $values['participantNotifications'] = $participantNotifications;
        }

        if ($hasLegacyValues && !array_key_exists('staffNotifications', $values)) {
            $staffNotifications = [];

            if ((bool)($values['notificationModeratorEnabled'] ?? false)) {
                $staffNotifications[] = self::NOTIFICATION_MODERATOR_PENDING;
            }

            if ((bool)($values['notificationModeratorEditEnabled'] ?? false)) {
                $staffNotifications[] = self::NOTIFICATION_MODERATOR_EDITED;
            }

            if ((bool)($values['notificationAdminEnabled'] ?? false)) {
                $staffNotifications[] = self::NOTIFICATION_STAFF_NEW;
            }

            if ((bool)($values['notificationFlaggedEnabled'] ?? false)) {
                $staffNotifications[] = self::NOTIFICATION_STAFF_REPORTED;
            }

            $values['staffNotifications'] = $staffNotifications;
        }

        if ($hasLegacyValues && !array_key_exists('subscriptionTypes', $values)) {
            $subscriptionTypes = [];
            $conversationEnabled = (bool)($values['notificationSubscribeEnabled'] ?? false)
                || (bool)($values['notificationSubscribeAuto'] ?? false);
            $repliesEnabled = (bool)($values['notificationSubscribeCommentEnabled'] ?? false)
                || ((bool)($values['notificationSubscribeDefault'] ?? false) && $conversationEnabled);

            if ($conversationEnabled) {
                $subscriptionTypes[] = SubscriptionType::Conversation->value;
            }

            if ($repliesEnabled) {
                $subscriptionTypes[] = SubscriptionType::Replies->value;
            }

            $values['subscriptionTypes'] = $subscriptionTypes;
        }

        if ($hasLegacyValues && !array_key_exists('subscriptionDefault', $values)) {
            if ((bool)($values['notificationSubscribeAuto'] ?? false)) {
                $values['subscriptionDefault'] = self::SUBSCRIPTION_DEFAULT_CONVERSATION;
            } elseif ((bool)($values['notificationSubscribeDefault'] ?? false)
                && in_array(SubscriptionType::Replies->value, $values['subscriptionTypes'] ?? [], true)
            ) {
                $values['subscriptionDefault'] = self::SUBSCRIPTION_DEFAULT_REPLIES;
            } else {
                $values['subscriptionDefault'] = self::SUBSCRIPTION_DEFAULT_NONE;
            }
        }

        if (!array_key_exists('notificationRecipients', $values)
            && (is_array($values['notificationAdmins'] ?? null) || ($values['notificationAdmins'] ?? null) instanceof Closure)
        ) {
            $values['notificationRecipients'] = $values['notificationAdmins'];
        }

        foreach ($legacyKeys as $legacyKey) {
            unset($values[$legacyKey]);
        }

        return $values;
    }
}
