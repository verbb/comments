<?php
namespace verbb\comments\queue\jobs;

use verbb\comments\Comments;

use Craft;
use craft\queue\BaseJob;

/**
 * Fetches commenters' Gravatar images for the site to serve, away from the page that needed them.
 */
class FetchAvatars extends BaseJob
{
    // Properties
    // =========================================================================

    /** @var int[] */
    public array $identityIds = [];


    // Public Methods
    // =========================================================================

    public function getDescription(): ?string
    {
        return Craft::t('comments', 'Fetching commenters’ Gravatar images.');
    }

    public function execute($queue): void
    {
        Comments::$plugin->getAvatars()->refresh($this->identityIds);
    }
}
