<?php
namespace verbb\comments\fields;

use verbb\comments\Comments;
use verbb\comments\web\assets\cp\CommentsFieldAsset;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;

use yii\db\Schema;

class CommentsField extends Field
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('comments', 'Comments');
    }

    public static function icon(): string
    {
        return '@verbb/comments/icon-mask.svg';
    }

    public static function supportedTranslationMethods(): array
    {
        return [];
    }

    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    /** Resolve stored and legacy field values to an explicit per-element override. */
    public static function enabledOverride(mixed $value): ?bool
    {
        return match (self::_normalizeState($value)) {
            self::STATE_ENABLED => true,
            self::STATE_DISABLED => false,
            default => null,
        };
    }

    private static function _normalizeState(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = Json::decodeIfJson($value);

            if ($decoded !== $value) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            if (array_key_exists('state', $value)) {
                $value = $value['state'];
            } elseif (array_key_exists('enabledOverride', $value)) {
                return match ($value['enabledOverride']) {
                    true, 1, '1', self::STATE_ENABLED => self::STATE_ENABLED,
                    false, 0, '0', self::STATE_DISABLED => self::STATE_DISABLED,
                    default => self::STATE_INHERIT,
                };
            } elseif (array_key_exists('commentEnabled', $value)) {
                // A legacy true value inherited source policy; only false was an override.
                return in_array($value['commentEnabled'], [false, 0, '0', self::STATE_DISABLED], true)
                    ? self::STATE_DISABLED
                    : self::STATE_INHERIT;
            }
        }

        return match ($value) {
            self::STATE_ENABLED => self::STATE_ENABLED,
            self::STATE_CLOSED => self::STATE_CLOSED,
            self::STATE_DISABLED, false, 0, '0' => self::STATE_DISABLED,
            default => self::STATE_INHERIT,
        };
    }


    // Constants
    // =========================================================================

    public const STATE_INHERIT = 'inherit';
    public const STATE_ENABLED = 'enabled';
    public const STATE_CLOSED = 'closed';
    public const STATE_DISABLED = 'disabled';


    // Properties
    // =========================================================================

    /**
     * Whether the input shows the comments summary (waiting and reported comments, the latest ones and
     * who's following) under the Open, Closed or Off setting. Off leaves only the setting.
     */
    public bool $showSummary = true;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // v3 exposed a default lightswitch, but its true value only meant "do not veto".
        // Removing both settings lets the source policy remain the sole inherited default.
        unset($config['columnType'], $config['commentEnabled'], $config['default']);

        parent::__construct($config);
    }

    public function getSettingsHtml(): ?string
    {
        return Cp::lightswitchFieldHtml([
            'label' => Craft::t('comments', 'Show Comments Summary'),
            'instructions' => Craft::t('comments', 'Show waiting, reported and latest comments below the setting.'),
            'id' => 'show-summary',
            'name' => 'showSummary',
            'on' => $this->showSummary,
        ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return self::_normalizeState($value);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return self::_normalizeState($value);
    }

    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        // Closing is a Conversation's state rather than a config override, so comments stay visible.
        if ($element->getIsCanonical() && $element->siteId) {
            Comments::$plugin->getConversations()->setClosed(
                (int)$element->id,
                (int)$element->siteId,
                $element->getFieldValue($this->handle) === self::STATE_CLOSED,
            );
        }

        // Field values participate in effective Conversation configuration immediately.
        Comments::$plugin->getConversations()->clearCaches();

        parent::afterElementSave($element, $isNew);
    }


    // Protected Methods
    // =========================================================================

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $view = Craft::$app->getView();
        $ownerElement = Comments::$plugin->getOwnerElement();
        $id = Html::id($this->handle);
        $summary = null;

        $view->registerAssetBundle(CommentsFieldAsset::class);

        // The panel's script rewrites these after a decision or a state change.
        $view->registerTranslations('comments', [
            '1 waiting for review',
            '{count} waiting for review',
            '1 reported',
            '{count} reported',
        ]);

        if ($this->showSummary && $element?->id && !$inline) {
            $ownerId = (int)($element->getCanonicalId() ?? $element->id);

            $summary = $ownerElement->getFieldSummary(
                $ownerId,
                (int)$element->siteId,
            );
        }

        return $view->renderTemplate('comments/_field/input', [
            'id' => $id,
            'name' => $this->handle,
            'value' => self::_normalizeState($value),
            'element' => $element,
            'summary' => $summary,
            'stateOptions' => $ownerElement->getStateOptions(),
            // What "inherit" resolves to, so the control can show it while nothing is overridden.
            'sourceStatus' => $element ? $ownerElement->getSourceStatus($element) : null,
        ]);
    }
}
