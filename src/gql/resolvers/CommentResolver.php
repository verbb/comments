<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\elements\Comment;
use verbb\comments\helpers\Gql as GqlHelper;

use Craft;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use craft\gql\base\ElementResolver;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;

class CommentResolver extends ElementResolver
{
    // Static Methods
    // =========================================================================

    public static function resolveOne(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $query = static::prepareElementQuery($source, $arguments, $context, $resolveInfo);
        $value = $query instanceof ElementQuery ? $query->one() : $query;

        return GqlHelper::applyDirectives($source, $resolveInfo, $value);
    }

    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $query = static::prepareElementQuery($source, $arguments, $context, $resolveInfo);
        $value = $query instanceof ElementQuery ? $query->all() : $query;

        return GqlHelper::applyDirectives($source, $resolveInfo, $value);
    }

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

        if (GqlHelper::isPublicSchema()) {
            self::_checkPublicCriteria($arguments);
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        if (!GqlHelper::canQueryComments()) {
            return ElementCollection::empty();
        }

        return $query;
    }

    protected static function prepareElementQuery(mixed $source, array $arguments, ?array $context, ResolveInfo $resolveInfo): ElementQuery|ElementCollection
    {
        $query = parent::prepareElementQuery($source, $arguments, $context, $resolveInfo);

        // Arguments on nested `children` and `parent` fields become eager-loading criteria, so they
        // need the same check as the query's own arguments.
        if ($query instanceof ElementQuery && is_array($query->with) && GqlHelper::isPublicSchema()) {
            self::_checkPublicEagerLoadPlans($query->with);
        }

        return $query;
    }

    /**
     * A public schema doesn't return commenters' email addresses, so it can't filter, search or order
     * by them either, which would reveal an address a guess at a time.
     */
    private static function _checkPublicCriteria(array $criteria): void
    {
        if (isset($criteria['email'])) {
            throw new UserError(Craft::t('comments', 'Comments can’t be filtered by email on a public schema.'));
        }

        // `search` is an array when `searchTermOptions` are also given.
        $search = $criteria['search'] ?? null;
        $search = is_array($search) ? ($search['query'] ?? null) : $search;

        if (is_string($search) && preg_match('/\bauthorEmail\s*:/i', $search)) {
            throw new UserError(Craft::t('comments', 'Comments can’t be searched by email on a public schema.'));
        }

        $orderBy = $criteria['orderBy'] ?? null;

        if (is_string($orderBy) && self::_ordersByEmail($orderBy)) {
            throw new UserError(Craft::t('comments', 'Comments can’t be ordered by email on a public schema.'));
        }
    }

    private static function _checkPublicEagerLoadPlans(array $plans): void
    {
        foreach ($plans as $plan) {
            // Only a comment's own structure leads to other comments.
            if (!$plan instanceof EagerLoadPlan || !in_array($plan->handle, ['children', 'parent'], true)) {
                continue;
            }

            self::_checkPublicCriteria($plan->criteria);
            self::_checkPublicEagerLoadPlans($plan->nested);
        }
    }

    private static function _ordersByEmail(string $orderBy): bool
    {
        foreach (explode(',', $orderBy) as $chunk) {
            // Craft only allows `column` or `table.column` with an optional direction, so compare the
            // bare column name.
            $column = strtolower((string)preg_replace('/\s+(asc|desc)$/i', '', trim($chunk)));
            $column = substr($column, (int)strrpos('.' . $column, '.'));

            if ($column === 'email') {
                return true;
            }
        }

        return false;
    }
}
