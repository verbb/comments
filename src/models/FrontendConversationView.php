<?php
namespace verbb\comments\models;

use craft\base\Model;

/**
 * What the mount point `craft.comments.render()` outputs needs to know about a thread. It's the same
 * for every visitor, so a page holding it can be cached by anyone.
 */
class FrontendConversationView extends Model
{
    // Properties
    // =========================================================================

    public string $instanceId;
    public int $ownerId;
    public int $siteId;
    public string $sort;
    public int $rootLimit = 20;
    public string $theme;
    public int $commentCount = 0;
}
