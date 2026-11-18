<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use craft\base\Element;

use Closure;

use verbb\base\services\Templates as BaseTemplates;

class Templates extends BaseTemplates
{
    // Properties
    // =========================================================================

    public string $pluginClass = Comments::class;
    public string|false|null $sandboxedAutoescape = false;


    // Public Methods
    // =========================================================================

    public function getSandboxedVariables(): array
    {
        return $this->getSiteTemplateVariables();
    }

    /**
     * Email templates can read a comment's own details and the custom fields it shows publicly, but no
     * value that's only collected or Off, which stays in the control panel (SD-42) however a system
     * message is written. Other elements keep the shared rules.
     */
    public function getDefaultSandboxedAllowedProperties(): array
    {
        $defaults = parent::getDefaultSandboxedAllowedProperties();
        $elements = $defaults[Element::class] ?? null;
        $commentProperties = ['ownerId', 'ownerSiteId', 'userId', 'name', 'email', 'gifUrl', 'commentDate', 'comment', 'rawComment', 'excerpt', 'timeAgo', 'author', 'authorName', 'authorEmail', 'owner', 'user', 'cpEditUrl'];

        return [
            Comment::class => static fn(Comment $comment, string $name): bool => in_array($name, $commentProperties, true)
                || Comments::$plugin->getFormFields()->isShownOn($comment, $name),
            Element::class => static fn(Element $element, string $name): bool => $element instanceof Comment
                ? in_array($name, ['id', 'uid', 'title', 'slug', 'uri', 'url', 'site', 'siteId', 'status', 'enabled', 'dateCreated', 'dateUpdated'], true)
                : ($elements instanceof Closure ? $elements($element, $name) : in_array($name, (array)$elements, true)),
        ] + $defaults;
    }
}
