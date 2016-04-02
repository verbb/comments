<?php
namespace Craft;

class CommentsVariable
{
    public function elements($elementType, $criteria = array())
    {
        return craft()->elements->getCriteria($elementType, $criteria);
    }

    public function all($criteria = array())
    {
        return craft()->comments->getCriteria($criteria);
    }

    public function total($elementId)
    {
        return craft()->comments->getTotalComments($elementId);
    }

    public function form($elementId, $criteria = array())
    {
        $settings = craft()->comments->getSettings();
        $oldPath = craft()->path->getTemplatesPath();
        $element = craft()->elements->getElementById($elementId);

        $criteria = array_merge($criteria, array(
            'elementId' => $element->id,
            'level' => '1',
        ));

        $comments = craft()->comments->getCriteria($criteria);

        // Is the user providing their own templates?
        if ($settings->templateFolderOverride) {

            // Check if this file even exists
            $commentTemplate = craft()->path->getSiteTemplatesPath() . $settings->templateFolderOverride . '/comments';
            foreach (craft()->config->get('defaultTemplateExtensions') as $extension) {
                if (IOHelper::fileExists($commentTemplate . "." . $extension)) {
                    $templateFile =  $settings->templateFolderOverride . '/comments';
                }
            }
        }

        // If no user templates, use our default
        if (!isset($templateFile)) {
            $templateFile = '_forms/templates/comments';

            craft()->path->setTemplatesPath(craft()->path->getPluginsPath() . 'comments/templates');
        }

        $variables = array(
            'element' => $element,
            'comments' => $comments,
            'settings' => $settings,
        );

        $html = craft()->templates->render($templateFile, $variables);

        craft()->path->setTemplatesPath($oldPath);

        // Finally - none of this matters if the permission to comment on this element is denied
        if (!craft()->comments_settings->checkPermissions($element)) {
            return false;
        }

        return new \Twig_Markup($html, craft()->templates->getTwig()->getCharset());
    }

    public function protect()
    {
        $fields = craft()->comments_protect->getFields();
        return new \Twig_Markup($fields, craft()->templates->getTwig()->getCharset());
    }

    public function isClosed($elementId)
    {
        return craft()->comments_settings->checkClosed($elementId);
    }

    public function getActiveComment()
    {
        return craft()->comments->getActiveComment();
    }


}
