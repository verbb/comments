<?php
namespace verbb\comments\helpers;

use Craft;
use craft\helpers\StringHelper as CraftStringHelper;

use yii\base\InvalidArgumentException;

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
     * Whether a table can store 4-byte characters such as emoji. Craft's own check only reads
     * `elements_sites`, but Comments' tables can differ from Craft's, as `comments_comments` dates
     * from Comments 2 and newer tables take Craft's configured charset. Each answer is kept for the
     * rest of the request.
     */
    public static function tableSupportsMb4(string $table): bool
    {
        $db = Craft::$app->getDb();
        $key = $db->getSchema()->getRawTableName($table);

        if (!isset(self::$_tablesSupportMb4[$key])) {
            try {
                self::$_tablesSupportMb4[$key] = $db->getSchema()->supportsMb4($table);
            } catch (InvalidArgumentException) {
                // A table that doesn't exist yet, such as during an install, follows Craft's check
                // without being remembered, so it's read again once the table is there.
                return $db->getSupportsMb4();
            }
        }

        return self::$_tablesSupportMb4[$key];
    }

    /**
     * Overrides what `tableSupportsMb4()` answers for a table, or forgets it with `null` so the
     * table is checked again.
     */
    public static function setTableSupportsMb4(string $table, ?bool $supportsMb4): void
    {
        $key = Craft::$app->getDb()->getSchema()->getRawTableName($table);

        if ($supportsMb4 === null) {
            unset(self::$_tablesSupportMb4[$key]);
        } else {
            self::$_tablesSupportMb4[$key] = $supportsMb4;
        }
    }

    /**
     * Plain text as it's written to a column of `$table`, comment text by default. A table never
     * converted to utf8mb4 refuses 4-byte characters, so there emoji are stored as shortcodes, as
     * Comments 2 stored them. `CommentQuery` and the records using `EmojiColumnsTrait` turn
     * shortcodes back into emoji as they load.
     */
    public static function emojiForDb(?string $str, string $table = self::COMMENTS_TABLE): ?string
    {
        if ($str === null || static::tableSupportsMb4($table)) {
            return $str;
        }

        return static::emojiToShortcodes($str);
    }

    /**
     * A rich comment's JSON document as it's written to the database. On a table without 4-byte
     * support, emoji become JSON escapes (`\ud83c\udf89`), which decoding the document gives back
     * exactly. Shortcodes aren't used, so text in the document is never rewritten when it loads.
     */
    public static function jsonEmojiForDb(?string $json, string $table = self::COMMENTS_TABLE): ?string
    {
        if ($json === null || static::tableSupportsMb4($table)) {
            return $json;
        }

        // A 4-byte character can only be inside a JSON string, where an escape means the same thing.
        return static::replaceMb4($json, static fn(string $char): string => substr(json_encode($char), 1, -1));
    }


    // Constants
    // =========================================================================

    private const COMMENTS_TABLE = '{{%comments_comments}}';


    // Properties
    // =========================================================================

    private static array $_tablesSupportMb4 = [];
}
