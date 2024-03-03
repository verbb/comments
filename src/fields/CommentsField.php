<?php
namespace verbb\comments\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;

use yii\db\Schema;

class CommentsField extends Field
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comment Options');
    }

    public static function icon(): string
    {
        return '@verbb/comments/icon-mask.svg';
    }

    public static function supportedTranslationMethods(): array
    {
        return [];
    }


    // Properties
    // =========================================================================

    public bool $commentEnabled = true;
    public bool $default = true;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Remove unused settings
        unset($config['columnType']);

        parent::__construct($config);
    }

    public function getSettingsHtml(): ?string
    {
        return Cp::lightswitchFieldHtml([
            'label' => Craft::t('app', 'Default Value'),
            'id' => 'default',
            'name' => 'default',
            'on' => $this->default,
        ]);
    }

    public function normalizeValue(mixed $value, ElementInterface $element = null): mixed
    {
        if ($value === null) {
            return ['commentEnabled' => $this->default];
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        return $value;
    }


    // Protected Methods
    // =========================================================================

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $id = Html::id($this->handle);

        return Craft::$app->getView()->renderTemplate('comments/_field/input', [
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'element' => $element,
        ]);
    }
}
