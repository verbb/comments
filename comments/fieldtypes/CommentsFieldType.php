<?php
namespace Craft;

class CommentsFieldType extends BaseFieldType
{
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
        //var_dump($value);
        return $value;
    }

    public function prepValueFromPost($value)
    {
        return json_encode($value);
    }

    public function prepSettings($settings)
    {
        //var_dump($settings);
        return $settings;
    }

    public function defineContentAttribute()
    {
        return AttributeType::Mixed;
    }

    protected function defineSettings()
    {
        return array(
            'allowAnonymousGlobal'            => array( AttributeType::Bool, 'default' => false ),
            'requireModerationGlobal'         => array( AttributeType::Bool, 'default' => false ),
            'commentsClosedGlobal'            => array( AttributeType::Bool, 'default' => false ),
            'userPermissionsGlobal'           => array( AttributeType::Mixed ),
        );
    }

}