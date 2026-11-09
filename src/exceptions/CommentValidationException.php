<?php
namespace verbb\comments\exceptions;

use yii\base\UserException;

/**
 * A comment that can't be saved as written, with what's wrong with each field so a form can say so.
 */
class CommentValidationException extends UserException
{
    // Static Methods
    // =========================================================================

    public static function fromErrors(array $errors): self
    {
        $fieldErrors = [];

        foreach ($errors as $attribute => $messages) {
            $name = str_starts_with($attribute, 'field:') ? substr($attribute, 6) : $attribute;
            $fieldErrors["fields[{$name}]"] = array_values($messages);
        }

        return new self(implode(' ', array_map(static fn(array $messages): string => (string)reset($messages), array_values($errors))), $fieldErrors);
    }


    // Public Methods
    // =========================================================================

    /** Field errors are keyed by the input's name. */
    public function __construct(string $message, public readonly array $fieldErrors = [])
    {
        parent::__construct($message);
    }
}
