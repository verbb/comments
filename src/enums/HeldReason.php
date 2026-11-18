<?php
namespace verbb\comments\enums;

use Craft;

/**
 * Why a comment is waiting for a moderator, or why it was marked as spam.
 */
enum HeldReason: string
{
    // Constants
    // =========================================================================

    case PreModeration = 'pre-moderation';
    case Commenter = 'commenter';
    case Protection = 'protection';
    case Keyword = 'keyword';
    case Links = 'links';
    case FirstComment = 'first-comment';
    case Edited = 'edited';
    case Moderator = 'moderator';


    // Public Methods
    // =========================================================================

    public function label(): string
    {
        return match ($this) {
            self::PreModeration => Craft::t('comments', 'Pre-moderation'),
            self::Commenter => Craft::t('comments', 'Held commenter'),
            self::Protection => Craft::t('comments', 'Possible spam'),
            self::Keyword => Craft::t('comments', 'Keyword filter'),
            self::Links => Craft::t('comments', 'Too many links'),
            self::FirstComment => Craft::t('comments', 'First comment'),
            self::Edited => Craft::t('comments', 'Edited'),
            self::Moderator => Craft::t('comments', 'Held by a moderator'),
        };
    }

    /**
     * The sentence the comment's page opens with while it waits.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::PreModeration => Craft::t('comments', 'Comments on this site wait for a moderator before they appear.'),
            self::Commenter => Craft::t('comments', 'This commenter’s comments are held for review, so it waits for a moderator.'),
            self::Protection => Craft::t('comments', 'Spam protection wasn’t sure about this comment, so it was held for you.'),
            self::Keyword => Craft::t('comments', 'This comment contains a word from the keyword filters, so it was held for you.'),
            self::Links => Craft::t('comments', 'This comment has more links than the Maximum Links setting allows, so it was held for you.'),
            self::FirstComment => Craft::t('comments', 'None of this commenter’s comments have been approved yet, so it waits for a moderator.'),
            self::Edited => Craft::t('comments', 'This comment was edited after it was approved, so it’s waiting for review again.'),
            self::Moderator => Craft::t('comments', 'A moderator held this comment for review.'),
        };
    }
}
