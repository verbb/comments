<?php
namespace verbb\comments\widgets;

use verbb\comments\elements\Comment;

use Craft;
use craft\base\Widget;

class Comments extends Widget
{
    // Properties
    // =========================================================================

    public $heading = 'All Comments';
    public $subheading = '';
    public $status = 'all';
    public $limit = 10;
    public $showFlagged = null;


    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comments');
    }

    public function getTitle(): string
    {
        return $this->heading;
    }

    public function getSubtitle()
    {
        return $this->subheading;
    }

    public static function icon()
    {
        return Craft::getAlias('@verbb/comments/icon-mask.svg');
    }

    public function getBodyHtml()
    {
        $comments = Comment::find()
            ->status($this->status)
            ->limit($this->limit)
            ->isFlagged($this->showFlagged)
            ->orderBy('dateCreated desc')
            ->all();

        return Craft::$app->getView()->renderTemplate('comments/_widget/body', [
            'comments' => $comments,
        ]);
    }
    
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('comments/_widget/settings', [
            'widget' => $this,
        ]);
    }
    
}