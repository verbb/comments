<?php
namespace verbb\comments\formfields;

use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;
use craft\fields\data\LinkData;
use craft\fields\Link;

/**
 * Link fields, as a web address. Only a web address can be entered on the site, whatever else the field
 * links to in the control panel.
 *
 * A shown value's `value` is the address only when it's `http` or `https`, so the renderer never links
 * anything else, and it links that with `rel="ugc nofollow noopener"`. Its `text` is always plain text.
 */
class UrlType extends FormFieldType
{
    // Static Methods
    // =========================================================================

    /**
     * Whether `$url` is an `http` or `https` address, the only kinds a shown value links to.
     */
    public static function isWebAddress(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true) && is_string(parse_url($url, PHP_URL_HOST));
    }


    // Public Methods
    // =========================================================================

    public function unsupportedReason(FieldInterface $field): ?string
    {
        if ($field instanceof Link && !in_array('url', $field->types, true)) {
            return Craft::t('comments', 'This Link field doesn’t take web addresses, which is all the site’s form can collect.');
        }

        return null;
    }

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        return [
            'type' => 'url',
            'multiple' => false,
            'options' => [],
            'attributes' => [],
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        // GraphQL's `LinkDataInput`, of which only the address is taken.
        if (is_array($submitted) && !array_is_list($submitted)) {
            $submitted = $submitted['value'] ?? null;
        }

        $value = $this->string($submitted);
        $value = $value === null ? '' : trim($value);

        if ($value === '') {
            return null;
        }

        // A visitor can only give a web address, never point the field at an entry, asset or email.
        return $field instanceof Link ? ['type' => 'url', 'value' => $value] : $value;
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        $url = $value instanceof LinkData ? $value->getUrl() : $this->text($value);
        $url = is_string($url) ? trim($url) : '';

        if ($url === '') {
            return null;
        }

        $label = $value instanceof LinkData ? $value->getLabel(true) : null;

        return [
            'value' => self::isWebAddress($url) ? $url : null,
            'text' => is_string($label) && trim($label) !== '' ? trim($label) : $url,
        ];
    }

    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        $url = $value instanceof LinkData ? $value->getUrl() : $this->text($value);

        return is_string($url) && $url !== '' ? $url : null;
    }
}
