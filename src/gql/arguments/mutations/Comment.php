<?php
namespace verbb\comments\gql\arguments\mutations;

use verbb\comments\compatibility\gql\LegacyGql;

use craft\gql\base\ElementMutationArguments;

use GraphQL\Type\Definition\Type;

class Comment extends ElementMutationArguments
{
    // Static Methods
    // =========================================================================

    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), LegacyGql::mutationArguments(), [
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
            'parentUid' => [
                'name' => 'parentUid',
                'type' => Type::string(),
                'description' => 'Parent comment UID, as the data API names it.',
            ],
            'notifyReplies' => [
                'name' => 'notifyReplies',
                'type' => Type::boolean(),
                'description' => 'A guest asks to be emailed about replies, which starts with confirming their address.',
            ],
            'operationId' => [
                'name' => 'operationId',
                'type' => Type::string(),
                'description' => 'A unique ID for this change. A repeat with the same ID within ten minutes returns the first result rather than acting again.',
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
                'description' => 'Authoring format: `plain` or `rich`.',
            ],
            'contentDocument' => [
                'name' => 'contentDocument',
                'type' => Type::string(),
                'description' => 'Canonical Editor JSON when creating or updating rich comments.',
            ],
        ]);
    }
}
