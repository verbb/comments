<?php
namespace verbb\comments\variables;

use verbb\comments\Comments;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\elements\db\CommentQuery;

use Craft;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;

use Twig\Markup;

class CommentsVariable
{
    // Public Methods
    // =========================================================================

    public function fetch($criteria = null): CommentQuery
    {
        return Comments::$plugin->getComments()->fetch($criteria);
    }

    public function render($elementId, $criteria = [], $jsSettings = []): Markup
    {
        return Comments::$plugin->getComments()->render($elementId, $criteria, $jsSettings);
    }

    public function protect(bool $loadInline = false): Markup
    {
        $fields = Comments::$plugin->getProtect()->getFields($loadInline);

        return Template::raw($fields);
    }

    public function isSubscribed($element, $comment = null): bool
    {
        $currentUser = Comments::$plugin->getService()->getUser();
        $elementId = $element->id ?? null;
        $elementSiteId = $element->siteId ?? null;
        $userId = $currentUser->id ?? null;
        $commentId = $comment->id ?? null;

        return Comments::$plugin->getSubscribe()->hasSubscribed($elementId, $elementSiteId, $userId, $commentId);
    }

    public function renderCss($elementId, $attributes = []): Markup
    {
        $view = Craft::$app->getView();
        $url = Craft::$app->getAssetManager()->getPublishedUrl('@verbb/comments/resources/dist/css/comments.css', true);

        $output = Html::cssFile($url, $attributes);

        return Template::raw($output);
    }

    public function renderJs($elementId, $criteria = [], $loadInline = true, $attributes = []): Markup
    {
        $view = Craft::$app->getView();
        $url = Craft::$app->getAssetManager()->getPublishedUrl('@verbb/comments/resources/dist/js/comments.js', true);

        $id = 'cc-w-' . $elementId;
        $jsVariables = Comments::$plugin->getComments()->getRenderJsVariables($id, $elementId, $criteria);

        // Wrap the Comment JS init code in a unqiue function, so it can be called onload
        if ($loadInline) {
            $functionName = StringHelper::appendRandomString('InitComments', 10);
            $attributes = array_merge(['onload' => $functionName . '()'], $attributes);
        }

        $output = [];
        $output[] = Html::jsFile($url, $attributes);

        if ($loadInline) {
            // Wrap JS in our unique function, to prevent it firing early
            $jsString = 'var ' . $functionName . ' = function() { new Comments.Instance(' .
                Json::encode('#' . $id, JSON_UNESCAPED_UNICODE) . ', ' .
                Json::encode($jsVariables, JSON_UNESCAPED_UNICODE) .
            '); }';
    
            $output[] = Html::script($jsString, ['type' => 'text/javascript']);
        }

        return Template::raw(implode(PHP_EOL, $output));
    }

    public function getJsVariables($elementId, $criteria = []): array
    {
        $id = 'cc-w-' . $elementId;
        $jsVariables = Comments::$plugin->getComments()->getRenderJsVariables($id, $elementId, $criteria);

        return [
            'id' => '#' . $id,
            'settings' => $jsVariables,
        ];
    }

    public function getAvatar(): string|Asset
    {
        return CommentsHelper::getAvatar(Comments::$plugin->getService()->getUser());
    }

    public function getUserVotes($userId): array
    {
        return Comments::$plugin->getVotes()->getVotesByUserId($userId);
    }

    public function getUserDownvotes($userId): array
    {
        return Comments::$plugin->getVotes()->getDownvotesByUserId($userId);
    }

    public function getUserUpvotes($userId): array
    {
        return Comments::$plugin->getVotes()->getUpvotesByUserId($userId);
    }

}
