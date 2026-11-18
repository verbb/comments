<?php
namespace verbb\comments\helpers;

use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\models\AllowedOrigin;

use Craft;
use craft\helpers\ConfigHelper;
use craft\helpers\FileHelper;

use yii\web\UploadedFile;

use Throwable;

/**
 * The rules every file uploaded to Comments meets, whatever an Editor context or a field allows:
 *
 * - SVG, HTML, XML and script files are refused, by extension and by what the file really is, because a
 *   browser can run them from the upload volume's address.
 * - The file's real type, read from its contents, must match its extension, so a page can't be passed
 *   off as an image or a document.
 * - The extension must be one the context or field allows, and the size within its limit and Craft's
 *   `maxUploadFileSize`.
 * - Images lose their metadata, including where they were taken, before they're stored. A phone photo
 *   is turned the way it was taken first, since that's in the metadata too.
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
        $notAllowed = new UploadRefusedException(Craft::t('comments', 'This kind of file can’t be uploaded here.'));

        if ($extension === '' || self::isBlockedExtension($extension) || !in_array($extension, array_map('strtolower', $allowedExtensions), true)) {
            throw $notAllowed;
        }

        $craftLimit = (int)ConfigHelper::sizeInBytes(Craft::$app->getConfig()->getGeneral()->maxUploadFileSize);
        $limit = $maxSize !== null ? min($maxSize, $craftLimit) : $craftLimit;
        $size = (int)filesize($file->tempName);

        if ($size > $limit) {
            throw new UploadRefusedException(Craft::t('comments', 'This file is too large.'), 'file_too_large');
        }

        // What the file is, from its contents alone: a name can say anything.
        $actual = FileHelper::getMimeType($file->tempName, null, false);
        $actual = is_string($actual) ? strtolower(trim(explode(';', $actual)[0])) : '';

        if ($actual === '' || self::isBlockedMimeType($actual) || !self::_extensionMatches($extension, $actual)) {
            throw $notAllowed;
        }

        if (in_array($actual, ImageMetadata::TIFF_TYPES, true)) {
            throw new UploadRefusedException(Craft::t('comments', 'TIFF images can’t be uploaded here. Upload a JPEG or PNG instead.'));
        }

        // HEIC and AVIF can only lose where a photo was taken through ImageMagick, which also reads them.
        if (in_array($actual, ImageMetadata::IMAGICK_TYPES, true)) {
            if (!ImageMetadata::canStripWithImagick($actual)) {
                throw new UploadRefusedException(Craft::t('comments', 'HEIC and AVIF images can only be uploaded when the server has ImageMagick to remove where they were taken. Upload a JPEG or PNG instead.'));
            }

            if (!ImageMetadata::stripWithImagick($file->tempName, $actual)) {
                throw $notAllowed;
            }

            clearstatcache(true, $file->tempName);
            $file->size = (int)filesize($file->tempName);
        } elseif (str_starts_with($actual, 'image/')) {
            if (@getimagesize($file->tempName) === false) {
                throw $notAllowed;
            }

            self::_turnUpright($file->tempName, $actual);

            if (!ImageMetadata::strip($file->tempName, $actual)) {
                throw new UploadRefusedException(Craft::t('comments', 'This kind of image can’t be uploaded here.'));
            }

            clearstatcache(true, $file->tempName);
            $file->size = (int)filesize($file->tempName);
        }

        $file->type = $actual;
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
     * Extensions refused whatever is allowed, because a browser can run what they hold: SVG, HTML and
     * XML documents, scripts, and executables.
     */
    public static function isBlockedExtension(string $extension): bool
    {
        return in_array(strtolower(ltrim($extension, '.')), [
            'svg', 'svgz', 'html', 'htm', 'xhtml', 'xht', 'shtml', 'mht', 'mhtml', 'hta', 'xml', 'xsl', 'xslt', 'rss', 'atom',
            'js', 'mjs', 'cjs', 'jsx', 'ts', 'vbs', 'wsf', 'swf', 'jar', 'jnlp',
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
            'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'ps1',
            'exe', 'dll', 'com', 'bat', 'cmd', 'msi', 'scr', 'app', 'dmg',
        ], true);
    }

    /**
     * Content types refused whatever the file's name says, for the same reason.
     */
    public static function isBlockedMimeType(string $mimeType): bool
    {
        $mimeType = strtolower($mimeType);

        if (str_ends_with($mimeType, '+xml') || str_contains($mimeType, 'javascript') || str_contains($mimeType, 'ecmascript')) {
            return true;
        }

        return in_array($mimeType, [
            'text/html', 'text/xml', 'application/xml', 'image/svg+xml', 'application/xhtml+xml', 'message/rfc822',
            'text/x-php', 'application/x-php', 'application/x-httpd-php', 'text/x-shellscript', 'application/x-sh',
            'text/x-python', 'text/x-perl', 'text/x-ruby', 'application/x-shockwave-flash', 'application/java-archive',
            'application/x-msdownload', 'application/x-dosexec', 'application/x-executable', 'application/x-mach-binary',
            'application/hta',
        ], true);
    }


    // Private Methods
    // =========================================================================

    private static function _originOf(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Whether `$extension` names what the contents are. Office documents and other ZIP-based formats
     * are read as ZIP files, and text formats such as CSV are often read as plain text.
     */
    private static function _extensionMatches(string $extension, string $actual): bool
    {
        $expected = FileHelper::getMimeTypeByExtension('file.' . $extension);
        $expected = is_string($expected) ? strtolower($expected) : null;

        if ($expected === $actual || in_array($extension, FileHelper::getExtensionsByMimeType($actual) ?? [], true)) {
            return true;
        }

        if ($actual === 'application/zip' && in_array($extension, ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'epub', 'zip'], true)) {
            return true;
        }

        if ($actual === 'text/plain' && $expected !== null && str_starts_with($expected, 'text/')) {
            return true;
        }

        // An extension Craft doesn't know can't be checked, so only plain text and unrecognised
        // binary data are let through under one.
        return $expected === null && in_array($actual, ['text/plain', 'application/octet-stream'], true);
    }

    /**
     * Turn a photo the way its metadata says it was taken, as Craft does when it's set to, because
     * removing the metadata would otherwise leave it on its side.
     */
    private static function _turnUpright(string $path, string $mimeType): void
    {
        if ($mimeType !== 'image/jpeg' || !Craft::$app->getConfig()->getGeneral()->rotateImagesOnUploadByExifData) {
            return;
        }

        // Craft goes by the file's extension, which PHP's temporary upload doesn't have.
        $copy = $path . '-upright.jpg';

        try {
            if (copy($path, $copy) && Craft::$app->getImages()->rotateImageByExifData($copy)) {
                rename($copy, $path);
            }
        } catch (Throwable) {
            // The image is still stored, only possibly on its side.
        } finally {
            if (is_file($copy)) {
                @unlink($copy);
            }
        }
    }
}
