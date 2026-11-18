<?php
namespace verbb\comments\enums;

use Craft;

/**
 * When a thread's comments load in the browser. A link to one comment loads its thread straight away,
 * whatever the mode.
 */
enum LoadMode: string
{
    // Static Methods
    // =========================================================================

    public static function options(): array
    {
        return [
            ['value' => self::Visible->value, 'label' => Craft::t('comments', 'When scrolled into view')],
            ['value' => self::Button->value, 'label' => Craft::t('comments', 'When a button is pressed')],
            ['value' => self::Eager->value, 'label' => Craft::t('comments', 'With the page')],
        ];
    }

    /**
     * Comments 4 development builds stored a `lazyLoad` switch, which was off for comments that came
     * with the page.
     */
    public static function fromLazyLoad(mixed $lazyLoad): self
    {
        return $lazyLoad ? self::Visible : self::Eager;
    }


    // Constants
    // =========================================================================

    /** Loads just before the thread scrolls into view. */
    case Visible = 'visible';

    /** Loads when the visitor presses **Show comments**. */
    case Button = 'button';

    /** Loads as soon as the page does. */
    case Eager = 'eager';
}
