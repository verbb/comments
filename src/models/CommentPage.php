<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;

use craft\base\Model;

/**
 * Paginated root Comment page. Product continuation token is simply `next`.
 *
 * @property-read Comment[] $comments
 */
class CommentPage extends Model
{
    // Public Methods
    // =========================================================================

    public function __construct(
        public array $comments,
        public ?string $next = null,
        array $config = [],
    ) {
        parent::__construct($config);
    }
}
