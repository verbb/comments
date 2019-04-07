<?php
namespace verbb\comments\elements;

use verbb\comments\Comments;
use verbb\comments\elements\actions\SetStatus;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\records\Comment as CommentRecord;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\User;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\validators\SiteIdValidator;

use Carbon\Carbon;
use LitEmoji\LitEmoji;
use TheIconic\NameParser\Parser;

class Comment extends Element
{
    // Constants
    // =========================================================================

    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_SPAM = 'spam';
    const STATUS_TRASHED = 'trashed';


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
        return Comments::$plugin->getSettings()->structureId;
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

        $indexSidebarLimit =  Comments::$plugin->getSettings()->indexSidebarLimit;

        $commentedElements = (new Query())
            ->select(['elements.id', 'elements.type', 'comments.ownerId', 'content.title', 'elements.dateDeleted'])
            ->from(['{{%elements}} elements'])
            ->innerJoin('{{%content}} content', '[[content.elementId]] = [[elements.id]]')
            ->innerJoin('{{%comments_comments}} comments', '[[comments.ownerId]] = [[elements.id]]')
            ->where(['is', 'elements.dateDeleted', null])
            ->limit($indexSidebarLimit)
            ->groupBy('ownerId')
            ->all();

        foreach ($commentedElements as $element) {
            switch ($element['type']::displayName()) {
                case 'Entry':
                    $displayName = 'Entries';
                    break;
                case 'Category':
                    $displayName = 'Categories';
                    break;
                case 'Asset':
                    $displayName = 'Assets';
                    break;
                case 'User':
                    $displayName = 'Users';
                    break;
                default:
                    $displayName = $element['type']::displayName();
                    break;
            }

            $key = 'type:' . $element['type']::displayName();

            $sources[$key] = ['heading' => $displayName];

            $sources[$key . ':all'] = [
                'key' => $key . ':all',
                'label' => Craft::t('comments', 'All ' . $displayName),
                'structureId' => self::getStructureId(),
                'structureEditable' => false,
                'criteria' => [
                    'ownerType' => $element['type'],
                ],
                'defaultSort' => ['structure', 'asc'],
            ];

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

    public function extraFields()
    {
        $names = parent::extraFields();
        $names[] = 'owner';
        return $names;
    }

    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['ownerId'], 'number', 'integerOnly' => true];
        $rules[] = [['ownerSiteId'], SiteIdValidator::class];
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
        if ($this->ownerSiteId !== null) {
            return [$this->ownerSiteId];
        }

        if (($owner = $this->getOwner())) {
            $siteIds = [];

            foreach (ElementHelper::supportedSitesForElement($owner) as $siteInfo) {
                $siteIds[] = $siteInfo['siteId'];
            }

            return $siteIds;
        }

        return [Craft::$app->getSites()->getPrimarySite()->id];
    }

    public function getCpEditUrl()
    {
        $url = UrlHelper::cpUrl('comments/' . $this->id);

        if (Craft::$app->getIsMultiSite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
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
                return (bool)$this->can('allowFlagging');
            case 'vote':
                return (bool)$this->can('allowVoting');
            case 'reply':
                return (bool)$this->canReply();
            case 'edit':
                return (bool)$this->canEdit();
            case 'trash':
                return (bool)$this->trashUrl();
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
        return (new Carbon($this->commentDate->format('c')))->diffForHumans();
    }

    public function isGuest()
    {
        return is_null($this->userId);
    }

    public function getAuthor()
    {
        // If this user is a guest, we make a temprary UserModel, which is particularly
        // used for email notifications (which require a UserModel instance)
        if ($this->isGuest()) {
            // If this wasn't a registered user...
            $author = new User();
            $author->email = $this->email;

            // We only store guest users full name, so we need to split it for Craft.
            // Best results using a library - particularly when we're dealing with worldwide names.
            $parser = new Parser();
            $nameInfo = $parser->parse($this->name);

            $author->firstName = $nameInfo->getFirstname();
            $author->lastName = $nameInfo->getLastname();

            if (!$author->firstName && !$author->lastName) {
                $author->firstName = 'Anonymous';
            }

            return $author;
        } else {
            return Craft::$app->getUsers()->getUserById($this->userId);
        }
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

    public function getOwner()
    {
        if ($this->_owner !== null) {
            return $this->_owner !== false ? $this->_owner : null;
        }

        if ($this->ownerId === null) {
            return null;
        }

        if (($this->_owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId)) === null) {
            $this->_owner = false;

            return null;
        }

        return $this->_owner;
    }

    public function setOwner(ElementInterface $owner = null)
    {
        $this->_owner = $owner;
    }

    public function canReply()
    {
        return (bool)Comments::$plugin->getSettings()->canComment($this->getOwner());
    }

    public function canEdit()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can edit a comment
        if (!$currentUser) {
            return;
        }

        // Check that user is trying to edit their own comment
        if ($currentUser->id !== $this->author->id) {
            return;
        }

        return true;
    }

    public function trashUrl()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can upvote a comment
        if (!$currentUser) {
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


    // Flags
    // =========================================================================

    public function flagUrl()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can flag a comment
        if (!$currentUser) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/flag', [
            'commentId' => $this->id,
        ]);
    }

    public function hasFlagged()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $hasFlagged = false;

        if ($currentUser) {
            $hasFlagged = Comments::$plugin->getFlags()->hasFlagged($this, $currentUser);
        }

        return $hasFlagged;
    }

    public function isFlagged()
    {
        return Comments::$plugin->getFlags()->isOverFlagThreshold($this);
    }


    // Votes
    // =========================================================================

    public function downvoteUrl()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can upvote a comment
        if (!$currentUser) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/vote', [
            'commentId' => $this->id,
            'downvote' => true,
        ]);
    }

    public function upvoteUrl()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Only logged in users can upvote a comment
        if (!$currentUser) {
            return;
        }

        return UrlHelper::actionUrl('comments/comments/vote', [
            'commentId' => $this->id,
            'upvote' => true,
        ]);
    }

    public function votes()
    {
        $upvotes = Comments::$plugin->getVotes()->getUpvotesByCommentId($this->id);
        $downvotes = Comments::$plugin->getVotes()->getDownvotesByCommentId($this->id);

        return $upvotes - $downvotes;
    }

    public function isPoorlyRated()
    {
        return Comments::$plugin->getVotes()->isOverDownvoteThreshold($this);
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

        // Let's check for spam!
        if (!Comments::$plugin->getProtect()->verifyFields() && $settings->enableSpamChecks) {
            $this->addError('comment', Craft::t('comments', 'Form validation failed. Marked as spam.'));
        }

        // Check against any security keywords we've set. Can be words, IP's, User Agents, etc.
        if (!Comments::$plugin->getSecurity()->checkSecurityPolicy($this)) {
            $this->addError('comment', Craft::t('comments', 'Comment blocked due to security policy.'));
        }

        // Protect against Anonymous submissions, if turned off
        if (!$settings->allowAnonymous && !$this->userId) {
            $this->addError('comment', Craft::t('comments', 'Must be logged in to comment.'));

            // Additionally, check for user email/name, which is compulsary for guests
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

        // Is this user trying to edit/save/delete a comment thats not their own?


        // Must have an actual comment
        if (!$this->comment) {
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
            // Should we send a Notification email to the author of this comment?
            if ($settings->notificationAuthorEnabled) {
                Comments::$plugin->comments->sendAuthorNotificationEmail($this);
            }

            // If a reply to another comment, should we send a Notification email
            // to the author of the original comment?
            if ($settings->notificationReplyEnabled && $this->_hasNewParent()) {
                Comments::$plugin->comments->sendReplyNotificationEmail($this);
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
            $html = '<div class="comment-block">';
            $html .= '<span class="status ' . $context['element']->status . '"></span>';
            $html .= '<a href="' . $context['element']->getCpEditUrl() . '">';
            $html .= '<span class="username">' . $context['element']->getAuthor() . '</span>';
            $html .= '<small>' . htmlspecialchars($context['element']->getExcerpt(0, 100)) . '</small></a>';
            $html .= '</div>';

            return $html;
        }
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'comment' => ['label' => Craft::t('comments', 'Comment')],
            'commentDate' => ['label' => Craft::t('comments', 'Date')],
            'ownerId' => ['label' => Craft::t('comments', 'Element')],
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
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'ownerId': {
                $owner = $this->getOwner();
                
                if ($owner) {
                    return "<a href='" . $owner->cpEditUrl . "'>" . $owner->title . "</a>";
                } else {
                    return Craft::t('comments', '[Deleted element]');
                }
            }
            default: {
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
        $oldParentQuery->enabledForSite(false);
        $oldParentQuery->select('elements.id');
        $oldParentId = $oldParentQuery->scalar();

        return $this->newParentId != $oldParentId;
    }

}
