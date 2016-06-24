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
        // Get the global permissions settings
        $permissions = craft()->comments->getSettings()->permissions;

        // Do we even have any settings setup? By default - anything can be commented on
        if ($permissions) {
            if (isset($permissions[$element->classHandle])) {
                if ($permissions[$element->classHandle] != '*') {
                    // Check for various elements
                    if ($element->classHandle == 'Entry') {
                        $id = $element->section->id;
                    } else {
                        $id = $element->group->id;
                    }

                    if (!in_array($id, $permissions[$element->classHandle])) {
                        return false;
                    }
                }
            }

            // Check for individual element permissions
            foreach ($element->content->attributes as $key => $value) {
                $field = craft()->fields->getFieldByHandle($key);

                if ($field['type'] == 'Comments') {
                    if (isset($value['commentEnabled'])) {
                        if (!$value['commentEnabled']) {
                            return false;
                        }
                    }
                }
            }
        }

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
            $now = new DateTime('now');
            $interval = $now->diff($comment->element->dateCreated);

            if ($interval->d > $settings->autoCloseDays) {
                return true;
            }
        }

        return false;
    }




}