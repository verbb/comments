<?php
namespace Craft;

class CommentsPlugin extends BasePlugin
{
    /* --------------------------------------------------------------
    * PLUGIN INFO
    * ------------------------------------------------------------ */

    public function getName()
    {
        return Craft::t('Comments');
    }

    public function getVersion()
    {
        return '0.3.7';
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
        return craft()->templates->render('comments/settings/plugin', array(
            'settings' => $this->getSettings(),
        ));
    }

    protected function defineSettings()
    {
        return array(
            'structureId'               => AttributeType::Number,
            'permissions'               => AttributeType::Mixed,
            'closed'                    => AttributeType::Mixed,

            // General
            //'allowAnonymous'            => array( AttributeType::Bool, 'default' => false ),
            //'requireModeration'         => array( AttributeType::Bool, 'default' => false ),
            'flaggedCommentLimit'       => array( AttributeType::Number, 'default' => '5' ),
            'downvoteCommentLimit'      => array( AttributeType::Number, 'default' => '5' ),
            'autoCloseDays'             => array( AttributeType::Number, 'default' => '' ),

            // Templates
            'templateFolderOverride'    => AttributeType::String,

            // Security
            'securityModeration'        => AttributeType::Mixed,
            'securityBlacklist'         => AttributeType::Mixed,
            'securityBanned'            => AttributeType::Mixed,
            'securityFlooding'          => AttributeType::Number,

            // Users
            //'users'                     => AttributeType::Mixed,
        );
    }

    public function registerCpRoutes()
    {
        return array(
            'comments/edit/(?P<commentId>\d+)' => array('action' => 'comments/editTemplate'),
            'comments/permissions' => array('action' => 'comments/permissions'),
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
