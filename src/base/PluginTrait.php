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


    // Private Methods
    // =========================================================================

    private function _registerComponents(): void
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

    private function _registerLogTarget(): void
    {
        BaseHelper::setFileLogging('comments');
    }

}