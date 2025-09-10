<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;

class EntryTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    // Public Methods
    // =========================================================================
    
    public function getLabel(): string
    {
        return Craft::t('app', 'Entry Type');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['ownerEntryType', 'ownerEntryTypeId'];
    }

    public function setAttributes($values, $safeOnly = true): void
    {
        if (array_key_exists('entryTypeUid', $values)) {
            $values['values'] = array_filter([$values['entryTypeUid']]);
            unset($values['entryTypeUid'], $values['sectionUid']);
        }

        parent::setAttributes($values, $safeOnly);
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery $query */
        $entriesService = Craft::$app->getEntries();

        $query->ownerEntryTypeId($this->paramValue(fn($uid) => $entriesService->getEntryTypeByUid($uid)->id ?? null));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry $element */
        return $this->matchValue((string)$element->getType()->uid);
    }


    // Protected Methods
    // =========================================================================

    protected function options(): array
    {
        $options = [];

        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            $options[$entryType->uid] = $entryType->getUiLabel();
        }

        return $options;
    }
}
