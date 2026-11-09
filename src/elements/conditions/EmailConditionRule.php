<?php
namespace verbb\comments\elements\conditions;

use verbb\comments\helpers\CommentsHelper;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

class EmailConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    // Static Methods
    // =========================================================================

    /**
     * Filtering by address would reveal who commented, so only admins get the rule. Craft also drops
     * it from a posted or saved condition for anyone else, because it isn't selectable for them.
     */
    public static function isSelectable(): bool
    {
        return parent::isSelectable() && CommentsHelper::canViewAddresses();
    }


    // Public Methods
    // =========================================================================

    public function getLabel(): string
    {
        return Craft::t('comments', 'Email');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['email'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->email($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->email);
    }
}
