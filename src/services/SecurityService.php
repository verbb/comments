<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;

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
        if ($this->_findInElementContent($comment, $settings->securitySpamlist)) {
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
                $lastComment = Comment::findOne(['orderBy' => 'commentDate desc', 'userId' => $comment->userId]);
            } else {
                $lastComment = Comment::findOne(['orderBy' => 'commentDate desc', 'email' => $comment->email]);
            }

            // Maybe this is a new user, never commented before?
            if ($lastComment) {
                $lastCommentTime = $lastComment->commentDate;
                $newCommentTime = new DateTime('now');
                $seconds = abs($newCommentTime->getTimestamp() - $lastCommentTime->getTimestamp());

                if ($seconds <= (int)$settings->securityFlooding) {
                    return false;
                }
            }
        }

        return true;
    }

    public function checkCommentLength(Comment $comment)
    {
        $settings = Comments::$plugin->getSettings();
        
        // Check if max comment length is set.
        if ($settings->securityMaxLength) {
            // Check if the input is a positive integer
            if (is_numeric($settings->securityMaxLength)
                && $settings->securityMaxLength > 0
                && $settings->securityMaxLength == round($settings->securityMaxLength, 0)
            ) {
                if (strlen($comment->getComment()) >= (int)$settings->securityMaxLength) {
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
        $settings = Comments::$plugin->getSettings();

        if (!$setting) {
            return false;
        }

        $values = explode("\n", $setting);
        $attributes = $comment->getAttributes();

        // add the comment too, it's not included in the attributes
        $comment = ["comment" => $comment->comment];
        $mergedAttributes = array_merge($attributes, $comment);
        
        foreach ($mergedAttributes as $attr) {
            if (!is_string($attr)) {
                continue;
            }

            // Cleanup the string content coming from submissions
            $attr = trim($attr);

            // Strip out encoded text
            $attr = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $attr);

            foreach ($values as $value) {
                $value = trim($value);

                if ($value) {
                    if ($settings->securityMatchExact) {
                        if (preg_match('/\b' . $value . '\b/', $attr)) {
                            return true;
                        }
                    } else {
                        if (stristr($attr, $value)) {
                            // Found a match - that's all folks!
                            return true;
                        }
                    }
                }
            }
        }
    }

}
