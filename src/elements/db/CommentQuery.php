<?php
namespace verbb\comments\elements\db;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\models\Section;
use craft\models\Site;

use DateTime;
use Exception;

class CommentQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    public ?bool $withStructure = true;

    public mixed $ownerId = null;
    public mixed $ownerSiteId = null;
    public mixed $userId = null;
    public mixed $name = null;
    public mixed $email = null;
    public mixed $comment = null;
    public mixed $url = null;
    public mixed $ipAddress = null;
    public mixed $userAgent = null;
    public mixed $commentDate = null;
    public array|string|null $status = null;

    public mixed $parentId = null;
    public mixed $ownerType = null;
    public mixed $ownerSectionId = null;
    public mixed $ownerSection = null;
    public mixed $isFlagged = null;


    // Public Methods
    // =========================================================================

    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Comments::$plugin->getSettings()->defaultQueryStatus;
        }

        parent::__construct($elementType, $config);
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'ownerSection':
                $this->ownerSection($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    public function ownerType($value): static
    {
        $this->ownerType = $value;
        return $this;
    }

    public function owner(ElementInterface $owner): static
    {
        $this->ownerId = $owner->id;
        $this->siteId = $owner->siteId;
        return $this;
    }

    public function ownerId($value): static
    {
        $this->ownerId = $value;
        return $this;
    }

    public function ownerSiteId($value): static
    {
        $this->ownerSiteId = $value;

        if ($value && strtolower($value) !== ':empty:') {
            // A block will never exist in a site that is different from its ownerSiteId,
            // so let's set the siteId param here too.
            $this->siteId = (int)$value;
        }

        return $this;
    }

    public function ownerSite($value): static
    {
        if ($value instanceof Site) {
            $this->ownerSiteId($value->id);
        } else {
            $site = Craft::$app->getSites()->getSiteByHandle($value);

            if (!$site) {
                throw new Exception('Invalid site handle: ' . $value);
            }

            $this->ownerSiteId($site->id);
        }

        return $this;
    }

    public function ownerSectionId($value): static
    {
        $this->ownerSectionId = $value;
        return $this;
    }

    public function ownerSection(mixed $value): static
    {
        if ($value instanceof Section) {
            $this->ownerSectionId = $value->id;
        } else if ($value !== null) {
            $this->ownerSectionId = (new Query())
                ->select(['id'])
                ->from([Table::SECTIONS])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->ownerSectionId = null;
        }

        return $this;
    }

    public function userId($value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function name($value): static
    {
        $this->name = $value;
        return $this;
    }

    public function email($value): static
    {
        $this->email = $value;
        return $this;
    }

    public function comment($value): static
    {
        $this->comment = $value;
        return $this;
    }

    public function url($value): static
    {
        $this->url = $value;
        return $this;
    }

    public function ipAddress($value): static
    {
        $this->ipAddress = $value;
        return $this;
    }

    public function userAgent($value): static
    {
        $this->userAgent = $value;
        return $this;
    }

    public function commentDate($value): static
    {
        $this->commentDate = $value;
        return $this;
    }

    public function isFlagged($value): static
    {
        $this->isFlagged = $value;
        return $this;
    }

    public function populate($rows): array
    {
        $results = parent::populate($rows);

        // Store the comment IDs we're fetching, so we can use them later in render functions
        // to limit DB queries to just this collection of comments (votes, flags.
        // But - because we can't rely on getting child comments this way, do another query by the owner
        // element, so we can be sure we're fetching all the comments for the page.
        $ownerId = $results[0]['ownerId'] ?? null;
        $ownerSiteId = $results[0]['ownerSiteId'] ?? null;

        if ($ownerId && $ownerSiteId) {
            $commentIds = (new Query())
                ->select('id')
                ->from('{{%comments_comments}}')
                ->where(['ownerId' => $ownerId, 'ownerSiteId' => $ownerSiteId])
                ->column();

            Comments::$plugin->getRenderCache()->setCommentIds($commentIds);
        }

        return $results;
    }


    // Protected Methods
    // =========================================================================

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('comments_comments');

        $this->query->select([
            'comments_comments.id',
            'comments_comments.ownerId',
            'comments_comments.ownerSiteId',
            'comments_comments.userId',
            'comments_comments.status',
            'comments_comments.name',
            'comments_comments.email',
            'comments_comments.url',
            'comments_comments.comment',
            'comments_comments.ipAddress',
            'comments_comments.userAgent',
            'comments_comments.commentDate',
        ]);

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.ownerId', $this->ownerId));
        }

        if ($this->ownerSiteId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.ownerSiteId', $this->ownerSiteId));
        }

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.userId', $this->userId));
        }

        if ($this->status) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.status', $this->status));
        }

        if ($this->name) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.name', $this->name));
        }

        if ($this->email) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.email', $this->email));
        }

        if ($this->comment) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.comment', $this->comment));
        }

        if ($this->url) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.url', $this->url));
        }

        if ($this->ipAddress) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.ipAddress', $this->ipAddress));
        }

        if ($this->userAgent) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.userAgent', $this->userAgent));
        }

        if ($this->commentDate) {
            $this->subQuery->andWhere(Db::parseDateParam('comments_comments.commentDate', $this->commentDate));
        }

        if ($this->isFlagged) {
            $this->subQuery->innerJoin('{{%comments_flags}} comments_flags', '[[comments_comments.id]] = [[comments_flags.commentId]]');
        }

        if ($this->ownerType) {
            $this->subQuery->innerJoin('{{%elements}} ownerElements', '[[comments_comments.ownerId]] = [[ownerElements.id]]');
            $this->subQuery->andWhere(Db::parseParam('ownerElements.type', $this->ownerType));
        }

        if ($this->ownerSectionId) {
            $this->subQuery->innerJoin('{{%entries}} ownerElements', '[[comments_comments.ownerId]] = [[ownerElements.id]]');
            $this->subQuery->andWhere(Db::parseParam('ownerElements.sectionId', $this->ownerSectionId));
        }

        if ($this->_orderByVotes()) {
            $subQuery = (new Query())
                ->select(['(IFNULL(SUM(comments_votes.upvote), 0) - IFNULL(SUM(comments_votes.downvote), 0))'])
                ->from('{{%comments_votes}} comments_votes')
                ->where('[[comments_votes.commentId]] = [[elements.id]]');

            $this->subQuery->addSelect(['voteCount' => $subQuery]);
        }

        if ($this->_orderByFlagged()) {
            $subQuery = (new Query())
                ->select(['SUM(NOT(ISNULL(comments_flags.id)))'])
                ->from('{{%comments_flags}} comments_flags')
                ->where('[[comments_flags.commentId]] = [[elements.id]]');

            $this->subQuery->addSelect(['flagCount' => $subQuery]);
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            Comment::STATUS_APPROVED => [
                'comments_comments.status' => Comment::STATUS_APPROVED,
            ],
            Comment::STATUS_PENDING => [
                'comments_comments.status' => Comment::STATUS_PENDING,
            ],
            Comment::STATUS_SPAM => [
                'comments_comments.status' => Comment::STATUS_SPAM,
            ],
            Comment::STATUS_TRASHED => [
                'comments_comments.status' => Comment::STATUS_TRASHED,
            ],
            default => parent::statusCondition($status),
        };
    }


    // Private Methods
    // =========================================================================

    private function _orderByVotes(): bool|string
    {
        if ($this->orderBy) {
            if (is_string($this->orderBy)) {
                return strstr($this->orderBy, 'voteCount');
            }

            if (is_array($this->orderBy)) {
                return isset($this->orderBy['voteCount']);
            }
        }

        return false;
    }

    private function _orderByFlagged(): bool|string
    {
        if ($this->orderBy) {
            if (is_string($this->orderBy)) {
                return strstr($this->orderBy, 'flagCount');
            }

            if (is_array($this->orderBy)) {
                return isset($this->orderBy['flagCount']);
            }
        }

        return false;
    }
}
