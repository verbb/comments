<?php
namespace verbb\comments\queue\jobs;

use verbb\comments\Comments;

use Craft;
use craft\queue\BaseJob;

use Exception;

class SendNotification extends BaseJob
{
    // Public Properties
    // =========================================================================

    public string $type;
    public int $commentId;


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

        $comment = Comments::$plugin->getComments()->getCommentById($this->commentId);

        if (!$comment) {
            throw new Exception('Unable to find comment: ' . $this->commentId . '.');
        }

        Comments::$plugin->getComments()->triggerNotificationEmail($this->type, $comment);

        $this->setProgress($queue, 1);
    }
}
