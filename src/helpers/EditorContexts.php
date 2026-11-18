<?php
namespace verbb\comments\helpers;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\integrations\EditorAssetProvider;
use verbb\comments\models\Settings;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\UrlHelper;

use verbb\editor\Editor;
use verbb\editor\models\AssetConfig;
use verbb\editor\models\EditorConfig;
use verbb\editor\models\ElementLinkConfig;
use verbb\editor\models\GifConfig;
use verbb\editor\models\MentionConfig;
use verbb\editor\services\EditorConfigs;

/**
 * Production Editor context registration for Comments front-end authoring.
 */
final class EditorContexts
{
    // Static Methods
    // =========================================================================

    public static function register(EditorConfigs $configs): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        // Replaces what's there, because `refresh()` registers these again.
        $configs->registerConfig(self::replyConfig($settings), true);
        $configs->registerConfig(self::editConfig($settings), true);
    }

    /**
     * Re-register contexts from current plugin settings (after CP saves or mid-request toggles).
     */
    public static function refresh(): void
    {
        self::register(Editor::getInstance()->getEditorConfigs());
    }

    /**
     * What the browser needs to run an editor for this viewer. It's per viewer and holds a CSRF token
     * when they're logged in, so it only goes into forms rendered for them.
     */
    public static function browserOptions(string $handle, int $ownerId, int $siteId, ?int $commentId = null): array
    {
        $scope = array_filter(['ownerId' => $ownerId, 'siteId' => $siteId, 'commentId' => $commentId]);
        $config = Editor::getInstance()->getEditorConfigs()->requireConfigByHandle($handle);

        // Mentions and uploads go through the data API, so every sign-in tier and Comments' upload
        // rules apply to them. Editor only adds the endpoints its features use.
        $endpoints = [
            'mentions' => UrlHelper::actionUrl('comments/api/mentions'),
            'upload' => UrlHelper::actionUrl('comments/api/upload'),
        ];

        // A member signed in by a token has no CSRF cookie to match a token against, and asking Craft
        // for one would set a cookie on an answer that mustn't have one.
        if (CommentsPlugin::$plugin->getSignIn()->usesHeaderToken()) {
            $generalConfig = Craft::$app->getConfig()->getGeneral();
            $csrf = $generalConfig->enableCsrfProtection;
            $generalConfig->enableCsrfProtection = false;

            try {
                $options = $config->browserOptions($scope, $endpoints);
            } finally {
                $generalConfig->enableCsrfProtection = $csrf;
            }
        } else {
            $options = $config->browserOptions($scope, $endpoints);
        }

        // Contexts register before the request's language is known.
        $options['placeholder'] = Craft::t('comments', (string)$options['placeholder']);

        return $options;
    }

    /**
     * The endpoints trust what this returns, and the browser can send anything. Mentions and uploads
     * only work somewhere this person could use them.
     */
    public static function resolveScope(array $scope, User $user, EditorConfig $config): ?array
    {
        $plugin = CommentsPlugin::$plugin;
        $ownerId = (int)($scope['ownerId'] ?? 0);
        $siteId = (int)($scope['siteId'] ?? 0);
        $elements = Craft::$app->getElements();
        $owner = $ownerId && $siteId ? $elements->getElementById($ownerId, null, $siteId) : null;

        if (!$owner) {
            return null;
        }

        if ($config->handle === 'comments.edit') {
            $comment = $plugin->getComments()->getCommentById((int)($scope['commentId'] ?? 0), $siteId);

            if (!$comment || (int)$comment->ownerId !== $ownerId) {
                return null;
            }

            // Someone who can edit anyone's comments may use the editor on them, except through a member
            // token from an origin that doesn't allow moderation.
            if (!$comment->canEdit() && !($plugin->getSignIn()->allowsModeration($user) && $elements->canSave($comment, $user))) {
                return null;
            }
        } elseif (
            !$plugin->getConversations()->getConfigForOwner($ownerId, $siteId)->enabled
            || (!$plugin->getConversations()->isOwnerPublic($owner) && !$elements->canView($owner, $user))
            || !$plugin->getSettings()->commentingAvailable($owner)['permission']
        ) {
            return null;
        }

        return array_filter([
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'commentId' => $config->handle === 'comments.edit' ? (int)($scope['commentId'] ?? 0) : null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * The context a stored comment is rendered in, so mentions resolve against its own conversation.
     */
    public static function forComment(Comment $comment): EditorConfig
    {
        $config = clone Editor::getInstance()->getEditorConfigs()->requireConfigByHandle('comments.reply');
        $config->referenceContext = [
            'ownerId' => (int)$comment->ownerId,
            'siteId' => (int)$comment->ownerSiteId,
            'commentId' => (int)$comment->id,
        ];

        return $config;
    }

    public static function replyConfig(?Settings $settings = null): EditorConfig
    {
        $settings ??= CommentsPlugin::$plugin->getSettings();
        // Contexts are only used once Comments has already selected rich authoring.
        $mentionsEnabled = $settings->allowMentions;
        $attachmentsEnabled = $settings->allowAttachments && $settings->isRichAuthoringEnabled();

        return new EditorConfig([
            'handle' => 'comments.reply',
            'placeholder' => 'Write a comment…',
            'features' => self::replyFeatures($mentionsEnabled, $attachmentsEnabled),
            'mentions' => new MentionConfig([
                'enabled' => $mentionsEnabled,
                'minQueryLength' => 2,
                'limit' => 10,
                // With a comment, the people it already mentions too, for editing and showing it.
                'eligibleIds' => static fn(array $context): array => isset($context['ownerId'], $context['siteId'])
                    ? CommentsPlugin::$plugin->getMentions()->eligibleUserIdsForOwner((int)$context['ownerId'], (int)$context['siteId'], isset($context['commentId']) ? (int)$context['commentId'] : null)
                    : [],
            ]),
            'elementLinks' => new ElementLinkConfig([
                'enabled' => false,
                'elementTypes' => [Entry::class],
                'limit' => 10,
            ]),
            'assets' => new AssetConfig([
                'enabled' => $attachmentsEnabled,
                'volumeId' => $settings->attachmentVolumeId,
                // A page, SVG or script can run from the upload volume's address, so they're never
                // offered, whatever the setting lists.
                'allowedExtensions' => array_values(array_filter(
                    $settings->attachmentExtensions,
                    static fn(mixed $extension): bool => is_string($extension) && !UploadPolicy::isBlockedExtension($extension),
                )),
                'maxFileSize' => $settings->attachmentMaxSize * 1024 * 1024,
            ]),
            // Editor's GIF control, saving to the comment's own GIF rather than its text (SD-52).
            'gifs' => new GifConfig([
                'enabled' => $settings->giphyEnabled && $settings->getGiphyApiKey() !== null,
                'apiKey' => $settings->getGiphyApiKey(),
                'rating' => $settings->giphyRating,
                'limit' => $settings->giphyLimit,
            ]),
            // Comments' upload rules, wherever an upload comes from.
            'assetProvider' => new EditorAssetProvider(),
            'resolveScope' => [self::class, 'resolveScope'],
        ]);
    }

    public static function editConfig(?Settings $settings = null): EditorConfig
    {
        $config = self::replyConfig($settings);
        $config->handle = 'comments.edit';
        $config->placeholder = 'Edit your comment…';

        return $config;
    }

    public static function replyFeatures(bool $mentionsEnabled = true, bool $attachmentsEnabled = false): array
    {
        return [
            'bold' => true,
            'italic' => true,
            'strike' => true,
            'lists' => true,
            'blockquote' => true,
            'code' => true,
            'link' => true,
            'mention' => $mentionsEnabled,
            'image' => $attachmentsEnabled,
            'attachment' => $attachmentsEnabled,
            'craftLink' => false,
        ];
    }
}
