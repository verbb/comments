<?php
namespace verbb\comments\variables;

use verbb\comments\Comments;
use verbb\comments\compatibility\twig\LegacyVariable;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\LoadMode;
use verbb\comments\enums\RemovalReason;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\exceptions\ApiException;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\ImageMetadata;
use verbb\comments\helpers\SettingsTabs;
use verbb\comments\helpers\UploadPolicy;
use verbb\comments\migrations\support\V3UpgradeLog;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Template;

use Twig\Markup;

class CommentsVariable
{
    // Traits
    // =========================================================================

    use LegacyVariable;


    // Public Methods
    // =========================================================================

    public function getPluginName(): string
    {
        return Comments::$plugin->getPluginName();
    }

    /**
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getSettingsNavItems(): array
    {
        return SettingsTabs::navItems();
    }

    /**
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getInteractionModeOptions(): array
    {
        return InteractionMode::options();
    }

    /**
     * The site origin the attachment volume serves files from, for the settings page's warning.
     *
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getAttachmentVolumeSiteOrigin(mixed $volumeId): ?string
    {
        return UploadPolicy::volumeSiteOrigin(is_numeric($volumeId) ? (int)$volumeId : null);
    }

    /**
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getLoadModeOptions(): array
    {
        return LoadMode::options();
    }

    /**
     * Each custom field in the Comment layout for the Comment Form Fields setting: its mode, placement
     * and replies switch, whether it can be collected and why not, and notes about choosing it.
     *
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getFormFieldRows(): array
    {
        return Comments::$plugin->getFormFields()->settingsRows();
    }

    /**
     * Whether HEIC and AVIF uploads are accepted, which needs ImageMagick to strip their metadata.
     *
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getCanStripHeicAndAvif(): bool
    {
        return ImageMetadata::canStripWithImagick('image/heic') && ImageMetadata::canStripWithImagick('image/avif');
    }

    /**
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function getUpgradeIssues(): array
    {
        return V3UpgradeLog::issues(Craft::$app->getDb());
    }

    /**
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function removalReasons(): array
    {
        return RemovalReason::options();
    }

    /**
     * For the control panel only. It isn't part of the documented Twig API, and can change in any release.
     *
     * @internal
     */
    public function reportReasonLabel(?string $reason): string
    {
        return Comments::$plugin->getReports()->reasonLabel($reason);
    }

    public function fetch($criteria = null): CommentQuery
    {
        return Comments::$plugin->getComments()->fetch($criteria);
    }

    /**
     * `craft.comments.render(entry, options)`, with the element or its ID. Options that aren't an array,
     * or that `render()` doesn't know, are ignored.
     */
    public function render(ElementInterface|int|string|null $owner, mixed $options = []): Markup
    {
        return Comments::$plugin->getComments()->render($owner, $options);
    }

    /**
     * `craft.comments.structuredData(entry, options)`: schema.org JSON-LD for an element's approved
     * comments, as a `<script type="application/ld+json">` tag. Options are `limit` (20 by default),
     * `includeAuthors` to add commenters' public names (left out by default), `id` to attach the comments
     * to a node an SEO plugin already outputs, and `asArray` to get the data instead of the tag. Nothing
     * is output for an element without a live thread.
     */
    public function structuredData(ElementInterface|int|string|null $owner, mixed $options = []): Markup|array
    {
        $options = is_array($options) ? $options : [];
        $api = Comments::$plugin->getApi();
        $asArray = (bool)($options['asArray'] ?? false);

        // A draft or a Live Preview describes the entry it belongs to.
        if ($owner instanceof ElementInterface) {
            $siteId = $owner->siteId;
            $owner = $owner->getCanonicalId();
        }

        try {
            $element = $api->resolveOwner(is_numeric($owner) ? (int)$owner : null, null, $siteId ?? null);
            $data = $api->structuredData($element, $options);
        } catch (ApiException) {
            return $asArray ? [] : Template::raw('');
        }

        if ($asArray) {
            return $data;
        }

        // Escaped so nothing a commenter wrote can close the script tag.
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);

        return Template::raw('<script type="application/ld+json">' . $json . '</script>');
    }

    public function protect(mixed $ownerId = null, ?int $siteId = null, ?int $commentId = null): Markup
    {
        // Comments 3's `protect()` took no element, which compatibility mode reads as the page's own.
        [$ownerId, $siteId] = $this->legacyProtectOwner($ownerId, $siteId);

        if (!$ownerId) {
            return Template::raw('');
        }

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
