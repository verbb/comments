<?php
namespace verbb\comments\gql\types;

use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * What the person making the request can do with a comment. The thread's own buttons come from the
 * same rules, so a headless front end can offer the same actions and leave out the rest.
 */
class CommentViewerType
{
    // Static Methods
    // =========================================================================

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentViewer', static fn() => new ObjectType([
            'name' => 'CommentViewer',
            'description' => 'What the person making the request can do with this comment.',
            'fields' => [
                'canReply' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can reply to it.'],
                'canEdit' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can change it with `saveComment`, as its author within the Author Edit Window.'],
                'canDelete' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can delete it with `deleteComment`, as its author.'],
                'canVote' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can upvote it, or like it.'],
                'canDownvote' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can downvote it, which needs the Upvotes and downvotes interaction mode.'],
                'canReport' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can report it.'],
                'vote' => ['type' => Type::string(), 'description' => 'How they voted on it: `upvote`, `downvote`, or null if they haven’t.'],
            ],
        ]));
    }
}
