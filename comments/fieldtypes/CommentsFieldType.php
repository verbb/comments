<?php
namespace Craft;

class CommentsFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('Comment Options');
    }

    public function defineContentAttribute()
    {
        return AttributeType::Mixed;
    }

    public function getInputHtml($name, $value)
    {
        return craft()->templates->render('comments/_fieldtype/input', array(
            'id' => craft()->templates->formatInputId($name),
            'name' => $name,
            'value' => $value,
            'element' => $this->element,
            'model' => $this->model,
        ));
    }

    public function prepValue($value)
    {
        if (!$value) {
            $value['commentEnabled'] = $this->getSettings()->commentEnabled;
        }

        return $value;
    }


    // Protected Methods
    // =========================================================================

    protected function defineSettings()
    {
        return array(
            'commentEnabled' => array( AttributeType::Bool, 'default' => true ),
        );
    }

}
