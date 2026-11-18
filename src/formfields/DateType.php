<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;
use craft\fields\Date;
use craft\helpers\DateTimeHelper;

use yii\base\InvalidArgumentException;

use DateTimeInterface;

/**
 * Date fields, as a date input taking `YYYY-MM-DD`, shown in the site's language.
 */
class DateType extends FormFieldType
{
    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => 'date',
            'multiple' => false,
            'options' => [],
            'attributes' => array_filter([
                'min' => $field instanceof Date && $field->min ? $field->min->format('Y-m-d') : null,
                'max' => $field instanceof Date && $field->max ? $field->max->format('Y-m-d') : null,
            ], static fn(mixed $value): bool => $value !== null),
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        if ($submitted === null || $submitted === '') {
            return null;
        }

        // GraphQL reads its `DateTime` arguments into dates already.
        if ($submitted instanceof DateTimeInterface) {
            return $submitted;
        }

        $date = is_string($submitted) && preg_match('/^\d{4}-\d{2}-\d{2}([T ][0-9:.]+(Z|[+-]\d{2}:?\d{2})?)?$/', trim($submitted))
            ? DateTimeHelper::toDateTime(trim($submitted))
            : false;

        if (!$date instanceof DateTimeInterface) {
            throw new InvalidArgumentException(Craft::t('comments', 'Enter a date.'));
        }

        return $date;
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        if (!$value instanceof DateTimeInterface) {
            return null;
        }

        $formatter = Craft::$app->getFormatter();
        $showTime = $field instanceof Date && $field->showTime;

        return [
            'value' => $showTime ? $value->format(DateTimeInterface::ATOM) : $value->format('Y-m-d'),
            'text' => $showTime ? $formatter->asDatetime($value, 'medium') : $formatter->asDate($value, 'medium'),
        ];
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : null;
    }
}
