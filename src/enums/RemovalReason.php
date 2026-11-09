<?php
namespace verbb\comments\enums;

use Craft;

/**
 * The fixed reasons a moderator gives for removing a comment, kept in its moderation history.
 */
enum RemovalReason: string
{
    // Static Methods
    // =========================================================================

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }


    // Constants
    // =========================================================================

    case OffTopic = 'off-topic';
    case Harassment = 'harassment';
    case PersonalInformation = 'personal-information';
    case Advertising = 'advertising';
    case Other = 'other';


    // Public Methods
    // =========================================================================

    public function label(): string
    {
        return match ($this) {
            self::OffTopic => Craft::t('comments', 'Off-topic'),
            self::Harassment => Craft::t('comments', 'Harassment or abuse'),
            self::PersonalInformation => Craft::t('comments', 'Personal information'),
            self::Advertising => Craft::t('comments', 'Advertising'),
            self::Other => Craft::t('comments', 'Other'),
        };
    }
}
