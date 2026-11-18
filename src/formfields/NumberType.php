<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;
use craft\fields\Number;

use yii\base\InvalidArgumentException;

/**
 * Number fields, with their limits on the input, shown with their prefix and suffix.
 */
class NumberType extends FormFieldType
{
    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        $decimals = $field instanceof Number ? $field->decimals : 0;

        return [
            'type' => 'number',
            'multiple' => false,
            'options' => [],
            'attributes' => array_filter([
                'min' => $field instanceof Number ? $field->min : null,
                'max' => $field instanceof Number ? $field->max : null,
                'step' => $field instanceof Number && $field->step ? $field->step : ($decimals ? 1 / (10 ** $decimals) : 1),
            ], static fn(mixed $value): bool => $value !== null),
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        if ($submitted === null || $submitted === '') {
            return null;
        }

        if (is_int($submitted) || is_float($submitted)) {
            return $submitted;
        }

        if (is_string($submitted) && is_numeric(trim($submitted))) {
            return trim($submitted) + 0;
        }

        throw new InvalidArgumentException(Craft::t('comments', 'Enter a number.'));
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return null;
        }

        $number = $value + 0;
        $decimals = $field instanceof Number ? $field->decimals : 0;
        $text = Craft::$app->getFormatter()->asDecimal($number, $decimals);

        if ($field instanceof Number) {
            $text = trim(($field->prefix ?? '') . $text . ($field->suffix ?? ''));
        }

        return ['value' => $number, 'text' => $text];
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? $value + 0 : null;
    }
}
