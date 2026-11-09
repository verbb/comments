<?php
namespace verbb\comments\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\Html;
use craft\web\View;

class CommentsField extends BaseField
{
    // Properties
    // =========================================================================

    public bool $required = true;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Hidden by default - better way?
        if (!isset($config['label'])) {
            $config['label'] = '__blank__';
        }

        parent::__construct($config);
    }

    public function attribute(): string
    {
        return 'comment';
    }

    public function mandatory(): bool
    {
        return true;
    }

    public function requirable(): bool
    {
        return true;
    }

    public function hasCustomWidth(): bool
    {
        return false;
    }

    public function label(): ?string
    {
        if ($this->label !== null && $this->label !== '' && $this->label !== '__blank__') {
            return Html::encode(Craft::t('comments', $this->label));
        }

        return Craft::t('comments', 'Comment');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        if ($this->instructions !== null && $this->instructions !== '' && $this->instructions !== '__blank__') {
            return Html::encode(Craft::t('comments', $this->instructions));
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate('comments/comments/_editor-body', [
            'comment' => $element,
            'static' => $static,
        ], View::TEMPLATE_MODE_CP);
    }
}
