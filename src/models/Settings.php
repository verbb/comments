<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\compatibility\settings\LegacySettings;
use verbb\comments\elements\Comment;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\CommentStatus;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\LoadMode;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\helpers\KeywordRules;
use verbb\comments\services\FormFields;
use verbb\comments\services\SpamSettings;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;

use yii\validators\EmailValidator;

use Twig\Error\Error as TwigError;
use Twig\Source;

use Closure;
use Throwable;

use verbb\protect\base\ProviderSettingsTrait;

class Settings extends Model
{
    // Traits
    // =========================================================================

    use LegacySettings;
    use ProviderSettingsTrait;


    // Static Methods
    // =========================================================================

    /**
     * What `config/comments.php` sets, when Craft is far enough along to read it.
     */
    private static function _configFile(): array
    {
        $config = Craft::$app?->getConfig()->getConfigFromFile('comments');

        return is_array($config) ? $config : [];
    }

    private static function _isEnvReference(string $value): bool
    {
        return (bool)preg_match('/^\$(\w+|\{\w+\})$/', trim($value));
    }

    /**
     * An editable table's lightswitch posts `1` or an empty string, and a config file gives a boolean.
     */
    private static function _flag(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private static function _stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * A list of field handles. A checkbox group with nothing selected posts an empty string.
     */
    /**
     * Notification Recipients rows, accepting a plain address for a row and treating a row with no
     * switch as on. Rows without an address are skipped.
     */
    private static function _recipientRows(mixed $rows): array
    {
        $normalized = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_string($row)) {
                $row = ['email' => $row];
            }

            if (!is_array($row) || trim((string)($row['email'] ?? '')) === '') {
                continue;
            }

            $normalized[] = [
                'email' => trim((string)$row['email']),
                'enabled' => !array_key_exists('enabled', $row) || (bool)$row['enabled'],
            ];
        }

        return $normalized;
    }

    private static function _handles(mixed $value): array
    {
        return is_array($value) ? array_values(array_unique(array_filter($value, static fn(mixed $handle): bool => is_string($handle) && $handle !== ''))) : [];
    }

    /**
     * Comment Form Fields as the settings page posts them, or a config file gives them: Off fields are
     * left out, a placement defaults to Details, and **On replies too** is a true or false.
     */
    private static function _formFieldSettings(mixed $value): array
    {
        $settings = [];

        foreach (is_array($value) ? $value : [] as $uid => $setting) {
            // The settings page sends `ask`, whether the site's form asks for the field, and `show`,
            // where its answer appears on comments: `inline`, `details` or `none`. `config/comments.php`
            // sets `mode` and `placement` instead.
            if (is_array($setting) && array_key_exists('ask', $setting)) {
                $show = is_string($setting['show'] ?? null) ? $setting['show'] : 'none';

                $setting = match (true) {
                    !self::_flag($setting['ask']) => ['mode' => FormFields::MODE_OFF] + $setting,
                    in_array($show, [FormFields::PLACEMENT_INLINE, FormFields::PLACEMENT_DETAILS], true) => ['mode' => FormFields::MODE_COLLECT_AND_SHOW, 'placement' => $show] + $setting,
                    default => ['mode' => FormFields::MODE_COLLECT] + $setting,
                };
            }

            $mode = is_array($setting) ? ($setting['mode'] ?? null) : $setting;

            if (!is_string($uid) || !in_array($mode, [FormFields::MODE_COLLECT, FormFields::MODE_COLLECT_AND_SHOW], true)) {
                continue;
            }

            $placement = is_array($setting) ? ($setting['placement'] ?? null) : null;

            $settings[$uid] = [
                'mode' => $mode,
                'placement' => in_array($placement, [FormFields::PLACEMENT_DETAILS, FormFields::PLACEMENT_INLINE], true) ? $placement : FormFields::PLACEMENT_DETAILS,
                'replies' => is_array($setting) && self::_flag($setting['replies'] ?? false),
            ];
        }

        return $settings;
    }


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

    public const GIPHY_RATINGS = ['g', 'pg', 'pg-13', 'r'];

    // Saving the settings pages, which leave the Spam settings to the Spam Protection page.
    public const SCENARIO_SETTINGS = 'settings';


    // Properties
    // =========================================================================

    public string $pluginName = 'Comments';
    public bool $closed = false;

    // Keeps Comments 3's Twig helpers, element methods, query parameters, GraphQL fields, console commands
    // and browser events working, each with a deprecation warning. Turn it off once the deprecation log
    // is clear. See `compatibility/` and the upgrade guide.
    public bool $compatibilityMode = true;

    // General
    public bool $allowGuest = false;
    public ?string $guestNotice = null;
    public bool $guestRequireEmailName = true;
    public bool $guestShowEmailName = true;
    public bool $requireModeration = true;
    public ?string $moderatorUserGroup = null;
    public bool $moderatorExcluded = true;
    // Holds each commenter's comments for review until one of theirs has been approved.
    public bool $holdFirstComments = false;
    public mixed $autoCloseDays = null;
    public mixed $maxReplyDepth = null;
    public mixed $maxUserComments = null;
    public mixed $authorEditWindow = null;

    // Interactions
    public string $interactionMode = InteractionMode::Like->value;
    public bool $allowGuestVoting = false;
    // Comments 3's author score: each signed-in author's net votes across their approved comments, beside
    // their name. Off on new installs; a Comments 3 site keeps what it had.
    public bool $showAuthorScore = false;
    // Comments 3's thresholds, as a collapse rather than a moderation decision: a comment with this many
    // downvotes, or open reports, shows folded with a way to read it. Null is off, as on new installs.
    public mixed $downvoteCommentLimit = null;

    // Flagging
    public bool $allowFlagging = true;
    public bool $allowGuestFlagging = false;
    public mixed $flaggedCommentLimit = null;
    public array $reportReasons = [
        ['value' => 'spam', 'label' => 'Spam', 'description' => 'Ads, scams or links that don’t belong'],
        ['value' => 'harassment', 'label' => 'Harassment or abuse', 'description' => 'Attacks or insults aimed at a person'],
        ['value' => 'off-topic', 'label' => 'Off-topic', 'description' => 'Not about this post'],
        ['value' => 'other', 'label' => 'Other', 'description' => 'Tell the moderators below'],
    ];

    // Templates - Default
    public bool $showAvatar = true;
    public mixed $placeholderAvatar = null;
    // Gravatar for commenters without a photo, once their address is known to be theirs. The site fetches
    // and serves each image, so visitors never contact Gravatar. See `services/Avatars.php`.
    public bool $enableGravatar = false;
    public bool $showTimeAgo = true;
    public ?string $ownerBadgeLabel = 'Author';
    public ?string $staffBadgeLabel = null;
    public array $staffBadgeGroups = [];
    public string $orderBy = 'desc';
    public bool $outputDefaultCss = true;
    public bool $outputDefaultJs = true;
    public string $loadMode = LoadMode::Visible->value;
    // Classes and attributes for the renderer's ten controls, for every thread on the site. Set in
    // `config/comments.php` only, never saved to project config, and merged first, so `render()`,
    // `Comments.configure()` and components can add to it.
    public array $themeConfig = [];
    // Wraps the bundled stylesheet in `@layer comments`, for sites whose own CSS is layered.
    public bool $useCssLayers = false;

    // Templates - Custom
    public ?string $templateEmail = null;

    // Security
    public bool $enableSpamChecks = true;
    public ?string $securityMaxLength = null;
    public ?string $securityFlooding = null;
    public ?string $securityModeration = null;
    public ?string $securitySpamlist = null;
    public ?string $securityBanned = null;
    public bool $securityMatchExact = false;
    // The challenge provider and spam classifier, with their keys, come from `ProviderSettingsTrait`.
    public bool $honeypotEnabled = true;
    public int $minimumSubmitTime = 3;
    public bool $replayProtectionEnabled = true;
    public bool $rateLimitEnabled = true;
    public int $rateLimitCount = 5;
    public int $rateLimitWindow = 60;
    public bool $challengeAuthenticated = true;
    public array $protectionHostnames = [];
    public bool $protectionFailClosed = false;
    public bool $saveSpam = true;
    // Spam older than this many days is deleted when Craft collects garbage. Null keeps it.
    public mixed $spamRetentionDays = null;
    // A comment with more links than this waits for review. Null is no limit.
    public mixed $maxLinks = null;
    // Email domains, one per line, whose commenters can't post. Each matches its subdomains too.
    public ?string $blockedEmailDomains = null;
    // Tells the classifier when a moderator marks a comment as spam or not spam, where it can be told.
    public bool $spamFeedbackEnabled = false;

    // Other sites. Front ends on another origin that may use the data API, and the keys a site's own
    // server signs member token requests with. The keys are environment variable references, so they
    // never land in project config.
    public array $allowedOrigins = [];
    public ?string $memberTokenKey = null;
    public ?string $memberTokenPreviousKey = null;

    // Notifications
    public array $participantNotifications = [
        self::NOTIFICATION_OWNER,
        self::NOTIFICATION_REPLY,
        self::NOTIFICATION_MENTION,
    ];
    public array $staffNotifications = [];
    // Who's emailed about new comments and reports, as rows of `['email' => …, 'enabled' => bool]`, as
    // Comments 3's `notificationAdmins` was. A config file can give a closure that returns the rows.
    public array|Closure $notificationRecipients = [];
    public array $subscriptionTypes = [];
    public string $subscriptionDefault = self::SUBSCRIPTION_DEFAULT_NONE;
    public bool $useQueueForNotifications = true;

    // Comment Form Fields. Each custom field's mode on the site's form, keyed by its field layout element's
    // UID: `['mode' => 'collect'|'collect-and-show', 'placement' => 'details'|'inline', 'replies' => bool]`. Fields that
    // aren't listed are Off. See `FormFields`.
    public array $formFields = [];
    // Whether guests are offered Assets fields on the site's form, and can upload to them. Off by default,
    // because a guest's uploads are anonymous and can only be limited by their address.
    public bool $allowGuestFieldUploads = false;

    // Permissions
    public array $permissions = [];

    // Authoring. New installs start with Editor; the upgrade from v3 writes `plain` so existing sites keep theirs.
    public string $authoringMode = AuthoringMode::Rich->value;
    // The thread's colours: `light`, `dark`, `auto` to follow the visitor's system, or `none` for the
    // structural stylesheet only.
    public string $theme = 'light';
    public bool $allowMentions = true;
    public bool $allowAttachments = false;
    // The UID of the volume uploads go to, which is what project config keeps, so it's the same volume on
    // every environment. Comments 4's development builds kept its ID instead.
    public ?string $attachmentVolume = null;
    // The volume's ID on this installation, worked out from `attachmentVolume`. Not saved.
    public ?int $attachmentVolumeId = null;
    // A folder in the volume for each comment's files, which can use the comment's properties in Twig,
    // such as `{owner.section.handle}`. Blank is the volume's root.
    public ?string $attachmentSubpath = null;
    public array $attachmentExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    public int $attachmentMaxSize = 10;

    // GIPHY. Visitors' browsers search GIPHY directly, as GIPHY's API terms require, so the key is
    // GIPHY's public web key and reaches the page for anyone who can comment. See `services/Giphy.php`.
    public bool $giphyEnabled = false;
    public ?string $giphyApiKey = null;
    public string $giphyRating = 'g';
    public int $giphyLimit = 24;

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

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName'], 'trim'];
        $rules[] = [['pluginName'], 'required'];
        $rules[] = [['interactionMode'], 'in', 'range' => array_column(InteractionMode::cases(), 'value')];
        $rules[] = [['authoringMode'], 'in', 'range' => array_column(AuthoringMode::cases(), 'value')];
        $rules[] = [['theme'], 'in', 'range' => ['light', 'dark', 'auto', 'none']];
        $rules[] = [['loadMode'], 'in', 'range' => array_column(LoadMode::cases(), 'value')];
        $rules[] = [['orderBy', 'sortDefaultDirection'], 'in', 'range' => ['asc', 'desc']];
        $rules[] = [['sortDefaultKey'], 'in', 'range' => ['commentDate', 'dateUpdated', 'thread', 'status']];
        $rules[] = [['minimumSubmitTime'], 'integer', 'min' => 0, 'max' => 300];
        $rules[] = [['rateLimitCount', 'rateLimitWindow'], 'integer', 'min' => 1, 'max' => 86400];
        array_push($rules, ...$this->defineProviderRules(fn() => $this->enableSpamChecks));
        $rules[] = [['protectionHostnames'], 'each', 'rule' => ['match', 'pattern' => '/^[a-zA-Z0-9.-]+$/']];
        $rules[] = [['enableSpamChecks'], 'validateProtection'];
        $rules[] = [['autoCloseDays', 'securityMaxLength'], 'integer', 'min' => 1];
        $rules[] = [['maxReplyDepth', 'securityFlooding'], 'integer', 'min' => 0];
        $rules[] = [['maxUserComments', 'downvoteCommentLimit', 'flaggedCommentLimit'], 'integer', 'min' => 1];
        $rules[] = [['attachmentVolumeId'], 'integer'];
        $rules[] = [['attachmentSubpath'], 'trim'];
        $rules[] = [['attachmentSubpath'], 'string', 'max' => 255];
        $rules[] = [['attachmentSubpath'], 'validateAttachmentSubpath'];
        $rules[] = [['attachmentMaxSize'], 'integer', 'min' => 1];
        $rules[] = [['giphyApiKey'], 'required', 'when' => fn() => $this->giphyEnabled];
        $rules[] = [['giphyRating'], 'in', 'range' => self::GIPHY_RATINGS];
        // GIPHY's beta keys return at most 50 results a search.
        $rules[] = [['giphyLimit'], 'integer', 'min' => 1, 'max' => 50];
        $rules[] = [['authorEditWindow'], 'integer', 'min' => 1];
        $rules[] = [['attachmentVolumeId'], 'required', 'when' => fn() => $this->allowAttachments && $this->attachmentVolume === null];
        $rules[] = [['spamRetentionDays'], 'integer', 'min' => 1];
        $rules[] = [['maxLinks'], 'integer', 'min' => 0];
        $rules[] = [['blockedEmailDomains'], 'validateBlockedEmailDomains'];
        $rules[] = [['securityModeration', 'securitySpamlist', 'securityBanned'], 'validateKeywordRules'];
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
        $rules[] = [['notificationRecipients'], 'validateNotificationRecipients'];
        $rules[] = [['allowedOrigins'], 'validateAllowedOrigins'];
        $rules[] = [['formFields'], 'validateFormFields'];
        $rules[] = [['memberTokenKey', 'memberTokenPreviousKey'], 'validateMemberTokenKey'];

        return $rules;
    }

    /**
     * The subfolder is rendered for each comment as it's posted, so a template that can't compile would
     * only fail then. Its syntax is checked now instead.
     */
    public function validateAttachmentSubpath(string $attribute): void
    {
        $subpath = $this->$attribute;

        if (!is_string($subpath) || !str_contains($subpath, '{')) {
            return;
        }

        $view = Craft::$app->getView();
        $twig = $view->getTwig();

        try {
            $twig->parse($twig->tokenize(new Source($view->normalizeObjectTemplate($subpath), 'attachmentSubpath')));
        } catch (Throwable $e) {
            $this->addError($attribute, Craft::t('comments', 'The subfolder isn’t valid Twig: {error}', ['error' => $e instanceof TwigError ? $e->getRawMessage() : $e->getMessage()]));
        }
    }

    /**
     * Every origin must be exact, listed once, and any per-origin challenge secret must come from an
     * environment variable, because project config is usually committed.
     */
    public function validateAllowedOrigins(string $attribute): void
    {
        if (!is_array($this->$attribute)) {
            $this->addError($attribute, Craft::t('comments', 'Allowed origins must be a list.'));

            return;
        }

        $seen = [];

        foreach (array_values($this->$attribute) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $value = App::parseEnv($row['origin'] ?? null);
            $origin = AllowedOrigin::normalize($value);
            $label = is_string($row['origin'] ?? null) && $row['origin'] !== '' ? $row['origin'] : '#' . ($index + 1);

            if ($origin === null) {
                $this->addError($attribute, Craft::t('comments', '“{origin}” isn’t an origin. Use the scheme and host, such as https://www.example.com, with no path or wildcard.', ['origin' => $label]));

                continue;
            }

            if (isset($seen[$origin])) {
                $this->addError($attribute, Craft::t('comments', '{origin} is listed more than once.', ['origin' => $origin]));
            }

            $seen[$origin] = true;
            $siteKey = trim((string)($row['protectionSiteKey'] ?? ''));
            $secretKey = trim((string)($row['protectionSecretKey'] ?? ''));

            if (($siteKey === '') !== ($secretKey === '')) {
                $this->addError($attribute, Craft::t('comments', '{origin} needs both a site key and a secret key, or neither.', ['origin' => $origin]));
            }

            if ($secretKey !== '' && !self::_isEnvReference($secretKey)) {
                $this->addError($attribute, Craft::t('comments', 'The secret key for {origin} must be an environment variable, such as $TURNSTILE_SECRET.', ['origin' => $origin]));
            }
        }
    }

    /**
     * A key that mints member tokens for any user is only accepted from an environment variable, and
     * must be long enough that it can't be guessed.
     */
    public function validateMemberTokenKey(string $attribute): void
    {
        $value = $this->$attribute;

        if ($value === null || trim($value) === '') {
            return;
        }

        if (!self::_isEnvReference($value)) {
            $this->addError($attribute, Craft::t('comments', 'Use an environment variable, such as $COMMENTS_MEMBER_TOKEN_KEY, so the key isn’t saved in project config.'));

            return;
        }

        $key = App::parseEnv($value);

        // An unset variable is left to the environment it's for, where the key is used.
        if (is_string($key) && $key !== $value && strlen($key) < 32) {
            $this->addError($attribute, Craft::t('comments', 'The key must be at least 32 characters long.'));
        }
    }

    /**
     * The front ends on other origins that may use the data API, keyed by canonical origin. Rows that
     * aren't valid origins are left out, so a mistake in a config file can't open anything up.
     */
    public function getAllowedOrigins(): array
    {
        $origins = [];

        foreach (is_array($this->allowedOrigins) ? $this->allowedOrigins : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $origin = AllowedOrigin::normalize(App::parseEnv($row['origin'] ?? null));

            if ($origin === null || isset($origins[$origin])) {
                continue;
            }

            $origins[$origin] = new AllowedOrigin([
                'origin' => $origin,
                'credentials' => self::_flag($row['credentials'] ?? false),
                'memberTokens' => self::_flag($row['memberTokens'] ?? false),
                'moderation' => self::_flag($row['moderation'] ?? false),
                'protectionSiteKey' => self::_stringOrNull($row['protectionSiteKey'] ?? null),
                'protectionSecretKey' => self::_stringOrNull($row['protectionSecretKey'] ?? null),
            ]);
        }

        return $origins;
    }

    /**
     * The keys member token requests may be signed with: the current one, then the previous one while
     * it's being rotated out. Unset keys are left out, so with none, minting is off.
     */
    public function getMemberTokenKeys(): array
    {
        $keys = [];

        foreach ([$this->memberTokenKey, $this->memberTokenPreviousKey] as $value) {
            $key = App::parseEnv($value);

            // An unresolved `$VARIABLE` comes back as itself, which is no key at all.
            if (is_string($key) && $key !== '' && !self::_isEnvReference($key) && strlen($key) >= 32) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    public function validateNotificationRecipients(string $attribute): void
    {
        if ($this->$attribute instanceof Closure) {
            return;
        }

        $validator = new EmailValidator();

        foreach (self::_recipientRows($this->$attribute) as $row) {
            if (!$validator->validate($row['email'])) {
                $this->addError($attribute, Craft::t('comments', '“{email}” isn’t a valid email address.', ['email' => $row['email']]));
            }
        }
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

    /**
     * Each field's mode, placement and replies switch must be one Comments knows, so a hand-edited config
     * file can't save something the site would read differently.
     */
    public function validateFormFields(string $attribute): void
    {
        foreach ($this->$attribute as $uid => $setting) {
            if (!is_string($uid) || !is_array($setting)
                || !in_array($setting['mode'] ?? null, [FormFields::MODE_COLLECT, FormFields::MODE_COLLECT_AND_SHOW], true)
                || !in_array($setting['placement'] ?? null, [FormFields::PLACEMENT_DETAILS, FormFields::PLACEMENT_INLINE], true)
                || !is_bool($setting['replies'] ?? null)
            ) {
                $this->addError($attribute, Craft::t('comments', 'A comment form field has a setting Comments doesn’t recognise.'));

                return;
            }
        }
    }

    public function fields(): array
    {
        $fields = parent::fields();

        // Config only, so a site's classes never end up in project config. The attachment volume is saved
        // by its UID, and its ID is only this installation's.
        unset($fields['themeConfig'], $fields['attachmentVolumeId']);

        // The Spam settings are kept in this installation's database instead. See `SpamSettings`.
        foreach (SpamSettings::KEYS as $key) {
            unset($fields[$key]);
        }

        return $fields;
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

    public function resolveLoadMode(): LoadMode
    {
        return LoadMode::tryFrom($this->loadMode) ?? LoadMode::Visible;
    }

    public function resolveTheme(): string
    {
        if (in_array($this->theme, ['light', 'dark', 'none', 'auto'], true)) {
            return $this->theme;
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

    /**
     * The enabled Notification Recipients addresses, once each. Invalid addresses are left out.
     *
     * @return string[]
     */
    public function getEnabledNotificationRecipientEmails(): array
    {
        $rows = $this->notificationRecipients instanceof Closure ? ($this->notificationRecipients)() : $this->notificationRecipients;
        $validator = new EmailValidator();
        $emails = [];

        foreach (self::_recipientRows(is_array($rows) ? $rows : []) as $row) {
            if ($row['enabled'] && $validator->validate($row['email'])) {
                $emails[strtolower($row['email'])] ??= $row['email'];
            }
        }

        return array_values($emails);
    }

    public function allowsSubscriptionType(SubscriptionType|string $type): bool
    {
        $value = $type instanceof SubscriptionType ? $type->value : $type;

        return in_array($value, $this->subscriptionTypes, true);
    }

    /**
     * How many downvotes fold a comment away, or null when they don't. Only up/down voting has downvotes.
     */
    public function getDownvoteCommentLimit(): ?int
    {
        return $this->resolveInteractionMode() === InteractionMode::UpDown && (int)$this->downvoteCommentLimit > 0 ? (int)$this->downvoteCommentLimit : null;
    }

    /**
     * How many open reports fold a comment away, or null when they don't.
     */
    public function getFlaggedCommentLimit(): ?int
    {
        return $this->allowFlagging && (int)$this->flaggedCommentLimit > 0 ? (int)$this->flaggedCommentLimit : null;
    }

    /**
     * Whether authors' scores show, which needs votes to add up.
     */
    public function showsAuthorScore(): bool
    {
        return $this->showAuthorScore && $this->resolveInteractionMode()->isEnabled();
    }

    public function getGiphyApiKey(): ?string
    {
        $key = App::parseEnv($this->giphyApiKey);

        // An unresolved `$VARIABLE` comes back as itself, which is no key at all.
        return is_string($key) && trim($key) !== '' && !self::_isEnvReference($key) ? trim($key) : null;
    }

    /**
     * The blocked email domains as lowercase hostnames, without a leading `@` or dot.
     */
    public function getBlockedEmailDomains(): array
    {
        $domains = preg_split('/[\s,]+/', (string)$this->blockedEmailDomains) ?: [];
        $domains = array_map(static fn(string $domain): string => trim(mb_strtolower($domain), " \t@."), $domains);

        return array_values(array_unique(array_filter($domains)));
    }

    public function validateBlockedEmailDomains(string $attribute): void
    {
        foreach ($this->getBlockedEmailDomains() as $domain) {
            if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*$/', $domain)) {
                $this->addError($attribute, Craft::t('comments', '“{domain}” isn’t a domain.', ['domain' => $domain]));

                return;
            }
        }
    }

    public function validateKeywordRules(string $attribute): void
    {
        foreach (KeywordRules::errors($this->$attribute) as $line => $error) {
            $this->addError($attribute, Craft::t('comments', 'Line {line}: {error}', ['line' => $line, 'error' => $error]));
        }
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
        return $this->requireModeration && !$this->isExemptFromModeration();
    }

    /**
     * Whether the person posting skips pre-moderation as a member of the moderator group, when
     * **Exclude Moderators** is on. It's a moderator's privilege, so a member token only carries it
     * from an origin that allows moderation. Every pre-moderation check uses this.
     */
    public function isExemptFromModeration(): bool
    {
        if (!$this->moderatorExcluded || !$this->moderatorUserGroup) {
            return false;
        }

        $currentUser = Comments::$plugin->getService()->getUser();

        if (!$currentUser instanceof User || !Comments::$plugin->getSignIn()->allowsModeration($currentUser)) {
            return false;
        }

        $groupId = Db::idByUid(Table::USERGROUPS, $this->moderatorUserGroup);

        // A removed or environment-specific group must fail closed: it cannot accidentally exempt every
        // signed-in user from moderation.
        return $groupId && User::find()->groupId($groupId)->id($currentUser->id)->exists();
    }


    // Private Methods
    // =========================================================================

    private function _normalizeAttributes(array $values): array
    {
        // Comments 3's settings, and those from Comments 4's development builds, become today's.
        $values = $this->normalizeLegacySettings($values);

        // The attachment volume is chosen by its UID, and used by its ID on this installation.
        if (array_key_exists('attachmentVolume', $values)) {
            $uid = is_string($values['attachmentVolume']) && trim($values['attachmentVolume']) !== '' ? trim($values['attachmentVolume']) : null;
            $values['attachmentVolume'] = $uid;
            $values['attachmentVolumeId'] = $uid !== null ? Db::idByUid(Table::VOLUMES, $uid) : null;
        }

        // A plain text input holds the list when it's posted from the settings page.
        if (isset($values['attachmentExtensions']) && is_string($values['attachmentExtensions'])) {
            $values['attachmentExtensions'] = array_values(array_filter(array_map(
                static fn(string $extension): string => ltrim(strtolower(trim($extension)), '.'),
                explode(',', $values['attachmentExtensions']),
            )));
        }

        // Craft posts an empty string for an editable table with no rows, and row IDs as keys otherwise.
        foreach (['reportReasons', 'staffBadgeGroups', 'allowedOrigins'] as $attribute) {
            if (isset($values[$attribute])) {
                $values[$attribute] = is_array($values[$attribute]) ? array_values($values[$attribute]) : [];
            }
        }

        // A config file can list an origin as a plain string, as Formie's `allowedOrigins` does. It's
        // read as that origin with everything else off.
        if (isset($values['allowedOrigins'])) {
            $values['allowedOrigins'] = array_map(
                static fn(mixed $row): mixed => is_string($row) ? ['origin' => $row] : $row,
                $values['allowedOrigins'],
            );
        }

        foreach (['challengeProvider', 'spamClassifier'] as $attribute) {
            if (array_key_exists($attribute, $values)) {
                $values[$attribute] = (string)$values[$attribute];
            }
        }

        // Number fields post strings, and a blank one means no limit. Anything else is left for validation.
        foreach (['spamRetentionDays', 'maxLinks'] as $attribute) {
            if (array_key_exists($attribute, $values) && is_string($values[$attribute])) {
                $value = trim($values[$attribute]);
                $values[$attribute] = $value === '' ? null : (ctype_digit($value) ? (int)$value : $value);
            }
        }

        if (array_key_exists('formFields', $values)) {
            $values['formFields'] = self::_formFieldSettings($values['formFields']);
        }

        if (isset($values['themeConfig']) && !is_array($values['themeConfig'])) {
            $values['themeConfig'] = [];
        }

        foreach (['participantNotifications', 'staffNotifications', 'subscriptionTypes'] as $attribute) {
            if (isset($values[$attribute]) && is_array($values[$attribute])) {
                $values[$attribute] = array_values(array_unique(array_filter($values[$attribute], 'is_string')));
            }
        }

        // An editable table with no rows posts an empty string.
        if (isset($values['notificationRecipients']) && !is_array($values['notificationRecipients']) && !$values['notificationRecipients'] instanceof Closure) {
            $values['notificationRecipients'] = [];
        }

        return $values;
    }
}
