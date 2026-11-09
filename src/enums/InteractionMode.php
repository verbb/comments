<?php
namespace verbb\comments\enums;

/**
 * Configured shape of Comment interactions.
 *
 * The v3 `allowVoting` boolean is translated to this canonical setting when
 * upgraded project configuration is loaded.
 */
enum InteractionMode: string
{
    // Static Methods
    // =========================================================================

    public static function options(): array
    {
        return [
            ['value' => self::None->value, 'label' => 'None'],
            ['value' => self::Like->value, 'label' => 'Likes (upvote only)'],
            ['value' => self::UpDown->value, 'label' => 'Upvotes and downvotes'],
        ];
    }


    // Constants
    // =========================================================================

    /** Interactions are disabled entirely. */
    case None = 'none';

    /** Single positive interaction — rendered as a like/upvote toggle. */
    case Like = 'like';

    /** Positive and negative interactions, at most one active per Identity. */
    case UpDown = 'updown';


    // Public Methods
    // =========================================================================

    public function isEnabled(): bool
    {
        return $this !== self::None;
    }

    public function allowsDownvote(): bool
    {
        return $this === self::UpDown;
    }

    /**
     * Whether a net score (up minus down) is meaningful for this mode.
     * Like mode shows a raw count instead.
     */
    public function usesScore(): bool
    {
        return $this === self::UpDown;
    }

    public function allows(InteractionType $type): bool
    {
        return match ($type) {
            InteractionType::Upvote => $this->isEnabled(),
            InteractionType::Downvote => $this->allowsDownvote(),
        };
    }
}
