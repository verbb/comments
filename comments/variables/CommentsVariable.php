<?php
namespace Craft;

class CommentsVariable
{
    public function getName()
    {
        $plugin = craft()->plugins->getPlugin('comments');
        return $plugin->getName();
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

	public function form($entry, $criteria = array())
	{	
		$settings = craft()->plugins->getPlugin('comments')->getSettings();
		$oldPath = craft()->path->getTemplatesPath();

		$criteria = array_merge($criteria, array(
			'entryId' => $entry->id,
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
			'entry' => $entry,
			'comments' => $comments,
		);
		
		$html = craft()->templates->render($templateFile, $variables);
		
		craft()->path->setTemplatesPath($oldPath);

        return new \Twig_Markup($html, craft()->templates->getTwig()->getCharset());
	}

}
