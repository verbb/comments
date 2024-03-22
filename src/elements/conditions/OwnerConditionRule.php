<?php
namespace verbb\comments\elements\conditions;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\base\NestedElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;

use yii\db\QueryInterface;

class OwnerConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    // Properties
    // =========================================================================
    
    public string $elementType = Entry::class;


    // Public Methods
    // =========================================================================
    
    public function getLabel(): string
    {
        return Craft::t('app', 'Owner');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['ownerId'];
    }

    public function modifyQuery(QueryInterface $query): void
    {
        $elementId = $this->getElementId();

        if ($elementId !== null) {
            $query->ownerId($elementId);
        }
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->ownerId);
    }


    // Protected Methods
    // =========================================================================
    
    protected function elementType(): string
    {
        return $this->elementType;
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['elementType'], 'safe'],
        ]);
    }

    protected function inputHtml(): string
    {
        $id = 'element-type';

        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                Cp::selectHtml([
                    'id' => $id,
                    'name' => 'elementType',
                    'options' => $this->_elementTypeOptions(),
                    'value' => $this->elementType,
                    'inputAttributes' => [
                        'hx' => [
                            'post' => UrlHelper::actionUrl('conditions/render'),
                        ],
                    ],
                ]) .
                parent::inputHtml(),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            );
    }


    // Private Methods
    // =========================================================================
    
    private function _elementTypeOptions(): array
    {
        $options = [];
        
        foreach (Craft::$app->getElements()->getAllElementTypes() as $elementType) {
            $options[] = [
                'value' => $elementType,
                'label' => $elementType::displayName(),
            ];
        }

        return $options;
    }
}
