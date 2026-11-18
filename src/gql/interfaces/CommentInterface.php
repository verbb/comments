<?php
namespace verbb\comments\gql\interfaces;

use verbb\comments\compatibility\gql\LegacyGql;
use verbb\comments\helpers\Gql as CommentsGql;

use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\gql\interfaces\CommentInterface as CommentInterfaceLocal;
use verbb\comments\gql\types\ArrayType;
use verbb\comments\gql\types\CommentAuthorType;
use verbb\comments\gql\types\CommentViewerType;
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
                'description' => 'How the comment was written: `plain` or `rich`.',
            ],
            'contentHtml' => [
                'name' => 'contentHtml',
                'type' => Type::string(),
                'description' => 'The rendered HTML of a rich comment. Null for plain text comments.',
            ],
            'contentDocument' => [
                'name' => 'contentDocument',
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
                'description' => 'The URL of the GIF from GIPHY shown with the comment, if it has one.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The guest’s name. Null for comments by signed-in users. Use `author` for the name the thread shows.',
            ],
            'author' => [
                'name' => 'author',
                'type' => Type::nonNull(CommentAuthorType::getType()),
                'description' => 'The comment’s author as the thread shows them: name, initials, avatar and badges. Needs no access to users.',
            ],
            'viewer' => [
                'name' => 'viewer',
                'type' => Type::nonNull(CommentViewerType::getType()),
                'description' => 'What the person making the request can do with this comment, and how they voted on it.',
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The url the comment was made on.',
            ],
            'commentsData' => [
                'name' => 'commentsData',
                'type' => ArrayType::getType(),
                'description' => 'The comment as the data API describes it to the person asking, with their permissions in `viewer` and, for a moderator on a schema that isn’t public, `moderation`.',
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
        ]), self::getName());
    }

    protected static function getConditionalFields(): array
    {
        $fields = [];

        if (GqlHelper::canQueryUsers()) {
            $fields = array_merge($fields, [
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
            ]);
        }

        // A guest's address, for a private schema allowed to read addresses, as Comments 3 gave every schema (SD-57).
        if (CommentsGql::canReadCommentEmails()) {
            $fields['email'] = [
                'name' => 'email',
                'type' => Type::string(),
                'description' => 'The guest’s email address. Null for comments by signed-in users. Only on a private schema allowed to view commenters’ email addresses.',
            ];
        }

        // Only moderators see that a comment has been reported, so only a schema that can moderate has
        // the count, and it's null unless whoever's asking can moderate too, as for `moderateComment`.
        if (GqlHelper::canSchema('comments', 'moderate')) {
            $fields['flags'] = [
                'name' => 'flags',
                'type' => Type::int(),
                'description' => 'The number of open reports on this comment. Null unless the request is from someone who can moderate comments, on a schema that isn’t public.',
            ];
        }

        // Comments 3's structure fields, and `flags` where it's otherwise left out, in compatibility mode.
        return array_merge($fields, LegacyGql::fields(isset($fields['flags'])));
    }
}
