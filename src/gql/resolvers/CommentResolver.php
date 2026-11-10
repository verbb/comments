<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\elements\Comment;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\Gql as GqlHelper;

use Craft;
use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use craft\gql\base\ElementResolver;

use GraphQL\Error\UserError;

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

        // Craft accepts any column name for `orderBy`. Sorting by one the schema can't read would
        // still reveal it, a comparison at a time, by placing a comment with a known value among the
        // others, so these stay as admin-only as the values themselves.
        if (!CommentsHelper::canViewAddresses() && self::_ordersBy((string)($arguments['orderBy'] ?? ''), self::PRIVATE_COLUMNS)) {
            throw new UserError(Craft::t('comments', 'Comments can’t be ordered by that.'));
        }

        // A guest's identity is the same on every thread they comment on, so a public schema can't
        // filter or sort by it, or a client could gather everything one guest has written.
        if (GqlHelper::isPublicSchema()) {
            if (isset($arguments['identityId'])) {
                throw new UserError(Craft::t('comments', 'Comments can’t be filtered by that.'));
            }

            if (self::_ordersBy((string)($arguments['orderBy'] ?? ''), ['identityid'])) {
                throw new UserError(Craft::t('comments', 'Comments can’t be ordered by that.'));
            }
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

    /**
     * Whether an `orderBy` value names any of these columns, given in lowercase, with or without its
     * table.
     */
    private static function _ordersBy(string $orderBy, array $columns): bool
    {
        foreach (explode(',', $orderBy) as $chunk) {
            $column = strtolower((string)preg_replace('/\s+(asc|desc)$/i', '', trim($chunk)));
            $column = str_replace(['`', '"', '[', ']'], '', $column);
            $column = substr($column, (int)strrpos('.' . $column, '.'));

            if (in_array($column, $columns, true)) {
                return true;
            }
        }

        return false;
    }


    // Constants
    // =========================================================================

    // A commenter's address, network and browser, and what spam protection recorded about them.
    private const PRIVATE_COLUMNS = ['email', 'ipaddress', 'useragent', 'protection'];
}
