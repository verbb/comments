<?php
namespace Craft;

class Comments_SecurityService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function checkSecurityPolicy(Comments_CommentModel $comment)
    {
        $settings = craft()->comments->getSettings();
        
        // Check for content where a comment should be marked as pending.
        if ($settings->securityModeration) {
            $values = explode("\n", $settings->securityModeration);

            foreach ($comment->getAttributes() as $attr) {
                foreach ($values as $value) {
                    if (trim($value)) {
                        if (stristr(trim($attr), trim($value))) {
                            $comment->status = Comments_CommentModel::PENDING;
                            
                            // Found a match - that's all folks!
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Check for content where a comment should be marked as spam.
        if ($settings->securityBlacklist) {
            $values = explode("\n", $settings->securityBlacklist);

            foreach ($comment->getAttributes() as $attr) {
                foreach ($values as $value) {
                    if (trim($value)) {
                        if (stristr(trim($attr), trim($value))) {
                            $comment->status = Comments_CommentModel::SPAM;

                            // Found a match - that's all folks!
                            break 2;
                        }
                    }
                }
            }
        }

        // Check for content where a comment should be blocked.
        if ($settings->securityBanned) {
            $values = explode("\n", $settings->securityBanned);

            foreach ($comment->getAttributes() as $attr) {
                foreach ($values as $value) {
                    if (trim($value)) {
                        if (stristr(trim($attr), trim($value))) {

                            // Found a match - that's all folks!
                            return false;
                        }
                    }
                }
            }
        }

        // Check if flood-control is active. Call in Master Chief!
        if ($settings->securityFlooding) {

            // Lookup last comment from this user (real or anon)
            if ($comment->userId) {
                $lastComment = craft()->comments->getCriteria(array('limit' => 1, 'order' => 'dateCreated desc', 'userId' => $comment->userId))->first();
            } else {
                $lastComment = craft()->comments->getCriteria(array('limit' => 1, 'order' => 'dateCreated desc', 'email' => $comment->email))->first();
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




}