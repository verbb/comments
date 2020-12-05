<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\elements\Comment;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\gql\base\ElementResolver;

use GraphQL\Type\Definition\ResolveInfo;

class CommentResolver extends ElementResolver
{
    // Public Methods
    // =========================================================================

    public static function prepareQuery($source, array $arguments, $fieldName = null)
    {
        if ($source === null) {
            $query = Comment::find();
        } else {
            $query = $source->$fieldName;
        }

        if (is_array($query)) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        if (!GqlHelper::canQueryComments()) {
            return [];
        }

        return $query;
    }
}
