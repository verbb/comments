<?php
namespace verbb\comments\helpers;

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
        return LitEmoji::shortcodeToUnicode($str);
    }
}