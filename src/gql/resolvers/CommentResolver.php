<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\Gql as GqlHelper;

use Craft;
use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use craft\fields\BaseRelationField;
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

        // A schema that can't moderate reads only the custom field values comments show publicly. A field
        // shown now can still hold values entered while it was only collected, so such a schema can't
        // order, search or filter comments by any custom field: placing a comment among others, or
        // finding it, would reveal a value a comparison at a time. Ordering is held to a fixed list of
        // columns, so nothing else on the table, such as the custom field JSON, can be reached either.
        $formFields = Comments::$plugin->getFormFields();

        if (!$formFields->canReadEveryField()) {
            // A private schema, used by a site's own server, may also order by commenter.
            $columns = GqlHelper::isPublicSchema() ? self::PUBLIC_ORDER_COLUMNS : [...self::PUBLIC_ORDER_COLUMNS, 'identityid', 'userid'];

            if (!CommentsHelper::canViewAddresses() && !self::_ordersOnlyBy((string)($arguments['orderBy'] ?? ''), $columns)) {
                throw new UserError(Craft::t('comments', 'Comments can’t be ordered by that.'));
            }

            if (self::_usesRelations($arguments) && self::_hasRelationField()) {
                throw new UserError(Craft::t('comments', 'Comments can’t be filtered by related elements here.'));
            }

            if ($query instanceof CommentQuery) {
                $query->withPrivateFields = false;
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


    /**
     * Whether a query's arguments relate it to other elements, which Comments' relation fields are part of.
     */
    private static function _usesRelations(array $arguments): bool
    {
        foreach ($arguments as $argument => $value) {
            if (!empty($value) && (str_starts_with((string)$argument, 'relatedTo') || str_starts_with((string)$argument, 'notRelatedTo'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the Comment layout has a relation field, whose related elements a filter would reveal.
     */
    private static function _hasRelationField(): bool
    {
        foreach (Craft::$app->getFields()->getLayoutByType(Comment::class)->getCustomFields() as $field) {
            if ($field instanceof BaseRelationField) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an `orderBy` value names only these columns, given in lowercase, with or without a table
     * and a direction. Anything else, such as an expression or a JSON column, is refused.
     */
    private static function _ordersOnlyBy(string $orderBy, array $columns): bool
    {
        if (trim($orderBy) === '') {
            return true;
        }

        foreach (explode(',', $orderBy) as $chunk) {
            $column = strtolower(trim((string)preg_replace('/\s+(asc|desc)$/i', '', trim($chunk))));
            $column = str_replace(['`', '"', '[', ']'], '', $column);

            if (!preg_match('/^([a-z0-9_]+\.)?[a-z0-9_]+$/', $column)) {
                return false;
            }

            if (!in_array(substr($column, (int)strrpos('.' . $column, '.')), $columns, true)) {
                return false;
            }
        }

        return true;
    }


    // Constants
    // =========================================================================

    // What a schema that can't moderate may order comments by.
    private const PUBLIC_ORDER_COLUMNS = ['id', 'uid', 'commentdate', 'datecreated', 'dateupdated', 'depth', 'rootid', 'parentid', 'ownerid', 'ownersiteid', 'conversationid', 'votecount', 'score', 'sortorder'];

    // A commenter's address, network and browser, and what spam protection recorded about them.
    private const PRIVATE_COLUMNS = ['email', 'ipaddress', 'useragent', 'protection'];
}
