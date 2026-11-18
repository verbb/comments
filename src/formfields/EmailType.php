<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use craft\base\FieldInterface;

/**
 * Email fields. Showing one publicly puts each commenter's address in front of everyone, which the
 * settings page warns about.
 */
class EmailType extends FormFieldType
{
    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => 'email',
            'multiple' => false,
            'options' => [],
            'attributes' => [],
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        $value = $this->string($submitted);

        return $value === null ? null : trim($value);
    }
}
