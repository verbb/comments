<?php
namespace verbb\comments\queue\jobs;

use verbb\comments\Comments;

use Craft;
use craft\queue\BaseJob;

/**
 * Always queued, so a request takes the same time whether or not an address is already registered.
 */
class SendEmailConfirmation extends BaseJob
{
    // Properties
    // =========================================================================

    public int $identityId;
    public string $email;
    public ?int $conversationId = null;
    public int $siteId;


    // Public Methods
    // =========================================================================

    public function getDescription(): ?string
    {
        return Craft::t('comments', 'Sending comment email confirmation.');
    }

    public function execute($queue): void
    {
        Comments::$plugin->getComments()->sendGuestConfirmationEmail($this->identityId, $this->email, $this->conversationId, $this->siteId);
    }
}
