<?php
namespace verbb\comments\base;

use verbb\comments\Comments;
use verbb\comments\services\CommentAssets;
use verbb\comments\services\CommentEditor;
use verbb\comments\services\Commenters;
use verbb\comments\services\Comments as CommentsService;
use verbb\comments\services\Conversations;
use verbb\comments\services\Diagnostics;
use verbb\comments\services\Frontend;
use verbb\comments\services\Identities;
use verbb\comments\services\Interactions;
use verbb\comments\services\Mentions;
use verbb\comments\services\Moderation;
use verbb\comments\services\Notifications;
use verbb\comments\services\OwnerElement;
use verbb\comments\services\Protection;
use verbb\comments\services\RenderCache;
use verbb\comments\services\Reports;
use verbb\comments\services\Security;
use verbb\comments\services\Service;
use verbb\comments\services\Subscriptions;
use verbb\comments\services\Templates;

use Craft;
use craft\helpers\App;

use verbb\base\helpers\Plugin;
use verbb\base\LogTrait;

trait PluginTrait
{
    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('comments');

        return [
            'components' => [
                'comments' => CommentsService::class,
                'conversations' => Conversations::class,
                'identities' => Identities::class,
                'mentions' => Mentions::class,
                'commentAssets' => CommentAssets::class,
                'moderation' => Moderation::class,
                'ownerElement' => OwnerElement::class,
                'commentEditor' => CommentEditor::class,
                'commenters' => Commenters::class,
                'diagnostics' => Diagnostics::class,
                'interactions' => Interactions::class,
                'reports' => Reports::class,
                'subscriptions' => Subscriptions::class,
                'notifications' => Notifications::class,
                'frontend' => Frontend::class,
                'protection' => Protection::class,
                'renderCache' => RenderCache::class,
                'security' => Security::class,
                'service' => Service::class,
                'templates' => Templates::class,
            ],
        ];
    }


    // Traits
    // =========================================================================

    use LogTrait;


    // Properties
    // =========================================================================

    public static ?Comments $plugin = null;


    // Public Methods
    // =========================================================================

    public function getComments(): CommentsService
    {
        return $this->get('comments');
    }

    public function getConversations(): Conversations
    {
        return $this->get('conversations');
    }

    public function getIdentities(): Identities
    {
        return $this->get('identities');
    }

    public function getMentions(): Mentions
    {
        return $this->get('mentions');
    }

    public function getCommentAssets(): CommentAssets
    {
        return $this->get('commentAssets');
    }

    public function getModeration(): Moderation
    {
        return $this->get('moderation');
    }

    public function getOwnerElement(): OwnerElement
    {
        return $this->get('ownerElement');
    }

    public function getCommentEditor(): CommentEditor
    {
        return $this->get('commentEditor');
    }

    public function getCommenters(): Commenters
    {
        return $this->get('commenters');
    }

    public function getDiagnostics(): Diagnostics
    {
        return $this->get('diagnostics');
    }

    public function getInteractions(): Interactions
    {
        return $this->get('interactions');
    }

    public function getReports(): Reports
    {
        return $this->get('reports');
    }

    public function getSubscriptions(): Subscriptions
    {
        return $this->get('subscriptions');
    }

    public function getNotifications(): Notifications
    {
        return $this->get('notifications');
    }

    public function getFrontend(): Frontend
    {
        return $this->get('frontend');
    }

    public function getProtection(): Protection
    {
        return $this->get('protection');
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

    public function getTemplates(): Templates
    {
        return $this->get('templates');
    }
}
