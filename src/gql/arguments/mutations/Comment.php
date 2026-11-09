<?php
namespace verbb\comments\gql\arguments\mutations;

use craft\gql\base\ElementMutationArguments;

use GraphQL\Type\Definition\Type;

class Comment extends ElementMutationArguments
{
    // Static Methods
    // =========================================================================

    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'protection' => [
                'name' => 'protection',
                'type' => \verbb\comments\gql\types\ProtectionEvidence::getType(),
                'description' => 'Version 1 Protect evidence from the Comments protection preparation endpoint.',
            ],
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::id(),
                'description' => 'The ID of the element that owns the comment.',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::id(),
                'description' => 'Site ID the comment belongs to.',
            ],
            'parentId' => [
                'name' => 'parentId',
                'type' => Type::id(),
                'description' => 'Parent comment ID.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The full name for the comment‘s author.',
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::string(),
                'description' => 'The email for the comment‘s author.',
            ],
            'comment' => [
                'name' => 'comment',
                'type' => Type::string(),
                'description' => 'The actual comment text.',
            ],
            'contentFormat' => [
                'name' => 'contentFormat',
                'type' => Type::string(),
                'description' => 'Authoring format: `plain` or `editor`.',
            ],
            'editorContent' => [
                'name' => 'editorContent',
                'type' => Type::string(),
                'description' => 'Canonical Editor JSON when creating or updating rich comments.',
            ],
        ]);
    }
}
