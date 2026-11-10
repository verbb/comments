<?php
namespace verbb\comments\gql\types;

use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Who wrote a comment, as the thread shows them to anyone. It's available on every schema that can
 * read comments, and never carries more than the thread does.
 */
class CommentAuthorType
{
    // Static Methods
    // =========================================================================

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentAuthor', static fn() => new ObjectType([
            'name' => 'CommentAuthor',
            'description' => 'A comment’s author as the thread shows them publicly. Use `user` for the account itself, on schemas that can read users.',
            'fields' => [
                'name' => ['type' => Type::nonNull(Type::string()), 'description' => 'The name shown on the comment. A guest without a name is “Guest”, and an account never shows its email address.'],
                'initials' => ['type' => Type::nonNull(Type::string()), 'description' => 'Up to two initials from the name, for an avatar without a photo.'],
                'avatarUrl' => ['type' => Type::string(), 'description' => 'The author’s photo, or the placeholder avatar. Null when avatars are turned off or there’s neither.'],
                'isGuest' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether the comment was made without an account.'],
                'badges' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(self::badgeType()))), 'description' => 'The Author and Staff badges the comment carries.'],
            ],
        ]));
    }

    public static function badgeType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentBadge', static fn() => new ObjectType([
            'name' => 'CommentBadge',
            'fields' => [
                'type' => ['type' => Type::nonNull(Type::string()), 'description' => '`owner` for the element’s author, or `staff`.'],
                'label' => ['type' => Type::nonNull(Type::string()), 'description' => 'The badge’s label, from the badge settings.'],
            ],
        ]));
    }
}
