<?php
namespace verbb\comments\gql\interfaces;

use verbb\comments\elements\Comment;
use verbb\comments\gql\types\generators\CommentGenerator;
use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\gql\interfaces\CommentInterface as CommentInterfaceLocal;

use craft\gql\interfaces\elements\User;
use craft\gql\interfaces\Structure;
use craft\gql\types\DateTime;
use craft\gql\TypeManager;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class CommentInterface extends Structure
{
    // Public Methods
    // =========================================================================

    public static function getTypeGenerator(): string
    {
        return CommentGenerator::class;
    }

    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all comments.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        CommentGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'CommentInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return TypeManager::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), self::getConditionalFields(), [
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::int(),
                'description' => 'The ID of the element that owns the comment.'
            ],
            'commentDate' => [
                'name' => 'commentDate',
                'type' => DateTime::getType(),
                'description' => 'The comment\'s post date.'
            ],
            'comment' => [
                'name' => 'comment',
                'type' => Type::string(),
                'description' => 'The actual comment text.'
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
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The url the comment was made on.'
            ],
            'children' => [
                'name' => 'children',
                'args' => CommentArguments::getArguments(),
                'type' => Type::listOf(CommentInterfaceLocal::getType()),
                'description' => 'The comment’s children. Accepts the same arguments as the `comments` query.'
            ],
            'parent' => [
                'name' => 'parent',
                'type' => CommentInterfaceLocal::getType(),
                'description' => 'The comment’s parent.'
            ],
            'votes' => [
                'name' => 'votes',
                'type' => Type::int(),
                'description' => 'The number of total votes for this comment.'
            ],
            'upvotes' => [
                'name' => 'upvotes',
                'type' => Type::int(),
                'description' => 'The number of upvotes for this comment.'
            ],
            'downvotes' => [
                'name' => 'downvotes',
                'type' => Type::int(),
                'description' => 'The number of downvotes for this comment.'
            ],
            'flags' => [
                'name' => 'flags',
                'type' => Type::int(),
                'description' => 'The number of flags for this comment.'
            ],
        ]), self::getName());
    }

    protected static function getConditionalFields(): array
    {
        if (Gql::canQueryUsers()) {
            return [
                'userId' => [
                    'name' => 'userId',
                    'type' => Type::int(),
                    'description' => 'The ID of the author of this comment.'
                ],
                'user' => [
                    'name' => 'user',
                    'type' => User::getType(),
                    'description' => 'The comment\'s author.'
                ],
            ];
        }

        return [];
    }
}
