<?php
namespace verbb\comments\gql\types;

use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class ProtectionEvidence
{
    // Static Methods
    // =========================================================================

    public static function getType(): InputObjectType
    {
        $challenge = GqlEntityRegistry::getEntity('CommentsProtectionChallenges') ?: GqlEntityRegistry::createEntity('CommentsProtectionChallenges', new InputObjectType([
            'name' => 'CommentsProtectionChallenges',
            'fields' => ['turnstile' => Type::string(), 'recaptchaV3' => Type::string()],
        ]));

        return GqlEntityRegistry::getEntity('CommentsProtectionEvidence') ?: GqlEntityRegistry::createEntity('CommentsProtectionEvidence', new InputObjectType([
            'name' => 'CommentsProtectionEvidence',
            'fields' => ['version' => Type::nonNull(Type::int()), 'preparation' => Type::nonNull(Type::string()), 'challenges' => $challenge],
        ]));
    }
}
