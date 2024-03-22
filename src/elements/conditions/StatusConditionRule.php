<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\conditions\ElementConditionRuleInterface;

use yii\db\QueryInterface;

class StatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    // Public Methods
    // =========================================================================
    
    public function getLabel(): string
    {
        return Craft::t('app', 'Status');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['status'];
    }

    public function modifyQuery(QueryInterface $query): void
    {
        /** @var ElementQueryInterface $query */
        $query->status($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->getStatus());
    }


    // Protected Methods
    // =========================================================================

    protected function options(): array
    {
        /** @var ElementCondition $condition */
        $condition = $this->getCondition();
        /** @var ElementInterface|string $elementType */
        $elementType = $condition->elementType;

        return array_map(fn($info) => $info['label'] ?? $info, $elementType::statuses());
    }

}
