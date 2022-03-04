<?php
namespace verbb\comments\base;

use verbb\comments\Comments;
use verbb\comments\services\CommentsService;
use verbb\comments\services\FlagsService;
use verbb\comments\services\ProtectService;
use verbb\comments\services\RenderCacheService;
use verbb\comments\services\SecurityService;
use verbb\comments\services\Service;
use verbb\comments\services\SubscribeService;
use verbb\comments\services\VotesService;

use Craft;

use yii\log\Logger;

use verbb\base\BaseHelper;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static Comments $plugin;


    // Public Methods
    // =========================================================================

    public function getComments(): CommentsService
    {
        return $this->get('comments');
    }

    public function getFlags(): FlagsService
    {
        return $this->get('flags');
    }

    public function getProtect(): ProtectService
    {
        return $this->get('protect');
    }

    public function getRenderCache(): RenderCacheService
    {
        return $this->get('renderCache');
    }

    public function getSecurity(): SecurityService
    {
        return $this->get('security');
    }

    public function getService(): Service
    {
        return $this->get('service');
    }

    public function getSubscribe(): SubscribeService
    {
        return $this->get('subscribe');
    }

    public function getVotes(): VotesService
    {
        return $this->get('votes');
    }

    public static function log($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'comments');
    }

    public static function error($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'comments');
    }


    // Private Methods
    // =========================================================================

    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'comments' => CommentsService::class,
            'flags' => FlagsService::class,
            'protect' => ProtectService::class,
            'renderCache' => RenderCacheService::class,
            'security' => SecurityService::class,
            'service' => Service::class,
            'subscribe' => SubscribeService::class,
            'votes' => VotesService::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _setLogging(): void
    {
        BaseHelper::setFileLogging('comments');
    }

}