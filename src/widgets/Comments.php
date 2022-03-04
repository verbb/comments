<?php
namespace verbb\comments\widgets;

use verbb\comments\elements\Comment;

use Craft;
use craft\base\Widget;

class Comments extends Widget
{
    // Properties
    // =========================================================================

    public string $heading = 'All Comments';
    public string $subheading = '';
    public string $status = 'all';
    public int $limit = 10;
    public bool $showFlagged = false;


    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comments');
    }

    public function getTitle(): ?string
    {
        return $this->heading;
    }

    public function getSubtitle(): ?string
    {
        return $this->subheading;
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@verbb/comments/icon-mask.svg');
    }

    public function getBodyHtml(): ?string
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
    
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('comments/_widget/settings', [
            'widget' => $this,
        ]);
    }
    
}