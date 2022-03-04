<?php
namespace verbb\comments\widgets;

use verbb\comments\elements\Comment;

use Craft;
use craft\base\Widget;

class Comments extends Widget
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comments');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@verbb/comments/icon-mask.svg');
    }


    // Properties
    // =========================================================================

    public string $heading = 'All Comments';
    public int $limit = 10;
    public bool $showFlagged = false;
    public string $status = 'all';
    public string $subheading = '';


    // Public Methods
    // =========================================================================

    public function getTitle(): ?string
    {
        return $this->heading;
    }

    public function getSubtitle(): ?string
    {
        return $this->subheading;
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