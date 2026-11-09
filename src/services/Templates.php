<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

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

    public function getDefaultSandboxedAllowedProperties(): array
    {
        return [
            Comment::class => ['ownerId', 'ownerSiteId', 'userId', 'name', 'email', 'gifUrl', 'commentDate', 'comment', 'rawComment', 'excerpt', 'timeAgo', 'author', 'authorName', 'authorEmail', 'owner', 'user', 'cpEditUrl'],
        ] + parent::getDefaultSandboxedAllowedProperties();
    }
}
