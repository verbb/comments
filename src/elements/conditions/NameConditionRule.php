<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\conditions\ElementConditionRuleInterface;

class NameConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    // Public Methods
    // =========================================================================
    
    public function getLabel(): string
    {
        return Craft::t('comments', 'Name');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['name'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->name($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->name);
    }
}
