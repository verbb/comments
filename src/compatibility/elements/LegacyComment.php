<?php
namespace verbb\comments\compatibility\elements;

use verbb\comments\Comments;
use verbb\comments\services\Compatibility;

use Craft;
use craft\elements\User;

/**
 * Comments 3's comment methods, which only work in compatibility mode. Votes thresholds and author scores
 * were removed with the features themselves, so those methods answer as if they had nothing to report.
 */
trait LegacyComment
{
    // Constants
    // =========================================================================

    /**
     * Comments 3 called a deleted comment `trashed`. Comments 4 calls it `removed`.
     *
     * @deprecated in 4.0.0. Use `Comment::STATUS_REMOVED`.
     */
    public const STATUS_TRASHED = 'removed';


    // Public Methods
    // =========================================================================

    /**
     * Whether the current person can `flag`, `vote`, `reply`, `edit` or `trash` this comment, or whether
     * a setting with that name is on, as Comments 3 answered.
     */
    public function can(string $property): bool
    {
        Compatibility::requireMethod(static::class, 'can', 'Use `comment.canFlag()`, `comment.canVote()`, `comment.canReply()` or `comment.canEdit()`, or read the setting from `craft.app.plugins.getPlugin(\'comments\').settings`.');

        $settings = Comments::$plugin->getSettings();

        if (property_exists($settings, $property)) {
            return (bool)$settings->$property;
        }

        return match ($property) {
            'flag' => $this->canFlag(),
            'vote' => $this->canVote(),
            'reply' => $this->canReply(),
            'edit' => $this->canEdit(),
            'trash' => $this->_legacyCanTrash(),
            default => false,
        };
    }

    /**
     * Whether the current person can delete this comment, which is their own, as the data API allows.
     */
    public function canTrash(): bool
    {
        Compatibility::requireMethod(static::class, 'canTrash', 'Comments 4’s thread offers **Delete** to a comment’s author itself. A form of your own can check `comment.canEdit()`.');

        return $this->_legacyCanTrash();
    }



    // Private Methods
    // =========================================================================

    private function _legacyCanTrash(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();

        // As `Api::delete()` checks: a signed-in author's own comment, not already deleted, still in their window.
        return $user instanceof User
            && $this->userId
            && (int)$this->userId === (int)$user->id
            && !$this->authorRemovedAt
            && $this->canDelete($user);
    }
}
