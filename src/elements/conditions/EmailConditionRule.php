<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\conditions\ElementConditionRuleInterface;

class EmailConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
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
