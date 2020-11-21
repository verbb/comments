<?php
namespace verbb\comments\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\Html;

use yii\base\InvalidArgumentException;

class CommentsField extends BaseField
{
    // Properties
    // =========================================================================

    public $required = true;


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


    // Protected Methods
    // =========================================================================

    public function label()
    {
        if ($this->label !== null && $this->label !== '' && $this->label !== '__blank__') {
            return Html::encode(Craft::t('comments', $this->label));
        }

        return '__blank__';
    }

    public function instructions()
    {
        if ($this->instructions !== null && $this->instructions !== '' && $this->instructions !== '__blank__') {
            return Html::encode(Craft::t('comments', $this->instructions));
        }

        return Craft::t('comments', 'Add a comment...');
    }

    protected function inputHtml(ElementInterface $element = null, bool $static = false)
    {
        return '';
    }
}
