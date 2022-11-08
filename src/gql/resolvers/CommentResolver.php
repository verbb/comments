<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\elements\Comment;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\elements\db\ElementQuery;
use craft\gql\base\ElementResolver;

use Illuminate\Support\Collection;

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
            return Collection::empty();
        }

        return $query;
    }
}
