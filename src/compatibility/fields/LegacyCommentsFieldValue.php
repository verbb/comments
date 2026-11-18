<?php
namespace verbb\comments\compatibility\fields;

/**
 * Values the Comments field stored before Comments 4's three states, read whatever `compatibilityMode` is,
 * because they're an upgraded site's saved content and stay until each element is next saved.
 */
trait LegacyCommentsFieldValue
{
    // Private Methods
    // =========================================================================

    /**
     * The state a legacy value means, or null when it isn't one.
     */
    private static function _legacyState(array $value): ?string
    {
        // Comments 4's development builds stored an explicit override.
        if (array_key_exists('enabledOverride', $value)) {
            return match ($value['enabledOverride']) {
                true, 1, '1', self::STATE_ENABLED => self::STATE_ENABLED,
                false, 0, '0', self::STATE_DISABLED => self::STATE_DISABLED,
                default => self::STATE_INHERIT,
            };
        }

        // Comments 3's switch. Off stopped new comments and still showed the existing ones, which is
        // Closed now. On followed the element's other settings.
        if (array_key_exists('commentEnabled', $value)) {
            return in_array($value['commentEnabled'], [false, 0, '0', self::STATE_DISABLED], true)
                ? self::STATE_CLOSED
                : self::STATE_INHERIT;
        }

        return null;
    }
}
