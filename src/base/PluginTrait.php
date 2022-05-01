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
use verbb\base\BaseHelper;

use Craft;

use yii\log\Logger;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static Comments $plugin;


    // Static Methods
    // =========================================================================

    public static function log(string $message, array $params = []): void
    {
        $message = Craft::t('comments', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'comments');
    }

    public static function error(string $message, array $params = []): void
    {
        $message = Craft::t('comments', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'comments');
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


    // Private Methods
    // =========================================================================

    private function _registerComponents(): void
    {
        $this->setComponents([
            'comments' => CommentsService::class,
            'flags' => Flags::class,
            'protect' => Protect::class,
            'renderCache' => RenderCache::class,
            'security' => Security::class,
            'service' => Service::class,
            'subscribe' => Subscribe::class,
            'votes' => Votes::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _registerLogTarget(): void
    {
        BaseHelper::setFileLogging('comments');
    }

}