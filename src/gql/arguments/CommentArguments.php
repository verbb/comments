<?php
namespace verbb\comments\gql\arguments;

use verbb\comments\compatibility\gql\LegacyGql;
use verbb\comments\helpers\Gql as CommentsGql;

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

        // A guest's address, for a private schema allowed to read addresses (SD-57).
        if (CommentsGql::canReadCommentEmails()) {
            $arguments['email'] = [
                'name' => 'email',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on guests’ email addresses. Only on a private schema allowed to view commenters’ email addresses.',
            ];
        }

        // Comments 3's structure arguments, in compatibility mode, answered from parents and depth.
        return array_merge($arguments, LegacyGql::queryArguments(), [
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
                'description' => 'Narrows the query results to the comments under these top-level comments, and the top-level comments themselves.',
            ],
            'parentId' => [
                'name' => 'parentId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results to the direct replies of these comments. Pass `":empty:"` for top-level comments.',
            ],
        ]);
    }
}
