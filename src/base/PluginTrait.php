<?php
namespace verbb\comments\base;

use verbb\comments\Comments;
use verbb\comments\services\CommentsService;
use verbb\comments\services\FlagsService;
use verbb\comments\services\ProtectService;
use verbb\comments\services\SecurityService;
use verbb\comments\services\SubscribeService;
use verbb\comments\services\VotesService;

use Craft;
use craft\log\FileTarget;

use yii\log\Logger;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function getComments()
    {
        return $this->get('comments');
    }

    public function getFlags()
    {
        return $this->get('flags');
    }

    public function getProtect()
    {
        return $this->get('protect');
    }

    public function getSecurity()
    {
        return $this->get('security');
    }

    public function getSubscribe()
    {
        return $this->get('subscribe');
    }

    public function getVotes()
    {
        return $this->get('votes');
    }

    private function _setPluginComponents()
    {
        $this->setComponents([
            'comments' => CommentsService::class,
            'flags' => FlagsService::class,
            'protect' => ProtectService::class,
            'security' => SecurityService::class,
            'subscribe' => SubscribeService::class,
            'votes' => VotesService::class,
        ]);
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/comments.log'),
            'categories' => ['comments'],
        ]);
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'comments');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'comments');
    }

}