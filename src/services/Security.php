<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\helpers\KeywordRules;

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

        // A comment with more links than the site allows is held in the same way.
        if (is_numeric($settings->maxLinks) && $this->countLinks((string)$comment->comment) > (int)$settings->maxLinks) {
            $wasHeldFor = $comment->moderationState === ModerationState::Pending->value ? HeldReason::tryFrom((string)$comment->heldReason) : null;

            $comment->status = Comment::STATUS_PENDING;
            $comment->moderationState = ModerationState::Pending->value;

            if (in_array($wasHeldFor, [null, HeldReason::PreModeration, HeldReason::Edited], true)) {
                $comment->heldReason = HeldReason::Links->value;
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

        // A guest's address at a blocked domain, or one of its subdomains.
        if (!$comment->userId && $this->isBlockedEmail((string)$comment->email)) {
            return false;
        }

        return true;
    }

    /**
     * How many links a comment's text has: each link in a rich comment, and each web address written out
     * in its text. A link whose text is its address counts once.
     */
    public function countLinks(string $content): int
    {
        $anchors = preg_match_all('/<a\b[^>]*\bhref\s*=/i', $content);
        $text = strip_tags((string)preg_replace('/<a\b[^>]*>.*?<\/a>/is', ' ', $content));
        $addresses = preg_match_all('~(?<![\w@])(?:https?://|www\.)[^\s<>"\']+~iu', html_entity_decode($text, ENT_QUOTES | ENT_HTML5));

        return (int)$anchors + (int)$addresses;
    }

    public function isBlockedEmail(string $email): bool
    {
        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = rtrim(mb_strtolower(trim(mb_substr($email, $at + 1))), '.');

        foreach (Comments::$plugin->getSettings()->getBlockedEmailDomains() as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
                return true;
            }
        }

        return false;
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

    private function _findInElementContent(Comment $comment, ?string $rules): bool
    {
        $settings = Comments::$plugin->getSettings();

        return KeywordRules::findMatch($rules, [$comment->comment, $comment->name, $comment->email], $comment->ipAddress, $settings->securityMatchExact) !== null;
    }
}
