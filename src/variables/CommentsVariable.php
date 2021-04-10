<?php
namespace verbb\comments\variables;

use verbb\comments\Comments;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\helpers\CommentsHelper;

use Craft;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;

class CommentsVariable
{
    // Public Methods
    // =========================================================================

    public function fetch($criteria = null): CommentQuery
    {
        return Comments::$plugin->getComments()->fetch($criteria);
    }

    public function render($elementId, $criteria = [], $jsSettings = [])
    {
        return Comments::$plugin->getComments()->render($elementId, $criteria, $jsSettings);
    }

    public function protect()
    {
        $fields = Comments::$plugin->getProtect()->getFields();
        return Template::raw($fields);
    }

    public function isSubscribed($element, $comment = null)
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $elementId = $element->id ?? null;
        $elementSiteId = $element->siteId ?? null;
        $userId = $currentUser->id ?? null;
        $commentId = $comment->id ?? null;

        return Comments::$plugin->getSubscribe()->hasSubscribed($elementId, $elementSiteId, $userId, $commentId);
    }

    public function renderCss($elementId, $attributes = [])
    {
        $view = Craft::$app->getView();
        $url = Craft::$app->getAssetManager()->getPublishedUrl('@verbb/comments/resources/dist/css/comments.css', true);

        $output = Html::cssFile($url, $attributes);

        return Template::raw($output);
    }

    public function renderJs($elementId, $criteria = [], $loadInline = true, $attributes = [])
    {
        $view = Craft::$app->getView();
        $url = Craft::$app->getAssetManager()->getPublishedUrl('@verbb/comments/resources/dist/js/comments.js', true);
        
        $id = 'cc-w-' . $elementId;
        $jsVariables = Comments::$plugin->getComments()->getRenderJsVariables($id, $elementId, $criteria);

        $output = [];
        $output[] = Html::jsFile($url, $attributes);

        if ($loadInline) {
            $jsString = 'window.addEventListener("load", function() { new Comments.Instance(' .
                Json::encode('#' . $id, JSON_UNESCAPED_UNICODE) . ', ' .
                Json::encode($jsVariables, JSON_UNESCAPED_UNICODE) .
            '); });';

            $output[] = Html::script($jsString, ['type' => 'text/javascript']);
        }

        return Template::raw(implode(PHP_EOL, $output));
    }

    public function getJsVariables($elementId, $criteria = [])
    {
        $id = 'cc-w-' . $elementId;
        $jsVariables = Comments::$plugin->getComments()->getRenderJsVariables($id, $elementId, $criteria);

        return [
            'id' => '#' . $id,
            'settings' => $jsVariables,
        ];
    }

    public function getAvatar()
    {
        return CommentsHelper::getAvatar(Craft::$app->getUser()->getIdentity());
    }

    public function getUserVotes($userId)
    {
        return Comments::$plugin->getVotes()->getVotesByUserId($userId);
    }

    public function getUserDownvotes($userId)
    {
        return Comments::$plugin->getVotes()->getDownvotesByUserId($userId);
    }

    public function getUserUpvotes($userId)
    {
        return Comments::$plugin->getVotes()->getUpvotesByUserId($userId);
    }


    // Deprecated Methods
    // =========================================================================

    public function all($criteria = null): CommentQuery
    {
        Craft::$app->getDeprecator()->log('craft.comments.all()', '`craft.comments.all()` has been deprecated. Use `craft.comments.fetch()` instead.');

        return $this->fetch($criteria);
    }

    public function form($elementId, $criteria = [])
    {
        Craft::$app->getDeprecator()->log('craft.comments.form()', '`craft.comments.form()` has been deprecated. Use `craft.comments.render()` instead.');

        return $this->render($elementId, $criteria);
    }

}
