<?php
namespace verbb\comments\gql\interfaces;

use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\gql\interfaces\CommentInterface as CommentInterfaceLocal;
use verbb\comments\gql\types\generators\CommentGenerator;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\interfaces\elements\User;
use craft\gql\types\DateTime;
use craft\helpers\Gql as GqlHelper;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class CommentInterface extends Element
{
    // Static Methods
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
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), self::getConditionalFields(), [
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::int(),
                'description' => 'The ID of the element that owns the comment.',
            ],
            'commentDate' => [
                'name' => 'commentDate',
                'type' => DateTime::getType(),
                'description' => 'The comment‘s post date.',
            ],
            'comment' => [
                'name' => 'comment',
                'type' => Type::string(),
                'description' => 'The actual comment text.',
            ],
            'contentFormat' => [
                'name' => 'contentFormat',
                'type' => Type::string(),
                'description' => 'How the comment was written: `plain` or `editor`.',
            ],
            'contentHtml' => [
                'name' => 'contentHtml',
                'type' => Type::string(),
                'description' => 'The rendered HTML of a rich comment. Null for plain text comments.',
            ],
            'editorContent' => [
                'name' => 'editorContent',
                'type' => Type::string(),
                'description' => 'The Editor JSON of a rich comment, to edit it. Null for plain text comments.',
            ],
            'editedAt' => [
                'name' => 'editedAt',
                'type' => DateTime::getType(),
                'description' => 'When the comment’s content last changed after it was posted.',
            ],
            'moderationState' => [
                'name' => 'moderationState',
                'type' => Type::string(),
                'description' => 'The comment’s moderation state. Public schemas only ever return `approved` comments.',
            ],
            'rootId' => [
                'name' => 'rootId',
                'type' => Type::int(),
                'description' => 'The ID of the comment at the top of this comment’s thread.',
            ],
            'depth' => [
                'name' => 'depth',
                'type' => Type::int(),
                'description' => 'How many replies deep the comment is. Top-level comments are 0.',
            ],
            'gifUrl' => [
                'name' => 'gifUrl',
                'type' => Type::string(),
                'description' => 'The URL of the GIF on a comment from before GIF search was removed.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The full name for the comment‘s author.',
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The url the comment was made on.',
            ],
            'children' => [
                'name' => 'children',
                'args' => CommentArguments::getArguments(),
                'type' => Type::listOf(CommentInterfaceLocal::getType()),
                'description' => 'The comment’s children. Accepts the same arguments as the `comments` query.',
            ],
            'parent' => [
                'name' => 'parent',
                'type' => CommentInterfaceLocal::getType(),
                'description' => 'The comment’s parent.',
            ],
            'votes' => [
                'name' => 'votes',
                'type' => Type::int(),
                'description' => 'The number of total votes for this comment.',
            ],
            'upvotes' => [
                'name' => 'upvotes',
                'type' => Type::int(),
                'description' => 'The number of upvotes for this comment.',
            ],
            'downvotes' => [
                'name' => 'downvotes',
                'type' => Type::int(),
                'description' => 'The number of downvotes for this comment.',
            ],
            'flags' => [
                'name' => 'flags',
                'type' => Type::int(),
                'description' => 'The number of flags for this comment.',
            ],
        ]), self::getName());
    }

    protected static function getConditionalFields(): array
    {
        if (GqlHelper::canQueryUsers()) {
            return [
                'userId' => [
                    'name' => 'userId',
                    'type' => Type::int(),
                    'description' => 'The ID of the author of this comment.',
                ],
                'user' => [
                    'name' => 'user',
                    'type' => User::getType(),
                    'description' => 'The comment‘s author.',
                ],
            ];
        }

        return [];
    }
}
