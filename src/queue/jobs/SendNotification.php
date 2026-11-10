<?php
namespace verbb\comments\queue\jobs;

use verbb\comments\Comments;

use Craft;
use craft\queue\BaseJob;

/**
 * Concrete notification delivery job. Payload is stable scalars only — no serialized elements.
 */
class SendNotification extends BaseJob
{
    // Properties
    // =========================================================================

    public int $commentId;
    public ?int $siteId = null;
    public string $type;
    public ?int $mentionedIdentityId = null;
    public ?int $actorUserId = null;


    // Public Methods
    // =========================================================================

    public function getDescription(): ?string
    {
        return Craft::t('comments', 'Sending comment notification.');
    }

    public function execute($queue): void
    {
        $this->setProgress($queue, 0);

        $comment = Comments::$plugin->getComments()->getCommentById($this->commentId, $this->siteId);

        // Trashed or deleted since this was queued, so there's nothing left to tell anyone about.
        if (!$comment) {
            return;
        }

        // Throws if any recipient couldn't be reached, leaving the job failed in the queue manager.
        Comments::$plugin->getComments()->triggerNotificationEmail(
            $this->type,
            $comment,
            $this->mentionedIdentityId,
            $this->actorUserId,
        );

        $this->setProgress($queue, 1);
    }
}
