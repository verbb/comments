<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\CommentStatus;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\SubscriptionType;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;

class Settings extends Model
{
    // Constants
    // =========================================================================

    public const NOTIFICATION_OWNER = 'owner';
    public const NOTIFICATION_REPLY = 'reply';
    public const NOTIFICATION_MENTION = 'mention';
    public const NOTIFICATION_APPROVED = 'approved';
    public const NOTIFICATION_MODERATOR_PENDING = 'moderator-pending';
    public const NOTIFICATION_MODERATOR_EDITED = 'moderator-edited';
    public const NOTIFICATION_STAFF_NEW = 'staff-new';
    public const NOTIFICATION_STAFF_REPORTED = 'staff-reported';

    public const SUBSCRIPTION_DEFAULT_NONE = 'none';
    public const SUBSCRIPTION_DEFAULT_REPLIES = 'replies';
    public const SUBSCRIPTION_DEFAULT_CONVERSATION = 'conversation';

    // Settings Comments 3 stored that have no meaning now. Project config can still hold them until the settings are next saved.
    private const RETIRED_SETTINGS = [
        'structureUid',
        'users',
        'defaultQueryStatus',
        'downvoteCommentLimit',
        'hideVotingForThreshold',
        'showAuthorScore',
        'flaggedCommentLimit',
        'enableGravatar',
        'assetBaseUrl',
        'assetBasePath',
        'giphyEnabled',
        'giphyApiKey',
        'giphyRating',
        'giphyLimit',
        'showCustomFieldNames',
        'showCustomFieldInstructions',
        'indexSidebarLimit',
        'indexSidebarGroup',
        'indexSidebarIndividualElements',
    ];


    // Properties
    // =========================================================================

    public string $pluginName = 'Comments';
    public bool $closed = false;

    // General
    public bool $allowGuest = false;
    public ?string $guestNotice = null;
    public bool $guestRequireEmailName = true;
    public bool $guestShowEmailName = true;
    public bool $requireModeration = true;
    public ?string $moderatorUserGroup = null;
    public bool $moderatorExcluded = true;
    public mixed $autoCloseDays = null;
    public mixed $maxReplyDepth = null;
    public mixed $maxUserComments = null;
    public mixed $authorEditWindow = null;

    // Interactions
    public string $interactionMode = InteractionMode::Like->value;
    public bool $allowGuestVoting = false;

    // Flagging
    public bool $allowFlagging = true;
    public bool $allowGuestFlagging = false;
    public array $reportReasons = [
        ['value' => 'spam', 'label' => 'Spam', 'description' => 'Ads, scams or links that don’t belong'],
        ['value' => 'harassment', 'label' => 'Harassment or abuse', 'description' => 'Attacks or insults aimed at a person'],
        ['value' => 'off-topic', 'label' => 'Off-topic', 'description' => 'Not about this post'],
        ['value' => 'other', 'label' => 'Other', 'description' => 'Tell the moderators below'],
    ];

    // Templates - Default
    public bool $showAvatar = true;
    public mixed $placeholderAvatar = null;
    public bool $showTimeAgo = true;
    public ?string $ownerBadgeLabel = 'Author';
    public ?string $staffBadgeLabel = null;
    public array $staffBadgeGroups = [];
    public string $orderBy = 'desc';
    public bool $outputDefaultCss = true;
    public bool $outputDefaultJs = true;
    public bool $lazyLoad = false;

    // Templates - Custom
    public ?string $templateFolderOverride = null;
    public ?string $templateEmail = null;

    // Security
    public bool $enableSpamChecks = true;
    public ?string $securityMaxLength = null;
    public ?string $securityFlooding = null;
    public ?string $securityModeration = null;
    public ?string $securitySpamlist = null;
    public ?string $securityBanned = null;
    public bool $securityMatchExact = false;
    public bool $recaptchaEnabled = false;
    public ?string $recaptchaKey = null;
    public ?string $recaptchaSecret = null;
    public float $recaptchaMinScore = 0.5;
    public bool $honeypotEnabled = true;
    public int $minimumSubmitTime = 3;
    public bool $javascriptCheckEnabled = false;
    public bool $replayProtectionEnabled = true;
    public bool $rateLimitEnabled = true;
    public int $rateLimitCount = 5;
    public int $rateLimitWindow = 60;
    public bool $challengeAuthenticated = true;
    public bool $challengeSendIp = false;
    public array $protectionHostnames = [];
    public bool $protectionFailClosed = false;
    public bool $persistSpam = true;
    public bool $turnstileEnabled = false;
    public ?string $turnstileKey = null;
    public ?string $turnstileSecret = null;
    public bool $akismetEnabled = false;
    public ?string $akismetApiKey = null;
    public ?string $akismetBlogUrl = null;
    public bool $akismetTestMode = false;
    public bool $akismetFeedbackEnabled = false;

    // Notifications
    public array $participantNotifications = [
        self::NOTIFICATION_OWNER,
        self::NOTIFICATION_REPLY,
        self::NOTIFICATION_MENTION,
    ];
    public array $staffNotifications = [];
    // Also email the moderator group about the staff notifications above, rather than only naming them in the event.
    public bool $emailModeratorGroup = false;
    public array $subscriptionTypes = [];
    public array $publicFields = [];
    public string $subscriptionDefault = self::SUBSCRIPTION_DEFAULT_NONE;
    public bool $useQueueForNotifications = true;

    // Permissions
    public array $permissions = [];

    // Authoring
    public string $authoringMode = AuthoringMode::Plain->value;
    public string $editorTheme = 'light';
    public bool $allowMentions = true;
    public bool $allowAttachments = false;
    public ?int $attachmentVolumeId = null;
    public array $attachmentExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    public int $attachmentMaxSize = 10;

    // CP Sorting
    public string $sortDefaultKey = 'commentDate';
    public string $sortDefaultDirection = 'desc';

    private ?Asset $_placeholderAvatar = null;


    // Public Methods
    // =========================================================================

    public function __construct(array $config = [])
    {
        $config = $this->_normalizeAttributes($config);

        parent::__construct($config);
    }

    public function validateChallengeProvider(): void
    {
        if ($this->enableSpamChecks && $this->recaptchaEnabled && $this->turnstileEnabled) {
            $this->addError('turnstileEnabled', Craft::t('comments', 'Choose either Turnstile or reCAPTCHA.'));
        }
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName'], 'trim'];
        $rules[] = [['pluginName'], 'required'];
        $rules[] = [['interactionMode'], 'in', 'range' => array_column(InteractionMode::cases(), 'value')];
        $rules[] = [['authoringMode'], 'in', 'range' => array_column(AuthoringMode::cases(), 'value')];
        $rules[] = [['editorTheme'], 'in', 'range' => ['light', 'dark', 'auto', 'none']];
        $rules[] = [['orderBy', 'sortDefaultDirection'], 'in', 'range' => ['asc', 'desc']];
        $rules[] = [['sortDefaultKey'], 'in', 'range' => ['commentDate', 'dateUpdated', 'thread', 'status']];
        $rules[] = [['minimumSubmitTime'], 'integer', 'min' => 0, 'max' => 300];
        $rules[] = [['rateLimitCount', 'rateLimitWindow'], 'integer', 'min' => 1, 'max' => 86400];
        $rules[] = [['turnstileKey', 'turnstileSecret'], 'required', 'when' => fn() => $this->enableSpamChecks && $this->turnstileEnabled];
        $rules[] = [['akismetApiKey'], 'required', 'when' => fn() => $this->enableSpamChecks && $this->akismetEnabled];
        $rules[] = [['akismetBlogUrl'], 'url'];
        $rules[] = [['protectionHostnames'], 'each', 'rule' => ['match', 'pattern' => '/^[a-zA-Z0-9.-]+$/']];
        $rules[] = [['turnstileEnabled'], 'validateChallengeProvider'];
        $rules[] = [['enableSpamChecks'], 'validateProtection'];
        $rules[] = [['recaptchaMinScore'], 'number', 'min' => 0, 'max' => 1];
        $rules[] = [['autoCloseDays', 'securityMaxLength'], 'integer', 'min' => 1];
        $rules[] = [['maxReplyDepth', 'securityFlooding'], 'integer', 'min' => 0];
        $rules[] = [['maxUserComments'], 'integer', 'min' => 1];
        $rules[] = [['attachmentVolumeId'], 'integer'];
        $rules[] = [['attachmentMaxSize'], 'integer', 'min' => 1];
        $rules[] = [['authorEditWindow'], 'integer', 'min' => 1];
        $rules[] = [['attachmentVolumeId'], 'required', 'when' => fn() => $this->allowAttachments];
        $rules[] = [['recaptchaKey', 'recaptchaSecret'], 'required', 'when' => fn() => $this->enableSpamChecks && $this->recaptchaEnabled];
        $rules[] = [['participantNotifications'], 'each', 'rule' => ['in', 'range' => [
            self::NOTIFICATION_OWNER,
            self::NOTIFICATION_REPLY,
            self::NOTIFICATION_MENTION,
            self::NOTIFICATION_APPROVED,
        ]]];
        $rules[] = [['staffNotifications'], 'each', 'rule' => ['in', 'range' => [
            self::NOTIFICATION_MODERATOR_PENDING,
            self::NOTIFICATION_MODERATOR_EDITED,
            self::NOTIFICATION_STAFF_NEW,
            self::NOTIFICATION_STAFF_REPORTED,
        ]]];
        $rules[] = [['subscriptionTypes'], 'each', 'rule' => ['in', 'range' => [
            SubscriptionType::Conversation->value,
            SubscriptionType::Replies->value,
        ]]];
        $rules[] = [['subscriptionDefault'], 'in', 'range' => [
            self::SUBSCRIPTION_DEFAULT_NONE,
            self::SUBSCRIPTION_DEFAULT_REPLIES,
            self::SUBSCRIPTION_DEFAULT_CONVERSATION,
        ]];
        $rules[] = [['subscriptionDefault'], 'validateSubscriptionDefault'];

        return $rules;
    }

    public function validateSubscriptionDefault(string $attribute): void
    {
        if ($this->$attribute === self::SUBSCRIPTION_DEFAULT_NONE) {
            return;
        }

        if (!in_array($this->$attribute, $this->subscriptionTypes, true)) {
            $this->addError(
                $attribute,
                Craft::t('comments', 'The default subscription must also be enabled under Available Subscriptions.'),
            );
        }
    }

    public function setAttributes($values, $safeOnly = true): void
    {
        $values = $this->_normalizeAttributes($values);

        parent::setAttributes($values, $safeOnly);
    }

    public function getPlaceholderAvatar(): ?Asset
    {
        if ($this->_placeholderAvatar !== null) {
            return $this->_placeholderAvatar;
        }

        if ($this->placeholderAvatar && isset($this->placeholderAvatar[0])) {
            /* @noinspection PhpIncompatibleReturnTypeInspection */
            return $this->_placeholderAvatar = Craft::$app->getElements()->getElementById($this->placeholderAvatar[0], Asset::class);
        }

        return null;
    }

    public function canComment($element): bool
    {
        $isAllowed = $this->commentingAvailable($element);

        return $isAllowed['permission'];
    }

    public function commentingAvailable($element): array
    {
        if (!$element || !isset($element->id, $element->siteId)) {
            return CommentStatus::Unpermitted;
        }

        $config = Comments::$plugin->getConversations()->getConfigForOwner(
            (int)$element->id,
            (int)$element->siteId,
        );
        $isClosed = Comments::$plugin->getComments()->checkManuallyClosed($element);

        if ($isClosed) {
            return CommentStatus::ManuallyClosed;
        }

        $isExpired = Comments::$plugin->getComments()->checkExpired($element);

        if ($isExpired) {
            return CommentStatus::Expired;
        }

        if (!$config->enabled) {
            return CommentStatus::Unpermitted;
        }

        $currentUser = Comments::$plugin->getService()->getUser();

        if (!$currentUser && !$config->allowGuests) {
            return CommentStatus::NoGuests;
        }

        if ($this->maxUserComments && $currentUser) {
            // Has the user already commented X amount of times on this element?
            $count = Comment::find()
                ->ownerId($element->id)
                ->ownerSiteId($element->siteId)
                ->userId($currentUser->id)
                ->status([Comment::STATUS_APPROVED, Comment::STATUS_PENDING])
                ->count();

            if ($count >= $this->maxUserComments) {
                return CommentStatus::TooManyComments;
            }
        }

        return CommentStatus::Allowed;
    }

    public function validateProtection(string $attribute): void
    {
        // A missing key has already said so, and would only be reported again here.
        if ($this->enableSpamChecks && !$this->hasErrors() && $error = Comments::$plugin->getProtection()->configurationError($this)) {
            $this->addError($attribute, $error);
        }
    }

    public function isRichAuthoringEnabled(): bool
    {
        return $this->authoringMode === AuthoringMode::Rich->value;
    }

    public function resolveInteractionMode(): InteractionMode
    {
        return InteractionMode::tryFrom($this->interactionMode) ?? InteractionMode::Like;
    }

    public function resolveEditorTheme(): string
    {
        if (in_array($this->editorTheme, ['light', 'dark', 'none', 'auto'], true)) {
            return $this->editorTheme;
        }

        return 'light';
    }

    public function isParticipantNotificationEnabled(string $event): bool
    {
        return in_array($event, $this->participantNotifications, true);
    }

    public function isStaffNotificationEnabled(string $event): bool
    {
        return in_array($event, $this->staffNotifications, true);
    }

    public function allowsSubscriptionType(SubscriptionType|string $type): bool
    {
        $value = $type instanceof SubscriptionType ? $type->value : $type;

        return in_array($value, $this->subscriptionTypes, true);
    }

    public function getRecaptchaKey()
    {
        return App::parseEnv($this->recaptchaKey);
    }

    public function getRecaptchaSecret()
    {
        return App::parseEnv($this->recaptchaSecret);
    }

    public function doesRequireModeration(): bool
    {
        // Check if we require moderation at all
        if ($this->requireModeration) {
            // Check if we should exclude the current user, if they are in the moderator group
            if ($this->moderatorExcluded && $this->moderatorUserGroup) {
                if ($currentUser = Comments::$plugin->getService()->getUser()) {
                    $groupId = Db::idByUid(Table::USERGROUPS, $this->moderatorUserGroup);

                    // A removed or environment-specific group must fail closed: it cannot
                    // accidentally exempt every signed-in user from moderation.
                    if ($groupId && User::find()->groupId($groupId)->id($currentUser->id)->exists()) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _normalizeAttributes(array $values): array
    {
        foreach (self::RETIRED_SETTINGS as $retired) {
            unset($values[$retired]);
        }

        // A plain text input holds the list when it's posted from the settings page.
        if (isset($values['attachmentExtensions']) && is_string($values['attachmentExtensions'])) {
            $values['attachmentExtensions'] = array_values(array_filter(array_map(
                static fn(string $extension): string => ltrim(strtolower(trim($extension)), '.'),
                explode(',', $values['attachmentExtensions']),
            )));
        }

        // Craft posts an empty string for an editable table with no rows, and row IDs as keys otherwise.
        foreach (['reportReasons', 'staffBadgeGroups'] as $attribute) {
            if (isset($values[$attribute])) {
                $values[$attribute] = is_array($values[$attribute]) ? array_values($values[$attribute]) : [];
            }
        }

        // The settings page offers one challenge provider, stored as a switch for each.
        if (array_key_exists('challengeProvider', $values)) {
            $values['turnstileEnabled'] = $values['challengeProvider'] === 'turnstile';
            $values['recaptchaEnabled'] = $values['challengeProvider'] === 'recaptcha';
            unset($values['challengeProvider']);
        }

        // v3 stored voting as a boolean. Translate it at the model boundary so only the
        // explicit v4 interaction mode is written back to project config.
        $hasInteractionMode = array_key_exists('interactionMode', $values)
            && $values['interactionMode'] !== null
            && $values['interactionMode'] !== '';

        if (!$hasInteractionMode && array_key_exists('allowVoting', $values)) {
            // v3 voting always offered both directions, so up/down is what preserves behaviour.
            $values['interactionMode'] = (bool)$values['allowVoting']
                ? InteractionMode::UpDown->value
                : InteractionMode::None->value;
        } elseif (!$hasInteractionMode) {
            unset($values['interactionMode']);
        }

        unset($values['allowVoting']);

        if (($values['sortDefaultKey'] ?? null) === 'structure') {
            $values['sortDefaultKey'] = 'commentDate';
            $values['sortDefaultDirection'] = 'desc';
        }

        $values = $this->_normalizeLegacyNotifications($values);

        // A checkbox group with nothing selected posts an empty string.
        if (isset($values['publicFields']) && !is_array($values['publicFields'])) {
            $values['publicFields'] = [];
        }

        foreach (['participantNotifications', 'staffNotifications', 'subscriptionTypes', 'publicFields'] as $attribute) {
            if (isset($values[$attribute]) && is_array($values[$attribute])) {
                $values[$attribute] = array_values(array_unique(array_filter($values[$attribute], 'is_string')));
            }
        }

        // Staff are told through an event now, so the old address list has nowhere to go.
        unset($values['notificationRecipients']);

        return $values;
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

        foreach ($legacyKeys as $legacyKey) {
            unset($values[$legacyKey]);
        }

        return $values;
    }
}
