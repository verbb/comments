<?php
namespace verbb\comments\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Json;

use yii\db\Schema;

class CommentsField extends Field
{
    // Static
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comment Options');
    }

    public static function supportedTranslationMethods(): array
    {
        return [];
    }


    // Properties
    // =========================================================================

    public $columnType = Schema::TYPE_TEXT;
    public $commentEnabled = true;
    public $default = true;


    // Public Methods
    // =========================================================================

    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $id = Craft::$app->getView()->formatInputId($this->handle);
        $nameSpacedId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('comments/_field/input', [
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'element' => $element,
        ]);
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'lightswitchField', [[
            'label' => Craft::t('app', 'Default Value'),
            'id' => 'default',
            'name' => 'default',
            'on' => $this->default,
        ]]);
    }

    public function normalizeValue($value, ElementInterface $element = null)
    {
        if ($value === null) {
            return ['commentEnabled' => $this->default];
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        return $value;
    }
}
