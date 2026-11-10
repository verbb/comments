<?php
namespace verbb\comments\gql\types;

use verbb\comments\Comments;
use verbb\comments\services\Frontend;

use Craft;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\DateTime;
use craft\helpers\DateTimeHelper;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ConversationType
{
    // Static Methods
    // =========================================================================

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentConversation', static fn() => new ObjectType([
            'name' => 'CommentConversation',
            'fields' => [
                'ownerId' => ['type' => Type::nonNull(Type::int()), 'description' => 'The ID of the element the conversation is on.'],
                'siteId' => ['type' => Type::nonNull(Type::int()), 'description' => 'The ID of the site the conversation is on.'],
                'commentCount' => ['type' => Type::nonNull(Type::int()), 'description' => 'The number of published comments.'],
                'lastActivityAt' => ['type' => DateTime::getType(), 'description' => 'When the latest published comment was made.'],
                'open' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether new comments can be posted.'],
                'interactionMode' => ['type' => Type::nonNull(Type::string()), 'description' => 'How people can vote: `like`, `updown` for upvotes and downvotes, or `none`.'],
                'maxLength' => ['type' => Type::int(), 'description' => 'The most characters a comment can have, or null for no limit.'],
                'guestPolicy' => ['type' => Type::nonNull(self::_guestPolicyType()), 'description' => 'What guests can do here.'],
                'reportReasons' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(self::_reportReasonType()))), 'description' => 'The reasons a report can give, in order. Empty while reporting is off.'],
                'viewer' => ['type' => Type::nonNull(self::_viewerType()), 'description' => 'What the person making the request can do here.'],
            ],
        ]));
    }

    /**
     * Elements with comments turned off return nothing, the same as they show nothing on the site.
     */
    public static function resolve(mixed $source, array $arguments): ?array
    {
        $ownerId = (int)$arguments['ownerId'];
        $siteId = (int)($arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id);
        $conversations = Comments::$plugin->getConversations();
        $settings = Comments::$plugin->getSettings();

        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if (!$owner || !$conversations->isOwnerPublic($owner)) {
            return null;
        }

        $config = $conversations->getConfigForOwner($ownerId, $siteId);

        if (!$config->enabled) {
            return null;
        }

        $summary = $conversations->getSummaryForOwner($ownerId, $siteId);
        $mode = $settings->resolveInteractionMode();

        return [
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'commentCount' => $summary?->commentCount ?? 0,
            'lastActivityAt' => $summary?->lastActivityAt ? DateTimeHelper::toDateTime($summary->lastActivityAt) : null,
            'open' => $config->isCommentingEnabled(),
            'interactionMode' => $mode->value,
            'maxLength' => $settings->securityMaxLength ? (int)$settings->securityMaxLength : null,
            'guestPolicy' => [
                'allowed' => $config->allowGuests,
                // The same rules that decide whether the default form asks a guest for these.
                'nameAndEmail' => match (true) {
                    (bool)$settings->guestRequireEmailName => 'required',
                    (bool)$settings->guestShowEmailName => 'optional',
                    default => 'none',
                },
                'canVote' => $mode->isEnabled() && $settings->allowGuestVoting,
                'canReport' => $settings->allowFlagging && $settings->allowGuestFlagging,
            ],
            'reportReasons' => $settings->allowFlagging ? Frontend::reportReasons() : [],
            // Only worked out when asked for, as it looks at the person making the request.
            'viewer' => static fn(): array => self::_viewer($owner),
        ];
    }

    private static function _viewer(ElementInterface $owner): array
    {
        $settings = Comments::$plugin->getSettings();
        $user = Comments::$plugin->getService()->getUser();
        $isGuest = !$user instanceof User;
        $mode = $settings->resolveInteractionMode();

        return [
            'isGuest' => $isGuest,
            // The same answer the thread gives, from closing dates to guests and the per-person limit.
            'canComment' => (bool)$settings->commentingAvailable($owner)['permission'],
            'canVote' => $mode->isEnabled() && (!$isGuest || $settings->allowGuestVoting),
            'canReport' => $settings->allowFlagging && (!$isGuest || $settings->allowGuestFlagging),
        ];
    }

    private static function _viewerType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentConversationViewer', static fn() => new ObjectType([
            'name' => 'CommentConversationViewer',
            'description' => 'What the person making the request can do in a conversation. Each comment’s `viewer` says what they can do with it.',
            'fields' => [
                'isGuest' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they’re making the request without an account.'],
                'canComment' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can post a new comment here now.'],
                'canVote' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can vote on published comments here.'],
                'canReport' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether they can report published comments here.'],
            ],
        ]));
    }

    private static function _guestPolicyType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentGuestPolicy', static fn() => new ObjectType([
            'name' => 'CommentGuestPolicy',
            'fields' => [
                'allowed' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether guests can comment here.'],
                'nameAndEmail' => ['type' => Type::nonNull(Type::string()), 'description' => 'Whether a guest gives their name and email: `required`, `optional` or `none`.'],
                'canVote' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether guests can vote.'],
                'canReport' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether guests can report comments.'],
            ],
        ]));
    }

    private static function _reportReasonType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentReportReason', static fn() => new ObjectType([
            'name' => 'CommentReportReason',
            'fields' => [
                'value' => ['type' => Type::nonNull(Type::string()), 'description' => 'What to send as `reason` to `flagComment`.'],
                'label' => ['type' => Type::nonNull(Type::string()), 'description' => 'The reason’s label, translated for the site.'],
                'description' => ['type' => Type::string(), 'description' => 'Its explanation, if it has one.'],
            ],
        ]));
    }
}
