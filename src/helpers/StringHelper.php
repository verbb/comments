<?php
namespace verbb\comments\helpers;

use Craft;
use craft\helpers\StringHelper as CraftStringHelper;

use LitEmoji\LitEmoji;

class StringHelper extends CraftStringHelper
{
    // Static Methods
    // =========================================================================

    public static function emojiToShortcodes(string $str): string
    {
        // Add delimiters around all 4-byte chars
        $dl = '__MB4_DL__';
        $dr = '__MB4_DR__';
        $str = self::replaceMb4($str, fn($char) => sprintf('%s%s%s', $dl, $char, $dr));

        // Strip out consecutive delimiters
        $str = str_replace(sprintf('%s%s', $dr, $dl), '', $str);

        // Replace all 4-byte sequences individually
        return preg_replace_callback("/$dl(.+?)$dr/", fn($m) => LitEmoji::unicodeToShortcode($m[1]), $str);
    }

    public static function shortcodesToEmoji(string $str): string
    {
        // Most comments have no colon at all, so they skip the lookup through every shortcode.
        if (!str_contains($str, ':')) {
            return $str;
        }

        return LitEmoji::shortcodeToUnicode($str);
    }

    /**
     * Plain comment text as it's written to the database. A database never converted to utf8mb4
     * refuses 4-byte characters, so there emoji are stored as shortcodes, as Comments 2 stored them.
     * `CommentQuery` turns shortcodes back into emoji as comments load.
     */
    public static function emojiForDb(?string $str): ?string
    {
        if ($str === null || Craft::$app->getDb()->getSupportsMb4()) {
            return $str;
        }

        return static::emojiToShortcodes($str);
    }

    /**
     * A rich comment's JSON document as it's written to the database. On a database without 4-byte
     * support, emoji become JSON escapes (`\ud83c\udf89`), which decoding the document gives back
     * exactly. Shortcodes aren't used, so text in the document is never rewritten when it loads.
     */
    public static function jsonEmojiForDb(?string $json): ?string
    {
        if ($json === null || Craft::$app->getDb()->getSupportsMb4()) {
            return $json;
        }

        // A 4-byte character can only be inside a JSON string, where an escape means the same thing.
        return static::replaceMb4($json, static fn(string $char): string => substr(json_encode($char), 1, -1));
    }
}
