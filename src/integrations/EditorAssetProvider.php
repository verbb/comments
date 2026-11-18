<?php
namespace verbb\comments\integrations;

use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\helpers\UploadPolicy;

use Craft;
use craft\elements\Asset;

use yii\web\UploadedFile;

use Throwable;

use verbb\editor\enums\AssetKind;
use verbb\editor\integrations\assets\CraftAssetProvider;
use verbb\editor\models\AssetConfig;
use verbb\editor\models\EditorConfig;

/**
 * Editor's Craft asset provider for Comments' contexts, with Comments' upload rules applied first.
 * Because the rules sit in the context rather than an endpoint, they hold however an upload arrives:
 * through `comments/api/upload`, through Editor's own action, or from PHP.
 *
 * A temporary upload made through another Editor context could otherwise be attached to a comment
 * without them, so finalising checks each temporary file again and only accepts one the rules leave
 * unchanged, which is what a file that came through them looks like.
 */
class EditorAssetProvider extends CraftAssetProvider
{
    // Public Methods
    // =========================================================================

    public function upload(UploadedFile $file, AssetConfig $config, EditorConfig $context, AssetKind $kind): array
    {
        UploadPolicy::check($file, $config->getAllowedExtensions($kind), $config->getMaxFileSize());

        return parent::upload($file, $config, $context, $kind);
    }

    public function finalize(array $references, ?AssetConfig $config, EditorConfig $context, int $actorId, array $associatedAssetIds = []): array
    {
        if ($config !== null) {
            foreach ($references as $reference) {
                $id = is_array($reference) ? filter_var($reference['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
                $kind = is_array($reference) ? AssetKind::tryFrom((string)($reference['kind'] ?? '')) : null;

                // Anything malformed or missing is refused by Editor below.
                if ($id === false || $kind === null) {
                    continue;
                }

                $asset = Asset::find()->id($id)->status(null)->one();

                if ($asset instanceof Asset && $asset->volumeId === null) {
                    $this->_recheck($asset, $config, $kind);
                }
            }
        }

        return parent::finalize($references, $config, $context, $actorId, $associatedAssetIds);
    }


    // Private Methods
    // =========================================================================

    private function _recheck(Asset $asset, AssetConfig $config, AssetKind $kind): void
    {
        try {
            $copy = $asset->getCopyOfFile();
        } catch (Throwable) {
            throw new UploadRefusedException(Craft::t('comments', 'An uploaded file can’t be used here.'));
        }

        try {
            $before = (string)file_get_contents($copy);
            UploadPolicy::check(new UploadedFile([
                'name' => $asset->getFilename(),
                'tempName' => $copy,
                'type' => $asset->getMimeType(),
                'size' => strlen($before),
                'error' => UPLOAD_ERR_OK,
            ]), $config->getAllowedExtensions($kind), $config->getMaxFileSize());

            clearstatcache(true, $copy);

            if ((string)file_get_contents($copy) !== $before) {
                throw new UploadRefusedException(Craft::t('comments', 'An uploaded file can’t be used here.'));
            }
        } finally {
            if (is_file($copy)) {
                @unlink($copy);
            }
        }
    }
}
