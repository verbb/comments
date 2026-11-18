<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Db;

use yii\base\UserException;

use verbb\editor\Editor;
use verbb\editor\RichText;

use Throwable;

/**
 * Comment ↔ Craft Asset junction persistence for Editor inline images and attachments.
 */
class CommentAssets extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Extract unique Craft Asset IDs referenced in a normalized rich document.
     */
    public function resolveAssetIdsFromRichText(RichText $richText): array
    {
        if (!CommentsPlugin::$plugin->getSettings()->allowAttachments) {
            return [];
        }

        $assetIds = [];

        foreach (Editor::getInstance()->getDocuments()->extractReferences($richText) as $reference) {
            if (!in_array($reference->nodeType, ['editorAsset', 'attachment'], true)) {
                continue;
            }

            $assetId = (int)($reference->referenceId() ?? 0);

            if ($assetId > 0) {
                $assetIds[$assetId] = $assetId;
            }
        }

        return array_values($assetIds);
    }

    public function resolveAssetReferencesFromRichText(RichText $richText): array
    {
        if (!CommentsPlugin::$plugin->getSettings()->allowAttachments) {
            return [];
        }

        $references = [];

        foreach (Editor::getInstance()->getDocuments()->extractReferences($richText) as $reference) {
            $kind = match ($reference->nodeType) {
                'editorAsset' => 'inlineImage',
                'attachment' => 'attachment',
                default => null,
            };
            $assetId = (int)($reference->referenceId() ?? 0);

            if ($kind !== null && $assetId > 0) {
                $references[$assetId] = ['id' => $assetId, 'kind' => $kind];
            }
        }

        return array_values($references);
    }

    /**
     * Moves new uploads out of temporary storage, into the comment's **Attachment Subfolder**. Anything
     * in `$existingAssetIds` is already attached to the comment, so an edit keeps it whoever is editing.
     */
    public function finalizeReferences(array $references, string $contextHandle, int $actorId, array $existingAssetIds = [], ?Comment $comment = null): array
    {
        if ($references === []) {
            return [];
        }

        $settings = CommentsPlugin::$plugin->getSettings();

        if (!$settings->allowAttachments || !$settings->attachmentVolumeId) {
            throw new UserException(Craft::t('comments', 'Attachments are not enabled for comments.'));
        }

        \verbb\comments\helpers\EditorContexts::refresh();
        $editor = Editor::getInstance();
        $context = $editor->getEditorConfigs()->requireConfigByHandle($contextHandle);

        if (!$context->assets?->enabled || (int)$context->assets->volumeId !== (int)$settings->attachmentVolumeId) {
            throw new UserException(Craft::t('comments', 'The comment attachment destination is unavailable.'));
        }

        // Editor only takes uploads from signed-in people, so a guest never has a new file to attach.
        // Editor's retry check would otherwise read an asset with no uploader, already in the
        // destination folder, as a guest's own: a guest could attach any file uploaded anonymously
        // there. A guest keeps only what's already on the comment.
        if ($actorId <= 0) {
            $existing = array_map('intval', $existingAssetIds);

            foreach ($references as $reference) {
                if (!in_array((int)($reference['id'] ?? 0), $existing, true)) {
                    throw new UserException(Craft::t('comments', 'One or more comment attachments could not be finalised.'));
                }
            }
        }

        $folderId = $this->getAttachmentFolderId($comment);

        if ($folderId !== null) {
            // The context is shared by every comment, so this comment's folder goes on a copy.
            $context = clone $context;
            $context->assets = clone $context->assets;
            $context->assets->folderId = $folderId;
        }

        $finalized = $editor->getAssets()->finalize($references, $context, $actorId, $existingAssetIds);
        $assetIds = array_map(static fn(array $asset): int => (int)$asset['id'], $finalized);
        $validIds = $this->filterValidAssetIds($assetIds);

        if (count($validIds) !== count($references)) {
            throw new UserException(Craft::t('comments', 'One or more comment attachments could not be finalised.'));
        }

        return $validIds;
    }

    /**
     * Make an edited comment's attachments what its new body refers to. New uploads move out of
     * temporary storage, and what was already attached stays usable whoever is editing.
     */
    public function syncEditedReferences(Comment $comment, array $references, int $actorId): void
    {
        $commentId = (int)$comment->id;

        $this->syncAssetIds($commentId, $this->finalizeReferences($references, 'comments.edit', $actorId, $this->getAssetIdsForComment($commentId), $comment));
    }

    /**
     * The folder a comment's new files go to: the **Attachment Subfolder** rendered for the comment, made
     * if it doesn't exist yet, or `null` for the volume's root. A subfolder that can't be worked out for
     * this comment, such as `{user.id}` for a guest, also means the root, so posting never fails over
     * where a file is kept. Each part of a rendered folder is cleaned like a filename, so what someone
     * writes can never reach outside the volume.
     */
    public function getAttachmentFolderId(?Comment $comment): ?int
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $subpath = trim((string)$settings->attachmentSubpath);
        $volume = $settings->attachmentVolumeId ? Craft::$app->getVolumes()->getVolumeById($settings->attachmentVolumeId) : null;

        if ($subpath === '' || !$volume) {
            return null;
        }

        try {
            [$path, $folder] = AssetsHelper::resolveSubpath($volume, $subpath, $comment);
        } catch (Throwable $e) {
            Craft::warning("Comment attachment subfolder “{$subpath}” couldn’t be worked out, so the volume’s root is used: {$e->getMessage()}", __METHOD__);

            return null;
        }

        $segments = explode('/', trim($path, '/'));

        if ($path === '' || array_intersect($segments, ['', '.', '..']) !== []) {
            Craft::warning("Comment attachment subfolder “{$subpath}” came out as “{$path}”, so the volume’s root is used.", __METHOD__);

            return null;
        }

        $folder ??= Craft::$app->getAssets()->ensureFolderByFullPathAndVolume($path, $volume);

        return (int)$folder->id;
    }

    /**
     * Validate asset IDs exist in the comment attachment volume before persisting junction rows. Who
     * may attach them was settled by finalising: most commenters have no Control Panel asset access.
     */
    public function filterValidAssetIds(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        $valid = [];
        $volumeId = CommentsPlugin::$plugin->getSettings()->attachmentVolumeId;
        $requestedIds = array_values(array_unique(array_filter(array_map('intval', $assetIds), static fn(int $id): bool => $id > 0)));
        $assets = Asset::find()
            ->id($requestedIds)
            ->status(null)
            ->indexBy('id')
            ->all();

        foreach ($requestedIds as $assetId) {
            $asset = $assets[$assetId] ?? null;

            if ($asset instanceof Asset && (!$volumeId || (int)$asset->volumeId === (int)$volumeId)) {
                $valid[$assetId] = $assetId;
            }
        }

        return array_values($valid);
    }

    /**
     * Replace junction rows for a comment with the supplied Asset IDs.
     */
    public function syncAssetIds(int $commentId, array $assetIds): void
    {
        $assetIds = $this->filterValidAssetIds($assetIds);
        $table = '{{%comments_assets}}';

        $existing = (new Query())
            ->select(['assetId'])
            ->from($table)
            ->where(['commentId' => $commentId])
            ->column();

        $existing = array_map('intval', $existing);
        $toDelete = array_diff($existing, $assetIds);
        $toInsert = array_diff($assetIds, $existing);

        if ($toDelete !== []) {
            Db::delete($table, [
                'commentId' => $commentId,
                'assetId' => $toDelete,
            ]);
        }

        foreach ($toInsert as $assetId) {
            Db::insert($table, [
                'commentId' => $commentId,
                'assetId' => (int)$assetId,
            ]);
        }
    }

    public function getAssetIdsForComment(int $commentId): array
    {
        return array_map('intval', (new Query())
            ->select(['assetId'])
            ->from('{{%comments_assets}}')
            ->where(['commentId' => $commentId])
            ->column());
    }

    /**
     * The attachments of a page of comments in one query, so rendering a list isn't one per comment.
     *
     * Asset IDs are keyed by comment ID, and comments with none are left out.
     */
    public function getAssetIdsForComments(array $commentIds): array
    {
        $assetIds = [];
        $rows = (new Query())
            ->select(['commentId', 'assetId'])
            ->from('{{%comments_assets}}')
            ->where(['commentId' => $commentIds])
            ->all();

        foreach ($rows as $row) {
            $assetIds[(int)$row['commentId']][] = (int)$row['assetId'];
        }

        return $assetIds;
    }

    public function assertAllowedForWrite(array $assetIds): void
    {
        if ($assetIds === [] || CommentsPlugin::$plugin->getSettings()->allowAttachments) {
            return;
        }

        throw new UserException(Craft::t('comments', 'Attachments are not enabled for comments.'));
    }
}
