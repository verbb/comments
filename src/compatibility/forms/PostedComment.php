<?php
namespace verbb\comments\compatibility\forms;

use craft\base\Model;

/**
 * What a refused post to the Comments 3 form actions sent, handed back to the site's template as
 * `comment` so its form can be filled in again. It holds only the posted values, as plain scalars, and
 * has nothing that looks anything up, so a template reading it can never reach a stored comment, its
 * parent, replies or owner. It keeps the names a Comments 3 template reads (`comment.comment`,
 * `comment.name`, `comment.email` and the IDs), and the errors, as `getErrors()` and `hasErrors()`.
 * Anything else a template asks it for, such as `comment.parent` or `comment.getReplies()`, is null.
 */
class PostedComment extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $ownerId = null;
    public ?int $ownerSiteId = null;
    public ?int $siteId = null;
    public ?int $parentId = null;
    public ?string $comment = null;
    public ?string $name = null;
    public ?string $email = null;


    // Public Methods
    // =========================================================================

    public function __get($name)
    {
        return $this->canGetProperty($name) ? parent::__get($name) : null;
    }

    public function __call($name, $params)
    {
        return $this->hasMethod($name) ? parent::__call($name, $params) : null;
    }
}
