<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;
use craft\fields\Lightswitch;

use yii\base\InvalidArgumentException;

/**
 * Lightswitch fields, as a single switch, shown with the field's own on and off labels.
 */
class LightswitchType extends FormFieldType
{
    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => 'toggle',
            'multiple' => false,
            'options' => [],
            'attributes' => [],
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        if ($submitted === null || $submitted === '') {
            return false;
        }

        $value = is_bool($submitted) ? $submitted : filter_var($submitted, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // `"false"` is false, not true as PHP would read it, and anything else is refused.
        if (!is_bool($value) || is_array($submitted)) {
            throw new InvalidArgumentException(Craft::t('comments', 'Send true or false.'));
        }

        return $value;
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $on = (bool)$value;
        $label = $field instanceof Lightswitch ? ($on ? $field->onLabel : $field->offLabel) : null;

        return [
            'value' => $on,
            'text' => is_string($label) && trim($label) !== ''
                ? Craft::t('site', $label)
                : ($on ? Craft::t('comments', 'Yes') : Craft::t('comments', 'No')),
        ];
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        return (bool)$value;
    }
}
