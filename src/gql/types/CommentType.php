<?php
namespace verbb\comments\gql\types;

use verbb\comments\Comments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\resolvers\CommentPresentation;
use verbb\comments\gql\resolvers\CommentResolver;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\elements\User;
use craft\gql\types\elements\Element;

use GraphQL\Type\Definition\ResolveInfo;

class CommentType extends Element
{
    // Public Methods
    // =========================================================================

    public function __construct(array $config)
    {
        $config['interfaces'] = [
            CommentInterface::getType(),
        ];

        parent::__construct($config);
    }


    // Protected Methods
    // =========================================================================

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        // The hierarchy accessors load every state, so route them through the same public rules.
        if ($resolveInfo->fieldName === 'children') {
            return CommentResolver::resolve($source, $arguments, $context, $resolveInfo);
        }

        if ($resolveInfo->fieldName === 'author') {
            return CommentPresentation::author($source);
        }

        if ($resolveInfo->fieldName === 'viewer') {
            return CommentPresentation::viewer($source);
        }

        // The same people `moderateComment` accepts, so a moderator's session can't be read through a
        // public schema, which needs no token.
        if ($resolveInfo->fieldName === 'flags') {
            $viewer = Comments::$plugin->getService()->getUser();

            if (GqlHelper::isPublicSchema() || !$viewer instanceof User || !Comments::$plugin->getModeration()->canModerateComment($viewer)) {
                return null;
            }
        }

        $value = parent::resolve($source, $arguments, $context, $resolveInfo);

        if ($resolveInfo->fieldName === 'parent' && GqlHelper::isPublicSchema() && !CommentResolver::isPubliclyReadable($value)) {
            return null;
        }

        return $value;
    }
}
