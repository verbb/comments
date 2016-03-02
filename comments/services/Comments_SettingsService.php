<?php
namespace Craft;

class Comments_SettingsService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getFieldSettings($elementId)
    {
        $settings = craft()->comments->getSettings();
        $element = craft()->elements->getElementById($elementId);
        $commentField = null;

        // Get the comments field for this element
        foreach ($element->fieldLayout->fields as $layoutField) {
            if ($layoutField->field->fieldType->classHandle == 'Comments') {
                $commentField = $layoutField->field;
            }
        }

        // Does this element have a comment field (ie, comments enabled on it)
        if ($commentField) {
            $elementSettings = array();

            // Check if this singular element has any settings for comments
            if ($element->content[$commentField->handle]) {
                $elementSettings = $element->content[$commentField->handle];
            }

            // Get the field settings for this field instance (ie, for the section, group or folder)
            $fieldSettings = $commentField->attributes['settings'];

            return (object) array_merge((array)$fieldSettings, $elementSettings);
        } else {
            // Otherwise, return the global Comments settings
            return $settings;
        }
    }

    // Checks is there are sufficient permissions for commenting on this element
    public function checkPermissions($element)
    {
        //$fieldSettings = $this->getFieldSettings($element->id);
        /*$settings = craft()->comments->getSettings();
        $elementType = craft()->elements->getElementTypeById($element->id);
        
        // Do we even have any settings setup? By default - anything can be commented on
        if ($settings->permissions) {

            // Check for elementype-wide permissions - if turned off for entry, we don't show any new ones
            // But we still need to show comments for entries that have specifically been enabled on a per-element basis
            if (!array_key_exists($element->id, $settings->permissions[$elementType])) {
                if (!$settings->permissions[$elementType]['*']) {
                    return false;
                }
            } else {
                // Check for individual element permissions
                if (!$settings->permissions[$elementType][$element->id]) {
                    return false;
                }
            }
        }*/

        return true;
    }

    public function checkClosed($comment)
    {
        $fieldSettings = $this->getFieldSettings($comment->elementId);
        $settings = craft()->comments->getSettings();

        if ($fieldSettings) {

            // Has this comment been manually closed? Takes precedence
            if (property_exists($fieldSettings, 'commentsClosed')) {
                if ($fieldSettings->commentsClosed) {
                    return true;
                }
            }
        }

        // Has this element's publish date exceeded the set auto-close limit? Does it even have a auto-close limit?
        if ($settings->autoCloseDays) {
            $element = craft()->elements->getElementById($comment->elementId);
            $now = new DateTime('now');
            $interval = $now->diff($element->dateCreated);

            if ($interval->d > $settings->autoCloseDays) {
                return true;
            }
        }

        return false;
    }




}