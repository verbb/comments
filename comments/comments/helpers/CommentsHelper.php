<?php
namespace Craft;

class CommentsHelper
{
    // Public Methods
    // =========================================================================

    public static function trashAction($comment, $options = array())
    {
        $params = array(
            'id' => $comment->id,
            'return' => craft()->request->getUrl(),
        );

        return UrlHelper::getActionUrl('comments/trash', $params);
    }

    public static function flagAction($comment, $options = array())
    {
        $user = craft()->userSession->getUser();

        $params = array(
            'id' => $comment->id,
            'return' => craft()->request->getUrl(),
        );

        // Only logged in users can flag a comment
        if ($user) {

            // Ensure the user has not already flagged comment
            $hasFlagged = craft()->comments_flag->hasFlagged($comment, $user);

            if (!$hasFlagged) {
                return UrlHelper::getActionUrl('comments/flagComment', $params);
            }
        }
    }

    public static function upvoteAction($comment, $options = array())
    {
        $user = craft()->userSession->getUser();

        $params = array(
            'id' => $comment->id,
            'return' => craft()->request->getUrl(),
        );

        // Only logged in users can upvote a comment
        if ($user) {

            // Ensure the user hasn't voted yet
            $hasVoted = craft()->comments_vote->hasUpVoted($comment, $user);

            if (!$hasVoted) {
                return UrlHelper::getActionUrl('comments/upvoteComment', $params);
            }
        }
    }

    public static function downvoteAction($comment, $options = array())
    {
        $user = craft()->userSession->getUser();

        $params = array(
            'id' => $comment->id,
            'return' => craft()->request->getUrl(),
        );

        // Only logged in users can downvote a comment
        if ($user) {

            // Ensure the user hasn't voted yet
            $hasVoted = craft()->comments_vote->hasDownVoted($comment, $user);

            if (!$hasVoted) {
                return UrlHelper::getActionUrl('comments/downvoteComment', $params);
            }
        }
    }

}


