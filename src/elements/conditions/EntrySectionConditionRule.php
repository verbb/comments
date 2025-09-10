<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;

class EntrySectionConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    // Properties
    // =========================================================================
    
    protected bool $reloadOnOperatorChange = true;


    // Public Methods
    // =========================================================================
    
    public function getLabel(): string
    {
        return Craft::t('app', 'Entry Section');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['ownerSection', 'ownerSectionId'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $sections = Craft::$app->getEntries();
        
        $query->ownerSectionId($this->paramValue(fn($uid) => $sections->getSectionByUid($uid)->id ?? null));
}

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->getSection()?->uid);
    }


    // Protected Methods
    // =========================================================================

    protected function options(): array
    {
        $sections = Craft::$app->getEntries()->getAllSections();

        return ArrayHelper::map($sections, 'uid', 'name');
    }
}
