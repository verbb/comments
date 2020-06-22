<?php
namespace verbb\comments\variables;

use verbb\comments\Comments;
use verbb\comments\elements\db\CommentQuery;

use Craft;
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

    public function render($elementId, $criteria = [])
    {
        return Comments::$plugin->getComments()->render($elementId, $criteria);
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

    public function renderCss($elementId, $criteria = [])
    {
        $view = Craft::$app->getView();

        $url = Craft::$app->getAssetManager()->getPublishedUrl('@verbb/comments/resources/dist/css/comments.css', true);

        echo '<link href="' . $url . '" rel="stylesheet">';
    }

    public function renderJs($elementId, $criteria = [])
    {
        $view = Craft::$app->getView();

        $url = Craft::$app->getAssetManager()->getPublishedUrl('@verbb/comments/resources/dist/js/comments.js', true);
        
        $id = 'cc-w-' . $elementId;
        $jsVariables = Comments::$plugin->getComments()->getRenderJsVariables($id, $elementId, $criteria);

        echo '<script src="' . $url . '"></script>';

        echo 'window.addEventListener("load", function () { new Comments.Instance(' .
            Json::encode('#' . $id, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($jsVariables, JSON_UNESCAPED_UNICODE) .
        '); });';
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
