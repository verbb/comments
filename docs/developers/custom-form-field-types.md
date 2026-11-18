# Custom Form Field Types

A form field type describes how a Craft field type is collected on the site’s comment form and shown on comments. Comments includes descriptions for Craft’s own field types, namely Plain Text, Number, Email, Link, Date, Dropdown, Multi-select, Radio Buttons, Checkboxes, Lightswitch and Assets. When your plugin or module adds its own field type, give it a description so it can be chosen under **Comments** → **Settings** → **Comment Fields**. Without one, the settings page shows the field with its mode turned off and the reason it can’t be collected.

A field class without its own description uses the nearest parent class that has one, so a field extending Craft’s Number field already works like a number. You only need a description when the field collects or shows its value differently.

## Create the Form Field Type Class

A description implements `verbb\comments\base\FormFieldTypeInterface`. It usually extends `verbb\comments\base\FormFieldType`, which draws the field with a JavaScript [field override](docs:template-guides/custom-fields#drawing-a-field-your-own-way), accepts text, numbers, true or false and lists of them as they come, and shows any value that reads as text. Override only what differs.

This example describes a hypothetical star rating field from a `ratings` module. It’s collected with a number input from 1 to 5, and shown on comments as “4 out of 5”.

```php
namespace modules\ratings\formfields;

use Craft;
use craft\base\FieldInterface;
use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;
use yii\base\InvalidArgumentException;

class StarRatingType extends FormFieldType
{
    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => 'number',
            'attributes' => ['min' => 1, 'max' => 5, 'step' => 1],
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        if ($submitted === null || $submitted === '') {
            return null;
        }

        if (!is_numeric($submitted) || (int)$submitted < 1 || (int)$submitted > 5) {
            throw new InvalidArgumentException(Craft::t('ratings', 'Choose a rating from 1 to 5.'));
        }

        return (int)$submitted;
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        if (!$value) {
            return null;
        }

        return ['value' => (int)$value, 'text' => $value . ' out of 5'];
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        return $value ? (int)$value : null;
    }
}
```

## Register the Form Field Type

Comments finds form field types through the `registerFormFieldTypes` event. Register your class from your module or plugin’s `init()` method.

```php
use modules\ratings\fields\StarRating;
use modules\ratings\formfields\StarRatingType;
use verbb\comments\events\RegisterFormFieldTypesEvent;
use verbb\comments\services\FormFields;
use yii\base\Event;

Event::on(FormFields::class, FormFields::EVENT_REGISTER_FORM_FIELD_TYPES, function(RegisterFormFieldTypesEvent $event) {
    $event->types[StarRating::class] = StarRatingType::class;
});
```

`$event->types` is keyed by field class. Each value is a class name, or an object implementing `FormFieldTypeInterface`. Setting a key that Comments already uses, such as `craft\fields\PlainText`, replaces its description for that field type.

## Methods

::: reference
### `describe()`

**Returns:** `array`

Returns the input the form shows for the field. `type` is one of `text`, `textarea`, `number`, `email`, `url`, `date`, `select`, `radio`, `checkboxes`, `toggle` or `file`, or `custom` for a field you draw yourself with a [field override](docs:template-guides/custom-fields#drawing-a-field-your-own-way). The array can also hold `multiple`, `options` (each with a `label` and `value`) and `attributes`, such as `min`, `max`, `step` or `maxlength`. A `file` input also takes `accept`, `maxFiles` and `maxSize`. Anything else your field override needs goes in `meta`, which the field’s JSON carries as it is, and any other key is moved into `meta` too.

The method receives the field and the comment being written or edited, which isn’t saved yet for a new comment.

```php
public function describe(FieldInterface $field, ?Comment $comment): array
{
    return [
        'type' => 'select',
        'options' => [
            ['label' => 'Agree', 'value' => 'agree'],
            ['label' => 'Disagree', 'value' => 'disagree'],
        ],
    ];
}
```
:::

::: reference
### `normalizeInput()`

**Returns:** `mixed`

Returns the value to set on the comment from what the site’s form or a headless client sent. Craft’s own validation for the field runs afterwards. When the value can’t be read, throw `yii\base\InvalidArgumentException` with a message for the person writing the comment, which the form shows beside the field.
:::

::: reference
### `display()`

**Returns:** `array|null`

Returns the field’s value as shown on a comment, as `['value' => …, 'text' => …]`. `value` is the raw value, which must be safe to encode as JSON, and `text` is plain text. Return `null` to show nothing. Comments never treats what this returns as HTML.
:::

::: reference
### `inputValue()`

**Returns:** `mixed`

Returns the value the form starts with when an author edits their comment, in the same shape the form submits it. `FormFieldType` returns the `value` from `display()`.
:::

::: reference
### `unsupportedReason()`

**Returns:** `string|null`

Returns why a particular field can’t be used on the comment form, such as a Link field that doesn’t accept web addresses, or `null` when it can. The settings page shows the reason. This method is on `FormFieldType` rather than the interface, and returns `null` by default.

```php
public function unsupportedReason(FieldInterface $field): ?string
{
    return $field->multiple ? 'Only single ratings can be collected on comments.' : null;
}
```
:::
