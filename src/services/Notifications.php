<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\errors\NotificationDeliveryException;
use verbb\comments\events\NotifyEvent;
use verbb\comments\models\Settings;
use verbb\comments\queue\jobs\SendNotification;
use verbb\comments\records\Report as ReportRecord;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\User;

/**
 * Post-commit notification orchestration. No Outbox — queue concrete jobs after save.
 *
 * Pending/Spam do not trigger participant notifications. Approval may trigger delayed public ones.
 * Guests, who have no account or other channel, are emailed. Everyone with an account, staff included,
 * is passed to `EVENT_AFTER_NOTIFY` for the site or a notifications plugin to tell. The one exception is
 * the opt-in Email the Moderator Group setting, which also emails the staff that event names.
 */
class Notifications extends Component
{
    // Constants
    // =========================================================================

    /**
     * Fired with the account holders who should hear about a comment, once it's settled in the state
     * that notifies them. Comments sends them nothing itself.
     */
    public const EVENT_AFTER_NOTIFY = 'afterNotify';

    public const TYPE_AUTHOR = 'author';
    public const TYPE_REPLY = 'reply';
    public const TYPE_SUBSCRIBE = 'subscribe';
    public const TYPE_MODERATOR = 'moderator';
    public const TYPE_MODERATOR_EDIT = 'moderator-edit';
    public const TYPE_MODERATOR_APPROVED = 'moderator-approved';
    public const TYPE_ADMIN = 'admin';
    public const TYPE_FLAG = 'flag';
    public const TYPE_MENTION = 'mention';


    // Public Methods
    // =========================================================================

    /**
     * After Comments::create() commits.
     */
    public function queueForNewComment(Comment $comment, ModerationState $state): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        // Admin can still see spam/pending activity when enabled.
        if ($settings->isStaffNotificationEnabled(Settings::NOTIFICATION_STAFF_NEW)) {
            $this->queue(self::TYPE_ADMIN, $comment);
        }

        if ($state === ModerationState::Pending) {
            if ($settings->isStaffNotificationEnabled(Settings::NOTIFICATION_MODERATOR_PENDING)) {
                $this->queue(self::TYPE_MODERATOR, $comment);
            }

            // Pending: no author/reply/subscribe participant notifications.
            return;
        }

        if ($state === ModerationState::Spam || $state === ModerationState::Removed) {
            // Spam/removed creates do not notify participants.
            return;
        }

        // Approved: public participant notifications.
        $this->_queueParticipantNotifications($comment);
    }

    /**
     * After a Moderation state change commits.
     */
    public function queueAfterModeration(
        Comment $comment,
        ModerationState $fromState,
        ModerationState $toState,
        string $action,
        ?int $actorUserId = null,
    ): void {
        $settings = CommentsPlugin::$plugin->getSettings();

        // Pending → Approved: delayed public notifications + optional author “approved” mail. Only the
        // first time it's published, so holding and re-approving a comment doesn't mail everyone again.
        if (
            $fromState === ModerationState::Pending
            && $toState === ModerationState::Approved
            && !CommentsPlugin::$plugin->getModeration()->wasPreviouslyPublic($comment)
        ) {
            if ($settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_APPROVED)) {
                $this->queue(self::TYPE_MODERATOR_APPROVED, $comment);
            }

            $this->_queueParticipantNotifications($comment);
            $this->_queuePublishedMentionNotifications($comment);

            return;
        }

        // Mark spam from public: no participant fan-out.
        if ($toState === ModerationState::Spam) {
            return;
        }
    }

    public function queueForReport(Comment $comment, ReportRecord $report): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        if ($settings->isStaffNotificationEnabled(Settings::NOTIFICATION_STAFF_REPORTED)) {
            // The reporter is left out of the staff they're telling.
            $reporterUserId = CommentsPlugin::$plugin->getIdentities()->getById((int)$report->identityId)?->userId;
            $this->queue(self::TYPE_FLAG, $comment, null, $reporterUserId ? (int)$reporterUserId : null);
        }
    }

    public function queueForModeratorEdit(Comment $comment): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        if ($settings->isStaffNotificationEnabled(Settings::NOTIFICATION_MODERATOR_EDITED)) {
            $this->queue(self::TYPE_MODERATOR_EDIT, $comment);
        }
    }

    /**
     * Queue or send a single notification type. Uses Craft Queue when settings request it.
     */
    public function queue(string $type, Comment $comment, ?int $mentionedIdentityId = null, ?int $actorUserId = null): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        if ($settings->useQueueForNotifications) {
            Craft::$app->getQueue()->push(new SendNotification([
                'commentId' => (int)$comment->id,
                'siteId' => $comment->siteId ? (int)$comment->siteId : null,
                'type' => $type,
                'mentionedIdentityId' => $mentionedIdentityId,
                'actorUserId' => $actorUserId,
            ]));

            return;
        }

        try {
            CommentsPlugin::$plugin->getComments()->triggerNotificationEmail($type, $comment, $mentionedIdentityId, $actorUserId);
        } catch (NotificationDeliveryException) {
            // Already logged. Without a queue job to fail, this shouldn't undo the comment being saved.
        }
    }

    /**
     * Name account holders in the event, once each, and return who was named.
     */
    public function notifyAccountHolders(string $type, Comment $comment, ?ElementInterface $element, array $recipients): array
    {
        $unique = [];

        foreach ($recipients as $recipient) {
            if ($recipient instanceof User && $recipient->id) {
                $unique[(int)$recipient->id] = $recipient;
            }
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_NOTIFY)) {
            $this->trigger(self::EVENT_AFTER_NOTIFY, new NotifyEvent([
                'type' => $type,
                'comment' => $comment,
                'element' => $element,
                'recipients' => array_values($unique),
            ]));
        }

        return array_values($unique);
    }

    public function queueMentionNotifications(Comment $comment, array $identityIds): void
    {
        if (!$identityIds || !CommentsPlugin::$plugin->getSettings()->isParticipantNotificationEnabled(Settings::NOTIFICATION_MENTION)) {
            return;
        }

        foreach ($identityIds as $identityId) {
            $this->queue(self::TYPE_MENTION, $comment, (int)$identityId);
        }
    }


    // Private Methods
    // =========================================================================

    private function _queuePublishedMentionNotifications(Comment $comment): void
    {
        $identityIds = CommentsPlugin::$plugin->getMentions()->getIdentityIdsForComment((int)$comment->id);

        $this->queueMentionNotifications(
            $comment,
            CommentsPlugin::$plugin->getMentions()->newlyAddedIdentityIds(
                [],
                $identityIds,
                $comment->identityId ? (int)$comment->identityId : null,
            ),
        );
    }

    private function _queueParticipantNotifications(Comment $comment): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        if ($settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_OWNER)) {
            $this->queue(self::TYPE_AUTHOR, $comment);
        }

        if ($settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_REPLY) && $comment->parentId) {
            $this->queue(self::TYPE_REPLY, $comment);
        }

        if ($settings->subscriptionTypes !== []) {
            $this->queue(self::TYPE_SUBSCRIBE, $comment);
        }
    }
}
