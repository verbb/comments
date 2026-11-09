<?php
namespace verbb\comments\helpers;

use verbb\comments\migrations\support\V3UpgradeLog;

use Craft;

/**
 * Canonical definition of the CP settings navigation.
 *
 * Pane entries map directly to `settings/_panes/<handle>` templates. Heading entries
 * only group the sidebar and are never routable.
 */
class SettingsTabs
{
    // Static Methods
    // =========================================================================

    public static function all(): array
    {
        $tabs = [
            'general' => [
                'label' => 'General Settings',
                'pane' => true,
            ],
            'authoring' => [
                'label' => 'Authoring',
                'pane' => true,
            ],
            'fields' => [
                'label' => 'Comment Fields',
                'pane' => true,
            ],
            'commenting' => [
                'label' => 'Commenting',
                'pane' => true,
            ],
            'engagementHeading' => [
                'heading' => 'Engagement',
                'pane' => false,
            ],
            'interactions' => [
                'label' => 'Interactions',
                'pane' => true,
            ],
            'notifications' => [
                'label' => 'Notifications',
                'pane' => true,
            ],
            'moderation' => [
                'label' => 'Moderation',
                'pane' => true,
            ],
            'spam' => [
                'label' => 'Spam',
                'pane' => true,
            ],
            'presentationHeading' => [
                'heading' => 'Presentation',
                'pane' => false,
            ],
            'appearance' => [
                'label' => 'Appearance',
                'pane' => true,
            ],
            'templates' => [
                'label' => 'Templates & Resources',
                'pane' => true,
            ],
        ];

        // Only sites that came from Comments 3 and have something to look at.
        if (V3UpgradeLog::issues(Craft::$app->getDb()) !== []) {
            $tabs['upgrade'] = [
                'label' => 'Upgrade Report',
                'pane' => true,
            ];
        }

        return $tabs;
    }

    public static function canonicalHandle(string $handle): string
    {
        return match ($handle) {
            'permissions' => 'commenting',
            'advanced' => 'authoring',
            'security' => 'spam',
            'frontend' => 'appearance',
            default => $handle,
        };
    }

    public static function isPane(string $handle): bool
    {
        return self::all()[$handle]['pane'] ?? false;
    }

    public static function has(string $handle): bool
    {
        return isset(self::all()[$handle]);
    }

    /**
     * The settings sidebar nav, in tab order.
     */
    public static function navItems(): array
    {
        $items = [];

        foreach (self::all() as $handle => $tab) {
            if (isset($tab['heading'])) {
                $items[$handle] = ['heading' => Craft::t('comments', $tab['heading'])];
            } else {
                $items[$handle] = ['title' => Craft::t('comments', $tab['label'])];
            }
        }

        return $items;
    }
}
