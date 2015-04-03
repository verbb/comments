<?php
namespace Craft;

class CommentsHelper
{
    public function deleteAction($comment, $options = array())
    {
        $params = array(
            'id' => $comment->id,
            'return' => craft()->request->getUrl(),
        );

        return UrlHelper::getActionUrl('comments/delete', $params);
    }

    public function flagAction($comment, $options = array())
    {
        $user = craft()->userSession->getUser();

        $params = array(
            'id' => $comment->id,
            'return' => craft()->request->getUrl(),
        );

        // Only logged in users can flag a comment
        if ($user) {

            // Ensure the user has no already flagged comment
            $hasFlagged = craft()->comments_flag->hasFlagged($comment, $user);

            if (!$hasFlagged) {
                return UrlHelper::getActionUrl('comments/flagComment', $params);
            }
        }
    }

    public function upvoteAction($comment, $options = array())
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

    public function downvoteAction($comment, $options = array())
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


