<?php
namespace verbb\comments\gql\arguments\mutations;

use craft\gql\base\ElementMutationArguments;
use craft\gql\types\DateTime;

use GraphQL\Type\Definition\Type;

class Comment extends ElementMutationArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::id(),
                'description' => 'The ID of the element that owns the comment.'
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::id(),
                'description' => 'Site ID the comment belongs to.',
            ],
            'newParentId' => [
                'name' => 'newParentId',
                'type' => Type::id(),
                'description' => 'Parent comment ID.',
            ],
            'userId' => [
                'name' => 'userId',
                'type' => Type::id(),
                'description' => 'The user ID of the comment’s author.',
            ],
            'commentDate' => [
                'name' => 'commentDate',
                'type' => DateTime::getType(),
                'description' => 'The comment\'s post date.'
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The full name for the comment\'s author.'
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::string(),
                'description' => 'The email for the comment\'s author.'
            ],
            'comment' => [
                'name' => 'comment',
                'type' => Type::string(),
                'description' => 'The actual comment text.'
            ],
        ]);
    }
}
