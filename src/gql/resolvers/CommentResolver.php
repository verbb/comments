<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\elements\Comment;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use craft\gql\base\ElementResolver;

class CommentResolver extends ElementResolver
{
    // Static Methods
    // =========================================================================

    public static function prepareQuery(mixed $source, array $arguments, $fieldName = null): mixed
    {
        if ($source === null) {
            $query = Comment::find();
        } else {
            $query = $source->$fieldName;
        }

        if (!$query instanceof ElementQuery) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        if (!GqlHelper::canQueryComments()) {
            return ElementCollection::empty();
        }

        // Applied after the arguments so a public schema can't ask for unpublished comments.
        if (GqlHelper::isPublicSchema()) {
            $query->status(Comment::STATUS_APPROVED)
                ->trashed(false)
                ->andWhere(['comments_comments.authorRemovedAt' => null]);
        }

        return $query;
    }

    public static function isPubliclyReadable(?Comment $comment): bool
    {
        return $comment
            && $comment->status === Comment::STATUS_APPROVED
            && !$comment->authorRemovedAt
            && !$comment->trashed;
    }
}
