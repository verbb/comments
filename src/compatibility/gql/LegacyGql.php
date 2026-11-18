<?php
namespace verbb\comments\compatibility\gql;

use verbb\comments\Comments;
use verbb\comments\services\Compatibility;

use craft\gql\types\DateTime;

use GraphQL\Type\Definition\Type;

/**
 * Comments 3's GraphQL schema, in compatibility mode: the structure arguments and fields Comments 3 had
 * from its Craft structure, the `newParentId` mutation argument, and the `flags` field on schemas that
 * can't moderate. Each is marked deprecated in the schema, so tooling flags it too. Comments 3's
 * `userId` and `commentDate` mutation arguments are accepted and ignored, and its clients, which never
 * sent spam protection evidence, can still post without it.
 */
class LegacyGql
{
    // Static Methods
    // =========================================================================

    /**
     * The structure arguments Comments 3's queries took from Craft, answered from each reply's parent and
     * depth by the comment query. GraphQL has no deprecation for arguments, so their descriptions say so.
     */
    public static function queryArguments(): array
    {
        if (!Compatibility::enabled()) {
            return [];
        }

        $deprecated = 'Deprecated: comments are no longer kept in a structure.';

        return [
            'level' => ['name' => 'level', 'type' => Type::int(), 'description' => "{$deprecated} Narrows the results to comments at this level, 1 for top-level comments. Use `depth`, which starts at 0."],
            'hasDescendants' => ['name' => 'hasDescendants', 'type' => Type::boolean(), 'description' => "{$deprecated} Narrows the results to comments with or without replies."],
            'ancestorOf' => ['name' => 'ancestorOf', 'type' => Type::int(), 'description' => "{$deprecated} Narrows the results to the comments above this comment, by its ID."],
            'ancestorDist' => ['name' => 'ancestorDist', 'type' => Type::int(), 'description' => "{$deprecated} Limits `ancestorOf` to this many levels."],
            'descendantOf' => ['name' => 'descendantOf', 'type' => Type::int(), 'description' => "{$deprecated} Narrows the results to the replies beneath this comment, by its ID."],
            'descendantDist' => ['name' => 'descendantDist', 'type' => Type::int(), 'description' => "{$deprecated} Limits `descendantOf` to this many levels."],
            'leaves' => ['name' => 'leaves', 'type' => Type::boolean(), 'description' => "{$deprecated} Narrows the results to comments without replies."],
        ];
    }

    /**
     * Comments 3's `newParentId`, on the mutations that create and save comments.
     */
    public static function mutationArguments(): array
    {
        if (!Compatibility::enabled()) {
            return [];
        }

        return [
            'newParentId' => [
                'name' => 'newParentId',
                'type' => Type::id(),
                'description' => 'Deprecated: use `parentId`. The comment this replies to.',
            ],
            'userId' => [
                'name' => 'userId',
                'type' => Type::id(),
                'description' => 'Deprecated and ignored: a comment’s author is whoever is making the request.',
            ],
            'commentDate' => [
                'name' => 'commentDate',
                'type' => DateTime::getType(),
                'description' => 'Deprecated and ignored: a comment’s date is when it’s posted.',
            ],
        ];
    }

    /**
     * A mutation's arguments without Comments 3's `userId` and `commentDate`, which only ever let a
     * client claim to be someone else or post in the past, so they're ignored.
     */
    public static function withoutIgnoredArguments(array $arguments): array
    {
        foreach (['userId', 'commentDate'] as $argument) {
            if (array_key_exists($argument, $arguments)) {
                Compatibility::deprecated("gql.{$argument}", "The `{$argument}` argument of `createComment` and `saveComment` is ignored, and will be removed. Comments are posted by whoever makes the request, when it's made.");
                unset($arguments[$argument]);
            }
        }

        return $arguments;
    }

    /**
     * Runs a mutation that posts or edits a comment. Comments 3's clients sent no spam protection
     * evidence, so in compatibility mode one without `protection` is checked without the single-use
     * preparation that replay protection needs. Rate limits, keyword filters, Akismet and any challenge
     * provider still apply, so a site with Turnstile or reCAPTCHA needs its client updated.
     */
    public static function write(array $arguments, callable $write): mixed
    {
        if (!Compatibility::enabled() || !empty($arguments['protection'])) {
            return $write();
        }

        Compatibility::deprecated('gql.protection', 'A `createComment` or `saveComment` mutation without a `protection` argument is checked without a preparation, which only compatibility mode allows. Get one from `comments/api/protection` and send it as `protection`.');

        return Comments::$plugin->getProtection()->withoutPreparation($write);
    }

    /**
     * The parent a mutation names, by `parentId` or Comments 3's `newParentId`.
     */
    public static function parentId(array $arguments): ?int
    {
        if (isset($arguments['parentId'])) {
            return (int)$arguments['parentId'];
        }

        if (isset($arguments['newParentId']) && Compatibility::enabled()) {
            Compatibility::deprecated('gql.newParentId', 'The `newParentId` argument of `createComment` has been replaced by `parentId`.');

            return (int)$arguments['newParentId'];
        }

        return null;
    }

    /**
     * Comments 3's structure fields on a comment, and `flags` on a schema that can't moderate, which is
     * null there, as it is for anyone who can't moderate.
     */
    public static function fields(bool $hasFlags): array
    {
        if (!Compatibility::enabled()) {
            return [];
        }

        $fields = [
            'level' => [
                'name' => 'level',
                'type' => Type::int(),
                'description' => 'The comment’s level, 1 for a top-level comment.',
                'deprecationReason' => 'Use `depth`, which is 0 for a top-level comment.',
                'resolve' => static fn(mixed $source): ?int => isset($source->depth) ? (int)$source->depth + 1 : null,
            ],
            'root' => [
                'name' => 'root',
                'type' => Type::int(),
                'description' => 'The ID of the top-level comment this is in.',
                'deprecationReason' => 'Use `rootId`.',
                'resolve' => static fn(mixed $source): ?int => isset($source->rootId) ? (int)$source->rootId : (isset($source->id) ? (int)$source->id : null),
            ],
            'lft' => [
                'name' => 'lft',
                'type' => Type::int(),
                'description' => 'Comments are no longer kept in a structure, so this is null.',
                'deprecationReason' => 'Comments are no longer kept in a structure. Use `parentId`, `rootId` and `depth`.',
                'resolve' => static fn(): ?int => null,
            ],
            'rgt' => [
                'name' => 'rgt',
                'type' => Type::int(),
                'description' => 'Comments are no longer kept in a structure, so this is null.',
                'deprecationReason' => 'Comments are no longer kept in a structure. Use `parentId`, `rootId` and `depth`.',
                'resolve' => static fn(): ?int => null,
            ],
        ];

        if (!$hasFlags) {
            $fields['flags'] = [
                'name' => 'flags',
                'type' => Type::int(),
                'description' => 'The number of open reports on this comment, which is only given to moderators.',
                'deprecationReason' => 'Only a schema that can moderate gives the number of reports. It’s null here.',
            ];
        }

        return $fields;
    }
}
