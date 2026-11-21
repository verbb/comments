<?php
namespace verbb\comments\helpers;

use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\models\AllowedOrigin;

use Craft;
use craft\helpers\ConfigHelper;

use yii\web\UploadedFile;

use Throwable;

use verbb\editor\errors\UploadRefusedException as EditorUploadRefusedException;
use verbb\editor\helpers\UploadSafety;

/**
 * The rules every file uploaded to Comments meets, whatever an Editor context or a field allows:
 *
 * - The extension must be one the context or field allows, and the size within its limit and Craft's
 *   `maxUploadFileSize`.
 * - Editor's upload safety (`UploadSafety`) then refuses SVG, HTML, XML and script files and anything
 *   whose contents don't match its extension, and removes images' metadata, including where they were
 *   taken. Its refusals become Comments' own messages and data API codes.
 */
final class UploadPolicy
{
    // Static Methods
    // =========================================================================

    /**
     * Check `$file` and clean it in place, or refuse it with the data API's code for why.
     * `$allowedExtensions` is lower case, without dots.
     */
    public static function check(UploadedFile $file, array $allowedExtensions, ?int $maxSize = null): void
    {
        if (in_array($file->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new UploadRefusedException(Craft::t('comments', 'This file is too large.'), 'file_too_large');
        }

        if ($file->getHasError() || !is_string($file->tempName) || !is_file($file->tempName)) {
            throw new UploadRefusedException(Craft::t('comments', 'The file couldn’t be uploaded. Please try again.'));
        }

        $extension = strtolower(pathinfo((string)$file->name, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, array_map('strtolower', $allowedExtensions), true)) {
            throw new UploadRefusedException(Craft::t('comments', 'This kind of file can’t be uploaded here.'));
        }

        $craftLimit = (int)ConfigHelper::sizeInBytes(Craft::$app->getConfig()->getGeneral()->maxUploadFileSize);
        $limit = $maxSize !== null ? min($maxSize, $craftLimit) : $craftLimit;

        if ((int)filesize($file->tempName) > $limit) {
            throw new UploadRefusedException(Craft::t('comments', 'This file is too large.'), 'file_too_large');
        }

        try {
            UploadSafety::check($file);
        } catch (EditorUploadRefusedException $e) {
            throw self::_refusal($e);
        }
    }

    /**
     * The site origin the upload volume's files are served from, when it's one of the site's own, or
     * null when they're served from another host or have no public URL. A file served from the site's
     * own origin runs there if a browser is ever talked into treating it as a page, so the settings page
     * warns about it.
     */
    public static function volumeSiteOrigin(?int $volumeId): ?string
    {
        $volume = $volumeId ? Craft::$app->getVolumes()->getVolumeById($volumeId) : null;

        if (!$volume) {
            return null;
        }

        try {
            $url = $volume->getFs()->getRootUrl();
        } catch (Throwable) {
            return null;
        }

        if (!is_string($url) || $url === '') {
            return null;
        }

        $siteOrigins = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $origin = AllowedOrigin::normalize(self::_originOf((string)$site->getBaseUrl()));

            if ($origin !== null) {
                $siteOrigins[] = $origin;
            }
        }

        // A root-relative URL is served from whichever site the page is on.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $siteOrigins[0] ?? null;
        }

        $origin = AllowedOrigin::normalize(self::_originOf(str_starts_with($url, '//') ? 'https:' . $url : $url));

        return $origin !== null && in_array($origin, $siteOrigins, true) ? $origin : null;
    }

    /**
     * Comments' message for why Editor's upload safety refused a file. Each is refused as a file type
     * that isn't allowed, so the data API's code stays `file_type_not_allowed`.
     */
    private static function _refusal(EditorUploadRefusedException $e): UploadRefusedException
    {
        return new UploadRefusedException(match ($e->reason) {
            EditorUploadRefusedException::REASON_TIFF => Craft::t('comments', 'TIFF images can’t be uploaded here. Upload a JPEG or PNG instead.'),
            EditorUploadRefusedException::REASON_NEEDS_IMAGICK => Craft::t('comments', 'HEIC and AVIF images can only be uploaded when the server has ImageMagick to remove where they were taken. Upload a JPEG or PNG instead.'),
            EditorUploadRefusedException::REASON_METADATA => Craft::t('comments', 'This kind of image can’t be uploaded here.'),
            EditorUploadRefusedException::REASON_UPLOAD_FAILED => Craft::t('comments', 'The file couldn’t be uploaded. Please try again.'),
            default => Craft::t('comments', 'This kind of file can’t be uploaded here.'),
        });
    }

    private static function _originOf(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
