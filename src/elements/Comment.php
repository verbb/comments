<?php
namespace verbb\comments\elements;

use verbb\comments\Comments;
use verbb\comments\compatibility\elements\LegacyComment;
use verbb\comments\compatibility\elements\LegacyStructure;
use verbb\comments\elements\actions\ApproveComments;
use verbb\comments\elements\actions\KeepComments;
use verbb\comments\elements\actions\MarkCommentsNotSpam;
use verbb\comments\elements\actions\MarkCommentsSpam;
use verbb\comments\elements\actions\RemoveComments;
use verbb\comments\elements\conditions\CommentCondition;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldLayoutElement;
use verbb\comments\formfields\AssetsType;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\helpers\StringHelper;
use verbb\comments\helpers\UploadPolicy;
use verbb\comments\records\Comment as CommentRecord;
use verbb\comments\services\CommentEditor;
use verbb\comments\services\Commenters;
use verbb\comments\services\FormFields;
use verbb\comments\services\Frontend;
use verbb\comments\web\assets\cp\CommentEditAsset;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\enums\Color;
use craft\fields\Assets as AssetsField;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\models\FieldLayout;
use craft\validators\SiteIdValidator;
use craft\web\CpScreenResponseBehavior;
use craft\web\UploadedFile;
use craft\web\View;

use yii\base\UserException;
use yii\db\Expression;
use yii\web\HttpException;
use yii\web\Response;

use DateTime;
use Exception;
use Throwable;

use TheIconic\NameParser\Parser;
use Twig\Markup;
use verbb\editor\RichText;

class Comment extends Element
{
    // Traits
    // =========================================================================

    use LegacyComment;
    use LegacyStructure;


    // Static Methods
    // =========================================================================

    /**
     * Whether the request is from someone using the control panel: a control panel request by a user who
     * can access it. Craft treats any request under the control panel's URL as a control panel request,
     * including one to an anonymous action, so the URL alone isn't enough.
     */
    public static function isControlPanelUser(): bool
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || !$request->getIsCpRequest()) {
            return false;
        }

        $user = Craft::$app->getUser()->getIdentity();

        return $user instanceof User && $user->can('accessCp');
    }

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
            self::STATUS_APPROVED => [
                'label' => Craft::t('comments', 'Approved'),
                // Teal, like a live entry, so published things share one colour across the CP.
                'color' => Color::Teal,
            ],
            self::STATUS_PENDING => [
                'label' => Craft::t('comments', 'Pending'),
                'color' => Color::Orange,
            ],
            self::STATUS_SPAM => [
                'label' => Craft::t('comments', 'Spam'),
                'color' => Color::Red,
            ],
            self::STATUS_REMOVED => [
                'label' => Craft::t('comments', 'Removed'),
                'color' => Color::Gray,
            ],
            // Keeps its moderation state in case it's restored, but isn't public.
            self::STATUS_AUTHOR_REMOVED => [
                'label' => Craft::t('comments', 'Deleted by author'),
                'color' => Color::Gray,
            ],
        ];
    }

    public static function find(): CommentQuery
    {
        return new CommentQuery(static::class);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(CommentCondition::class, [static::class]);
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

    protected static function defineSources(string $context = 'index'): array
    {
        $settings = Comments::$plugin->getSettings();
        $defaultSort = [$settings->sortDefaultKey, $settings->sortDefaultDirection];

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('comments', 'All comments'),
                'defaultSort' => $defaultSort,
            ],
        ];

        // Moderation happens here, in the index, rather than on a page of its own.
        $user = Craft::$app->getUser();

        if ($context === 'index' && ($user->getIsAdmin() || $user->checkPermission('comments-moderate'))) {
            $counts = Comments::$plugin->getModeration()->getQueueCounts();
            $sources[] = ['heading' => Craft::t('comments', 'Moderation')];
            $sources[] = [
                'key' => 'needs-review',
                'label' => Craft::t('comments', 'Needs review'),
                // A comment its author has deleted needs no decision, so it doesn't wait here.
                'criteria' => ['moderationState' => ModerationState::Pending->value, 'authorRemoved' => false],
                // Oldest first, so nothing waits longest by accident.
                'defaultSort' => ['commentDate', 'asc'],
                'badgeCount' => $counts['pending'],
            ];
            $sources[] = [
                'key' => 'reported',
                'label' => Craft::t('comments', 'Reported'),
                'criteria' => ['moderationState' => ModerationState::Approved->value, 'hasOpenReports' => true],
                'defaultSort' => ['commentDate', 'asc'],
                'badgeCount' => $counts['reported'],
            ];
            $sources[] = [
                'key' => 'spam',
                'label' => Craft::t('comments', 'Spam'),
                'criteria' => ['moderationState' => ModerationState::Spam->value],
                'defaultSort' => ['commentDate', 'desc'],
            ];
        }

        $sources[] = [
            'heading' => Craft::t('comments', 'Element Type'),
        ];

        $ownerTypes = [
            'entries' => Entry::class,
            'categories' => Category::class,
            'assets' => Asset::class,
            'users' => User::class,
        ];

        foreach ($ownerTypes as $key => $ownerType) {
            $sources[] = [
                'key' => "owner-type:$key",
                'label' => $ownerType::pluralDisplayName(),
                'criteria' => [
                    'ownerType' => $ownerType,
                ],
                'defaultSort' => ['commentDate', 'desc'],
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        $elementsService = Craft::$app->getElements();

        $actions = parent::defineActions($source);

        $user = Craft::$app->getUser();
        $canRemove = $user->getIsAdmin() || $user->checkPermission('comments-trash');

        // Approve, keep, remove, mark spam or not spam. Bulk moderation never deletes permanently.
        if ($user->getIsAdmin() || $user->checkPermission('comments-moderate')) {
            $decisions = match ($source) {
                'needs-review' => [ApproveComments::class, MarkCommentsSpam::class, RemoveComments::class],
                'reported' => [KeepComments::class, RemoveComments::class],
                'spam' => [MarkCommentsNotSpam::class],
                default => [ApproveComments::class, KeepComments::class, RemoveComments::class, MarkCommentsSpam::class, MarkCommentsNotSpam::class],
            };

            foreach ($decisions as $decision) {
                if ($decision !== RemoveComments::class || $canRemove) {
                    $actions[] = $decision;
                }
            }
        }

        if ($canRemove) {
            $actions[] = $elementsService->createAction([
                'type' => Restore::class,
                'successMessage' => Craft::t('comments', 'Comments restored.'),
                'partialSuccessMessage' => Craft::t('comments', 'Some comments restored.'),
                'failMessage' => Craft::t('comments', 'Comments not restored.'),
            ]);
        }

        // Craft's Delete is a soft delete, undone from the trash with Restore.
        if ($user->getIsAdmin() || $user->checkPermission('comments-delete')) {
            $actions[] = $elementsService->createAction([
                'type' => Delete::class,
                'confirmationMessage' => Craft::t('comments', 'Are you sure you want to delete the selected comments?'),
                'successMessage' => Craft::t('comments', 'Comments deleted.'),
            ]);
        }

        return $actions;
    }

    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'comment' => ['label' => Craft::t('comments', 'Comment')],
            'reason' => ['label' => Craft::t('comments', 'Reason')],
            'commentDate' => ['label' => Craft::t('comments', 'Date Posted')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            'ownerId' => ['label' => Craft::t('comments', 'Element')],
            'email' => ['label' => Craft::t('comments', 'Email')],
            'name' => ['label' => Craft::t('comments', 'Name')],
            'voteCount' => ['label' => Craft::t('comments', 'Votes')],
            'flagCount' => ['label' => Craft::t('comments', 'Reports')],
        ];

        // D1: the address column is admin-only.
        if (!CommentsHelper::canViewAddresses()) {
            unset($attributes['email']);
        }

        return $attributes;
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        // Moderation sources lead with why each comment needs someone.
        if (in_array($source, ['needs-review', 'reported', 'spam'], true)) {
            return ['reason', 'ownerId', 'commentDate'];
        }

        return [
            'ownerId',
            'commentDate',
        ];
    }

    /**
     * `authorEmail` stays indexed so admins can search by address. `CommentQuery` keeps it out of
     * everyone else's searches (D1).
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['rawComment', 'authorName', 'authorEmail'];
    }

    protected static function defineSortOptions(): array
    {
        $options = [
            'status' => Craft::t('comments', 'Status'),
            [
                'label' => Craft::t('comments', 'Thread'),
                'orderBy' => function(int $dir) {
                    $rootDirection = $dir === SORT_ASC ? 'ASC' : 'DESC';

                    return new Expression(
                        "[[comments_comments.rootId]] {$rootDirection}, " .
                        '[[comments_comments.depth]] ASC, ' .
                        '[[comments_comments.commentDate]] ASC, ' .
                        '[[elements.id]] ASC'
                    );
                },
                'attribute' => 'thread',
                'defaultDir' => 'desc',
            ],
            'comment' => Craft::t('comments', 'Comment'),
            [
                'label' => Craft::t('comments', 'Date Posted'),
                'orderBy' => 'commentDate',
                'attribute' => 'commentDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            'ownerId' => Craft::t('comments', 'Element'),
            'email' => Craft::t('comments', 'Email'),
            'name' => Craft::t('comments', 'Name'),
            'voteCount' => Craft::t('comments', 'Votes'),
            'flagCount' => Craft::t('comments', 'Reports'),
        ];

        // D1: ordering by address would let someone narrow down what it is.
        if (!CommentsHelper::canViewAddresses()) {
            unset($options['email']);
        }

        return $options;
    }

    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        if ($attribute === 'user') {
            $elementQuery->andWith('user');
        } elseif ($attribute === 'owner') {
            $elementQuery->andWith('owner');
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }


    // Constants
    // =========================================================================

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SPAM = 'spam';
    public const STATUS_REMOVED = 'removed';
    public const STATUS_AUTHOR_REMOVED = 'author-removed';

    public const SCENARIO_CP = 'cp';
    public const SCENARIO_FRONT_END = 'frontEnd';

    // The `comment` column is a MySQL `TEXT` column, which holds 65,535 bytes.
    public const COMMENT_MAX_BYTES = 65535;

    public const ACTION_SAVE = 'save';
    public const ACTION_DELETE = 'delete';

    // How Craft's search index names the author's address, which only admins may search by (D1).
    public const SEARCH_ATTRIBUTE_EMAIL = 'authoremail';


    // Properties
    // =========================================================================

    public ?int $ownerId = null;
    public ?int $ownerSiteId = null;
    public ?int $userId = null;
    public ?string $comment = null;
    public ?string $status = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $gifUrl = null;
    public ?int $gifWidth = null;
    public ?int $gifHeight = null;
    public ?string $url = null;
    public ?string $ipAddress = null;
    public ?string $userAgent = null;
    public ?DateTime $commentDate = null;

    // Comments 4 adjacency + content columns.
    public ?int $conversationId = null;
    public ?int $identityId = null;
    public ?int $parentId = null;
    public ?int $rootId = null;
    public ?int $depth = null;
    public ?string $moderationState = null;
    public ?string $contentFormat = null;
    /** Canonical body text from comments_comments.content (not Craft element field content). */
    public ?string $bodyContent = null;
    public ?DateTime $authorRemovedAt = null;
    public ?DateTime $editedAt = null;
    /** Why the comment is waiting or was marked as spam, as a HeldReason value. */
    public ?string $heldReason = null;
    /** What spam protection found when the comment was checked, as JSON. */
    public ?string $protection = null;

    /** When true, Comments::create() owns notification side effects. */
    public bool $skipAfterSaveSideEffects = false;

    /** Transient CP mode used to validate the mutually exclusive author controls. */
    public ?string $cpAuthorType = null;

    /** What the control panel's editor is holding back until this comment has saved. */
    public ?array $editorChanges = null;

    private ?string $_editorError = null;
    private ?string $_renderedBodyHtml = null;

    private ElementInterface|false|null $_owner = null;
    private ?User $_author = null;
    private mixed $_user = null;
    private ?array $_interactionTotals = null;
    private ?int $_openReportCount = null;
    private ?string $_action = null;
    // While submitted values are applied, the handles of the custom fields that may be filled in.
    private ?array $_formFieldHandles = null;
    // The layout element UIDs of the custom fields this submission may fill in, which are required for it.
    private ?array $_formFieldElementUids = null;
    // The custom fields the comment was posted with, as layout element UID to the mode each was in.
    private ?array $_postedFields = null;
    // Request file parameters that have passed the upload rules, so Craft may store them.
    private array $_checkedUploadParams = [];
    private ?string $_previousStatus = null;
    private ?string $_previousCommentText = null;


    // Public Methods
    // =========================================================================

    public function __toString(): string
    {
        return (string)$this->comment;
    }

    public function init(): void
    {
        parent::init();

        // Comments 3's `level`, in compatibility mode.
        $this->initLegacyLevel();

        if ($this->id && $this->_previousStatus === null) {
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

    /**
     * The comment this is a reply to, when it's approved, so a template walking up from approved comments
     * never shows a held or removed one. `getParentComment()` returns it whatever its status.
     */
    public function getParent(): ?ElementInterface
    {
        if (!$this->parentId) {
            return null;
        }

        return static::find()
            ->id((int)$this->parentId)
            ->siteId((int)$this->siteId)
            ->one();
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        // Ths is the only way I can figure out extra scenarios to work...
        $scenarios[self::SCENARIO_CP] ??= [];
        $scenarios[self::SCENARIO_FRONT_END] ??= [];

        return $scenarios;
    }

    public function getChipLabelHtml(): string
    {
        // The author's full name over the comment's excerpt, as on the Commenters screen.
        $name = Html::tag('strong', Html::encode($this->getAuthorName() ?: Craft::t('comments', 'Guest')), [
            'class' => 'comments-comment-author',
        ]);

        if ($this->isGuest()) {
            $name .= ' ' . Html::tag('span', Craft::t('comments', 'Guest'), ['class' => 'comments-guest-pill']);
        }

        $excerpt = Html::tag('span', Html::encode($this->getExcerpt(0, 110)), [
            'class' => 'comments-comment-excerpt',
        ]);

        return Html::tag('span', Html::tag('span', $name, ['class' => 'comments-comment-name']) . $excerpt, [
            'class' => 'comments-comment-label',
        ]);
    }

    public function getThumbHtml(int $size): ?string
    {
        $author = $this->getAuthor();

        if ($author instanceof User && $author->photoId) {
            return $author->getThumbHtml($size);
        }

        $name = (string)($this->getAuthorName() ?: Craft::t('comments', 'Guest'));

        return Html::tag('span', Html::encode(Commenters::initials($name)), [
            'class' => ['comments-avatar', $this->isGuest() ? 'comments-avatar--guest' : null],
            'data-tone' => Frontend::avatarTone($name),
            'aria-hidden' => 'true',
        ]);
    }

    public function showStatusIndicator(): bool
    {
        // The index's Status column and reason badges say it, so chips don't repeat it as a dot.
        return false;
    }

    /**
     * The comment this is a reply to, whatever its status, for code that decides itself what to show.
     */
    public function getParentComment(): ?self
    {
        if (!$this->parentId) {
            return null;
        }

        return Comments::$plugin->getComments()->getCommentById((int)$this->parentId, $this->siteId);
    }

    /**
     * A query for the approved direct replies, like any comment query's default. Add `status(null)`, or
     * another status, for replies in any state.
     */
    public function getReplies(): CommentQuery
    {
        return self::find()
            ->parentId((int)$this->id)
            ->siteId((int)$this->siteId);
    }

    public function canView(User $user): bool
    {
        return parent::canView($user) || $user->can('comments-view');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user) || $user->can('comments-edit')) {
            return true;
        }

        // Craft's restore action checks this permission too.
        return $this->trashed && $user->can('comments-trash');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        if ($user->can('comments-delete')) {
            return true;
        }

        return $this->userId === $user->id && $this->isWithinAuthorWindow($user);
    }

    public function getSupportedSites(): array
    {
        // Only support the site the comment is being made on
        $siteId = $this->siteId ?: Craft::$app->getSites()->getPrimarySite()->id;

        return [$siteId];
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

    /**
     * Apply custom field values posted from the site's own form, as `fields[<handle>]`. Only the fields
     * this person can fill in on this comment are read, however the request is put together, and a file
     * posted to an Assets field has to pass Comments' upload rules before Craft stores it.
     */
    public function setFormFieldValuesFromRequest(string $paramNamespace = 'fields'): void
    {
        $formFields = Comments::$plugin->getFormFields();
        $user = Comments::$plugin->getService()->getUser();
        $user = $user instanceof User ? $user : null;
        $elements = $this->id
            ? $formFields->editable($this, $user)
            : $formFields->collectable($formFields->prospect((int)$this->ownerId, (int)($this->ownerSiteId ?: $this->siteId), $this->parentId ? self::find()->id($this->parentId)->status(null)->siteId('*')->one() : null, $user), $user);
        $handles = array_map(static fn($element): string => $element->getField()->handle, $elements);

        foreach ($elements as $element) {
            $field = $element->getField();

            if ($field instanceof AssetsField) {
                $this->_checkRequestUploads($field, ($paramNamespace !== '' ? $paramNamespace . '.' : '') . $field->handle);
            }
        }

        $this->setFormFieldElements(array_keys($elements));
        $formFields->recordPostedModes($this, array_keys($elements));
        $this->applyFormFieldValues(array_values($handles), fn() => $this->setFieldValuesFromRequest($paramNamespace));
    }

    /**
     * Runs `$apply` with only the custom fields in `$handles` writable, as the site's form, the data API
     * and GraphQL set them. An Assets field then only takes the files it already has and the person's own
     * uploads for it.
     */
    public function applyFormFieldValues(array $handles, callable $apply): mixed
    {
        $this->_formFieldHandles = $handles;

        try {
            return $apply();
        } finally {
            $this->_formFieldHandles = null;
        }
    }

    /**
     * The layout element UIDs of the custom fields this submission may fill in. Their own Required
     * setting applies on the site only to these.
     */
    public function setFormFieldElements(array $uids): void
    {
        $this->_formFieldElementUids = array_values($uids);
    }

    /**
     * The layout element UIDs of the custom fields the comment was posted with, or null for a comment
     * from before Comments recorded them.
     */
    public function getPostedFieldUids(): ?array
    {
        return $this->_postedFields === null ? null : array_keys($this->_postedFields);
    }

    /**
     * The custom fields the comment was posted with, as layout element UID to the mode each was in then,
     * `collect` or `collect-and-show`, or null for a comment from before Comments recorded them. A value is only shown
     * publicly if it was entered while its field was shown.
     */
    public function getPostedFieldModes(): ?array
    {
        return $this->_postedFields;
    }

    public function setPostedFieldModes(?array $modes): void
    {
        if ($modes === null) {
            $this->_postedFields = null;

            return;
        }

        $clean = [];

        foreach ($modes as $uid => $mode) {
            // A list of UIDs, as development builds stored it, counts as collected.
            if (is_int($uid) && is_string($mode)) {
                $clean[$mode] = FormFields::MODE_COLLECT;
            } elseif (is_string($uid)) {
                $clean[$uid] = $mode === FormFields::MODE_COLLECT_AND_SHOW ? FormFields::MODE_COLLECT_AND_SHOW : FormFields::MODE_COLLECT;
            }
        }

        $this->_postedFields = $clean;
    }

    /**
     * Set from the `postedFields` column, which holds them as JSON.
     */
    public function setPostedFields(mixed $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->setPostedFieldModes(is_array($value) ? $value : null);
    }

    public function setFieldValueFromRequest(string $fieldHandle, mixed $value): void
    {
        $this->_assertFormFieldValue($fieldHandle, $value);

        parent::setFieldValueFromRequest($fieldHandle, $value);
    }

    /**
     * Craft 5.10's GraphQL mutations set custom fields through here rather than
     * `setFieldValueFromRequest()`, so the same rules apply on both.
     */
    public function setFieldValue(string $fieldHandle, mixed $value): void
    {
        $this->_assertFormFieldValue($fieldHandle, $value);

        parent::setFieldValue($fieldHandle, $value);
    }

    public function getRawComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): ?string
    {
        return $this->authorRemovedAt ? self::STATUS_AUTHOR_REMOVED : $this->status;
    }

    public function getComment(): ?string
    {
        // Emoji stored as shortcodes are turned back as the comment loads, in `CommentQuery::createElement()`.
        return (string)$this->comment;
    }

    public function isEditorContent(): bool
    {
        return $this->contentFormat === ContentFormat::Rich->value;
    }

    /**
     * The canonical Editor document of a rich comment, for clients that edit rich comments themselves.
     * GraphQL returns it as `contentDocument`.
     */
    public function getContentDocument(): ?string
    {
        return $this->isEditorContent() ? $this->bodyContent : null;
    }

    public function getContentHtml(): ?string
    {
        return $this->isEditorContent() ? (string)$this->getRenderedBodyHtml() : null;
    }

    public function getRenderedBodyHtml(): Markup
    {
        if (!$this->isEditorContent() || !$this->bodyContent) {
            return Template::raw('');
        }

        if ($this->_renderedBodyHtml !== null) {
            return Template::raw($this->_renderedBodyHtml);
        }

        try {
            $decoded = Json::decode($this->bodyContent);
        } catch (\Throwable) {
            return Template::raw(nl2br(htmlspecialchars((string)$this->comment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        }

        // Not `normalize()`, which enforces limits: lowering one shouldn't turn old comments into page errors.
        $html = \verbb\editor\Editor::getInstance()->getDocuments()->toHtml(
            RichText::from($decoded),
            EditorContexts::forComment($this),
            associatedAssetIds: Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$this->id),
        );

        return Template::raw($html);
    }

    /**
     * For when a whole page of comments has been rendered at once.
     */
    public function setRenderedBodyHtml(string $html): void
    {
        $this->_renderedBodyHtml = $html;
    }

    /**
     * For this comment's editor in the control panel.
     */
    public function getEditorOptions(): array
    {
        return EditorContexts::browserOptions('comments.edit', (int)$this->ownerId, (int)$this->ownerSiteId, (int)$this->id);
    }

    /**
     * Whether the control panel edits this comment in Editor: a rich comment, or a plain one while rich
     * authoring is on, as its author would get editing it on the site. A plain one only becomes rich
     * once its text is changed.
     */
    public function editsInEditor(): bool
    {
        return $this->isEditorContent() || Comments::$plugin->getSettings()->isRichAuthoringEnabled();
    }

    /**
     * The document an editor starts from. It's the stored one with its media and mentions filled in,
     * which the editor displays but leaves out again when it saves. A plain comment starts as its text,
     * in paragraphs.
     */
    public function getEditorFormContent(): ?string
    {
        if (!$this->isEditorContent()) {
            return Json::encode(RichText::fromText((string)$this->comment)->getValue());
        }

        if (!$this->bodyContent) {
            return null;
        }

        $richText = \verbb\editor\Editor::getInstance()->getDocuments()->resolve(
            RichText::from($this->bodyContent),
            EditorContexts::forComment($this),
            Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$this->id),
        );

        return Json::encode($richText->getValue());
    }

    public function getExcerpt($startPos = 0, $maxLength = 100): ?string
    {
        $comment = StringHelper::safeTruncate((string)$this->getComment(), $startPos + $maxLength, '…');

        return $startPos > 0 ? StringHelper::mb_substr($comment, $startPos) : $comment;
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

    /**
     * The custom field values this comment shows publicly, as `comment.shownFields` in Twig: the fields
     * set to **Collect and show** that it was posted with, each with its `handle`, `label`, `type`,
     * `placement`, raw `value` and formatted `text`, the same as the data API's `fields`. A field's
     * value read by its handle, such as `comment.orderNumber`, is the value whatever the field's mode,
     * so templates showing values to everyone use this instead.
     */
    public function getShownFields(): array
    {
        return Comments::$plugin->getFormFields()->shownFields($this);
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
            // What emails greet people with.
            $author->firstName = $author->fullName;

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
            return $author->getName();
        }

        return $this->name;
    }

    public function getAuthorEmail(): ?string
    {
        if ($this->email) {
            return $this->email;
        }

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

    public function setOwner(?ElementInterface $owner = null, bool $loaded = false): void
    {
        $this->_owner = $loaded && !$owner ? false : $owner;
    }

    public function getOwnerType(): string
    {
        if ($owner = $this->getOwner()) {
            return $owner::class;
        }

        if (!$this->ownerId) {
            return '';
        }

        return (string)(new Query())
            ->select(['type'])
            ->from(['{{%elements}}'])
            ->where(['id' => $this->ownerId])
            ->scalar();
    }

    public function canReply(): bool
    {
        $settings = Comments::$plugin->getSettings();

        // The same for every comment on the owner, so it's asked once per request.
        $canReply = Comments::$plugin->getConversations()->canComment($this->getOwner());

        if ($canReply && is_numeric($settings->maxReplyDepth)) {
            $maxReplyDepth = (int)$settings->maxReplyDepth;

            // Roots have depth 0, so the adjacency depth maps directly to the configured limit.
            if ((int)$this->depth >= $maxReplyDepth) {
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

        // Spam and removed comments are a moderator's call, and an author-removed comment is gone.
        if ($this->authorRemovedAt || !$this->isWithinAuthorWindow($currentUser)) {
            return false;
        }

        if (!$this->isDiscussionOpenForEdits()) {
            return false;
        }

        return in_array(Comments::$plugin->getModeration()->currentState($this), [
            ModerationState::Approved,
            ModerationState::Pending,
        ], true);
    }

    /**
     * Whether the comment's discussion takes edits: one that's closed or turned off doesn't, the same as
     * saving an edit checks.
     */
    public function isDiscussionOpenForEdits(): bool
    {
        $config = $this->ownerId && $this->ownerSiteId
            ? Comments::$plugin->getConversations()->getConfigForOwner((int)$this->ownerId, (int)$this->ownerSiteId)
            : null;

        return (bool)$config?->isCommentingEnabled();
    }

    /**
     * Whether the comment's author can still change it. People who can edit or delete anyone's comments aren't bound by this.
     */
    public function isWithinAuthorWindow(User $user): bool
    {
        $minutes = (int)Comments::$plugin->getSettings()->authorEditWindow;

        // Staff skip the window, but through a member token only from an origin that allows moderation.
        if ($minutes < 1 || !$this->commentDate || ($user->can('comments-edit') && Comments::$plugin->getSignIn()->allowsModeration($user))) {
            return true;
        }

        return time() - $this->commentDate->getTimestamp() < $minutes * 60;
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

    public function setUser(?User $user = null, bool $loaded = false): void
    {
        $this->_user = $loaded && !$user ? false : $user;
        $this->_author = null;
    }

    public function isSubscribed(): bool
    {
        if (!$this->conversationId) {
            return false;
        }

        $identity = $this->_resolveViewerIdentity();

        return $identity
            ? Comments::$plugin->getSubscriptions()->isSubscribed(
                (int)$this->conversationId,
                (int)$identity->id,
                (int)$this->id,
                SubscriptionType::Replies,
            )
            : false;
    }

    public function hasFlagged(): bool
    {
        $identity = $this->_resolveViewerIdentity();

        if (!$identity) {
            return false;
        }

        return Comments::$plugin->getReports()->getOpenForIdentity((int)$this->id, (int)$identity->id) !== null;
    }

    /**
     * Whether the current visitor has voted on this comment, or liked it when Interaction Mode only allows likes.
     */
    public function hasVoted(): bool
    {
        return $this->_viewerInteraction() !== null;
    }

    public function hasUpvoted(): bool
    {
        return $this->_viewerInteraction() === InteractionType::Upvote;
    }

    public function hasDownvoted(): bool
    {
        return $this->_viewerInteraction() === InteractionType::Downvote;
    }

    public function isFlagged(): bool
    {
        return $this->getFlags() > 0;
    }

    /**
     * Whether the comment has at least **Hide After Downvotes** downvotes, as Comments 3 had it.
     */
    public function isPoorlyRated(): bool
    {
        $limit = Comments::$plugin->getSettings()->getDownvoteCommentLimit();

        return $limit !== null && $this->getDownvotes() >= $limit;
    }

    /**
     * Why the thread folds this comment away, as `reports` or `downvotes`, or null when it doesn't. A
     * folded comment is still public, and its moderation state is unchanged; readers open it themselves.
     */
    public function getFoldReason(): ?string
    {
        $reports = Comments::$plugin->getSettings()->getFlaggedCommentLimit();

        if ($reports !== null && $this->getFlags() >= $reports) {
            return 'reports';
        }

        return $this->isPoorlyRated() ? 'downvotes' : null;
    }

    /**
     * The author's net votes across their approved comments, or 0 for a guest, as Comments 3's
     * `getAuthorScore()` was.
     */
    public function getAuthorScore(): int
    {
        return $this->userId ? Comments::$plugin->getInteractions()->getAuthorScore((int)$this->userId) : 0;
    }

    public function getFlags(): int
    {
        // The count its query loaded, unless a report was made or settled during this request.
        if ($this->_openReportCount !== null && !Comments::$plugin->getReports()->hasChangedInRequest((int)$this->id)) {
            return $this->_openReportCount;
        }

        $counts = Comments::$plugin->getReports()->getOpenCounts([(int)$this->id]);

        return $counts[(int)$this->id] ?? 0;
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
        $totals = $this->_getInteractionTotals();

        return $totals['upvote'] - $totals['downvote'];
    }

    public function setInteractionTotals(array $totals): void
    {
        $this->_interactionTotals = [
            'upvote' => (int)($totals['upvote'] ?? 0),
            'downvote' => (int)($totals['downvote'] ?? 0),
        ];
    }

    /**
     * Whether a query loaded this comment's vote totals with it, and no vote has changed them since,
     * so reading them costs nothing.
     */
    public function hasInteractionTotals(): bool
    {
        return $this->_interactionTotals !== null && !Comments::$plugin->getInteractions()->hasChangedInRequest((int)$this->id);
    }

    public function setOpenReportCount(int $count): void
    {
        $this->_openReportCount = $count;
    }

    public function getAllVotes(): int
    {
        $totals = $this->_getInteractionTotals();

        return ($totals['upvote'] ?? 0) + ($totals['downvote'] ?? 0);
    }

    public function getUpvotes(): int
    {
        return $this->_getInteractionTotals()['upvote'] ?? 0;
    }

    public function getDownvotes(): int
    {
        return $this->_getInteractionTotals()['downvote'] ?? 0;
    }

    public function canVote(): bool
    {
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();

        // If interactions are plain disabled
        if (!$settings->resolveInteractionMode()->isEnabled()) {
            return false;
        }

        // Only guests can vote if the setting is configured to do so
        if (!$currentUser && !$settings->allowGuestVoting) {
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

        // Element validation is deterministic; application services run request-bound protection.
        if ($this->scenario === self::SCENARIO_LIVE) {
            if (!Comments::$plugin->getSecurity()->checkSecurityPolicy($this)) {
                $this->addError('comment', Craft::t('comments', 'Comment blocked due to security policy.'));
            }

            if (!Comments::$plugin->getSecurity()->checkCommentLength($this)) {
                $this->addError('comment', Craft::t('comments', 'Comment must be {limit} characters or fewer.', [
                    'limit' => $settings->securityMaxLength,
                ]));
            }

            $config = $this->ownerId && $this->ownerSiteId
                ? Comments::$plugin->getConversations()->getConfigForOwner((int)$this->ownerId, (int)$this->ownerSiteId)
                : null;

            // Protect against guest submissions when disabled for this conversation.
            if ((!$config || !$config->allowGuests) && !$this->userId) {
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

            // Effective configuration includes source policy and any explicit element override.
            if (!$config?->isCommentingEnabled()) {
                $this->addError('comment', Craft::t('comments', 'Comments are disabled for this element.'));
            }

            // Is this user trying to edit/save/delete a comment that’s not their own?
            // This is permissible from the CP, for someone who can use it
            if ($this->id && !self::isControlPanelUser()) {
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
                // Check against any security keywords we've set. Can be words, IP's, User Agents, etc.
                if (!Comments::$plugin->getSecurity()->checkSecurityPolicy($this)) {
                    $this->addError('comment', Craft::t('comments', 'Comment blocked due to security policy.'));
                }

                // Check the maximum comment length.
                if (!Comments::$plugin->getSecurity()->checkCommentLength($this)) {
                    $this->addError('comment', Craft::t('comments', 'Comment must be {limit} characters or fewer.', [
                        'limit' => $settings->securityMaxLength,
                    ]));
                }

                $config = $this->ownerId && $this->ownerSiteId
                    ? Comments::$plugin->getConversations()->getConfigForOwner((int)$this->ownerId, (int)$this->ownerSiteId)
                    : null;

                // Protect against guest submissions when disabled for this conversation.
                if ((!$config || !$config->allowGuests) && !$this->userId) {
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

                // Effective configuration includes source policy and any explicit element override.
                if (!$config?->isCommentingEnabled()) {
                    $this->addError('comment', Craft::t('comments', 'Comments are disabled for this element.'));
                }

                // Are they a guest trying to comment using a users details?
                if (!$this->userId && !empty($this->email)) {
                    $matchedUser = User::find()->email(Db::escapeParam($this->email))->status(null)->one();

                    if ($matchedUser) {
                        $this->addError('comment', Craft::t('comments', 'Unauthorized.'));
                    }
                }

                // Are they logged in, but altered their email through POST manipulation?
                if ($user = $this->getUser()) {
                    $this->email = null;
                    $this->name = null;
                }
            }

            // Is this user trying to edit/save/delete a comment that’s not their own?
            // This is permissible from the CP, for someone who can use it
            if ($this->id && !self::isControlPanelUser()) {
                $currentUser = Comments::$plugin->getService()->getUser();

                if ($currentUser?->id !== $this->getAuthor()?->id) {
                    $this->addError('comment', Craft::t('comments', 'Unable to modify another user’s comment.'));
                }
            }
        }

        // Must have an actual comment if required. A comment can be just its GIF.
        // A rich comment can be just an image, and whether it's blank was settled when it was normalized.
        if (!trim((string)$this->comment) && !$this->gifUrl && (!$this->isEditorContent() || !$this->bodyContent) && $this->_getCommentIsRequired()) {
            $this->addError('comment', Craft::t('comments', 'Comment must not be blank.'));
        }

        // What the columns hold, so a long guest name or comment is refused rather than failing to save.
        // Checked here because the site's scenario has no attribute rules.
        foreach (['name' => Craft::t('comments', 'Name'), 'email' => Craft::t('comments', 'Email')] as $attribute => $label) {
            if (mb_strlen((string)$this->$attribute) > 255) {
                $this->addError($attribute, Craft::t('yii', '{attribute} should contain at most {max, number} {max, plural, one{character} other{characters}}.', ['attribute' => $label, 'max' => 255]));
            }
        }

        if (strlen(StringHelper::emojiForDb((string)$this->comment)) > self::COMMENT_MAX_BYTES) {
            $this->addError('comment', Craft::t('comments', 'Comment is too long.'));
        }

        return parent::beforeValidate();
    }

    public function beforeSave(bool $isNew): bool
    {
        // Snapshot persisted values before this element writes them. Craft's element cache may return
        // the same in-memory instance, so reloading the element is not a reliable previous-value source.
        $originalRecord = null;

        if ($this->id) {
            $originalRecord = CommentRecord::findOne($this->id);

            if ($originalRecord) {
                $this->_previousStatus = $originalRecord->status;
                $this->_previousCommentText = StringHelper::shortcodesToEmoji((string)$originalRecord->comment);
            }
        }

        $this->_prepareCanonicalRelations($isNew);

        // Keep direct/legacy plain-text element saves v4-safe in both directions. A caller may update
        // `comment` (the v3 property) or `bodyContent` (the canonical v4 property); if the other side
        // still matches the persisted row, it has not been intentionally changed and can be synced.
        if ($originalRecord && $this->contentFormat === ContentFormat::Plain->value) {
            // The row may hold emoji as shortcodes, and the comment holds them as emoji.
            $originalComment = StringHelper::shortcodesToEmoji((string)$originalRecord->comment);
            $originalContent = StringHelper::shortcodesToEmoji((string)$originalRecord->content);
            $commentChanged = (string)$this->comment !== $originalComment;
            $contentChanged = (string)$this->bodyContent !== $originalContent;

            if ($commentChanged && !$contentChanged) {
                $this->bodyContent = (string)$this->comment;
            } elseif ($contentChanged && !$commentChanged) {
                $this->comment = (string)$this->bodyContent;
            }
        }

        if ($originalRecord && $this->_contentChangedFrom($originalRecord)) {
            $this->editedAt = new DateTime();
        }

        // Before the fields' own `beforeElementSave()`, where Craft's Assets fields store uploaded files.
        $this->_refuseUncheckedUploads();

        // A save in the control panel records each field that's on the site's form as it is now, so a
        // moderator's save can show a value the way an author's edit can.
        if (!$this->propagating && $this->getScenario() !== self::SCENARIO_FRONT_END && self::isControlPanelUser()) {
            $formFields = Comments::$plugin->getFormFields();
            $formFields->recordPostedModes($this, array_keys(array_filter(
                $formFields->settings(),
                static fn(array $setting): bool => $setting['mode'] !== FormFields::MODE_OFF,
            )));
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew): void
    {
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
        $record->name = StringHelper::emojiForDb($this->name);
        $record->email = $this->email;
        $record->comment = StringHelper::emojiForDb($this->comment);
        $record->gifUrl = $this->gifUrl;

        if ($record->hasAttribute('gifWidth')) {
            $record->gifWidth = $this->gifUrl ? $this->gifWidth : null;
            $record->gifHeight = $this->gifUrl ? $this->gifHeight : null;
        }

        $record->url = $this->url;
        $record->ipAddress = $this->ipAddress;
        // Some in-app browsers send a longer User-Agent than the column holds.
        $record->userAgent = $this->userAgent !== null ? mb_substr($this->userAgent, 0, 255) : null;
        $record->commentDate = $this->commentDate;
        $record->editedAt = $this->editedAt;
        $record->heldReason = $this->heldReason;
        $record->protection = $this->protection;

        if ($this->_postedFields !== null && $record->hasAttribute('postedFields')) {
            $record->postedFields = Json::encode((object)$this->_postedFields);
        }

        // Persist v4 columns when set (Comments::create and migrator).
        if ($this->conversationId !== null) {
            $record->conversationId = $this->conversationId;
        }

        if ($this->identityId !== null) {
            $record->identityId = $this->identityId;
        }

        // parentId may be null for roots; still write when conversationId is present.
        if ($this->conversationId !== null) {
            $record->parentId = $this->parentId;
            $record->rootId = $this->rootId;
            $record->depth = $this->depth;
            $record->moderationState = $this->moderationState;
            $record->contentFormat = $this->contentFormat;
            $record->content = $this->isEditorContent()
                ? StringHelper::jsonEmojiForDb($this->bodyContent)
                : StringHelper::emojiForDb($this->bodyContent);
            $record->authorRemovedAt = $this->authorRemovedAt;
        }

        if (!$this->commentDate) {
            $record->commentDate = new DateTime();
        }

        $record->save(false);

        $this->id = $record->id;
        $this->commentDate = DateTimeHelper::toDateTime($record->commentDate);

        if ($isNew && !$this->rootId) {
            $this->rootId = (int)$this->id;
            $record->rootId = $this->rootId;
            $record->save(false);
        }

        $previousStatus = $this->_previousStatus;
        $previousCommentText = $this->_previousCommentText;

        // Its author may now have reached Maximum Comments Per User.
        Comments::$plugin->getConversations()->clearCommentingCache();

        if ($this->skipAfterSaveSideEffects) {
            parent::afterSave($isNew);

            return;
        }

        // A decision from the comment's page is applied after the save, and queues its own notifications,
        // so only what this save itself wrote counts as a direct approval below.
        $savedStatus = $this->status;

        Comments::$plugin->getCommentEditor()->finalizeSave($this);

        if ($isNew) {
            $state = ModerationState::tryFrom((string)$this->moderationState) ?? match ($this->status) {
                self::STATUS_PENDING => ModerationState::Pending,
                self::STATUS_SPAM => ModerationState::Spam,
                self::STATUS_REMOVED => ModerationState::Removed,
                default => ModerationState::Approved,
            };

            Comments::$plugin->getSubscriptions()->applyDefaultForComment($this);
            Comments::$plugin->getNotifications()->queueForNewComment($this, $state);
        }

        // Direct element saves still pass notification timing through the canonical orchestrator.
        if ($previousStatus == self::STATUS_PENDING && $savedStatus == self::STATUS_APPROVED) {
            Comments::$plugin->getNotifications()->queueAfterModeration(
                $this,
                ModerationState::Pending,
                ModerationState::Approved,
                'approve',
                Craft::$app->getUser()->getId(),
            );
        }

        if (!$isNew
            && $this->getScenario() === self::SCENARIO_FRONT_END
            && $this->status == self::STATUS_PENDING
            && $previousCommentText !== $this->comment
        ) {
            Comments::$plugin->getNotifications()->queueForModeratorEdit($this);
        }

        parent::afterSave($isNew);
    }

    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Soft deletes keep the row, so the hierarchy foreign keys are untouched.
        if (!$this->hardDelete) {
            return true;
        }

        $id = (int)$this->id;

        // Permanently deleting a thread's parent is a deliberate act, so the replies go first.
        $hasDescendants = (new Query())
            ->from('{{%comments_comments}}')
            ->where(['or', ['parentId' => $id], ['rootId' => $id]])
            ->andWhere(['not', ['id' => $id]])
            ->exists();

        if ($hasDescendants) {
            $this->addError('id', Craft::t('comments', 'This comment has replies and cannot be permanently deleted. Delete the replies first.'));

            return false;
        }

        return true;
    }

    public function afterDelete(): void
    {
        // Both soft and hard deletes remove a Comment from the public count, so the
        // Conversation aggregate has to be recomputed either way.
        $this->_rebuildConversationAggregates();

        // Craft's own Delete and Restore change what's waiting, and the index sends the badges in the same request.
        Comments::$plugin->getModeration()->clearQueueCounts();

        parent::afterDelete();
    }

    public function afterRestore(): void
    {
        $this->_rebuildConversationAggregates();
        Comments::$plugin->getModeration()->clearQueueCounts();

        parent::afterRestore();
    }

    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this);
    }

    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle === 'user') {
            $this->_user = $elements[0] ?? null;
        } elseif ($handle === 'owner') {
            $this->_owner = $elements[0] ?? null;
        } else {
            parent::setEagerLoadedElements($handle, $elements, $plan);
        }
    }

    public function setAttributesFromRequest(array $values): void
    {
        try {
            Comments::$plugin->getCommentEditor()->applyRequest($this);
        } catch (HttpException $e) {
            throw $e;
        } catch (UserException $e) {
            // Shown on the form with everything else, rather than as an error page that loses the edit.
            $this->editorChanges ??= [];
            $this->_editorError = $e->getMessage();
        }
    }

    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        // Craft's editor saves with the live scenario, which is for what visitors submit: a staff member
        // correcting a guest's comment shouldn't be told guests can't comment.
        if ($this->editorChanges !== null) {
            $this->setScenario(self::SCENARIO_CP);
        }

        $isValid = parent::validate($attributeNames, $clearErrors);

        if ($this->_editorError !== null) {
            $this->addError('comment', $this->_editorError);

            return false;
        }

        return $isValid;
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        parent::prepareEditScreen($response, $containerId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response->selectedSubnavItem('comments');

        $view = Craft::$app->getView();
        $view->registerAssetBundle(CommentEditAsset::class);
        $view->registerJsWithVars(static fn($selector) => "Craft.Comments.initCommentEditor(document.querySelector($selector));", [
            "#$containerId",
        ]);
    }

    public function getAdditionalButtons(): string
    {
        $html = parent::getAdditionalButtons();
        $url = $this->getPermalink();

        // Craft's own Preview needs drafts, which comments don't have. This shows the comment where it
        // lives on the site instead, where moderators also see what's held.
        if ($url) {
            $html .= Html::button(Craft::t('comments', 'Preview'), [
                'class' => 'btn',
                'data-icon' => 'eye',
                'data-comments-preview' => $url,
                'data-comments-preview-title' => Craft::t('comments', 'Preview'),
            ]);
        }

        if (Comments::$plugin->getCommentEditor()->canReply($this, Craft::$app->getUser()->getIdentity())) {
            // Craft's icon font has no reply icon, so this uses Craft's own button with the SVG one.
            $html .= Cp::buttonHtml([
                'label' => Craft::t('comments', 'Reply'),
                'icon' => 'reply',
                'attributes' => [
                    'data' => [
                        'comments-reply' => $this->id,
                        'comments-reply-site' => $this->siteId,
                    ],
                ],
            ]);
        }

        return $html;
    }

    /**
     * Where this comment appears on the site, if its element has a page.
     */
    public function getPermalink(): ?string
    {
        $url = $this->getOwner()?->getUrl();

        // The parameter lets the page render the comment's thread, however far back it is.
        return $url ? UrlHelper::urlWithParams($url, ['comment' => $this->id]) . "#comment-{$this->id}" : null;
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('comments');
    }

    public function getSidebarHtml(bool $static): string
    {
        // One box for the element, author and status, then the moderator's decisions in their own
        // section, then Craft's metadata.
        $view = Craft::$app->getView();
        $pickers = $static ? ['owner' => null, 'author' => false] : $this->_sidebarPickers();
        $fields = $view->renderTemplate('comments/comments/_editor-pickers', [
            'comment' => $this,
            'ownerHandle' => $pickers['owner'],
            'showAuthor' => $pickers['author'],
        ], View::TEMPLATE_MODE_CP) . $view->renderTemplate('comments/comments/_editor-status', [
            'comment' => $this,
            // What saving the status accepts, so the menu offers no more than that. It's part of the form, so
            // someone who can't save the comment uses the Actions buttons instead.
            'canModerate' => $this->canSave(Craft::$app->getUser()->getIdentity()) && Comments::$plugin->getModeration()->canModerateComment(Craft::$app->getUser()->getIdentity(), $this),
            'canRemove' => Comments::$plugin->getModeration()->canRemoveComment(Craft::$app->getUser()->getIdentity()),
        ], View::TEMPLATE_MODE_CP);

        return Html::tag('div', $fields, ['class' => 'meta']) .
            $view->renderTemplate('comments/comments/_editor-actions', [
                'comment' => $this,
                // Without a form to submit, a decision is sent on its own.
                'readOnly' => !$this->canSave(Craft::$app->getUser()->getIdentity()),
            ], View::TEMPLATE_MODE_CP) .
            parent::getSidebarHtml($static);
    }

    public function getMetadata(): array
    {
        $metadata = parent::getMetadata();

        // The sidebar's status menu already shows this, and Posted is the date that matters.
        unset($metadata[Craft::t('app', 'Status')], $metadata[Craft::t('app', 'Created at')]);

        // ID goes last, after the dates, as on Craft's own elements.
        $id = $metadata[Craft::t('app', 'ID')] ?? null;
        unset($metadata[Craft::t('app', 'ID')]);

        if ($id !== null) {
            $metadata[Craft::t('app', 'ID')] = $id;
        }

        return $metadata;
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['ownerId'], 'number', 'integerOnly' => true];
        $rules[] = [['ownerSiteId'], SiteIdValidator::class];
        $rules[] = [['cpAuthorType'], 'in', 'range' => ['user', 'guest'], 'on' => self::SCENARIO_CP];
        $rules[] = [['userId'], 'required',
            'when' => fn(): bool => $this->cpAuthorType === 'user',
            'message' => Craft::t('comments', 'Select an author.'),
            'on' => self::SCENARIO_CP,
        ];
        $rules[] = [['name', 'email'], 'required',
            'when' => fn(): bool => $this->cpAuthorType === 'guest',
            'on' => self::SCENARIO_CP,
        ];
        $rules[] = [['email'], 'email', 'on' => self::SCENARIO_CP];

        // Craft only checks required custom fields for `SCENARIO_LIVE`, and Comments uses its own scenarios.
        // In the control panel every required field is required. On the site, a required field is only
        // required of someone it was offered to, as conditions and the replies rule decide, so a field
        // that's Off or hidden from them can't stop them posting.
        if ($fieldLayout = $this->getFieldLayout()) {
            $formFields = Comments::$plugin->getFormFields();

            foreach ($fieldLayout->getCustomFieldElements() as $element) {
                if (!$element->required) {
                    continue;
                }

                $field = $element->getField();
                $uid = (string)$element->uid;
                $attribute = 'field:' . $field->handle;
                $isEmpty = [$this, 'isFieldEmpty:' . $field->handle];

                $rules[] = [[$attribute], 'required', 'isEmpty' => $isEmpty, 'on' => [self::SCENARIO_CP]];

                if ($formFields->settingFor($uid)['mode'] !== FormFields::MODE_OFF) {
                    $rules[] = [[$attribute], 'required', 'isEmpty' => $isEmpty, 'on' => [self::SCENARIO_FRONT_END],
                        'when' => fn(): bool => in_array($uid, $this->_formFieldElementUids ?? [], true),
                    ];
                }
            }
        }

        return $rules;
    }

    protected function fieldLayoutFields(bool $visibleOnly = false, bool $editableOnly = false): array
    {
        $fields = parent::fieldLayoutFields($visibleOnly, $editableOnly);

        if ($this->_formFieldHandles === null) {
            return $fields;
        }

        $handles = $this->_formFieldHandles;

        return array_values(array_filter($fields, static fn($field) => in_array($field->handle, $handles, true)));
    }

    protected function attributeHtml(string $attribute): string
    {
        if ($attribute == 'ownerId') {
            if ($owner = $this->getOwner()) {
                $label = $owner->title ?: Craft::t('comments', 'Element #{id}', ['id' => $owner->id]);
                $a = Html::a(Html::encode($label), $owner->getCpEditUrl());

                return Template::raw($a);
            }

            return Craft::t('comments', '[Deleted element]');
        } elseif ($attribute == 'name') {
            return Html::encode($this->getAuthorName()) ?? '-';
        } elseif ($attribute == 'email') {
            return CommentsHelper::canViewAddresses() ? Html::encode((string)$this->getAuthorEmail()) : '';
        } elseif ($attribute == 'voteCount') {
            return $this->getVotes();
        } elseif ($attribute == 'flagCount') {
            return (string)$this->getFlags();
        } elseif ($attribute == 'comment') {
            return Html::encode($this->getExcerpt(0, 100));
        } elseif ($attribute == 'reason') {
            return $this->_reasonHtml();
        } elseif ($attribute == 'commentDate' || $attribute == 'dateUpdated') {
            // Moderating often means working out what happened within the same day.
            return Html::encode(Craft::$app->getFormatter()->asDatetime($this->$attribute, 'short'));
        }

        return parent::attributeHtml($attribute);
    }

    protected function previewTargets(): array
    {
        $url = $this->getPermalink();

        // Gives the editor Craft's View button, opening the comment in its thread on the site.
        return $url ? [['label' => Craft::t('comments', 'Comment on the site'), 'url' => $url]] : [];
    }

    protected function safeActionMenuItems(): array
    {
        $items = parent::safeActionMenuItems();

        // Craft's slideout has no room for the page's own buttons, so Reply is in its menu instead.
        if (
            Craft::$app->getRequest()->getHeaders()->has('X-Craft-Container-Id')
            && Comments::$plugin->getCommentEditor()->canReply($this, Craft::$app->getUser()->getIdentity())
        ) {
            $items[] = [
                'icon' => 'reply',
                'label' => Craft::t('comments', 'Reply'),
                'attributes' => [
                    'data' => [
                        'comments-reply' => $this->id,
                        'comments-reply-site' => $this->siteId,
                    ],
                ],
            ];
        }

        return $items;
    }

    protected function showStatusField(): bool
    {
        return false;
    }

    protected function crumbs(): array
    {
        return [
            ['label' => Craft::t('comments', 'Comments'), 'url' => UrlHelper::cpUrl('comments')],
        ];
    }

    protected function uiLabel(): ?string
    {
        $comment = strip_tags((string)$this->getComment());
        $comment = preg_replace('/\s+/u', ' ', $comment) ?? $comment;
        $comment = trim($comment);

        if ($comment === '') {
            return Craft::t('comments', 'Comment #{id}', ['id' => $this->id]);
        }

        return StringHelper::truncate($comment, 120);
    }

    protected function metadata(): array
    {
        $user = Craft::$app->getUser();
        $isAdmin = $user->getIsAdmin();
        $owner = $this->getOwner();
        $pickers = $this->_sidebarPickers();
        $canModerate = $user->checkPermission('comments-moderate');

        return [
            ($owner ? $owner::displayName() : Craft::t('comments', 'Element')) => match (true) {
                $pickers['owner'] !== null => false,
                $owner !== null => Cp::elementChipHtml($owner, ['hyperlink' => true, 'showStatus' => false]),
                default => Html::tag('span', Craft::t('comments', '[Deleted element]'), ['class' => 'light']),
            },
            Craft::t('comments', 'Author') => $pickers['author'] ? false : fn() => $this->_authorMetaHtml($canModerate),
            // With the author picker showing, the commenter's page is still a click away.
            Craft::t('comments', 'Commenter') => $pickers['author'] && $canModerate && $this->identityId
                ? Html::a(Craft::t('comments', 'View commenter'), UrlHelper::cpUrl('comments/commenters/' . $this->identityId))
                : false,
            Craft::t('app', 'Email') => $isAdmin && $this->getAuthorEmail() ? Html::encode((string)$this->getAuthorEmail()) : false,
            Craft::t('app', 'Site') => Craft::$app->getIsMultiSite() ? Html::encode((string)$this->getSite()->getUiLabel()) : false,
            Craft::t('comments', 'Posted') => $this->commentDate
                ? Craft::$app->getFormatter()->asDatetime($this->commentDate, Formatter::FORMAT_WIDTH_SHORT, true)
                : false,
            Craft::t('comments', 'Edited') => $this->editedAt
                ? Craft::$app->getFormatter()->asDatetime($this->editedAt, Formatter::FORMAT_WIDTH_SHORT, true)
                : false,
            // Personal data, so like addresses it's for admins only.
            Craft::t('comments', 'Posted from') => $isAdmin && $this->url
                ? Html::a(Html::encode($this->url), $this->url, ['target' => '_blank', 'rel' => 'noopener'])
                : false,
            Craft::t('comments', 'IP Address') => $isAdmin && $this->ipAddress ? Html::encode($this->ipAddress) : false,
            Craft::t('comments', 'User Agent') => $isAdmin && $this->userAgent ? Html::encode($this->userAgent) : false,
        ];
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('comments/' . $this->id);
    }


    // Private Methods
    // =========================================================================

    private function _rebuildConversationAggregates(): void
    {
        if (!$this->conversationId) {
            return;
        }

        Comments::$plugin->getConversations()->rebuildAggregates((int)$this->conversationId);
    }

    /**
     * Why the comment needs a moderator, as a badge for the index.
     */
    private function _reasonHtml(): string
    {
        $state = ModerationState::tryFrom((string)$this->moderationState);

        if ($state === ModerationState::Approved && $this->getFlags() > 0) {
            $label = $this->getFlags() === 1
                ? Craft::t('comments', 'Reported')
                : Craft::t('comments', 'Reported ×{count}', ['count' => $this->getFlags()]);

            return Html::tag('span', Html::encode($label), ['class' => ['comments-reason', 'comments-reason--reported']]);
        }

        if (!in_array($state, [ModerationState::Pending, ModerationState::Spam], true)) {
            return '';
        }

        $reason = HeldReason::tryFrom((string)$this->heldReason) ?? ($state === ModerationState::Pending ? HeldReason::PreModeration : null);

        return $reason
            ? Html::tag('span', Html::encode($reason->label()), ['class' => ['comments-reason', 'comments-reason--' . $reason->value]])
            : '';
    }

    /**
     * Which of the element and author the current person can change from the sidebar: the element's
     * picker handle, or null, and whether the author picker shows.
     */
    private function _sidebarPickers(): array
    {
        $user = Craft::$app->getUser()->getIdentity();

        if (!$user || !$user->can('comments-edit')) {
            return ['owner' => null, 'author' => false];
        }

        $owner = $this->getOwner();

        // Moving a comment moves its whole thread, so only a thread's first comment offers it.
        $ownerHandle = $owner && !$this->parentId ? array_search($owner::class, CommentEditor::OWNER_TYPES, true) : false;

        return [
            'owner' => is_string($ownerHandle) ? $ownerHandle : null,
            // A guest's name and address are edited on the Content tab instead.
            'author' => !$this->isGuest(),
        ];
    }

    private function _authorMetaHtml(bool $canModerate): string
    {
        $name = Html::encode((string)($this->getAuthorName() ?: Craft::t('comments', 'Guest')));
        $html = $canModerate && $this->identityId
            ? Html::a($name, UrlHelper::cpUrl('comments/commenters/' . $this->identityId))
            : $name;

        if ($this->isGuest()) {
            $html .= ' ' . Html::tag('span', Craft::t('comments', '(guest)'), ['class' => 'light']);
        }

        return Html::tag('span', $html);
    }

    private function _getInteractionTotals(): array
    {
        if ($this->hasInteractionTotals()) {
            return $this->_interactionTotals;
        }

        $totals = Comments::$plugin->getInteractions()->getTotals([(int)$this->id]);

        return $totals[(int)$this->id] ?? ['upvote' => 0, 'downvote' => 0];
    }

    /**
     * The current visitor's vote, if the Interaction Mode still offers it. A downvote kept from
     * before the site switched to likes isn't one the visitor can see, or take back.
     */
    private function _viewerInteraction(): ?InteractionType
    {
        $mode = Comments::$plugin->getSettings()->resolveInteractionMode();
        $identity = $mode->isEnabled() ? $this->_resolveViewerIdentity() : null;

        if (!$identity) {
            return null;
        }

        $type = InteractionType::tryFrom((string)Comments::$plugin->getInteractions()->getForIdentity((int)$this->id, (int)$identity->id)?->type);

        return $type && $mode->allows($type) ? $type : null;
    }

    private function _resolveViewerIdentity(): ?\verbb\comments\records\Identity
    {
        $user = Comments::$plugin->getService()->getUser();

        if ($user) {
            return Comments::$plugin->getIdentities()->findForUser((int)$user->id);
        }

        $token = Comments::$plugin->getIdentities()->guestTokenFromRequest();

        if (!$token) {
            return null;
        }

        return \verbb\comments\records\Identity::findOne([
            'type' => \verbb\comments\enums\IdentityType::Guest->value,
            'guestTokenHash' => Comments::$plugin->getIdentities()->hashGuestToken($token),
        ]);
    }

    /**
     * Rich content is compared by structure, because saving it again can reorder its JSON without changing it.
     */
    /**
     * While submitted values are applied, only the fields this person can fill in can be written. An
     * asset ID for an Assets field would relate, and with a restricted location move, any asset on the
     * site, so only the files the comment already has on the field and the person's own uploads for it
     * may be sent. Files can't come as data either: they're uploaded on their own first.
     */
    private function _assertFormFieldValue(string $fieldHandle, mixed $value): void
    {
        if ($this->_formFieldHandles === null) {
            return;
        }

        if (!in_array($fieldHandle, $this->_formFieldHandles, true)) {
            throw CommentValidationException::fromErrors([
                'field:' . $fieldHandle => [Craft::t('comments', 'This field can’t be filled in here.')],
            ]);
        }

        $field = $this->getFieldLayout()?->getFieldByHandle($fieldHandle);

        if ($field instanceof AssetsField) {
            $this->_assertOwnAssetIds($field, $value);
        }
    }

    private function _assertOwnAssetIds(AssetsField $field, mixed $value): void
    {
        // Craft relates nothing for anything but an array.
        if (!is_array($value)) {
            return;
        }

        $refused = CommentValidationException::fromErrors([
            'field:' . $field->handle => [Craft::t('comments', 'Only files you upload here can be added.')],
        ]);

        // Craft would store `data` as a new file, without Comments' upload rules.
        if (array_key_exists('data', $value) || array_key_exists('filename', $value)) {
            throw $refused;
        }

        $posted = array_values(array_filter($value, static fn(mixed $id): bool => $id !== '' && $id !== null));

        if ($posted === []) {
            return;
        }

        $allowed = $this->id ? array_map('strval', (new Query())
            ->select(['targetId'])
            ->from([Table::RELATIONS])
            ->where(['fieldId' => $field->id, 'sourceId' => $this->id])
            ->column()) : [];
        $element = null;

        foreach ($this->getFieldLayout()?->getCustomFieldElements() ?? [] as $candidate) {
            if ($candidate->getField()->handle === $field->handle) {
                $element = $candidate;
            }
        }

        if ($element) {
            $user = Comments::$plugin->getService()->getUser();
            $identities = Comments::$plugin->getIdentities();
            $identity = $user instanceof User ? $identities->findForUser((int)$user->id) : $identities->findGuestForRequest();
            $allowed = [...$allowed, ...array_map('strval', Comments::$plugin->getFormFields()->pendingUploadIds((string)$element->uid, $identity))];
        }

        foreach ($posted as $assetId) {
            if (!is_scalar($assetId) || !in_array((string)$assetId, $allowed, true)) {
                throw $refused;
            }
        }
    }

    /**
     * Files posted with the site's own form for an Assets field, under `$param`, pass Comments' upload
     * rules, or the form is refused. Craft stores them as the comment saves.
     */
    private function _checkRequestUploads(AssetsField $field, string $param): void
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return;
        }

        foreach (UploadedFile::getInstancesByName($param) as $file) {
            try {
                UploadPolicy::check($file, AssetsType::allowedExtensions($field), AssetsType::maxSize());
            } catch (UploadRefusedException $e) {
                throw CommentValidationException::fromErrors(['field:' . $field->handle => [$e->getMessage()]]);
            }
        }

        $this->_checkedUploadParams[] = $param;
    }

    /**
     * Craft's Assets fields store any file in the request under their name as an element saves, whoever
     * sent it and however. Outside the control panel, only files the site's form posted for a field this
     * person can fill in, after the upload rules, are left for it; anything else is refused before Craft
     * sees it.
     */
    private function _refuseUncheckedUploads(): void
    {
        // The control panel's own editor saves with `SCENARIO_CP`. A control panel URL alone proves
        // nothing, because Craft treats any request under it as one, including to the site's own
        // anonymous actions.
        if (Craft::$app->getRequest()->getIsConsoleRequest() || $this->getScenario() === self::SCENARIO_CP || self::isControlPanelUser()) {
            return;
        }

        $namespace = $this->getFieldParamNamespace();

        foreach ($this->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if (!$field instanceof AssetsField) {
                continue;
            }

            $param = ($namespace ? $namespace . '.' : '') . $field->handle;

            if (!in_array($param, $this->_checkedUploadParams, true) && UploadedFile::getInstancesByName($param) !== []) {
                throw CommentValidationException::fromErrors([
                    'field:' . $field->handle => [Craft::t('comments', 'Only files you upload here can be added.')],
                ]);
            }
        }
    }

    private function _contentChangedFrom(CommentRecord $original): bool
    {
        if ((string)$this->comment !== StringHelper::shortcodesToEmoji((string)$original->comment)) {
            return true;
        }

        // Adding, changing or removing a GIF is an edit too.
        if ((string)$this->gifUrl !== (string)$original->gifUrl) {
            return true;
        }

        if (!$this->isEditorContent()) {
            return false;
        }

        return Json::decodeIfJson((string)$this->bodyContent) != Json::decodeIfJson((string)$original->content);
    }

    private function _prepareCanonicalRelations(bool $isNew): void
    {
        if (!$this->ownerId || !$this->ownerSiteId) {
            return;
        }

        if (!$this->conversationId) {
            $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner(
                (int)$this->ownerId,
                (int)$this->ownerSiteId,
            );
            $this->conversationId = (int)$conversation->id;
        }

        if (!$this->identityId) {
            if ($this->userId && ($user = Craft::$app->getUsers()->getUserById((int)$this->userId))) {
                $this->identityId = (int)Comments::$plugin->getIdentities()->getOrCreateForUser($user)->id;
            } else {
                $this->identityId = (int)Comments::$plugin->getIdentities()->createHistoricalGuest(
                    $this->name,
                    $this->email,
                )->id;
            }
        }

        if ($isNew && $this->parentId) {
            $parent = Comments::$plugin->getComments()->getCommentById((int)$this->parentId, $this->siteId);

            if (!$parent || (int)$parent->conversationId !== (int)$this->conversationId) {
                throw new Exception('Invalid parent comment ID: ' . $this->parentId);
            }

            $this->rootId = (int)($parent->rootId ?: $parent->id);
            $this->depth = (int)$parent->depth + 1;
        } elseif ($isNew) {
            $this->depth = 0;
        }

        $this->contentFormat ??= ContentFormat::Plain->value;
        $this->bodyContent ??= (string)$this->comment;
        $this->moderationState ??= match ($this->status) {
            self::STATUS_PENDING => ModerationState::Pending->value,
            self::STATUS_SPAM => ModerationState::Spam->value,
            self::STATUS_REMOVED => ModerationState::Removed->value,
            default => ModerationState::Approved->value,
        };
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
