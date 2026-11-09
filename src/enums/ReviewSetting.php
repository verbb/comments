<?php
namespace verbb\comments\enums;

use Craft;

/**
 * How a commenter's comments are reviewed. Everything but Normal is stored as a restriction row.
 */
enum ReviewSetting: string
{
    // Static Methods
    // =========================================================================

    public static function fromRestriction(?string $type): self
    {
        return match ($type) {
            RestrictionType::Trusted->value => self::Trusted,
            RestrictionType::Moderated->value => self::Held,
            RestrictionType::Blocked->value => self::Blocked,
            default => self::Normal,
        };
    }


    // Constants
    // =========================================================================

    case Trusted = 'trusted';
    case Normal = 'normal';
    case Held = 'held';
    case Blocked = 'blocked';


    // Public Methods
    // =========================================================================

    public function restrictionType(): ?RestrictionType
    {
        return match ($this) {
            self::Trusted => RestrictionType::Trusted,
            self::Held => RestrictionType::Moderated,
            self::Blocked => RestrictionType::Blocked,
            self::Normal => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Trusted => Craft::t('comments', 'Trusted'),
            self::Normal => Craft::t('comments', 'Normal'),
            self::Held => Craft::t('comments', 'Held'),
            self::Blocked => Craft::t('comments', 'Blocked'),
        };
    }

    public function instructions(): string
    {
        return match ($this) {
            self::Trusted => Craft::t('comments', 'Skips review. Their comments appear straight away, even under pre-moderation. Spam checks still apply.'),
            self::Normal => Craft::t('comments', 'Follows the site’s moderation setting.'),
            self::Held => Craft::t('comments', 'Every comment waits for a moderator, whatever the site setting, until you change this.'),
            self::Blocked => Craft::t('comments', 'Can’t post. Existing comments stay as they are.'),
        };
    }
}
