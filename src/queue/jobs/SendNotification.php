<?php
namespace verbb\comments\queue\jobs;

use verbb\comments\Comments;

use Craft;
use craft\queue\BaseJob;

use Exception;

class SendNotification extends BaseJob
{
    // Properties
    // =========================================================================

    public int $commentId;
    public ?int $siteId = null;
    public string $type;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getDescription(): ?string
    {
        return Craft::t('comments', 'Sending comment notification.');
    }

    /**
     * @inheritDoc
     */
    public function execute($queue): void
    {
        $this->setProgress($queue, 0);

        $comment = Comments::$plugin->getComments()->getCommentById($this->commentId, $this->siteId);

        if (!$comment) {
            throw new Exception('Unable to find comment: ' . $this->commentId . '.');
        }

        Comments::$plugin->getComments()->triggerNotificationEmail($this->type, $comment);

        $this->setProgress($queue, 1);
    }
}
