<?php
namespace verbb\comments\base;

use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;

use yii\base\InvalidArgumentException;

/**
 * A starting point for a field type's front-end description. Out of the box it draws the field with a
 * JavaScript field override (`custom`), takes text, numbers, true or false and lists of them as they
 * come, and shows a value that reads as text. A plugin's own type overrides what it needs.
 */
abstract class FormFieldType implements FormFieldTypeInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Why this particular field can't be drawn on the site, such as a Link field that doesn't take web
     * addresses, or null when it can. The settings page shows the reason.
     */
    public function unsupportedReason(FieldInterface $field): ?string
    {
        return null;
    }

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => 'custom',
            'multiple' => false,
            'options' => [],
            'attributes' => [],
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        if ($submitted === null || is_scalar($submitted)) {
            return $submitted;
        }

        if (is_array($submitted) && array_is_list($submitted) && array_filter($submitted, static fn(mixed $item): bool => !is_scalar($item)) === []) {
            return $submitted;
        }

        throw new InvalidArgumentException(Craft::t('comments', 'This value can’t be read.'));
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        $text = $this->text($value);

        return $text === null ? null : ['value' => $text, 'text' => $text];
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        return $this->display($field, $value)['value'] ?? null;
    }


    // Protected Methods
    // =========================================================================

    /**
     * A value as one line of text, or null when there's nothing to show.
     */
    protected function text(mixed $value): ?string
    {
        if ($value === null || is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            return null;
        }

        $text = trim((string)$value);

        return $text === '' ? null : $text;
    }

    /**
     * A submitted value as a string, refusing anything that isn't one.
     */
    protected function string(mixed $submitted): ?string
    {
        if ($submitted === null) {
            return null;
        }

        if (!is_string($submitted) && !is_int($submitted) && !is_float($submitted)) {
            throw new InvalidArgumentException(Craft::t('comments', 'Enter text here.'));
        }

        return (string)$submitted;
    }
}
