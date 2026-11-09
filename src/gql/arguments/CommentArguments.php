<?php
namespace verbb\comments\gql\arguments;

use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;

use GraphQL\Type\Definition\Type;

class CommentArguments extends ElementArguments
{
    // Static Methods
    // =========================================================================

    public static function getArguments(): array
    {
        $arguments = parent::getArguments();

        // Comments 4 uses adjacency columns, not Craft Structures. ElementArguments exposes
        // Structure-only filters generically, so remove them from the public Comment schema.
        foreach ([
            'structureId',
            'level',
            'hasDescendants',
            'ancestorOf',
            'ancestorDist',
            'descendantOf',
            'descendantDist',
            'leaves',
            'nextSiblingOf',
            'prevSiblingOf',
            'positionedAfter',
            'positionedBefore',
        ] as $argument) {
            unset($arguments[$argument]);
        }

        return array_merge($arguments, [
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the owner element the comment was made on, per the owners’ IDs.',
            ],
            'userId' => [
                'name' => 'userId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the comment’s authors.',
            ],
            'commentDate' => [
                'name' => 'commentDate',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ commented date.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ full name.',
            ],
            'comment' => [
                'name' => 'comment',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ actual comment text.',
            ],
            'conversationId' => [
                'name' => 'conversationId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results to a conversation.',
            ],
            'identityId' => [
                'name' => 'identityId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on canonical commenter identities.',
            ],
            'moderationState' => [
                'name' => 'moderationState',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results by moderation state.',
            ],
            'rootId' => [
                'name' => 'rootId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results to an adjacency thread root.',
            ],
            'adjacencyParentId' => [
                'name' => 'adjacencyParentId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results to direct replies of an adjacency parent.',
            ],
        ]);
    }
}
