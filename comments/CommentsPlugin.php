<?php
namespace Craft;

class CommentsPlugin extends BasePlugin
{
    /* --------------------------------------------------------------
    * PLUGIN INFO
    * ------------------------------------------------------------ */

    public function getName()
    {
        $pluginName = Craft::t('Comments');
        $pluginNameOverride = $this->getSettings()->pluginNameOverride;

        return ($pluginNameOverride) ? $pluginNameOverride : $pluginName;
    }

    public function getVersion()
    {
        return '0.1.1';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function hasCpSection()
    {
        return true;
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render( 'comments/settings', array(
            'settings' => $this->getSettings(),
        ));
    }

    protected function defineSettings()
    {
        return array(
            'structureId'               => AttributeType::Number,
            'pluginNameOverride'        => AttributeType::String,
            'cpSectionDisabled'         => array( AttributeType::Bool, 'default' => false ),
            'allowAnonymous'            => array( AttributeType::Bool, 'default' => false ),
            'requireModeration'         => array( AttributeType::Bool, 'default' => false ),
            'templateFolderOverride'    => AttributeType::String,
            'flaggedCommentLimit'       => array( AttributeType::Number, 'default' => '5' ),
        );
    }

    public function registerCpRoutes()
    {
        return array(
            'comments/edit/(?P<commentId>\d+)' => array('action' => 'comments/edit'),
        );
    }

    public function init()
    {
        // Comments are a Structure, which helps with hierarchy-goodness.
        // We only use a single structure for all our comments so store this at the plugin settings level
        if (!$this->getSettings()->structureId) {
            $structure = new StructureModel();

            craft()->structures->saveStructure($structure);

            // Update our plugin settings straight away!
            craft()->plugins->savePluginSettings($this, array('structureId' => $structure->id));
        }
    }


    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */
 
}

