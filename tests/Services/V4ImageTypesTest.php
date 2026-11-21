<?php
use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\helpers\UploadPolicy;
use verbb\editor\helpers\ImageMetadata;
use yii\web\UploadedFile;

/**
 * TIFF, HEIC and AVIF uploads (SD-46, decision 7): TIFF is always refused, and HEIC and AVIF are accepted
 * only when ImageMagick can strip their metadata. Editor's `UploadSafety` decides, and has its own tests,
 * including stripping with ImageMagick. These check that every Comments upload path, through
 * `UploadPolicy`, refuses them with Comments' own messages. The HEIC and AVIF test is skipped where PHP
 * has Imagick.
 */

function imageTypesFile(string $name, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imgtype');
    file_put_contents($path, $contents);

    return new UploadedFile(['name' => $name, 'tempName' => $path, 'type' => 'application/octet-stream', 'size' => strlen($contents), 'error' => UPLOAD_ERR_OK]);
}

function imageTypesRefusal(UploadedFile $file, array $extensions): ?string
{
    try {
        UploadPolicy::check($file, $extensions);
    } catch (UploadRefusedException $e) {
        return $e->getMessage();
    } finally {
        @unlink($file->tempName);
    }

    return null;
}

it('always refuses TIFF images, whatever is allowed', function() {
    $tiff = "II*\0" . pack('V', 8) . pack('v', 0) . pack('V', 0);

    expect(imageTypesRefusal(imageTypesFile('scan.tif', $tiff), ['tif', 'tiff']))->toBe('TIFF images can’t be uploaded here. Upload a JPEG or PNG instead.');
});

it('refuses HEIC and AVIF with the reason when ImageMagick isn’t there to strip them', function() {
    if (ImageMetadata::canStripWithImagick('image/heic') || ImageMetadata::canStripWithImagick('image/avif')) {
        $this->markTestSkipped('This PHP has ImageMagick, so HEIC and AVIF are accepted.');
    }

    $avif = pack('N', 28) . 'ftypavif' . pack('N', 0) . 'avifmif1miaf' . pack('N', 8) . 'mdat';
    $heic = pack('N', 24) . 'ftypheic' . pack('N', 0) . 'mif1heic' . pack('N', 8) . 'mdat';
    $message = 'HEIC and AVIF images can only be uploaded when the server has ImageMagick to remove where they were taken. Upload a JPEG or PNG instead.';

    expect(imageTypesRefusal(imageTypesFile('photo.avif', $avif), ['avif']))->toBe($message)
        ->and(imageTypesRefusal(imageTypesFile('photo.heic', $heic), ['heic']))->toBe($message);
});

it('refuses what Editor’s upload safety refuses with Comments’ message and data API code', function() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $codeFor = static function(UploadedFile $file, array $extensions): ?string {
        try {
            UploadPolicy::check($file, $extensions);
        } catch (UploadRefusedException $e) {
            return $e->errorCode;
        } finally {
            @unlink($file->tempName);
        }

        return null;
    };
    $message = 'This kind of file can’t be uploaded here.';

    // Listed or not, a file a browser can run is refused, as is one that isn't what its name says.
    expect(imageTypesRefusal(imageTypesFile('drawing.svg', $svg), ['svg']))->toBe($message)
        ->and(imageTypesRefusal(imageTypesFile('pixel.png', $svg), ['png']))->toBe($message)
        ->and(imageTypesRefusal(imageTypesFile('broken.png', substr($png, 0, 20)), ['png']))->toBe($message)
        ->and($codeFor(imageTypesFile('pixel.png', $svg), ['png']))->toBe('file_type_not_allowed')
        ->and(imageTypesRefusal(imageTypesFile('pixel.png', $png), ['png']))->toBeNull();
});
