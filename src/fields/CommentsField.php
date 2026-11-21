<?php
namespace verbb\comments\fields;

use verbb\comments\Comments;
use verbb\comments\compatibility\fields\LegacyCommentsFieldValue;
use verbb\comments\web\assets\cp\CommentsFieldAsset;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;

use yii\db\Schema;

class CommentsField extends Field
{
    // Traits
    // =========================================================================

    use LegacyCommentsFieldValue;


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

    /** Whether a stored field value closes the owner's comments, keeping existing ones visible. */
    public static function isClosedValue(mixed $value): bool
    {
        return self::_normalizeState($value) === self::STATE_CLOSED;
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
            } elseif (($legacy = self::_legacyState($value)) !== null) {
                return $legacy;
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

    /**
     * Whether an element without its own setting is closed, so comments are opt-in: existing ones show,
     * and nobody can post until an editor chooses Open. Comments 3's **Default Value** turned off is
     * read as this.
     */
    public bool $startClosed = false;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // v3 exposed a default lightswitch. On, it only meant "do not veto", so the source policy is the
        // inherited default. Off, elements without a value of their own were closed, which is Start Closed.
        if (array_key_exists('default', $config) && !array_key_exists('startClosed', $config) && App::normalizeBooleanValue($config['default']) !== true) {
            $config['startClosed'] = true;
        }

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
        ]) . Cp::lightswitchFieldHtml([
            'label' => Craft::t('comments', 'Start Closed'),
            'instructions' => Craft::t('comments', 'Elements without their own setting are closed, so their existing comments show, but nobody can post until an editor chooses Open.'),
            'id' => 'start-closed',
            'name' => 'startClosed',
            'on' => $this->startClosed,
        ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        // An element that has never had a value of its own, including a new one, starts closed. One an
        // editor set to Default keeps following the source.
        if ($this->startClosed && ($value === null || $value === '')) {
            return self::STATE_CLOSED;
        }

        return self::_normalizeState($value);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return self::_normalizeState($value);
    }

    public function beforeElementSave(ElementInterface $element, bool $isNew): bool
    {
        // Start Closed gives an element without a value of its own Closed. Save that value with it, so the
        // closed flag afterElementSave() writes matches what the field shows if Start Closed is turned off.
        if ($this->startClosed && $element->getIsCanonical() && !$element->isFieldDirty($this->handle) && $element->getFieldValue($this->handle) === self::STATE_CLOSED) {
            $element->setFieldValue($this->handle, self::STATE_CLOSED);
        }

        return parent::beforeElementSave($element, $isNew);
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
            'stateOptions' => $ownerElement->getStateOptions(!$element || Comments::$plugin->getComments()->checkPermissions($element)),
            // What "inherit" resolves to, so the control can show it while nothing is overridden.
            'sourceStatus' => $element ? $ownerElement->getSourceStatus($element) : null,
        ]);
    }
}
