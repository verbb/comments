<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationMode;
use verbb\comments\enums\ModerationState;
use verbb\comments\fields\CommentsField;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\helpers\UrlHelper;

use DateTime;

/** Provides the compact operational summary shown by the Comments custom field. */
class OwnerElement extends Component
{
    // Public Methods
    // =========================================================================

    public function getFieldSummary(int $ownerId, int $siteId): ?array
    {
        if (!$this->_canView()) {
            return null;
        }

        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if (!$owner) {
            return null;
        }

        $conversations = CommentsPlugin::$plugin->getConversations();
        $config = $conversations->getConfigForOwner($ownerId, $siteId);
        $summary = $conversations->getSummaryForOwner($ownerId, $siteId);
        $status = $config->enabled
            ? ($config->closed ? 'closed' : 'open')
            : 'disabled';

        return [
            'status' => $status,
            'statusLabel' => $this->_statusLabel($status),
            'statusText' => $this->_statusText($status),
            'counts' => [
                'approved' => $summary?->commentCount ?? 0,
                'pending' => (int)Comment::find()
                    ->status(null)
                    ->ownerId($ownerId)
                    ->ownerSiteId($siteId)
                    ->moderationState(ModerationState::Pending->value)
                    ->count(),
                'openReports' => $this->_openReportCount($ownerId, $siteId),
            ],
            'manageUrl' => UrlHelper::cpUrl('comments', [
                'site' => Craft::$app->getSites()->getSiteById($siteId)?->handle,
                'ownerId' => $ownerId,
            ]),
            'waiting' => $this->_waiting($ownerId, $siteId),
            'latest' => $this->_latest($ownerId, $siteId),
            'following' => $summary ? count(CommentsPlugin::$plugin->getSubscriptions()->getConversationSubscribers((int)$summary->conversationId)) : 0,
        ];
    }

    /**
     * The open, closed or off state the element gets while its Comments field follows the source
     * setting, so the field can show what "inherit" currently means.
     *
     * Mirrors `Conversations::getConfigForOwner()` without the field's own override. The field is the
     * only writer of a Conversation's closed flag, so an inheriting element is closed only when the
     * global setting closes everything.
     */
    public function getSourceStatus(ElementInterface $owner): string
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $enabled = CommentsPlugin::$plugin->getComments()->checkPermissions($owner);
        $ownerId = $owner->getCanonicalId() ?? $owner->id;

        // A Conversation can switch moderation off, which turns comments off whatever the source says.
        if ($enabled && $ownerId && $owner->siteId) {
            $mode = CommentsPlugin::$plugin->getConversations()->getConfigForOwner((int)$ownerId, (int)$owner->siteId)->moderationMode;
            $enabled = $mode !== ModerationMode::Disabled;
        }

        if (!$enabled) {
            return 'disabled';
        }

        return $settings->closed ? 'closed' : 'open';
    }

    /**
     * The field's three explicit choices in display order, keyed by the value the field stores, with
     * the matching summary status and the sentence explaining it.
     */
    public function getStateOptions(): array
    {
        $options = [];

        foreach ([
            CommentsField::STATE_ENABLED => 'open',
            CommentsField::STATE_CLOSED => 'closed',
            CommentsField::STATE_DISABLED => 'disabled',
        ] as $value => $status) {
            $options[$value] = [
                'status' => $status,
                'label' => $this->_statusLabel($status),
                'text' => $this->_statusText($status),
            ];
        }

        return $options;
    }


    // Private Methods
    // =========================================================================

    private function _canView(): bool
    {
        $user = Craft::$app->getUser();

        return $user->getIsAdmin() || $user->checkPermission('comments-view');
    }

    private function _statusLabel(string $status): string
    {
        return match ($status) {
            'open' => Craft::t('comments', 'Open'),
            'closed' => Craft::t('comments', 'Closed'),
            default => Craft::t('comments', 'Off'),
        };
    }

    private function _statusText(string $status): string
    {
        return match ($status) {
            'open' => Craft::t('comments', 'Visitors can read and post.'),
            'closed' => Craft::t('comments', 'Visitors can read, but not post.'),
            default => Craft::t('comments', 'Comments are hidden from visitors.'),
        };
    }

    /**
     * The comments on this element that wait for a moderator, held or reported, oldest first, with the
     * decisions the panel offers on each. A held comment gets Approve and Spam, a reported one Keep
     * and Remove; anything else stays on the comment's own page.
     */
    private function _waiting(int $ownerId, int $siteId): array
    {
        $moderation = CommentsPlugin::$plugin->getModeration();
        $viewer = Craft::$app->getUser()->getIdentity();
        $base = static fn(): \verbb\comments\elements\db\CommentQuery => Comment::find()->status(null)->ownerId($ownerId)->ownerSiteId($siteId)->orderBy(['commentDate' => SORT_ASC])->limit(5);
        $comments = array_merge(
            $base()->moderationState(ModerationState::Pending->value)->all(),
            $base()->moderationState(ModerationState::Approved->value)->hasOpenReports(true)->all(),
        );

        return array_map(function(Comment $comment) use ($moderation, $viewer): array {
            $pending = $comment->moderationState === ModerationState::Pending->value;
            $held = HeldReason::tryFrom((string)$comment->heldReason);
            $offered = $pending
                ? [Moderation::DECISION_APPROVE, Moderation::DECISION_SPAM]
                : [Moderation::DECISION_KEEP, Moderation::DECISION_REMOVE];

            return [
                'comment' => $comment,
                'kind' => $pending ? 'pending' : 'reported',
                'reason' => $pending
                    ? ($held ?? HeldReason::PreModeration)->label()
                    : Craft::t('comments', 'Reported'),
                'author' => $this->_author($comment),
                'age' => $comment->getTimeAgo(),
                // Keep the offered order so the primary action is always first.
                'decisions' => array_values(array_intersect($offered, $moderation->availableDecisions($comment, $viewer))),
            ];
        }, array_slice($comments, 0, 5));
    }

    private function _latest(int $ownerId, int $siteId): array
    {
        $comments = Comment::find()
            ->ownerId($ownerId)
            ->ownerSiteId($siteId)
            ->moderationState(ModerationState::Approved->value)
            ->orderBy(['commentDate' => SORT_DESC])
            ->limit(3)
            ->all();

        return array_map(fn(Comment $comment): array => [
            'comment' => $comment,
            'author' => $this->_author($comment),
            'age' => $this->_shortAge($comment->commentDate),
        ], $comments);
    }

    /**
     * The shared avatar and name treatment: the full name, and the user's photo or initials on a
     * colour picked from the name.
     */
    private function _author(Comment $comment): array
    {
        $name = (string)($comment->getAuthorName() ?: Craft::t('comments', 'Guest'));
        $author = $comment->getAuthor();
        $photo = $author instanceof User && $author->photoId ? $author->getPhoto() : null;

        return [
            'name' => $name,
            'initials' => Commenters::initials($name),
            'tone' => Frontend::avatarTone($name),
            'photoUrl' => $photo ? Craft::$app->getAssets()->getThumbUrl($photo, 56) : null,
        ];
    }

    /**
     * A compact age for the Latest list, such as "5 h" or "2 d", falling back to a short date once a
     * comment is more than a few weeks old.
     */
    private function _shortAge(?DateTime $date): string
    {
        if (!$date) {
            return '';
        }

        $seconds = max(0, time() - $date->getTimestamp());

        return match (true) {
            $seconds < 60 => Craft::t('comments', 'now'),
            $seconds < 3600 => Craft::t('comments', '{num} m', ['num' => intdiv($seconds, 60)]),
            $seconds < 86400 => Craft::t('comments', '{num} h', ['num' => intdiv($seconds, 3600)]),
            $seconds < 86400 * 7 => Craft::t('comments', '{num} d', ['num' => intdiv($seconds, 86400)]),
            $seconds < 86400 * 28 => Craft::t('comments', '{num} w', ['num' => intdiv($seconds, 86400 * 7)]),
            default => Craft::$app->getFormatter()->asDate($date, 'short'),
        };
    }

    /**
     * Reported comments, not reports: three reports on one comment are one comment to decide. Like the
     * index's Reported source (AD-S2-8), that's an approved comment with an open report, so the count
     * matches the cards below it, which the field's script steps down by one per decision.
     */
    private function _openReportCount(int $ownerId, int $siteId): int
    {
        return (int)Comment::find()
            ->status(null)
            ->ownerId($ownerId)
            ->ownerSiteId($siteId)
            ->moderationState(ModerationState::Approved->value)
            ->hasOpenReports(true)
            ->count();
    }
}
