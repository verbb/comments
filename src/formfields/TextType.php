<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use craft\base\FieldInterface;
use craft\fields\PlainText;

/**
 * Plain Text fields, as a text input, or a text area for multi-line ones.
 */
class TextType extends FormFieldType
{
    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        $multiline = $field instanceof PlainText && $field->multiline;

        return [
            'type' => $multiline ? 'textarea' : 'text',
            'multiple' => false,
            'options' => [],
            'attributes' => array_filter([
                'maxlength' => $field instanceof PlainText ? $field->charLimit : null,
            ], static fn(mixed $value): bool => $value !== null),
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        return $this->string($submitted);
    }
}
