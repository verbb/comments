<?php
namespace verbb\comments\variables;

use verbb\comments\Comments;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\RemovalReason;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\SettingsTabs;
use verbb\comments\migrations\support\V3UpgradeLog;

use Craft;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Template;

use Twig\Markup;

class CommentsVariable
{
    // Public Methods
    // =========================================================================

    public function getPluginName(): string
    {
        return Comments::$plugin->getPluginName();
    }

    public function getSettingsNavItems(): array
    {
        return SettingsTabs::navItems();
    }

    public function getInteractionModeOptions(): array
    {
        return InteractionMode::options();
    }

    public function getUpgradeIssues(): array
    {
        return V3UpgradeLog::issues(Craft::$app->getDb());
    }

    public function removalReasons(): array
    {
        return RemovalReason::options();
    }

    public function reportReasonLabel(?string $reason): string
    {
        return Comments::$plugin->getReports()->reasonLabel($reason);
    }

    public function fetch($criteria = null): CommentQuery
    {
        return Comments::$plugin->getComments()->fetch($criteria);
    }

    public function render($elementId, $criteria = [], $jsSettings = []): Markup
    {
        return Comments::$plugin->getComments()->render($elementId, $criteria, $jsSettings);
    }

    public function protect(int $ownerId, ?int $siteId = null, ?int $commentId = null): Markup
    {
        $fields = Comments::$plugin->getProtection()->getFields($ownerId, $siteId ?? (int)Craft::$app->getSites()->getCurrentSite()->id, $commentId);

        return Template::raw($fields);
    }

    public function isSubscribed($element, $comment = null): bool
    {
        $currentUser = Comments::$plugin->getService()->getUser();
        $elementId = $element->id ?? null;
        $elementSiteId = $element->siteId ?? null;
        $commentId = $comment->id ?? null;

        if (!$elementId || !$elementSiteId || !$currentUser) {
            return false;
        }

        $conversation = Comments::$plugin->getConversations()->findForOwner((int)$elementId, (int)$elementSiteId);

        if ($conversation) {
            $identity = Comments::$plugin->getIdentities()->findForUser((int)$currentUser->id);

            if (!$identity) {
                return false;
            }

            return Comments::$plugin->getSubscriptions()->isSubscribed(
                (int)$conversation->id,
                (int)$identity->id,
                $commentId ? (int)$commentId : null,
                $commentId ? SubscriptionType::Replies : SubscriptionType::Conversation,
            );
        }

        return false;
    }

    public function getSummary($element)
    {
        $elementId = $element->id ?? null;
        $elementSiteId = $element->siteId ?? null;

        if (!$elementId || !$elementSiteId) {
            return null;
        }

        return Comments::$plugin->getConversations()->getSummaryForOwner((int)$elementId, (int)$elementSiteId);
    }

    public function getConfig($element)
    {
        $elementId = $element->id ?? null;
        $elementSiteId = $element->siteId ?? null;

        if (!$elementId || !$elementSiteId) {
            return null;
        }

        return Comments::$plugin->getConversations()->getConfigForOwner((int)$elementId, (int)$elementSiteId);
    }

    public function getAvatar(): string|Asset
    {
        return CommentsHelper::getAvatar(Comments::$plugin->getService()->getUser());
    }

    public function getUserVotes($userId): array
    {
        return Comments::$plugin->getInteractions()->getForUser((int)$userId);
    }

    public function getUserDownvotes($userId): array
    {
        return Comments::$plugin->getInteractions()->getForUser((int)$userId, \verbb\comments\enums\InteractionType::Downvote);
    }

    public function getUserUpvotes($userId): array
    {
        return Comments::$plugin->getInteractions()->getForUser((int)$userId, \verbb\comments\enums\InteractionType::Upvote);
    }
}
