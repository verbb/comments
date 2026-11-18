<?php
namespace verbb\comments\gql\resolvers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use GraphQL\Deferred;

/**
 * The `author` and `viewer` fields of GraphQL comments. Each comment in a response asks for its own,
 * but the answers are worked out for every comment waiting at the time, so a page of comments
 * costs a few queries rather than a few per comment.
 *
 * Results are only kept until each comment that asked has read its own. The viewer can change between
 * queries in one PHP process, such as in tests or a queue worker, so nothing outlives the response.
 */
class CommentPresentation
{
    // Static Methods
    // =========================================================================

    public static function author(Comment $comment): Deferred
    {
        return self::_defer('author', $comment);
    }

    public static function viewer(Comment $comment): Deferred
    {
        return self::_defer('viewer', $comment);
    }

    /**
     * The data API's description of a comment, for the person asking. On a public schema it never says
     * what a moderator could decide.
     */
    public static function commentsData(Comment $comment, bool $withModeration): Deferred
    {
        return self::_defer($withModeration ? 'commentsData' : 'commentsDataPublic', $comment);
    }

    private static function _defer(string $kind, Comment $comment): Deferred
    {
        $id = (int)$comment->id;
        self::$_pending[$kind][$id] = $comment;
        self::$_waiting[$kind][$id] = (self::$_waiting[$kind][$id] ?? 0) + 1;

        return new Deferred(static function() use ($kind, $id): ?array {
            if (!array_key_exists($id, self::$_loaded[$kind] ?? [])) {
                $batch = self::$_pending[$kind] ?? [];
                self::$_pending[$kind] = [];
                $frontend = Comments::$plugin->getFrontend();
                $values = match ($kind) {
                    'author' => $frontend->publicAuthorsFor(array_values($batch)),
                    'viewer' => $frontend->viewerStatesFor(array_values($batch)),
                    default => Comments::$plugin->getApi()->commentDataFor(array_values($batch), $kind === 'commentsData'),
                };

                self::$_loaded[$kind] = (self::$_loaded[$kind] ?? []) + $values;
            }

            $value = self::$_loaded[$kind][$id] ?? null;

            // The same comment can appear more than once in a response, such as a parent of two replies.
            if (--self::$_waiting[$kind][$id] <= 0) {
                unset(self::$_loaded[$kind][$id], self::$_waiting[$kind][$id]);
            }

            return $value;
        });
    }


    // Properties
    // =========================================================================

    private static array $_pending = [];
    private static array $_loaded = [];
    private static array $_waiting = [];
}
