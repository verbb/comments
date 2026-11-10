<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;

use craft\base\Component;

class Security extends Component
{
    // Public Methods
    // =========================================================================

    public function checkSecurityPolicy(Comment $comment): bool
    {
        $settings = Comments::$plugin->getSettings();

        // Check for content where a comment should be marked as pending. The keyword becomes the reason, which
        // also stops a Trusted commenter's review setting approving it. A comment already held for something
        // more particular than site-wide pre-moderation or an edit keeps that reason.
        if ($this->_findInElementContent($comment, $settings->securityModeration)) {
            $wasHeldFor = $comment->moderationState === ModerationState::Pending->value ? HeldReason::tryFrom((string)$comment->heldReason) : null;

            $comment->status = Comment::STATUS_PENDING;
            $comment->moderationState = ModerationState::Pending->value;

            if (in_array($wasHeldFor, [null, HeldReason::PreModeration, HeldReason::Edited], true)) {
                $comment->heldReason = HeldReason::Keyword->value;
            }
        }

        // Check for content where a comment should be marked as spam.
        if ($this->_findInElementContent($comment, $settings->securitySpamlist)) {
            $comment->status = Comment::STATUS_SPAM;
            $comment->moderationState = ModerationState::Spam->value;
            $comment->heldReason = HeldReason::Keyword->value;
        }

        // Check for content where a comment should be blocked.
        if ($this->_findInElementContent($comment, $settings->securityBanned)) {
            return false;
        }

        return true;
    }

    public function checkCommentLength(Comment $comment): bool
    {
        $settings = Comments::$plugin->getSettings();

        // Check if max comment length is set.
        if ($settings->securityMaxLength) {
            // Check if the input is a positive integer
            if (is_numeric($settings->securityMaxLength)
                && $settings->securityMaxLength > 0
                && $settings->securityMaxLength == round($settings->securityMaxLength, 0)
            ) {
                if (mb_strlen((string)$comment->getComment()) > (int)$settings->securityMaxLength) {
                    return false;
                }
            }
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _findInElementContent($comment, $setting): bool
    {
        $settings = Comments::$plugin->getSettings();

        if (!$setting) {
            return false;
        }

        $values = array_filter(array_map('trim', explode("\n", $setting)));

        foreach ([$comment->comment, $comment->name, $comment->email, $comment->ipAddress] as $attr) {
            if (!is_string($attr)) {
                continue;
            }

            // Control characters could otherwise split a word so that it doesn't match. Line breaks
            // stay, because they're what separates a word at the start of a line from the one before.
            $attr = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', trim($attr));

            foreach ($values as $value) {
                $found = $settings->securityMatchExact
                    ? preg_match('/(?<![\p{L}\p{N}_])' . preg_quote($value, '/') . '(?![\p{L}\p{N}_])/iu', $attr)
                    : mb_stripos($attr, $value) !== false;

                if ($found) {
                    return true;
                }
            }
        }

        return false;
    }
}
