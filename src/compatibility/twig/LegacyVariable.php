<?php
namespace verbb\comments\compatibility\twig;

use verbb\comments\services\Compatibility;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Template;

use Twig\Markup;

use yii\base\InvalidArgumentException;

/**
 * Comments 3's `craft.comments` methods, which only work in compatibility mode. `render()` now registers
 * the thread's CSS and JavaScript itself, so the methods that output them return nothing.
 */
trait LegacyVariable
{
    // Public Methods
    // =========================================================================

    public function renderCss(mixed $elementId = null, array $attributes = []): Markup
    {
        Compatibility::requireMethod(static::class, 'renderCss', '`craft.comments.render()` registers Comments’ CSS itself, controlled by the **Load Bundled CSS** setting, so remove the call.');

        return Template::raw('');
    }

    public function renderJs(mixed $elementId = null, mixed $criteria = [], bool $loadInline = true, array $attributes = []): Markup
    {
        Compatibility::requireMethod(static::class, 'renderJs', '`craft.comments.render()` registers Comments’ JavaScript itself, controlled by the **Load Bundled JavaScript** setting, so remove the call.');

        return Template::raw('');
    }

    public function getJsVariables(mixed $elementId = null, mixed $criteria = []): array
    {
        Compatibility::requireMethod(static::class, 'getJsVariables', 'Comments 3’s script, which read these, has been removed. Configure threads with `render()` options or `Comments.configure()` instead.');

        return [];
    }


    // Protected Methods
    // =========================================================================

    /**
     * The owner a Comments 3 `craft.comments.protect()` call meant. It took no element, or `true` to load its
     * scripts inline, and protected the page's own form. That's the element Craft matched for the page, so
     * a form posting for anything else is refused when it's checked, never let through.
     *
     * @return array{int, int|null} The owner's ID and site ID.
     */
    protected function legacyProtectOwner(mixed $ownerId, ?int $siteId): array
    {
        if (is_int($ownerId) || (is_string($ownerId) && ctype_digit($ownerId))) {
            return [(int)$ownerId, $siteId];
        }

        if (!Compatibility::enabled()) {
            throw new InvalidArgumentException('`craft.comments.protect()` needs the ID of the element the form comments on, such as `craft.comments.protect(entry.id, entry.siteId)`.');
        }

        Compatibility::deprecated('protect', '`craft.comments.protect()` now takes the ID and site ID of the element the form comments on, such as `craft.comments.protect(entry.id, entry.siteId)`. Without them it protects the page’s own element.');

        $element = Craft::$app->getUrlManager()->getMatchedElement();

        if (!$element instanceof ElementInterface || !$element->id) {
            return [0, $siteId];
        }

        return [(int)$element->id, $siteId ?? (int)$element->siteId];
    }
}
