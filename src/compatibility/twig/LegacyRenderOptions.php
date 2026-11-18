<?php
namespace verbb\comments\compatibility\twig;

use verbb\comments\services\Compatibility;

/**
 * Options given to `craft.comments.render()` that it no longer takes. Comments 3's second argument set
 * properties on the comment query, and Comments 4's development builds had options that drawing every
 * thread from JSON made pointless.
 */
class LegacyRenderOptions
{
    // Constants
    // =========================================================================

    // Options from Comments 4's development builds, logged whatever the mode, because ignoring them is safe.
    public const REMOVED = [
        'sharedCache' => 'The `sharedCache` option of `craft.comments.render()` has been removed. The page never holds anything about the visitor now, so every page with comments can be cached.',
        'experimentalRenderer' => 'The `experimentalRenderer` option of `craft.comments.render()` has been removed. Every thread is drawn by the comments script now.',
    ];

    // What `render()` takes now. Anything else in a Comments 3 template set a property on the comment query.
    private const OPTIONS = ['siteId', 'sort', 'limit', 'loadMode', 'themeConfig', 'fields', 'nonce'];


    // Static Methods
    // =========================================================================

    /**
     * Logs removed options, and in compatibility mode reads Comments 3's `orderBy` as `sort` and logs the
     * other query properties `render()` ignores.
     */
    public static function normalize(array $options): array
    {
        foreach (self::REMOVED as $option => $message) {
            if (array_key_exists($option, $options)) {
                Compatibility::deprecated('render.' . $option, $message);
            }
        }

        if (!Compatibility::enabled()) {
            return $options;
        }

        // Comments 3 ordered by `commentDate asc` or `commentDate desc`, which are oldest or newest first.
        if (!array_key_exists('sort', $options) && is_string($options['orderBy'] ?? null)) {
            $direction = stripos($options['orderBy'], 'asc') !== false ? 'oldest' : 'newest';
            $options['sort'] = $direction;

            Compatibility::deprecated('render.orderBy', "The `orderBy` option of `craft.comments.render()` has been replaced by `sort`, which takes `newest`, `oldest` or `top`. Use `sort: '{$direction}'`.");
        }

        foreach (array_keys($options) as $option) {
            if (!in_array($option, self::OPTIONS, true) && $option !== 'orderBy' && !isset(self::REMOVED[$option])) {
                Compatibility::deprecated('render.' . $option, "The `{$option}` option of `craft.comments.render()` is ignored. Comments 3 set it on the comment query, and `render()` now takes only `siteId`, `sort`, `limit`, `loadMode`, `themeConfig`, `fields` and `nonce`.");
            }
        }

        return $options;
    }
}
