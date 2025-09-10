<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;

use yii\db\QueryInterface;

class OwnerTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    // Public Methods
    // =========================================================================
    
    public function getLabel(): string
    {
        return Craft::t('app', 'Owner Type');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['ownerType'];
    }

    public function modifyQuery(QueryInterface $query): void
    {
        /** @var ElementQueryInterface $query */
        $query->ownerType($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue(get_class($element));
    }


    // Protected Methods
    // =========================================================================

    protected function options(): array
    {
        return [
            ['label' => Asset::displayName(), 'value' => Asset::class],
            ['label' => Category::displayName(), 'value' => Category::class],
            ['label' => Entry::displayName(), 'value' => Entry::class],
            ['label' => User::displayName(), 'value' => User::class],
        ];
    }

}
