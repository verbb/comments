<?php
namespace verbb\comments\elements;

use verbb\comments\Comments;
use verbb\comments\elements\actions\SetCommentStatus;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldLayoutElement;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\models\Subscribe;
use verbb\comments\records\Comment as CommentRecord;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\User;
use craft\elements\actions\Delete;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\SiteIdValidator;

use LitEmoji\LitEmoji;
use TheIconic\NameParser\Parser;

use Throwable;
use Exception;
use DateTime;

class Comment extends Element
{
    // Constants
    // =========================================================================

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SPAM = 'spam';
    public const STATUS_TRASHED = 'trashed';

    public const SCENARIO_CP = 'cp';
    public const SCENARIO_FRONT_END = 'frontEnd';

    public const ACTION_SAVE = 'save';
    public const ACTION_DELETE = 'delete';


    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comment');
    }

    public static function refHandle(): ?string
    {
        return 'comment';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_APPROVED => Craft::t('comments', 'Approved'),
            self::STATUS_PENDING => Craft::t('comments', 'Pending'),
            self::STATUS_SPAM => Craft::t('comments', 'Spam'),
            self::STATUS_TRASHED => Craft::t('comments', 'Trashed'),
        ];
    }

    public static function find(): CommentQuery
    {
        return new CommentQuery(static::class);
    }

    public static function getStructureId(): int
    {
        return Comments::$plugin->getSettings()->getStructureId();
    }

    public static function getCommentElementTitleHtml(&$context): string
    {
        if (!isset($context['element'])) {
            return '';
        }

        // Only do this for a Comment ElementType
        if ($context['element']::class === static::class) {
            $span1 = Html::tag('span', '', ['class' => 'status ' . $context['element']->status]);
            $span2 = Html::tag('span', Html::encode($context['element']->getAuthor()), ['class' => 'username']);
            $small = Html::tag('small', Html::encode($context['element']->getExcerpt(0, 100)));
            $a = Html::a($span2 . $small, $context['element']->getCpEditUrl());

            $html = Html::tag('div', $span1 . $a, ['class' => 'comment-block']);

            return Template::raw($html);
        }

        return '';
    }

    public static function eagerLoadingMap(array $sourceElements, string $handle): array|false|null
    {
        if ($handle === 'user') {
            // Get the source element IDs
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

            $map = (new Query())
                ->select(['id as source', 'userId as target'])
                ->from(['{{%comments_comments}}'])
                ->where(['and', ['id' => $sourceElementIds], ['not', ['userId' => null]]])
                ->all();

            return [
                'elementType' => User::class,
                'map' => $map,
            ];
        }

        if ($handle === 'owner') {
            // Get the source element IDs
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

            $map = (new Query())
                ->select(['id as source', 'ownerId as target'])
                ->from(['{{%comments_comments}}'])
                ->where(['and', ['id' => $sourceElementIds], ['not', ['ownerId' => null]]])
                ->all();

            // This isn't amazing, but its benefit is pretty considerable. The thinking here is that its
            // unlikely you'll be fetching comments across multiple different element types
            // $elementType = Entry::class;
            $firstElement = $sourceElements[0] ?? [];

            if (!$firstElement) {
                return null;
            }

            return [
                'elementType' => $firstElement->getOwnerType(),
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        return 'Comment';
    }

    protected static function defineSources(string $context = null): array
    {
        $settings = Comments::$plugin->getSettings();

        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('comments', 'All comments'),
                'structureId' => self::getStructureId(),
                'structureEditable' => false,
                'defaultSort' => [$settings->sortDefaultKey, $settings->sortDefaultDirection],
            ],
        ];

        $indexSidebarLimit = $settings->indexSidebarLimit;
        $indexSidebarGroup = $settings->indexSidebarGroup;
        $indexSidebarIndividualElements = $settings->indexSidebarIndividualElements;

        $query = (new Query())
            ->select(['elements.id', 'elements.type', 'comments.ownerId', 'content.title', 'entries.sectionId'])
            ->from(['{{%elements}} elements'])
            ->innerJoin('{{%content}} content', '[[content.elementId]] = [[elements.id]]')
            ->innerJoin('{{%comments_comments}} comments', '[[comments.ownerId]] = [[elements.id]]')
            ->leftJoin('{{%entries}} entries', '[[comments.ownerId]] = [[entries.id]]')
            ->limit($indexSidebarLimit)
            ->groupBy(['ownerId', 'title', 'elements.id', 'entries.sectionId']);

        // Support Craft 3.1+
        if (Craft::$app->getDb()->columnExists('{{%elements}}', 'dateDeleted')) {
            $query
                ->addSelect(['elements.dateDeleted'])
                ->where(['is', 'elements.dateDeleted', null]);
        }

        $commentedElements = $query->all();

        // Keep a cache of sections here
        $sectionsById = [];

        foreach (Craft::$app->getSections()->getAllSections() as $section) {
            $sectionsById[$section->id] = $section;
        }

        foreach ($commentedElements as $element) {
            try {
                $elementGroupPrefix = '';
                $displayName = $element['type']::pluralDisplayName();

                switch ($element['type']) {
                    case Entry::class:
                        $elementGroupPrefix = 'section';
                        break;
                    case Category::class:
                        $elementGroupPrefix = 'categorygroup';
                        break;
                    case Asset::class:
                        $elementGroupPrefix = 'volume';
                        break;
                    case User::class:
                        $elementGroupPrefix = 'usergroup';
                        break;
                }

                $key = 'type:' . $element['type'];

                $sources[$key] = ['heading' => $displayName];

                $sources[$key . ':all'] = [
                    'key' => $key . ':all',
                    'label' => Craft::t('comments', 'All {elements}', ['elements' => $displayName]),
                    'structureId' => self::getStructureId(),
                    'structureEditable' => false,
                    'criteria' => [
                        'ownerType' => $element['type'],
                    ],
                    'defaultSort' => [$settings->sortDefaultKey, $settings->sortDefaultDirection],
                ];

                // Just do sections for the moment
                if ($indexSidebarGroup && $elementGroupPrefix == 'section' && $element['sectionId']) {
                    $section = $sectionsById[$element['sectionId']] ?? '';

                    $sources[$elementGroupPrefix . ':' . $element['sectionId']] = [
                        'key' => $elementGroupPrefix . ':' . $element['sectionId'],
                        'label' => $section->name ?? '',
                        'structureId' => self::getStructureId(),
                        'structureEditable' => false,
                        'criteria' => [
                            'ownerSectionId' => $element['sectionId'],
                        ],
                        'defaultSort' => [$settings->sortDefaultKey, $settings->sortDefaultDirection],
                    ];
                }

                if ($indexSidebarIndividualElements) {
                    $sources['elements:' . $element['ownerId']] = [
                        'key' => 'elements:' . $element['ownerId'],
                        'label' => $element['title'],
                        'structureId' => self::getStructureId(),
                        'structureEditable' => false,
                        'criteria' => [
                            'ownerId' => $element['ownerId'],
                        ],
                        'defaultSort' => ['structure', 'asc'],
                    ];
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        $elementsService = Craft::$app->getElements();

        $actions = parent::defineActions($source);

        $actions[] = $elementsService->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('comments', 'Are you sure you want to delete the selected comments?'),
            'successMessage' => Craft::t('comments', 'Comments deleted.'),
        ]);

        $statuses = [
            [
                'id' => self::STATUS_APPROVED,
                'name' => Craft::t('comments', 'Approved'),
                'color' => 'approved',
            ],
            [
                'id' => self::STATUS_PENDING,
                'name' => Craft::t('comments', 'Pending'),
                'color' => 'pending',
            ],
            [
                'id' => self::STATUS_SPAM,
                'name' => Craft::t('comments', 'Spam'),
                'color' => 'spam',
            ],
            [
                'id' => self::STATUS_TRASHED,
                'name' => Craft::t('comments', 'Trashed'),
                'color' => 'trashed',
            ],
        ];

        $actions[] = $elementsService->createAction([
            'type' => SetCommentStatus::class,
            'statuses' => $statuses,
        ]);

        return $actions;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'comment' => ['label' => Craft::t('comments', 'Comment')],
            'commentDate' => ['label' => Craft::t('comments', 'Date')],
            'ownerId' => ['label' => Craft::t('comments', 'Element')],
            'email' => ['label' => Craft::t('comments', 'Email')],
            'name' => ['label' => Craft::t('comments', 'Name')],
            'voteCount' => ['label' => Craft::t('comments', 'Votes')],
            'flagCount' => ['label' => Craft::t('comments', 'Flagged')],
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['rawComment', 'authorName', 'authorEmail'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'status' => Craft::t('comments', 'Status'),
            'comment' => Craft::t('comments', 'Comment'),
            [
                'label' => Craft::t('comments', 'Date'),
                'orderBy' => 'commentDate',
                'attribute' => 'commentDate',
            ],
            'ownerId' => Craft::t('comments', 'Element'),
            'email' => Craft::t('comments', 'Email'),
            'name' => Craft::t('comments', 'Name'),
            'voteCount' => Craft::t('comments', 'Votes'),
            'flagCount' => Craft::t('comments', 'Flagged'),
        ];
    }

    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        if ($attribute === 'user') {
            $elementQuery->andWith('user');
        } else if ($attribute === 'owner') {
            $elementQuery->andWith('owner');
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }


    // Properties
    // =========================================================================

    public ?int $ownerId = null;
    public ?int $ownerSiteId = null;
    public ?int $userId = null;
    public ?string $status = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $url = null;
    public ?string $ipAddress = null;
    public ?string $userAgent = null;
    public ?DateTime $commentDate = null;

    public ?int $newParentId = null;
    private ?bool $_hasNewParent = null;
    private ?string $comment = null;
    private ?ElementInterface $_owner = null;
    private ?User $_author = null;
    private mixed $_user = null;
    private ?string $_action = null;
    private ?Comment $previousComment = null;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        if ($this->id) {
            // Add this comment to a render cache, so when calling `parent` we can make use of it
            Comments::$plugin->getRenderCache()->addComment($this->id, $this);
        }
    }

    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'owner';
        $names[] = 'user';
        $names[] = 'author';
        return $names;
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        // Ths is the only way I can figure out extra scenarios to work...
        $scenarios[self::SCENARIO_CP] ??= [];
        $scenarios[self::SCENARIO_FRONT_END] ??= [];

        return $scenarios;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['ownerId'], 'number', 'integerOnly' => true];
        $rules[] = [['ownerSiteId'], SiteIdValidator::class];

        // Check for custom fields. Craft will only check this for `SCENARIO_LIVE`, and we use custom scenarios
        if ($fieldLayout = $this->getFieldLayout()) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $attribute = 'field:' . $field->handle;
                $isEmpty = [$this, 'isFieldEmpty:' . $field->handle];

                if ($field->required) {
                    // Allow custom field validation with our custom scenarios
                    $rules[] = [[$attribute], 'required', 'isEmpty' => $isEmpty, 'on' => [self::SCENARIO_CP, self::SCENARIO_FRONT_END]];
                }
            }
        }

        return $rules;
    }

    public function canView(User $user): bool
    {
        return true;
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        return ($this->userId === $user->id || $user->can('comments-edit'));
    }

    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        return ($this->userId === $user->id || $user->can('comments-delete'));
    }

    public function getSupportedSites(): array
    {
        // Only support the site the comment is being made on
        $siteId = $this->siteId ?: Craft::$app->getSites()->getPrimarySite()->id;

        return [$siteId];
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('comments/' . $this->id);
    }

    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    public function setAction(string $action): void
    {
        $this->_action = $action;
    }

    public function getAction(): ?string
    {
        return $this->_action;
    }

    public function getComment(): ?string
    {
        $comment = $this->comment;

        // Add Emoji support
        if ($comment !== null) {
            $comment = LitEmoji::shortcodeToUnicode($comment);
            $comment = trim(preg_replace('/\R/u', "\n", $comment));
        }

        return $comment;
    }

    public function setComment($comment): void
    {
        // Add Emoji support
        if ($comment !== null) {
            $comment = LitEmoji::unicodeToShortcode($comment);
        }

        // Replace any 4-byte string that've been missed
        $comment = preg_replace('%(?:\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})%xs', '', $comment);

        $this->comment = $comment;
    }

    public function getRawComment(): ?string
    {
        return $this->comment;
    }

    public function can($property): bool
    {
        // See if there's a plugin setting for it
        if (property_exists(Comments::$plugin->getSettings(), $property)) {
            return (bool)Comments::$plugin->getSettings()->$property;
        }

        // Provide some helpers
        return match ($property) {
            'flag' => $this->canFlag(),
            'vote' => $this->canVote(),
            'reply' => $this->canReply(),
            'edit' => $this->canEdit(),
            'trash' => $this->canTrash(),
            default => false,
        };
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getExcerpt($startPos = 0, $maxLength = 100): ?string
    {
        $comment = $this->getComment();

        if (strlen($comment) > $maxLength) {
            $excerpt = substr($comment, $startPos, $maxLength - 3);
            $lastSpace = strrpos($excerpt, ' ');
            $excerpt = substr($excerpt, 0, $lastSpace);
            $excerpt .= '...';
        } else {
            $excerpt = $comment;
        }

        return $excerpt;
    }

    public function getTimeAgo(): string
    {
        $diff = (new DateTime())->diff($this->commentDate);
        return CommentsHelper::humanDurationFromInterval($diff);
    }

    public function isGuest(): bool
    {
        return is_null($this->userId);
    }

    public function getAuthor(): bool|User
    {
        // Provide some caching
        if ($this->_author !== null) {
            return $this->_author;
        }

        // If this user is a guest, we make a temporary UserModel, which is particularly
        // used for email notifications (which require a UserModel instance)
        if ($this->isGuest()) {
            // If this wasn't a registered user...
            $author = new User();
            $author->email = $this->email;
            $author->fullName = $this->name ?: Craft::t('comments', 'Guest');

            $this->_author = $author;

            return $author;
        }

        // Check if this is a regular user
        $user = $this->getUser();

        // But, they might have been deleted!
        if (!$user) {
            $author = new User();
            $author->email = null;
            $author->fullName = Craft::t('comments', '[Deleted User]');

            $this->_author = $author;

            return $author;
        }

        $this->_author = $user;

        return $user;
    }

    public function getAuthorName(): ?string
    {
        if ($author = $this->getAuthor()) {
            return $author->fullName;
        }

        return $this->name;
    }

    public function getAuthorEmail(): ?string
    {
        if ($author = $this->getAuthor()) {
            return $author->email;
        }

        return $this->email;
    }

    public function getAvatar()
    {
        $author = $this->getAuthor();

        $renderCache = Comments::$plugin->getRenderCache();
        $cacheKey = $author->id ?? $author->email ?? '';

        if ($cacheKey && $cachedAvatar = $renderCache->getAvatar($cacheKey)) {
            return $cachedAvatar;
        }

        $avatar = CommentsHelper::getAvatar($author);

        if ($avatar) {
            $renderCache->addAvatar($cacheKey, $avatar);
        }

        return $avatar;
    }

    public function getOwner(): ?ElementInterface
    {
        $renderCache = Comments::$plugin->getRenderCache();
        $cacheKey = $this->ownerId;

        if ($this->_owner !== null) {
            return $this->_owner !== false ? $this->_owner : null;
        }

        if ($cacheKey && $this->_owner = $renderCache->getElement($cacheKey)) {
            return $this->_owner;
        }

        if ($this->ownerId) {
            $this->_owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId);
        }

        if ($this->_owner) {
            $renderCache->addElement($cacheKey, $this->_owner);
        }

        return $this->_owner;
    }

    public function setOwner(ElementInterface $owner = null): void
    {
        $this->_owner = $owner;
    }

    public function getOwnerType(): string
    {
        if ($owner = $this->getOwner()) {
            return $owner::class;
        }

        return '';
    }

    public function canReply(): bool
    {
        $settings = Comments::$plugin->getSettings();

        $canReply = $settings->canComment($this->getOwner());

        if ($canReply && is_numeric($settings->maxReplyDepth)) {
            $maxReplyDepth = (int)$settings->maxReplyDepth;

            // Check against plugin reply level settings
            if (($this->level - 1) >= $settings->maxReplyDepth) {
                $canReply = false;
            }
        }

        return $canReply;
    }

    public function canEdit(): bool
    {
        $currentUser = Comments::$plugin->getService()->getUser();

        // Only logged-in users can edit a comment
        if (!$currentUser) {
            return false;
        }

        // We better have an author
        if (!$this->getAuthor()) {
            return false;
        }

        // Check that user is trying to edit their own comment
        if ($currentUser->id !== $this->getAuthor()->id) {
            return false;
        }

        return true;
    }

    public function canTrash(): bool
    {
        $currentUser = Comments::$plugin->getService()->getUser();

        // Only logged in users can upvote a comment
        if (!$currentUser) {
            return false;
        }

        // We better have an author
        if (!$this->getAuthor()) {
            return false;
        }

        // Check that user is trying to trash their own comment
        if ($currentUser->id !== $this->getAuthor()->id) {
            return false;
        }

        return true;
    }

    public function getUser(): bool|User|null
    {
        if ($this->_user === null) {
            if ($this->userId === null) {
                return null;
            }

            if (($this->_user = Craft::$app->getUsers()->getUserById($this->userId)) === null) {
                $this->_user = false;
            }
        }

        return $this->_user ?: null;
    }

    public function setUser(User $user = null): void
    {
        $this->_user = $user;
    }

    public function isSubscribed(): bool
    {
        $currentUser = Comments::$plugin->getService()->getUser();
        $userId = $currentUser->id ?? null;

        return Comments::$plugin->getSubscribe()->hasSubscribed($this->ownerId, $this->ownerSiteId, $userId, $this->id);
    }

    public function hasFlagged(): bool
    {
        $currentUser = Comments::$plugin->getService()->getUser();

        return Comments::$plugin->getFlags()->hasFlagged($this, $currentUser);
    }

    public function isFlagged(): bool
    {
        return Comments::$plugin->getFlags()->isOverFlagThreshold($this);
    }

    public function getFlags(): int
    {
        return Comments::$plugin->getFlags()->getFlagsByCommentId($this->id);
    }

    public function canFlag(): bool
    {
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();

        // If flagging is plain disabled
        if (!$settings->allowFlagging) {
            return false;
        }

        // Only guests can flag if the setting is configured to do so
        if (!$currentUser && !$settings->allowGuestFlagging) {
            return false;
        }

        return true;
    }

    public function getVotes(): float|int
    {
        $upvotes = Comments::$plugin->getVotes()->getUpvotesByCommentId($this->id);
        $downvotes = Comments::$plugin->getVotes()->getDownvotesByCommentId($this->id);

        return $upvotes - $downvotes;
    }

    public function isPoorlyRated(): bool
    {
        return Comments::$plugin->getVotes()->isOverDownvoteThreshold($this);
    }

    public function getAllVotes(): int
    {
        return Comments::$plugin->getVotes()->getVotesByCommentId($this->id);
    }

    public function getUpvotes(): int
    {
        return Comments::$plugin->getVotes()->getUpvotesByCommentId($this->id);
    }

    public function getDownvotes(): int
    {
        return Comments::$plugin->getVotes()->getDownvotesByCommentId($this->id);
    }

    public function canVote(): bool
    {
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();

        // If voting is plain disabled
        if (!$settings->allowVoting) {
            return false;
        }

        // Only guests can vote if the setting is configured to do so
        if (!$currentUser && !$settings->allowGuestVoting) {
            return false;
        }

        // Has the downvote threshold been met, and the config setting set?
        if ($settings->hideVotingForThreshold && $this->isPoorlyRated()) {
            return false;
        }

        return true;
    }

    public function beforeValidate(): bool
    {
        $settings = Comments::$plugin->getSettings();

        // If saving via a queue (ResaveElements() for instance), skip validation
        if ($this->scenario === Element::SCENARIO_ESSENTIALS) {
            return parent::beforeValidate();
        }

        // If saving via GraphQL, a valid token means we don’t have to check for form fields (honeypot, etc.)
        if ($this->scenario === self::SCENARIO_LIVE) {
            if (!Comments::$plugin->getSecurity()->checkSecurityPolicy($this)) {
                $this->addError('comment', Craft::t('comments', 'Comment blocked due to security policy.'));
            }

            if (!Comments::$plugin->getSecurity()->checkCommentLength($this)) {
                $this->addError('comment', Craft::t('comments', 'Comment must be shorter than {limit} characters.', [
                    'limit' => $settings->securityMaxLength,
                ]));
            }

            // Protect against Guest submissions, if turned off
            if (!$settings->allowGuest && !$this->userId) {
                $this->addError('comment', Craft::t('comments', 'Must be logged in to comment.'));
            }

            // Additionally, check for user email/name, which is compulsory for guests
            if ($settings->guestRequireEmailName && !$this->userId) {
                if (!$this->name) {
                    $this->addError('name', Craft::t('comments', 'Name is required.'));
                }

                if (!$this->email) {
                    $this->addError('email', Craft::t('comments', 'Email is required.'));
                }
            }

            // Is someone sneakily making a comment on a non-allowed element through some black magic POST-ing?
            if (!Comments::$plugin->getComments()->checkPermissions($this->getOwner())) {
                $this->addError('comment', Craft::t('comments', 'Comments are disabled for this element.'));
            }

            // Is this user trying to edit/save/delete a comment that’s not their own?
            // This is permissible from the CP
            if ($this->id && !Craft::$app->getRequest()->getIsCpRequest()) {
                $currentUser = Comments::$plugin->getService()->getUser();

                if (empty($currentUser) || $currentUser->id !== $this->getAuthor()->id) {
                    $this->addError('comment', Craft::t('comments', 'Unable to modify another user’s comment.'));
                }
            }
        }

        // Skip for CP saving
        if ($this->scenario === self::SCENARIO_FRONT_END) {
            // Check if we're deleting or saving (add/edit). Things like spam checks can be disabled for deletion
            if ($this->getAction() === self::ACTION_SAVE) {
                // Let's check for spam!
                if (!Comments::$plugin->getProtect()->verifyFields() && $settings->enableSpamChecks) {
                    $this->addError('comment', Craft::t('comments', 'Form validation failed. Marked as spam.'));
                }

                // Check against any security keywords we've set. Can be words, IP's, User Agents, etc.
                if (!Comments::$plugin->getSecurity()->checkSecurityPolicy($this)) {
                    $this->addError('comment', Craft::t('comments', 'Comment blocked due to security policy.'));
                }

                // Check the maximum comment length.
                if (!Comments::$plugin->getSecurity()->checkCommentLength($this)) {
                    $this->addError('comment', Craft::t('comments', 'Comment must be shorter than {limit} characters.', [
                        'limit' => $settings->securityMaxLength,
                    ]));
                }

                // Protect against Guest submissions, if turned off
                if (!$settings->allowGuest && !$this->userId) {
                    $this->addError('comment', Craft::t('comments', 'Must be logged in to comment.'));
                }

                // Additionally, check for user email/name, which is compulsory for guests
                if ($settings->guestRequireEmailName && !$this->userId) {
                    if (!$this->name) {
                        $this->addError('name', Craft::t('comments', 'Name is required.'));
                    }

                    if (!$this->email) {
                        $this->addError('email', Craft::t('comments', 'Email is required.'));
                    }
                }

                // Is someone sneakily making a comment on a non-allowed element through some black magic POST-ing?
                if (!Comments::$plugin->getComments()->checkPermissions($this->getOwner())) {
                    $this->addError('comment', Craft::t('comments', 'Comments are disabled for this element.'));
                }
            }

            // Is this user trying to edit/save/delete a comment that’s not their own?
            // This is permissible from the CP
            if ($this->id && !Craft::$app->getRequest()->getIsCpRequest()) {
                $currentUser = Comments::$plugin->getService()->getUser();

                if ($currentUser->id !== $this->getAuthor()->id) {
                    $this->addError('comment', Craft::t('comments', 'Unable to modify another user’s comment.'));
                }
            }
        }

        // Must have an actual comment if required
        if (!trim($this->comment) && $this->_getCommentIsRequired()) {
            $this->addError('comment', Craft::t('comments', 'Comment must not be blank.'));
        }

        return parent::beforeValidate();
    }

    public function beforeSave(bool $isNew): bool
    {
        if ($this->_hasNewParent()) {
            if ($this->newParentId) {
                $parentNode = Comments::$plugin->getComments()->getCommentById($this->newParentId, $this->siteId);

                if (!$parentNode) {
                    throw new Exception('Invalid comment ID: ' . $this->newParentId);
                }
            } else {
                $parentNode = null;
            }

            $this->setParent($parentNode);
        }

        // Save the current status for later - remember to fetch it fresh, as the model has already been updated
        if ($this->id) {
            $originalElement = Craft::$app->getElements()->getElementById($this->id, Comment::class, $this->siteId);

            if ($originalElement) {
                $this->previousComment = $originalElement;
            }
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew): void
    {
        $settings = Comments::$plugin->getSettings();

        if (!$isNew) {
            $record = CommentRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid comment ID: ' . $this->id);
            }
        } else {
            $record = new CommentRecord();
            $record->id = $this->id;
        }

        $record->ownerId = $this->ownerId;
        $record->ownerSiteId = $this->ownerSiteId;
        $record->userId = $this->userId;
        $record->status = $this->status;
        $record->name = $this->name;
        $record->email = $this->email;
        $record->comment = $this->comment;
        $record->url = $this->url;
        $record->ipAddress = $this->ipAddress;
        $record->userAgent = $this->userAgent;
        $record->commentDate = $this->commentDate;

        if (!$this->commentDate) {
            $record->commentDate = new DateTime();
        }

        $record->save(false);

        $this->id = $record->id;
        $this->commentDate = DateTimeHelper::toDateTime($record->commentDate);

        $previousStatus = $this->previousComment ? $this->previousComment->status : null;
        $previousCommentText = $this->previousComment ? $this->previousComment->comment : null;

        if ($isNew) {
            // Should we send moderator emails?
            if ($settings->notificationModeratorEnabled && $this->status == self::STATUS_PENDING) {
                Comments::$plugin->getComments()->sendNotificationEmail('moderator', $this);
            } else {
                Comments::log('Moderator Notifications disabled.');
            }

            // Don't send reply or author emails if we're moderating first
            if ($settings->doesRequireModeration()) {
                Comments::log('Not sending reply or author notification - marked as pending (to be moderated).');
            } else {
                // Should we send a Notification email to the author of this comment?
                if ($settings->notificationAuthorEnabled) {
                    Comments::$plugin->getComments()->sendNotificationEmail('author', $this);
                } else {
                    Comments::log('Author Notifications disabled.');
                }

                // If a reply to another comment, should we send a Notification email
                // to the author of the original comment?
                if ($settings->notificationReplyEnabled) {
                    if ($this->_isReplying()) {
                        Comments::$plugin->getComments()->sendNotificationEmail('reply', $this);
                    }
                } else {
                    Comments::log('Reply Notifications disabled.');
                }

                // Do we need to auto-subscribe the user?
                if ($settings->notificationSubscribeAuto) {
                    $this->_saveNewSubscriber();
                }

                // Check for all users subscribed to notifications
                if ($settings->notificationSubscribeEnabled || $settings->notificationSubscribeAuto) {
                    Comments::$plugin->getComments()->sendNotificationEmail('subscribe', $this);
                }
            }

            // Send admin notifications
            if ($settings->notificationAdminEnabled) {
                Comments::$plugin->getComments()->sendNotificationEmail('admin', $this);
            }
        }

        // Check to see if we're moderating, and has just switch from pending to approved
        if ($previousStatus == self::STATUS_PENDING && $this->status == self::STATUS_APPROVED) {
            if ($settings->notificationModeratorApprovedEnabled) {
                Comments::$plugin->getComments()->sendNotificationEmail('moderator-approved', $this);
            } else {
                Comments::log('Moderator Approved Notifications disabled.');
            }

            // Should we send a Notification email to the author of this comment?
            if ($settings->notificationAuthorEnabled) {
                Comments::$plugin->getComments()->sendNotificationEmail('author', $this);
            } else {
                Comments::log('Author Notifications disabled.');
            }

            // If a reply to another comment, should we send a Notification email
            // to the author of the original comment?
            if ($settings->notificationReplyEnabled) {
                if ($this->_isReplying()) {
                    Comments::$plugin->getComments()->sendNotificationEmail('reply', $this);
                }
            } else {
                Comments::log('Reply Notifications disabled.');
            }

            // Do we need to auto-subscribe the user?
            if ($settings->notificationSubscribeAuto) {
                $this->_saveNewSubscriber();
            }

            // Check for all users subscribed to notifications
            if ($settings->notificationSubscribeEnabled || $settings->notificationSubscribeAuto) {
                Comments::$plugin->getComments()->sendNotificationEmail('subscribe', $this);
            }
        }

        // Are we editing an existing comment, and moderating comments is enabled (the comment will be pending)
        // and allow moderation-edit notifications? Send the moderators an edit notification.
        if (!$isNew && $settings->notificationModeratorEditEnabled && $this->status == self::STATUS_PENDING) {
            // Has the comment actually changed?
            if ($previousCommentText !== $this->comment) {
                Comments::$plugin->getComments()->sendNotificationEmail('moderator-edit', $this);
            }
        }

        if ($this->_hasNewParent()) {
            if (!$this->newParentId) {
                Craft::$app->getStructures()->appendToRoot(self::getStructureId(), $this);
            } else {
                Craft::$app->getStructures()->append(self::getStructureId(), $this, $this->getParent());
            }
        }

        parent::afterSave($isNew);
    }

    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this);
    }

    public function setEagerLoadedElements(string $handle, array $elements): void
    {
        if ($handle === 'user') {
            $this->_user = $elements[0] ?? false;
        } else if ($handle === 'owner') {
            $this->_owner = $elements[0] ?? false;
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }


    // Protected Methods
    // =========================================================================

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'ownerId':
            {
                $owner = $this->getOwner();

                if ($owner) {
                    $a = Html::a(Html::encode($owner->title), $owner->cpEditUrl);

                    return Template::raw($a);
                }

                return Craft::t('comments', '[Deleted element]');
            }
            case 'name':
            {
                return Html::encode($this->getAuthorName()) ?? '-';
            }
            case 'email':
            {
                return Html::encode($this->getAuthorEmail()) ?? '-';
            }
            case 'voteCount':
            {
                return $this->getVotes();
            }
            case 'flagCount':
            {
                return $this->hasFlagged() ? '<span class="status off"></span>' : '<span class="status"></span>';
            }
            case 'comment':
            {
                return Html::encode($this->getComment());
            }
            default:
            {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _hasNewParent(): bool
    {
        if ($this->_hasNewParent !== null) {
            return $this->_hasNewParent;
        }

        return $this->_hasNewParent = $this->_checkForNewParent();
    }

    private function _isReplying(): bool
    {
        return $this->newParentId || $this->getParent();
    }

    private function _checkForNewParent(): bool
    {
        // Is it a brand new node?
        if ($this->id === null) {
            return true;
        }

        // Was a new parent ID actually submitted?
        if ($this->newParentId === null) {
            return false;
        }

        // Is it set to the top level now, but it hadn't been before?
        if (!$this->newParentId && $this->level != 1) {
            return true;
        }

        // Is it set to be under a parent now, but didn't have one before?
        if ($this->newParentId && $this->level == 1) {
            return true;
        }

        // Is the newParentId set to a different node ID than its previous parent?
        $oldParentQuery = self::find();
        $oldParentQuery->ancestorOf($this);
        $oldParentQuery->ancestorDist(1);
        $oldParentQuery->status(null);
        $oldParentQuery->siteId($this->siteId);
        $oldParentQuery->status(null);
        $oldParentQuery->select('elements.id');
        $oldParentId = $oldParentQuery->scalar();

        return $this->newParentId != $oldParentId;
    }

    private function _saveNewSubscriber(): void
    {
        $currentUser = Comments::$plugin->getService()->getUser();

        $ownerId = $this->ownerId;
        $siteId = $this->siteId;
        $commentId = null;
        $userId = $currentUser->id ?? null;

        $subscribe = Comments::$plugin->getSubscribe()->getSubscribe($ownerId, $siteId, $userId, $commentId) ?? new Subscribe();
        $subscribe->ownerId = $ownerId;
        $subscribe->ownerSiteId = $siteId;
        $subscribe->commentId = $commentId;
        $subscribe->subscribed = true;

        // Okay if no user here, although required, the model validation will pick it up
        $subscribe->userId = $userId;

        Comments::$plugin->getSubscribe()->saveSubscribe($subscribe);
    }

    private function _getCommentIsRequired(): bool
    {
        // Default to true, mostly for backward compatibility, just in case for some reason
        // the field layout element isn't found.
        $isCommentRequired = true;

        // From the field layout designer, find if the comment is required
        if ($fieldLayout = $this->getFieldLayout()) {
            foreach ($fieldLayout->getTabs() as $tab) {
                foreach ($tab->elements as $element) {
                    if ($element instanceof CommentsFieldLayoutElement) {
                        $isCommentRequired = (bool)$element->required;
                    }
                }
            }
        }

        return $isCommentRequired;
    }

}
