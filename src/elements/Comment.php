<?php
namespace verbb\comments\elements;

use verbb\comments\Comments;
use verbb\comments\elements\actions\SetStatus;
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
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\validators\SiteIdValidator;

use LitEmoji\LitEmoji;
use TheIconic\NameParser\Parser;

use Exception;

class Comment extends Element
{
    // Constants
    // =========================================================================

    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_SPAM = 'spam';
    const STATUS_TRASHED = 'trashed';

    const SCENARIO_CP = 'cp';
    const SCENARIO_FRONT_END = 'frontEnd';


    // Public Properties
    // =========================================================================

    public $ownerId;
    public $ownerSiteId;
    public $userId;
    public $status;
    public $name;
    public $email;
    public $url;
    public $ipAddress;
    public $userAgent;
    public $commentDate;

    public $newParentId;
    private $_hasNewParent;
    private $comment;
    private $_owner;
    private $_author;
    private $_user;
    private $previousStatus;


    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comment');
    }

    public static function refHandle()
    {
        return 'comment';
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
            self::STATUS_TRASHED => Craft::t('comments', 'Trashed')
        ];
    }

    public static function find(): ElementQueryInterface
    {
        return new CommentQuery(static::class);
    }

    public static function getStructureId()
    {
        return Comments::$plugin->getSettings()->getStructureId();
    }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('comments', 'All comments'),
                'structureId' => self::getStructureId(),
                'structureEditable' => false,
                'defaultSort' => ['structure', 'asc'],
            ]
        ];

        $indexSidebarLimit = Comments::$plugin->getSettings()->indexSidebarLimit;
        $indexSidebarGroup = Comments::$plugin->getSettings()->indexSidebarGroup;
        $indexSidebarIndividualElements = Comments::$plugin->getSettings()->indexSidebarIndividualElements;

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
                'defaultSort' => ['structure', 'asc'],
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
                    'defaultSort' => ['structure', 'asc'],
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
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('comments', 'Are you sure you want to delete the selected comments?'),
            'successMessage' => Craft::t('comments', 'Comments deleted.'),
        ]);

        $actions[] = SetStatus::class;

        return $actions;
    }


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        if ($this->id) {
            // Add this comment to a render cache, so when calling `parent` we can make use of it
            Comments::$plugin->getRenderCache()->addComment($this->id, $this);
        }
    }

    public function extraFields()
    {
        $names = parent::extraFields();
        $names[] = 'owner';
        $names[] = 'user';
        $names[] = 'author';
        return $names;
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();

        // Ths is the only way I can figure out extra scenarios to work...
        $scenarios[self::SCENARIO_CP] = $scenarios[self::SCENARIO_CP] ?? [];
        $scenarios[self::SCENARIO_FRONT_END] = $scenarios[self::SCENARIO_FRONT_END] ?? [];

        return $scenarios;
    }

    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['ownerId'], 'number', 'integerOnly' => true];
        $rules[] = [['ownerSiteId'], SiteIdValidator::class];

        // Check for custom fields. Craft will only check this for `SCENARIO_LIVE`, and we use custom scenarios
        if ($fieldLayout = $this->getFieldLayout()) {
            foreach ($fieldLayout->getFields() as $field) {
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

    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'commentDate';
        return $attributes;
    }

    public function getSupportedSites(): array
    {
        // Only support the site the comment is being made on
        $siteId = $this->siteId ?: Craft::$app->getSites()->getPrimarySite()->id;

        return [$siteId];
    }

    public function getCpEditUrl()
    {
        $url = UrlHelper::cpUrl('comments/' . $this->id);

        if (Craft::$app->getIsMultiSite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
    }

    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    public function getComment()
    {
        $comment = $this->comment;

        // Add Emoji support
        if ($comment !== null) {
            $comment = LitEmoji::shortcodeToUnicode($comment);
            $comment = trim(preg_replace('/\R/u', "\n", $comment));
        }

        return $comment;
    }

    public function setComment($comment)
    {
        // Add Emoji support
        if ($comment !== null) {
            $comment = LitEmoji::unicodeToShortcode($comment);
        }

        // Replace any 4-byte string that've been missed
        $comment = preg_replace('%(?:\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})%xs', '', $comment);

        $this->comment = $comment;
    }

    public function getRawComment()
    {
        return $this->comment;
    }

    public function can($property)
    {
        // See if there's a plugin setting for it
        if (property_exists(Comments::$plugin->getSettings(), $property)) {
            return (bool)Comments::$plugin->getSettings()->$property;
        }

        // Provide some helpers
        switch ($property) {
            case 'flag':
                return (bool)$this->canFlag();
            case 'vote':
                return (bool)$this->canVote();
            case 'reply':
                return (bool)$this->canReply();
            case 'edit':
                return (bool)$this->canEdit();
            case 'trash':
                return (bool)$this->canTrash();
        }
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getExcerpt($startPos = 0, $maxLength = 100) {
        if (strlen($this->comment) > $maxLength) {
            $excerpt   = substr($this->comment, $startPos, $maxLength-3);
            $lastSpace = strrpos($excerpt, ' ');
            $excerpt   = substr($excerpt, 0, $lastSpace);
            $excerpt  .= '...';
        } else {
            $excerpt = $this->comment;
        }

        return $excerpt;
    }

    public function getTimeAgo()
    {
        $diff = (new \DateTime())->diff($this->commentDate);
        return CommentsHelper::humanDurationFromInterval($diff);
    }

    public function isGuest()
    {
        return is_null($this->userId);
    }

    public function getAuthor()
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

            // We only store guest users full name, so we need to split it for Craft.
            // Best results using a library - particularly when we're dealing with worldwide names.
            if ($this->name) {
                $parser = new Parser();
                $nameInfo = $parser->parse($this->name);

                $author->firstName = $nameInfo->getFirstname();
                $author->lastName = $nameInfo->getLastname();
            }

            if (!$author->firstName && !$author->lastName) {
                $author->firstName = Craft::t('comments', 'Guest');
            }

            $this->_author = $author;

            return $author;
        }

        // Check if this is a regular user
        $user = $this->getUser();

        // But, they might have been deleted!
        if (!$user) {
            $author = new User();
            $author->email = null;
            $author->firstName = Craft::t('comments', '[Deleted');
            $author->lastName = Craft::t('comments', 'User]');

            $this->_author = $author;

            return $author;
        }

        $this->_author = $user;

        return $user;
    }

    public function getAuthorName()
    {
        if ($author = $this->getAuthor()) {
            return $author->fullName;
        }

        return $this->name;
    }

    public function getAuthorEmail()
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

    public function getOwner()
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

    public function setOwner(ElementInterface $owner = null)
    {
        $this->_owner = $owner;
    }

    public function getOwnerType()
    {
        if ($owner = $this->getOwner()) {
            return get_class($owner);
        }

        return '';
    }

    public function canReply()
    {
        $settings = Comments::$plugin->getSettings();

        $canReply = (bool)$settings->canComment($this->getOwner());

        if ($canReply && is_numeric($settings->maxReplyDepth)) {
            $maxReplyDepth = (int)$settings->maxReplyDepth;

            // Check against plugin reply level settings
            if (($this->level - 1) >= $settings->maxReplyDepth) {
                $canReply = false;
            }
        }

        return $canReply;
    }

    public function canEdit()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can edit a comment
        if (!$currentUser) {
            return;
        }

        // We better have an author
        if (!$this->author) {
            return;
        }

        // Check that user is trying to edit their own comment
        if ($currentUser->id !== $this->author->id) {
            return;
        }

        return true;
    }

    public function canTrash()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can upvote a comment
        if (!$currentUser) {
            return;
        }

        // We better have an author
        if (!$this->author) {
            return;
        }

        // Check that user is trying to trash their own comment
        if ($currentUser->id !== $this->author->id) {
            return;
        }

        return true;
    }

    public function trashUrl()
    {
        Craft::$app->getDeprecator()->log('trashUrl', '`trashUrl` has been deprecated. Use POST form instead, refer to [docs](https://verbb.io/craft-plugins/comments/docs/developers/comment).');

        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can upvote a comment
        if (!$currentUser) {
            return;
        }

        // We better have an author
        if (!$this->author) {
            return;
        }

        // Check that user is trying to trash their own comment
        if ($currentUser->id !== $this->author->id) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/trash', [
            'commentId' => $this->id,
        ]);
    }

    public function getUser()
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

    public function setUser(User $user = null)
    {
        $this->_user = $user;
    }

    public function isSubscribed()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $userId = $currentUser->id ?? null;

        return Comments::$plugin->getSubscribe()->hasSubscribed($this->ownerId, $this->ownerSiteId, $userId, $this->id);
    }


    // Flags
    // =========================================================================

    public function flagUrl()
    {
        Craft::$app->getDeprecator()->log('flagUrl', '`flagUrl` has been deprecated. Use POST form instead, refer to [docs](https://verbb.io/craft-plugins/comments/docs/developers/flag).');

        // Check if this user can flag comments
        if (!$this->canFlag()) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/flag', [
            'commentId' => $this->id,
        ]);
    }

    public function hasFlagged()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        return Comments::$plugin->getFlags()->hasFlagged($this, $currentUser);
    }

    public function isFlagged()
    {
        return Comments::$plugin->getFlags()->isOverFlagThreshold($this);
    }

    public function getFlags()
    {
        return Comments::$plugin->getFlags()->getFlagsByCommentId($this->id);
    }

    public function canFlag()
    {
        $settings = Comments::$plugin->getSettings();
        $currentUser = Craft::$app->getUser()->getIdentity();

        // If flagging is plain disabled
        if (!$settings->allowFlagging) {
            return;
        }

        // Only guests can flag if the setting is configured to do so
        if (!$currentUser && !$settings->allowGuestFlagging) {
            return;
        }

        return true;
    }


    // Votes
    // =========================================================================

    public function downvoteUrl()
    {
        Craft::$app->getDeprecator()->log('downvoteUrl', '`downvoteUrl` has been deprecated. Use POST form instead, refer to [docs](https://verbb.io/craft-plugins/comments/docs/developers/vote).');

        // Check if this user can vote on comments
        if (!$this->canVote()) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/vote', [
            'commentId' => $this->id,
            'downvote' => true,
        ]);
    }

    public function upvoteUrl()
    {
        Craft::$app->getDeprecator()->log('upvoteUrl', '`upvoteUrl` has been deprecated. Use POST form instead, refer to [docs](https://verbb.io/craft-plugins/comments/docs/developers/vote).');

        // Check if this user can vote on comments
        if (!$this->canVote()) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/vote', [
            'commentId' => $this->id,
            'upvote' => true,
        ]);
    }

    public function getVotes()
    {
        $upvotes = Comments::$plugin->getVotes()->getUpvotesByCommentId($this->id);
        $downvotes = Comments::$plugin->getVotes()->getDownvotesByCommentId($this->id);

        return $upvotes - $downvotes;
    }

    public function isPoorlyRated()
    {
        return Comments::$plugin->getVotes()->isOverDownvoteThreshold($this);
    }

    public function getAllVotes()
    {
        return Comments::$plugin->getVotes()->getVotesByCommentId($this->id);
    }

    public function getUpvotes()
    {
        return Comments::$plugin->getVotes()->getUpvotesByCommentId($this->id);
    }

    public function getDownvotes()
    {
        return Comments::$plugin->getVotes()->getDownvotesByCommentId($this->id);
    }

    public function canVote()
    {
        $settings = Comments::$plugin->getSettings();
        $currentUser = Craft::$app->getUser()->getIdentity();

        // If voting is plain disabled
        if (!$settings->allowVoting) {
            return;
        }

        // Only guests can vote if the setting is configured to do so
        if (!$currentUser && !$settings->allowGuestVoting) {
            return;
        }

        // Has the downvote threshold been met, and the config setting set?
        if ($settings->hideVotingForThreshold && $this->isPoorlyRated()) {
            return;
        }

        return true;
    }



    // Events
    // =========================================================================

    public function beforeValidate()
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
            if (!Comments::$plugin->getComments()->checkPermissions($this->owner)) {
                $this->addError('comment', Craft::t('comments', 'Comments are disabled for this element.'));
            }

            // Is this user trying to edit/save/delete a comment that’s not their own?
            // This is permissible from the CP
            if ($this->id && !Craft::$app->getRequest()->getIsCpRequest()) {
                $currentUser = Craft::$app->getUser()->getIdentity();

                if (empty($currentUser) || $currentUser->id !== $this->author->id) {
                    $this->addError('comment', Craft::t('comments', 'Unable to modify another user’s comment.'));
                }
            }
        }

        // Skip for CP saving
        if ($this->scenario === self::SCENARIO_FRONT_END) {
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
            if (!Comments::$plugin->getComments()->checkPermissions($this->owner)) {
                $this->addError('comment', Craft::t('comments', 'Comments are disabled for this element.'));
            }

            // Is this user trying to edit/save/delete a comment that’s not their own?
            // This is permissible from the CP
            if ($this->id && !Craft::$app->getRequest()->getIsCpRequest()) {
                $currentUser = Craft::$app->getUser()->getIdentity();

                if ($currentUser->id !== $this->author->id) {
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
                $parentNode = Comments::$plugin->comments->getCommentById($this->newParentId, $this->siteId);

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
                $this->previousStatus = $originalElement->status;
            }
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew)
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
            $record->commentDate = new \DateTime();
        }

        $record->save(false);

        $this->id = $record->id;
        $this->commentDate = DateTimeHelper::toDateTime($record->commentDate);

        if ($isNew) {
            // Should we send moderator emails?
            if ($settings->notificationModeratorEnabled && $this->status == self::STATUS_PENDING) {
                Comments::$plugin->comments->sendModeratorNotificationEmail($this);
            } else {
                Comments::log('Moderator Notifications disabled.');
            }

            // Don't send reply or author emails if we're moderating first
            if ($settings->requireModeration) {
                Comments::log('Not sending reply or author notification - marked as pending (to be moderated).');
            } else {
                // Should we send a Notification email to the author of this comment?
                if ($settings->notificationAuthorEnabled) {
                    Comments::$plugin->comments->sendAuthorNotificationEmail($this);
                } else {
                    Comments::log('Author Notifications disabled.');
                }

                // If a reply to another comment, should we send a Notification email
                // to the author of the original comment?
                if ($settings->notificationReplyEnabled) {
                    if ($this->_isReplying()) {
                        Comments::$plugin->comments->sendReplyNotificationEmail($this);
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
                    Comments::$plugin->comments->sendSubscribeNotificationEmail($this);
                }
            }

            // Send admin notifications
            if ($settings->notificationAdminEnabled) {
                Comments::$plugin->comments->sendAdminNotificationEmail($this);
            }
        }

        // Check to see if we're moderating, and has just switch from pending to approved
        if ($this->previousStatus == self::STATUS_PENDING && $this->status == self::STATUS_APPROVED) {
            if ($settings->notificationModeratorApprovedEnabled) {
                Comments::$plugin->comments->sendModeratorApprovedNotificationEmail($this);
            } else {
                Comments::log('Moderator Approved Notifications disabled.');
            }

            // Should we send a Notification email to the author of this comment?
            if ($settings->notificationAuthorEnabled) {
                Comments::$plugin->comments->sendAuthorNotificationEmail($this);
            } else {
                Comments::log('Author Notifications disabled.');
            }

            // If a reply to another comment, should we send a Notification email
            // to the author of the original comment?
            if ($settings->notificationReplyEnabled) {
                if ($this->_isReplying()) {
                    Comments::$plugin->comments->sendReplyNotificationEmail($this);
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
                Comments::$plugin->comments->sendSubscribeNotificationEmail($this);
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


    // Element index methods
    // =========================================================================

    public static function getCommentElementTitleHtml(&$context)
    {
        if (!isset($context['element'])) {
            return;
        }

        // Only do this for a Comment ElementType
        if (get_class($context['element']) === static::class) {
            $span1 = Html::tag('span', '', ['class' => 'status ' . $context['element']->status]);
            $span2 = Html::tag('span', Html::encode($context['element']->getAuthor()), ['class' => 'username']);
            $small = Html::tag('small', Html::encode($context['element']->getExcerpt(0, 100)));
            $a = Html::a($span2 . $small, $context['element']->getCpEditUrl());

            $html = Html::tag('div', $span1 . $a, ['class' => 'comment-block']);
            
            return Template::raw($html);
        }
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'comment' => ['label' => Craft::t('comments', 'Comment')],
            'commentDate' => ['label' => Craft::t('comments', 'Date')],
            'ownerId' => ['label' => Craft::t('comments', 'Element')],
            'votes' => ['label' => Craft::t('comments', 'Votes')],
            'flagged' => ['label' => Craft::t('comments', 'Flagged')],
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
                'attribute' => 'commentDate'
            ],
            'ownerId' => Craft::t('comments', 'Element'),
            'email' => Craft::t('comments', 'Email'),
            'name' => Craft::t('comments', 'Name'),
            'votes' => Craft::t('comments', 'Votes'),
            'flagged' => Craft::t('comments', 'Flagged'),
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'ownerId': {
                $owner = $this->getOwner();

                if ($owner) {
                    $a = Html::a(Html::encode($owner->title), $owner->cpEditUrl);
                    
                    return Template::raw($a);
                } else {
                    return Craft::t('comments', '[Deleted element]');
                }
            }
            case 'votes': {
                return $this->getVotes();
            }
            case 'flagged': {
                return $this->hasFlagged() ? '<span class="status off"></span>' : '<span class="status"></span>';
            }
            default: {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }

    public static function eagerLoadingMap(array $sourceElements, string $handle)
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
                'map' => $map
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
                'map' => $map
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    public static function gqlTypeNameByContext($context): string
    {
        return 'Comment';
    }

    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute)
    {
        if ($attribute === 'user') {
            $elementQuery->andWith('user');
        } else if ($attribute === 'owner') {
            $elementQuery->andWith('owner');
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }

    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this);
    }

    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle === 'user') {
            $this->_user = $elements[0] ?? false;
        } else if ($handle === 'owner') {
            $this->_owner = $elements[0] ?? false;
        } else {
            parent::setEagerLoadedElements($handle, $elements);
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
        return (bool)$this->newParentId || (bool)$this->getParent();
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
        $oldParentQuery->anyStatus();
        $oldParentQuery->select('elements.id');
        $oldParentId = $oldParentQuery->scalar();

        return $this->newParentId != $oldParentId;
    }

    private function _saveNewSubscriber()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

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

    private function _getCommentIsRequired()
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
