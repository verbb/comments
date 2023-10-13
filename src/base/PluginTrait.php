<?php
namespace verbb\comments\base;

use verbb\comments\Comments;
use verbb\comments\services\Comments as CommentsService;
use verbb\comments\services\Flags;
use verbb\comments\services\Protect;
use verbb\comments\services\RenderCache;
use verbb\comments\services\Security;
use verbb\comments\services\Service;
use verbb\comments\services\Subscribe;
use verbb\comments\services\Votes;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static ?Comments $plugin = null;


    // Traits
    // =========================================================================

    use LogTrait;


    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('comments');

        return [
            'components' => [
                'comments' => CommentsService::class,
                'flags' => Flags::class,
                'protect' => Protect::class,
                'renderCache' => RenderCache::class,
                'security' => Security::class,
                'service' => Service::class,
                'subscribe' => Subscribe::class,
                'votes' => Votes::class,
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getComments(): CommentsService
    {
        return $this->get('comments');
    }

    public function getFlags(): Flags
    {
        return $this->get('flags');
    }

    public function getProtect(): Protect
    {
        return $this->get('protect');
    }

    public function getRenderCache(): RenderCache
    {
        return $this->get('renderCache');
    }

    public function getSecurity(): Security
    {
        return $this->get('security');
    }

    public function getService(): Service
    {
        return $this->get('service');
    }

    public function getSubscribe(): Subscribe
    {
        return $this->get('subscribe');
    }

    public function getVotes(): Votes
    {
        return $this->get('votes');
    }

}