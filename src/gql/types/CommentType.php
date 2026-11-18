<?php
namespace verbb\comments\gql\types;

use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\resolvers\CommentPresentation;
use verbb\comments\gql\resolvers\CommentResolver;
use verbb\comments\gql\types\generators\CommentGenerator;
use verbb\comments\helpers\Gql as GqlHelper;

use Craft;
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

        // As for `flags`, a public schema never says what a moderator could decide.
        if ($resolveInfo->fieldName === 'commentsData') {
            return CommentPresentation::commentsData($source, !GqlHelper::isPublicSchema());
        }

        // The same people `moderateComment` accepts, so a moderator's session can't be read through a
        // public schema, which needs no token.
        if ($resolveInfo->fieldName === 'flags') {
            $viewer = Comments::$plugin->getService()->getUser();

            if (GqlHelper::isPublicSchema() || !$viewer instanceof User || !Comments::$plugin->getModeration()->canModerateComment($viewer)) {
                return null;
            }
        }

        // A custom field is only read on a schema that can moderate, or where the comment shows it
        // publicly, and a generated field, which can be made from any of them, only on one that can
        // moderate. Fields with their own resolver are checked by `CommentGenerator`.
        if ($this->_isLayoutField($resolveInfo->fieldName)) {
            $formFields = Comments::$plugin->getFormFields();
            $generated = !Craft::$app->getFields()->getLayoutByType(CommentElement::class)->getFieldByHandle($resolveInfo->fieldName);

            if ($generated ? !$formFields->canReadEveryField() : !CommentGenerator::canRead($source, $resolveInfo->fieldName)) {
                return null;
            }
        }

        // `getParent()` only returns an approved parent. A schema that isn't public can read every state,
        // as its `comments` query can, so it gets the parent whatever its status.
        if ($resolveInfo->fieldName === 'parent' && !GqlHelper::isPublicSchema() && $source instanceof CommentElement) {
            return $source->getParentComment();
        }

        $value = parent::resolve($source, $arguments, $context, $resolveInfo);

        if ($resolveInfo->fieldName === 'parent' && GqlHelper::isPublicSchema() && !CommentResolver::isPubliclyReadable($value)) {
            return null;
        }

        return $value;
    }


    // Private Methods
    // =========================================================================

    /**
     * Whether `$name` is one of the Comment field layout's custom or generated fields, rather than one of
     * a comment's own.
     */
    private function _isLayoutField(string $name): bool
    {
        $layout = Craft::$app->getFields()->getLayoutByType(CommentElement::class);

        if ($layout->getFieldByHandle($name)) {
            return true;
        }

        foreach ($layout->getGeneratedFields() as $field) {
            if (($field['handle'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
}
