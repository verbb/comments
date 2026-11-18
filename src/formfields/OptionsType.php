<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;
use craft\fields\BaseOptionsField;
use craft\fields\Checkboxes;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\SingleOptionFieldData;
use craft\fields\MultiSelect;
use craft\fields\RadioButtons;

use yii\base\InvalidArgumentException;

/**
 * Dropdown, Radio Buttons, Multi-select and Checkboxes fields, shown by their options' labels.
 */
class OptionsType extends FormFieldType
{
    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => match (true) {
                $field instanceof RadioButtons => 'radio',
                $field instanceof Checkboxes => 'checkboxes',
                default => 'select',
            },
            'multiple' => $this->_isMultiple($field),
            'options' => $this->_options($field),
            'attributes' => [],
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        $allowed = array_column($this->_options($field), 'value');

        if (!$this->_isMultiple($field)) {
            if ($submitted === null || $submitted === '') {
                return null;
            }

            if (!is_scalar($submitted) || !in_array((string)$submitted, $allowed, true)) {
                throw new InvalidArgumentException(Craft::t('comments', 'Choose one of the options.'));
            }

            return (string)$submitted;
        }

        if ($submitted === null || $submitted === '') {
            return [];
        }

        if (!is_array($submitted) || !array_is_list($submitted)) {
            throw new InvalidArgumentException(Craft::t('comments', 'Choose from the options.'));
        }

        $values = [];

        foreach ($submitted as $value) {
            if (!is_scalar($value) || !in_array((string)$value, $allowed, true)) {
                throw new InvalidArgumentException(Craft::t('comments', 'Choose from the options.'));
            }

            $values[] = (string)$value;
        }

        return array_values(array_unique($values));
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        $labels = array_column($this->_options($field), 'label', 'value');

        if ($value instanceof MultiOptionsFieldData) {
            $values = array_values(array_filter(
                array_map(static fn($option): string => (string)$option->value, iterator_to_array($value)),
                static fn(string $option): bool => $option !== '',
            ));

            if ($values === []) {
                return null;
            }

            return [
                'value' => $values,
                'text' => implode(', ', array_map(static fn(string $option): string => (string)($labels[$option] ?? $option), $values)),
            ];
        }

        if ($value instanceof SingleOptionFieldData) {
            $option = $value->value === null ? '' : (string)$value->value;

            return $option === '' ? null : ['value' => $option, 'text' => (string)($labels[$option] ?? $option)];
        }

        return null;
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        if ($value instanceof MultiOptionsFieldData) {
            return array_values(array_map(static fn($option): string => (string)$option->value, iterator_to_array($value)));
        }

        if ($value instanceof SingleOptionFieldData) {
            return $value->value !== null && $value->value !== '' ? (string)$value->value : null;
        }

        return $this->_isMultiple($field) ? [] : null;
    }


    // Private Methods
    // =========================================================================

    private function _isMultiple(FieldInterface $field): bool
    {
        return $field instanceof MultiSelect || $field instanceof Checkboxes;
    }

    private function _options(FieldInterface $field): array
    {
        if (!$field instanceof BaseOptionsField) {
            return [];
        }

        $options = [];

        foreach ($field->options as $option) {
            // Option groups only have a heading, so their options are listed plainly.
            if (isset($option['value'])) {
                $options[] = ['label' => Craft::t('site', (string)$option['label']), 'value' => (string)$option['value']];
            }
        }

        return $options;
    }
}
