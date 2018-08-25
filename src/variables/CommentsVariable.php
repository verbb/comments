<?php
namespace verbb\comments\variables;

use verbb\comments\Comments;
use verbb\comments\elements\db\CommentQuery;

use Craft;
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
