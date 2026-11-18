<?php
namespace verbb\comments\events;

use yii\base\Event;

/**
 * Registers front-end descriptions for custom field types, so they can be collected on the site's comment
 * form and shown on comments. `types` is keyed by field class, and each value is a
 * `FormFieldTypeInterface` object or class name. A field class without a description of its own uses the
 * nearest parent class that has one.
 *
 * ```php
 * Event::on(FormFields::class, FormFields::EVENT_REGISTER_FORM_FIELD_TYPES, function(RegisterFormFieldTypesEvent $event) {
 *     $event->types[StarRating::class] = StarRatingFormFieldType::class;
 * });
 * ```
 */
class RegisterFormFieldTypesEvent extends Event
{
    // Properties
    // =========================================================================

    public array $types = [];
}
