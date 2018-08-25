<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\Component;

use DateTime;

class SecurityService extends Component
{
    // Public Methods
    // =========================================================================

    public function checkSecurityPolicy(Comment $comment)
    {
        $settings = Comments::$plugin->getSettings();
        
        // Check for content where a comment should be marked as pending.
        if ($this->_findInElementContent($comment, $settings->securityModeration)) {
            $comment->status = Comment::STATUS_PENDING;
        }
        
        // Check for content where a comment should be marked as spam.
        if ($this->_findInElementContent($comment, $settings->securityBlacklist)) {
            $comment->status = Comment::STATUS_SPAM;
        }

        // Check for content where a comment should be blocked.
        if ($this->_findInElementContent($comment, $settings->securityBanned)) {
            return false;
        }

        // Check if flood-control is active. Call in Master Chief!
        if ($settings->securityFlooding) {

            // Lookup last comment from this user (real or anon)
            if ($comment->userId) {
                $lastComment = Comment::findOne(['orderBy' => 'elements.dateCreated desc', 'userId' => $comment->userId]);
            } else {
                $lastComment = Comment::findOne(['orderBy' => 'elements.dateCreated desc', 'email' => $comment->email]);
            }

            // Maybe this is a new user, never commented before?
            if ($lastComment) {
                $lastCommentTime = $lastComment->dateCreated;
                $newCommentTime = new DateTime('now');
                $seconds = abs($newCommentTime->getTimestamp() - $lastCommentTime->getTimestamp());

                if ($seconds <= (int)$settings->securityFlooding) {
                    return false;
                }
            }
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _findInElementContent($comment, $setting)
    {
        if (!$setting) {
            return false;
        }

        $values = explode("\n", $setting);

        foreach ($comment->getAttributes() as $attr) {
            if (!is_string($attr)) {
                continue;
            }

            foreach ($values as $value) {
                if (trim($value)) {
                    if (stristr(trim($attr), trim($value))) {
                        // Found a match - that's all folks!
                        return true;
                    }
                }
            }
        }
    }

}
