<?php
namespace verbb\comments\compatibility\elements;

use verbb\comments\services\Compatibility;

use craft\elements\db\ElementQueryInterface;
use craft\elements\ElementCollection;

/**
 * Comments 3 kept comments in a Craft structure. Comments 4 keeps each reply's parent and depth instead,
 * and these answer Craft's structure methods from them. `getChildren()` and `getDescendants()` work
 * whatever the mode, and `level` and `getAncestors()` only in compatibility mode.
 */
trait LegacyStructure
{
    // Public Methods
    // =========================================================================

    /**
     * The approved direct replies, as for `getReplies()`.
     */
    public function getChildren(): ElementQueryInterface|ElementCollection
    {
        return $this->getReplies();
    }

    /**
     * The approved replies beneath this comment, at every depth or down to `$dist` levels. A reply under
     * one that isn't approved is left out with it, as the thread leaves it out.
     */
    public function getDescendants(?int $dist = null): ElementQueryInterface|ElementCollection
    {
        if (!$this->id || $dist === 0) {
            return new ElementCollection();
        }

        $descendants = [];
        $parentIds = [(int)$this->id];
        $depth = 0;

        while ($parentIds !== [] && ($dist === null || $depth < $dist)) {
            $children = static::find()
                ->parentId($parentIds)
                ->siteId((int)$this->siteId)
                ->orderBy([
                    'comments_comments.commentDate' => SORT_ASC,
                    'elements.id' => SORT_ASC,
                ])
                ->all();

            foreach ($children as $child) {
                $descendants[] = $child;
            }

            $parentIds = array_map(static fn(self $comment): int => (int)$comment->id, $children);
            $depth++;
        }

        return new ElementCollection($descendants);
    }

    /**
     * The approved comments above this one, from the top-level comment down, or the nearest `$dist` of
     * them. It stops at a comment that isn't approved, as `getParent()` does.
     */
    public function getAncestors(?int $dist = null): ElementQueryInterface|ElementCollection
    {
        if (!Compatibility::enabled()) {
            return parent::getAncestors($dist);
        }

        Compatibility::deprecated('getAncestors', 'Comments are no longer kept in a structure. Walk up with `comment.getParent()`, or use `rootId` for the top-level comment.');

        $ancestors = [];
        $comment = $this;

        while (($dist === null || count($ancestors) < $dist) && ($comment = $comment->getParent())) {
            array_unshift($ancestors, $comment);
        }

        return new ElementCollection($ancestors);
    }


    // Protected Methods
    // =========================================================================

    /**
     * Comments 3's `level` started at 1 for a top-level comment, where Comments 4's `depth` starts at 0.
     */
    protected function initLegacyLevel(): void
    {
        if ($this->level === null && $this->depth !== null && Compatibility::enabled()) {
            $this->level = $this->depth + 1;
        }
    }
}
