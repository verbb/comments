<?php
use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\helpers\ImageMetadata;
use verbb\comments\helpers\UploadPolicy;
use yii\web\UploadedFile;

/**
 * TIFF, HEIC and AVIF uploads (SD-46, decision 7): TIFF is always refused, and HEIC and AVIF are accepted
 * only when ImageMagick can strip their metadata, which `UploadPolicy` checks for every upload path:
 * Editor's uploads, uploads for custom fields, and the check again when a comment's files are finalised.
 * The tests that need ImageMagick are skipped where PHP has no Imagick extension.
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

it('strips where a HEIC or AVIF photo was taken with ImageMagick, and leaves a clean one as it is', function(string $format, string $extension, string $mimeType) {
    if (!ImageMetadata::canStripWithImagick($mimeType)) {
        $this->markTestSkipped('Needs PHP’s Imagick extension with ' . $format . ' support.');
    }

    $image = new Imagick();
    $image->newImage(16, 12, new ImagickPixel('teal'));
    $image->setImageFormat($format);
    $image->setImageProperty('exif:GPSLatitude', '51/1, 30/1, 0/1');
    $image->setImageProperty('comment', 'Taken at home');
    $image->profileImage('xmp', '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/></x:xmpmeta>');
    $path = tempnam(sys_get_temp_dir(), 'imgtype') . '.' . $extension;
    $image->writeImage($path);
    $image->clear();
    $file = new UploadedFile(['name' => 'photo.' . $extension, 'tempName' => $path, 'type' => $mimeType, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK]);

    try {
        UploadPolicy::check($file, [$extension]);
        $stripped = (string)file_get_contents($path);
        $check = new Imagick($path);
        $properties = array_keys($check->getImageProperties('*', true));
        $profiles = array_map('strtolower', array_keys($check->getImageProfiles('*', false)));
        $check->clear();

        // Checked again, as finalising a comment's files does, it isn't changed.
        UploadPolicy::check($file, [$extension]);

        expect(array_filter($properties, static fn(string $name): bool => (bool)preg_match('/^(exif|xmp|iptc|gps):/i', $name)))->toBe([])
            ->and(array_intersect($profiles, ['exif', 'xmp', 'iptc', '8bim']))->toBe([])
            ->and($stripped)->not->toContain('<x:xmpmeta')
            ->and((string)file_get_contents($path))->toBe($stripped);
    } finally {
        @unlink($path);
    }
})->with([
    'HEIC' => ['HEIC', 'heic', 'image/heic'],
    'AVIF' => ['AVIF', 'avif', 'image/avif'],
]);
