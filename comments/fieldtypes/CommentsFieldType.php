<?php
namespace Craft;

class CommentsFieldType extends BaseFieldType
{
    // Public Methods
    // =========================================================================

    public function getName()
    {
        return Craft::t('Comments');
    }

    public function getInputHtml($name, $value)
    {
        return craft()->templates->render('comments/_fields/input', array(
            'name'     => $name,
            'value'    => $value,
            'settings' => $this->getSettings(),
        ));
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render('comments/_fields/settings', array(
            'settings' => $this->getSettings()
        ));
    }

    public function prepValue($value)
    {
        return $value;
    }

    public function prepValueFromPost($value)
    {
        return json_encode($value);
    }

    public function prepSettings($settings)
    {
        return $settings;
    }

    public function defineContentAttribute()
    {
        return AttributeType::Mixed;
    }


    // Protected Methods
    // =========================================================================

    protected function defineSettings()
    {
        return array(
            'allowAnonymous'            => array( AttributeType::Bool, 'default' => false ),
            'requireModeration'         => array( AttributeType::Bool, 'default' => false ),
            'commentsClosed'            => array( AttributeType::Bool, 'default' => false ),
            'userPermissions'           => array( AttributeType::Mixed ),
        );
    }

}