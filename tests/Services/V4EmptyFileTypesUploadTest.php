<?php

use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\integrations\EditorAssetProvider;
use verbb\editor\enums\AssetKind;
use verbb\editor\models\AssetConfig;
use yii\web\UploadedFile;

/**
 * An emptied **Allowed File Types** means nothing can be uploaded. Editor falls back to its own image
 * types for inline images when a list is empty, so Comments' provider checks Comments' own list.
 */
function emptyTypesPng(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'comments-png');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

    return new UploadedFile(['name' => 'pixel.png', 'tempName' => $path, 'type' => 'image/png', 'size' => filesize($path), 'error' => UPLOAD_ERR_OK]);
}

it('refuses an inline image when no file types are allowed, though Editor would fall back to images', function() {
    $config = new AssetConfig(['enabled' => true, 'allowedExtensions' => []]);

    expect($config->getAllowedExtensions(AssetKind::InlineImage))->toContain('png')
        ->and(fn() => (new EditorAssetProvider())->upload(emptyTypesPng(), $config, EditorContexts::replyConfig(), AssetKind::InlineImage))
        ->toThrow(UploadRefusedException::class);
});

it('still refuses an image that isn’t listed when other types are', function() {
    $config = new AssetConfig(['enabled' => true, 'allowedExtensions' => ['pdf']]);

    expect(fn() => (new EditorAssetProvider())->upload(emptyTypesPng(), $config, EditorContexts::replyConfig(), AssetKind::InlineImage))
        ->toThrow(UploadRefusedException::class);
});
