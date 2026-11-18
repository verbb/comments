<?php
namespace verbb\comments\helpers;

use Throwable;

/**
 * Removes metadata from an uploaded image, including where it was taken (EXIF GPS, XMP and IPTC),
 * without re-encoding it, so quality isn't lost and it doesn't depend on Craft's image settings. Colour
 * profiles and everything needed to draw the image are kept.
 *
 * JPEG, PNG, WebP and GIF are rewritten here, and BMP and icons carry no metadata. HEIC and AVIF are
 * stripped by ImageMagick when the server has it, and refused when it doesn't (SD-46). TIFF, and a file
 * that isn't the image it says it is, are refused.
 */
final class ImageMetadata
{
    // Static Methods
    // =========================================================================

    /**
     * Whether ImageMagick, through PHP's Imagick extension, can read and write `$mimeType`, so its
     * metadata can be stripped.
     */
    public static function canStripWithImagick(string $mimeType): bool
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return false;
        }

        $format = in_array($mimeType, ['image/avif'], true) ? 'AVIF' : 'HEIC';

        try {
            return \Imagick::queryFormats($format) !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Strip a HEIC or AVIF image's metadata with ImageMagick, which re-encodes it, then check the result
     * holds no EXIF, GPS, XMP or IPTC. An image that's already clean is left exactly as it is, so a file
     * checked again when its comment is saved isn't changed. Returns false when it couldn't be done.
     */
    public static function stripWithImagick(string $path, string $mimeType): bool
    {
        if (!self::canStripWithImagick($mimeType)) {
            return false;
        }

        try {
            if (self::_isCleanWithImagick($path)) {
                return true;
            }

            $image = new \Imagick($path);
            $image->stripImage();

            foreach (array_keys($image->getImageProfiles('*', false)) as $profile) {
                $image->removeImageProfile($profile);
            }

            if (!$image->writeImage($path)) {
                return false;
            }

            $image->clear();
            clearstatcache(true, $path);

            return self::_isCleanWithImagick($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Strip `$path` in place. Returns false when it couldn't be done, so the upload is refused rather
     * than stored with its metadata.
     */
    public static function strip(string $path, string $mimeType): bool
    {
        $data = @file_get_contents($path);

        if (!is_string($data) || $data === '') {
            return false;
        }

        $stripped = match ($mimeType) {
            'image/jpeg', 'image/pjpeg' => self::stripJpeg($data),
            'image/png', 'image/apng' => self::stripPng($data),
            'image/webp' => self::stripWebp($data),
            'image/gif' => self::stripGif($data),
            'image/bmp', 'image/x-ms-bmp', 'image/x-icon', 'image/vnd.microsoft.icon' => $data,
            default => null,
        };

        if ($stripped === null) {
            return false;
        }

        return $stripped === $data || file_put_contents($path, $stripped) === strlen($stripped);
    }

    /**
     * A JPEG with only the segments that draw it: frame and scan headers, Huffman and quantisation
     * tables, restart intervals and the compressed data, plus JFIF, an ICC colour profile and Adobe's
     * colour transform. Everything else is dropped: EXIF, XMP and IPTC, other application segments,
     * comments, extension markers, and anything after the image, where cameras put extra pictures with
     * their own metadata and where a page could be hidden.
     */
    public static function stripJpeg(string $data): ?string
    {
        $length = strlen($data);

        if ($length < 4 || substr($data, 0, 2) !== "\xFF\xD8") {
            return null;
        }

        $out = "\xFF\xD8";
        $pos = 2;

        while ($pos < $length) {
            if ($data[$pos] !== "\xFF") {
                return null;
            }

            // Markers may be padded with any number of 0xFF bytes.
            while ($pos + 1 < $length && $data[$pos + 1] === "\xFF") {
                $pos++;
            }

            if ($pos + 1 >= $length) {
                return null;
            }

            $marker = ord($data[$pos + 1]);
            $pos += 2;

            if ($marker === 0xD9) {
                return $out . "\xFF\xD9";
            }

            // Markers without a length: restart markers are kept, anything else is dropped.
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD8)) {
                if ($marker >= 0xD0 && $marker <= 0xD7) {
                    $out .= "\xFF" . chr($marker);
                }

                continue;
            }

            if ($pos + 2 > $length) {
                return null;
            }

            $segmentLength = unpack('n', substr($data, $pos, 2))[1];

            if ($segmentLength < 2 || $pos + $segmentLength > $length) {
                return null;
            }

            $segment = substr($data, $pos, $segmentLength);
            $pos += $segmentLength;

            if (self::_keepJpegSegment($marker, substr($segment, 2))) {
                $out .= "\xFF" . chr($marker) . $segment;
            }

            // A scan's compressed data follows its header, up to the next marker. Inside it, 0xFF is
            // followed by 0x00 or a restart marker, so anything else is the next segment.
            if ($marker === 0xDA) {
                $start = $pos;

                while ($pos + 1 < $length) {
                    if ($data[$pos] === "\xFF") {
                        $next = ord($data[$pos + 1]);

                        if ($next !== 0x00 && !($next >= 0xD0 && $next <= 0xD7)) {
                            break;
                        }
                    }

                    $pos++;
                }

                $out .= substr($data, $start, $pos - $start);
            }
        }

        return null;
    }

    /**
     * A PNG with only the chunks that draw it: the four critical ones, transparency, colour and gamma,
     * resolution and animation. Text chunks (which hold XMP), `eXIf`, timestamps, unknown chunks and
     * anything after the image are removed, and a file with a critical chunk this doesn't know is
     * refused, since it can't be drawn without it.
     */
    public static function stripPng(string $data): ?string
    {
        $signature = "\x89PNG\r\n\x1A\n";
        $length = strlen($data);

        if (substr($data, 0, 8) !== $signature) {
            return null;
        }

        $keep = ['tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'cICP', 'mDCv', 'cLLi', 'pHYs', 'bKGD', 'hIST', 'sPLT', 'acTL', 'fcTL', 'fdAT'];
        $out = $signature;
        $pos = 8;

        while ($pos + 12 <= $length) {
            $chunkLength = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);

            if ($pos + 12 + $chunkLength > $length || !preg_match('/^[A-Za-z]{4}$/', $type)) {
                return null;
            }

            // A chunk whose type starts in upper case is critical, and can't be dropped.
            if (ctype_upper($type[0]) && !in_array($type, ['IHDR', 'PLTE', 'IDAT', 'IEND'], true)) {
                return null;
            }

            if (ctype_upper($type[0]) || in_array($type, $keep, true)) {
                $out .= substr($data, $pos, 12 + $chunkLength);
            }

            $pos += 12 + $chunkLength;

            if ($type === 'IEND') {
                return $out;
            }
        }

        return null;
    }

    /**
     * A WebP with only the chunks that draw it (image data, alpha, animation and the colour profile),
     * read no further than the RIFF header says, so `EXIF`, `XMP `, unknown chunks and anything after
     * the image are removed, with the extended header's flags for metadata cleared.
     */
    public static function stripWebp(string $data): ?string
    {
        $length = strlen($data);

        if ($length < 12 || substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            return null;
        }

        $chunks = '';
        $pos = 12;
        $length = min($length, 8 + unpack('V', substr($data, 4, 4))[1]);
        $keep = ['VP8 ', 'VP8L', 'VP8X', 'ALPH', 'ANIM', 'ANMF', 'ICCP'];

        while ($pos + 8 <= $length) {
            $type = substr($data, $pos, 4);
            $chunkLength = unpack('V', substr($data, $pos + 4, 4))[1];
            $padded = $chunkLength + ($chunkLength % 2);

            if ($pos + 8 + $chunkLength > $length) {
                return null;
            }

            $chunk = substr($data, $pos, 8 + $padded);
            $pos += 8 + $padded;

            if (!in_array($type, $keep, true)) {
                continue;
            }

            if ($type === 'VP8X' && $chunkLength >= 1) {
                $chunk[8] = chr(ord($chunk[8]) & ~0x0C);
            }

            $chunks .= str_pad($chunk, 8 + $padded, "\0");
        }

        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }

    /**
     * A GIF without comments, plain text or application data, which is where XMP goes, keeping the
     * extensions that time frames, loop an animation and carry its colour profile, and nothing after
     * its end.
     */
    public static function stripGif(string $data): ?string
    {
        $length = strlen($data);

        if ($length < 13 || !in_array(substr($data, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            return null;
        }

        $packed = ord($data[10]);
        $pos = 13 + (($packed & 0x80) ? 3 * (2 ** (($packed & 0x07) + 1)) : 0);
        $out = substr($data, 0, $pos);

        while ($pos < $length) {
            $byte = ord($data[$pos]);

            if ($byte === 0x3B) {
                return $out . ';';
            }

            if ($byte === 0x2C) {
                if ($pos + 10 > $length) {
                    return null;
                }

                $descriptor = ord($data[$pos + 9]);
                $start = $pos;
                $pos += 10 + (($descriptor & 0x80) ? 3 * (2 ** (($descriptor & 0x07) + 1)) : 0) + 1;
                $end = self::_gifBlocksEnd($data, $pos);

                if ($end === null) {
                    return null;
                }

                $out .= substr($data, $start, $end - $start);
                $pos = $end;

                continue;
            }

            if ($byte !== 0x21 || $pos + 2 > $length) {
                return null;
            }

            $label = ord($data[$pos + 1]);
            $end = self::_gifBlocksEnd($data, $pos + 2);

            if ($end === null) {
                return null;
            }

            $application = $label === 0xFF ? substr($data, $pos + 3, 11) : null;
            // Only frame timing and the extensions that loop an animation or carry its colour profile.
            // Comments, plain text and other applications' data, which is where XMP goes, are dropped.
            $keep = $label === 0xF9
                || ($label === 0xFF && in_array($application, ['NETSCAPE2.0', 'ANIMEXTS1.0', 'ICCRGBG1012'], true));

            if ($keep) {
                $out .= substr($data, $pos, $end - $pos);
            }

            $pos = $end;
        }

        return null;
    }


    // Constants
    // =========================================================================

    /** TIFF images, which are always refused. */
    public const TIFF_TYPES = ['image/tiff', 'image/tif'];

    /** HEIC and AVIF images, accepted only when ImageMagick can strip them. */
    public const IMAGICK_TYPES = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence', 'image/avif'];


    // Private Methods
    // =========================================================================

    /**
     * Whether ImageMagick reads no EXIF, GPS, XMP or IPTC in an image, and its bytes hold no EXIF or XMP
     * block either.
     */
    private static function _isCleanWithImagick(string $path): bool
    {
        $image = new \Imagick($path);
        $properties = $image->getImageProperties('*', true);
        $profiles = array_map('strtolower', array_keys($image->getImageProfiles('*', false)));
        $image->clear();

        foreach (array_keys($properties) as $name) {
            if (preg_match('/^(exif|xmp|iptc|gps):/i', (string)$name)) {
                return false;
            }
        }

        if (array_intersect($profiles, ['exif', 'xmp', 'iptc', '8bim']) !== []) {
            return false;
        }

        $data = (string)@file_get_contents($path);

        return !str_contains($data, "Exif\0\0") && !str_contains($data, '<x:xmpmeta') && !str_contains($data, 'http://ns.adobe.com/xap/');
    }

    private static function _keepJpegSegment(int $marker, string $payload): bool
    {
        return match (true) {
            // Frame headers (except the reserved JPG marker), Huffman and arithmetic tables.
            $marker >= 0xC0 && $marker <= 0xCF && $marker !== 0xC8 => true,
            // Quantisation tables, scan headers, the number of lines and restart intervals.
            in_array($marker, [0xDB, 0xDA, 0xDC, 0xDD], true) => true,
            $marker === 0xE0 => str_starts_with($payload, "JFIF\0"),
            $marker === 0xE2 => str_starts_with($payload, "ICC_PROFILE\0"),
            $marker === 0xEE => str_starts_with($payload, 'Adobe'),
            default => false,
        };
    }

    /**
     * Where a run of GIF sub-blocks starting at `$pos` ends, after its zero-length terminator.
     */
    private static function _gifBlocksEnd(string $data, int $pos): ?int
    {
        $length = strlen($data);

        while ($pos < $length) {
            $size = ord($data[$pos]);
            $pos++;

            if ($size === 0) {
                return $pos;
            }

            $pos += $size;
        }

        return null;
    }
}
