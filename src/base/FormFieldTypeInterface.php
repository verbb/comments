<?php
namespace verbb\comments\base;

use verbb\comments\elements\Comment;

use craft\base\FieldInterface;

/**
 * How one kind of custom field is filled in on the site's comment form and shown on a comment.
 *
 * Comments' own descriptions for Craft's field types go through `FormFields` like any other, and a plugin
 * or module registers one for its own type with `FormFields::EVENT_REGISTER_FORM_FIELD_TYPES`. A field type
 * with no description can't be collected on the site, and the settings page says so.
 *
 * Nothing a description returns is ever treated as HTML. The renderer escapes everything apart from a
 * comment's own body, so `display()` returns plain text and JSON-safe data.
 */
interface FormFieldTypeInterface
{
    // Public Methods
    // =========================================================================

    /**
     * The input for the field: `type` is one of `text`, `textarea`, `number`, `email`, `url`, `date`,
     * `select`, `radio`, `checkboxes`, `toggle` or `file`, or `custom` for a type the site draws with a
     * field override in JavaScript. Also `multiple`, `options` (each `label` and `value`) and
     * `attributes`, such as `min`, `max`, `step` or `maxlength`, and for `file`, `accept`, `maxFiles`
     * and `maxSize`. Anything else the site's own override needs goes in `meta`, an array the field's
     * JSON carries as it is; other keys are moved into `meta` too. `$comment` is the comment being
     * written or edited, which hasn't been saved yet for a new one.
     */
    public function describe(FieldInterface $field, ?Comment $comment): array;

    /**
     * The value to set on the comment from what the site's form or a headless client sent, after which
     * Craft's own validation for the field runs. Throw `yii\base\InvalidArgumentException` with a message
     * for the person writing when it can't be read.
     */
    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed;

    /**
     * The field's value shown on a comment, as `['value' => <JSON-safe raw value>, 'text' => <plain
     * text>]`, or null to show nothing. Never HTML.
     */
    public function display(FieldInterface $field, mixed $value): ?array;

    /**
     * The field's value as the form takes it back, for its author editing their comment: the same shape
     * a submission sends.
     */
    public function inputValue(FieldInterface $field, mixed $value): mixed;
}
