<?php
namespace Craft;

class CommentsVariable
{
    public function getName()
    {
        $plugin = craft()->plugins->getPlugin('comments');
        return $plugin->getName();
    }

	public function elements($elementType, $criteria = array())
	{
		return craft()->elements->getCriteria($elementType, $criteria);
	}

    // TODO - remove this
	public function replies($comment)
	{
		return craft()->comments->getCriteria(array('descendantOf' => $comment->id))->find();
	}

	public function all($criteria = array())
	{
		return craft()->comments->getCriteria($criteria);
	}

	public function form($elementId, $criteria = array())
	{	
		$settings = craft()->plugins->getPlugin('comments')->getSettings();
		$oldPath = craft()->path->getTemplatesPath();
		$element = craft()->elements->getElementById($elementId);
        
		$criteria = array_merge($criteria, array(
			'elementId' => $element->id,
			'level' => '1',
			'status' => Comments_CommentModel::APPROVED,
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
		);
		
		$html = craft()->templates->render($templateFile, $variables);
		
		craft()->path->setTemplatesPath($oldPath);

		// Finally - none of this matters if the permission to comment on this element is denied
		if (!craft()->comments->checkPermissions($element)) {
			return false;
		}

        return new \Twig_Markup($html, craft()->templates->getTwig()->getCharset());
	}

	public function protect()
	{
		$fields = craft()->comments_protect->getFields();
        return new \Twig_Markup($fields, craft()->templates->getTwig()->getCharset());
	}

}
