<?php
namespace verbb\comments\gql\arguments;

use craft\gql\base\StructureElementArguments;
use craft\gql\types\QueryArgument;

use GraphQL\Type\Definition\Type;

class CommentArguments extends StructureElementArguments
{
    // Public Methods
    // =========================================================================

    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the owner element the comment was made on, per the owners’ IDs.'
            ],
            'userId' => [
                'name' => 'userId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the comment’s authors.'
            ],
            'commentDate' => [
                'name' => 'commentDate',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ commented date.'
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ full name.'
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ email.'
            ],
            'comment' => [
                'name' => 'comment',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the comments’ actual comment text.'
            ],
        ]);
    }
}
