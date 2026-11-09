<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\IdentityType;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\models\Settings;
use verbb\comments\queue\jobs\SendEmailConfirmation;
use verbb\comments\records\Conversation as ConversationRecord;
use verbb\comments\records\Identity as IdentityRecord;
use verbb\comments\records\Subscription as SubscriptionRecord;
use verbb\comments\services\Protection;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use yii\base\UserException;
use yii\db\IntegrityException;
use yii\validators\EmailValidator;

use DateTime;
use InvalidArgumentException;

/**
 * Conversation / reply subscriptions with durable subscribed boolean (v3 opt-out preserved).
 */
class Subscriptions extends Component
{
    // Constants
    // =========================================================================

    public const PURPOSE_ALL = 'all';
    public const PURPOSE_CONVERSATION = 'conversation';
    public const PURPOSE_CONFIRM = 'confirm';

    public const OUTCOME_SUBSCRIBED = 'subscribed';
    public const OUTCOME_UNSUBSCRIBED = 'unsubscribed';
    public const OUTCOME_CONFIRMING = 'confirming';

    private const TOKEN_PREFIX = 'comments-email:';
    private const CONFIRMATION_LIFETIME = 7 * 86400;
    private const CONFIRMATION_INTERVAL = 600;

    // Rate limits are per network, which can be spoofed on a site without `trustedHosts` configured.
    private const CONFIRMATION_SITE_LIMIT = 200;


    // Public Methods
    // =========================================================================

    /**
     * Subscribe or unsubscribe whoever is making this request. A guest can stop email straight away, but
     * has to prove an address is theirs before anything is sent to it.
     *
     * Returns the `OUTCOME_*` constant for what actually happened.
     */
    public function setConversationSubscription(int $ownerId, int $siteId, bool $subscribed, ?string $email = null): string
    {
        $plugin = CommentsPlugin::$plugin;
        $user = Craft::$app->getUser()->getIdentity();

        $owner = $subscribed ? Craft::$app->getElements()->getElementById($ownerId, null, $siteId) : null;

        if ($subscribed && (
            !$owner
            || !$plugin->getConversations()->isOwnerPublic($owner)
            || !$plugin->getConversations()->getConfigForOwner($ownerId, $siteId)->enabled
        )) {
            throw new UserException(Craft::t('comments', 'Comments are unavailable for this element.'));
        }

        if ($user) {
            $conversation = $plugin->getConversations()->getOrCreateForOwner($ownerId, $siteId);
            $identity = $plugin->getIdentities()->getOrCreateForUser($user);

            if ($subscribed) {
                $this->subscribeToConversation((int)$conversation->id, (int)$identity->id);

                return self::OUTCOME_SUBSCRIBED;
            }

            $this->unsubscribe((int)$conversation->id, (int)$identity->id);

            return self::OUTCOME_UNSUBSCRIBED;
        }

        $identity = $plugin->getIdentities()->findGuestForRequest();

        if (!$subscribed) {
            $conversation = $plugin->getConversations()->findForOwner($ownerId, $siteId);

            if ($identity && $conversation) {
                $this->unsubscribe((int)$conversation->id, (int)$identity->id);
            }

            return self::OUTCOME_UNSUBSCRIBED;
        }

        if (!$plugin->getConversations()->getConfigForOwner($ownerId, $siteId)->allowGuests) {
            throw new UserException(Craft::t('comments', 'Must be logged in to subscribe.'));
        }

        $plugin->getProtection()->limitEngagement(Protection::ENGAGEMENT_SUBSCRIPTION);

        $email = trim((string)$email);
        $conversation = $plugin->getConversations()->getOrCreateForOwner($ownerId, $siteId);

        if ($identity?->verifiedAt && ($email === '' || strcasecmp($email, (string)$identity->email) === 0)) {
            $this->subscribeToConversation((int)$conversation->id, (int)$identity->id);

            return self::OUTCOME_SUBSCRIBED;
        }

        if (!(new EmailValidator())->validate($email)) {
            throw new UserException(Craft::t('comments', 'Enter a valid email address to subscribe.'));
        }

        $identity = $plugin->getIdentities()->resolveForCurrentRequest(null, $email);
        $this->requestConfirmation($identity, (int)$conversation->id, $siteId);

        return self::OUTCOME_CONFIRMING;
    }

    /**
     * Email a guest a link to confirm their address. The visitor sees the same result whether or not
     * this sends, so nothing here can be used to find out which addresses are registered.
     */
    public function requestConfirmation(IdentityRecord $identity, ?int $conversationId, int $siteId): void
    {
        $email = (string)$identity->email;

        if (!$this->canConfirmAddress($identity)) {
            return;
        }

        // Stops an address being flooded, by any number of visitors each with their own identity.
        $recentlySent = IdentityRecord::find()
            ->where(['lower([[email]])' => strtolower($email)])
            ->andWhere(['>', 'emailConfirmationSentAt', Db::prepareDateForDb(new DateTime('-' . self::CONFIRMATION_INTERVAL . ' seconds'))])
            ->exists();

        if ($recentlySent) {
            return;
        }

        $sentThisHour = (int)IdentityRecord::find()
            ->where(['>', 'emailConfirmationSentAt', Db::prepareDateForDb(new DateTime('-1 hour'))])
            ->count();

        if ($sentThisHour >= self::CONFIRMATION_SITE_LIMIT) {
            Craft::warning('Comment email confirmations are being held back: the hourly limit has been reached.', __METHOD__);

            return;
        }

        $identity->emailConfirmationSentAt = Db::prepareDateForDb(new DateTime());
        $identity->save(false);

        Craft::$app->getQueue()->push(new SendEmailConfirmation([
            'identityId' => (int)$identity->id,
            'email' => $email,
            'conversationId' => $conversationId,
            'siteId' => $siteId,
        ]));
    }

    /**
     * Never a registered user's address, and never one its owner has stopped through somebody else's
     * identity. A guest can still ask again for an address they stopped themselves.
     */
    public function canConfirmAddress(IdentityRecord $identity): bool
    {
        $email = (string)$identity->email;

        if ($email === '' || User::find()->email(Db::escapeParam($email))->status(null)->exists()) {
            return false;
        }

        return !IdentityRecord::find()
            ->where(['lower([[email]])' => strtolower($email)])
            ->andWhere(['not', ['id' => $identity->id]])
            ->andWhere(['not', ['emailOptOutAt' => null]])
            ->exists();
    }

    /**
     * A link for an email to do one thing. It can only ever reduce what's sent unless it's a
     * confirmation, which is tied to the address it went to and expires.
     */
    public function managementUrl(string $purpose, IdentityRecord $identity, int $siteId, ?int $conversationId = null): string
    {
        $conversation = $conversationId ? CommentsPlugin::$plugin->getConversations()->getById($conversationId) : null;

        $payload = [
            'p' => $purpose,
            'i' => $identity->uid,
            'c' => $conversation?->uid,
        ];

        if ($purpose === self::PURPOSE_CONFIRM) {
            $payload['e'] = $this->_emailHash((string)$identity->email);
            $payload['x'] = time() + self::CONFIRMATION_LIFETIME;
        }

        $token = Craft::$app->getSecurity()->hashData(self::TOKEN_PREFIX . Json::encode($payload));

        return UrlHelper::siteUrl('comments/manage', [
            // Craft keeps `token` for its own route tokens, and rejects the request if it doesn't recognise one.
            'emailToken' => rtrim(strtr(base64_encode($token), '+/', '-_'), '='),
        ], null, $siteId);
    }

    public function readManagementToken(string $token): ?array
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        $data = $decoded !== false && $decoded !== '' ? Craft::$app->getSecurity()->validateData($decoded) : false;

        if (!is_string($data) || !str_starts_with($data, self::TOKEN_PREFIX)) {
            return null;
        }

        $payload = Json::decodeIfJson(substr($data, strlen(self::TOKEN_PREFIX)));

        if (!is_array($payload) || !isset($payload['p'], $payload['i'])) {
            return null;
        }

        if (isset($payload['x']) && $payload['x'] < time()) {
            return null;
        }

        return CommentsPlugin::$plugin->getIdentities()->getByUid((string)$payload['i']) ? $payload : null;
    }

    /**
     * Safe to repeat, because mail clients and visitors both can.
     *
     * Returns the `PURPOSE_*` constant that was applied, or null if a confirmation no longer matches the address.
     */
    public function applyManagementToken(array $management, bool $stopAll = false): ?string
    {
        $plugin = CommentsPlugin::$plugin;
        $identity = $plugin->getIdentities()->getByUid((string)$management['i']);
        $conversation = !empty($management['c'])
            ? ConversationRecord::findOne(['uid' => $management['c']])
            : null;

        if (!$identity) {
            return null;
        }

        if ($management['p'] === self::PURPOSE_CONFIRM) {
            if (($management['e'] ?? null) !== $this->_emailHash((string)$identity->email)) {
                return null;
            }

            $identity->verifiedAt = Db::prepareDateForDb(new DateTime());
            $identity->save(false);

            if ($conversation) {
                $this->subscribeToConversation((int)$conversation->id, (int)$identity->id);
            }

            $plugin->getIdentities()->clearCaches();

            return self::PURPOSE_CONFIRM;
        }

        if ($stopAll || $management['p'] === self::PURPOSE_ALL) {
            $identity->emailOptOutAt = Db::prepareDateForDb(new DateTime());
            $identity->save(false);

            // The person stopped email to their address, and guests can hold several identities with it.
            if (!$identity->userId && $identity->email) {
                IdentityRecord::updateAll(['emailOptOutAt' => $identity->emailOptOutAt], [
                    'and',
                    ['type' => IdentityType::Guest->value],
                    ['lower([[email]])' => strtolower((string)$identity->email)],
                ]);
            }

            $plugin->getIdentities()->clearCaches();

            return self::PURPOSE_ALL;
        }

        // Its element has gone since the email was sent, so there's nothing left to stop.
        if (!$conversation) {
            return self::PURPOSE_CONVERSATION;
        }

        SubscriptionRecord::updateAll(['subscribed' => false], [
            'conversationId' => $conversation->id,
            'identityId' => $identity->id,
        ]);
        $this->unsubscribe((int)$conversation->id, (int)$identity->id);

        return self::PURPOSE_CONVERSATION;
    }

    public function managementElementTitle(array $management): ?string
    {
        $conversation = !empty($management['c'])
            ? ConversationRecord::findOne(['uid' => $management['c']])
            : null;

        if (!$conversation) {
            return null;
        }

        $owner = Craft::$app->getElements()->getElementById((int)$conversation->ownerId, null, (int)$conversation->siteId);

        return $owner && CommentsPlugin::$plugin->getConversations()->isOwnerPublic($owner) ? ((string)$owner->title ?: null) : null;
    }

    /**
     * Asking to subscribe is also asking for email again, so it overrides having stopped it.
     */
    public function subscribeToConversation(int $conversationId, int $identityId): SubscriptionRecord
    {
        $this->_clearOptOut($identityId);

        return $this->_upsert(
            $conversationId,
            $identityId,
            null,
            SubscriptionType::Conversation,
            true,
        );
    }

    public function subscribeToReplies(int $conversationId, int $identityId, int $commentId): SubscriptionRecord
    {
        $this->_clearOptOut($identityId);

        return $this->_upsert(
            $conversationId,
            $identityId,
            $commentId,
            SubscriptionType::Replies,
            true,
        );
    }

    public function unsubscribe(
        int $conversationId,
        int $identityId,
        ?int $commentId = null,
        ?SubscriptionType $type = null,
    ): SubscriptionRecord {
        $type ??= $commentId === null
            ? SubscriptionType::Conversation
            : SubscriptionType::Replies;

        return $this->_upsert($conversationId, $identityId, $commentId, $type, false);
    }

    public function isSubscribed(
        int $conversationId,
        int $identityId,
        ?int $commentId = null,
        ?SubscriptionType $type = null,
    ): bool {
        $type ??= $commentId === null
            ? SubscriptionType::Conversation
            : SubscriptionType::Replies;

        $row = $this->get(
            $conversationId,
            $identityId,
            $commentId,
            $type,
        );

        return $row ? (bool)$row->subscribed : false;
    }

    public function get(
        int $conversationId,
        int $identityId,
        ?int $commentId = null,
        ?SubscriptionType $type = null,
    ): ?SubscriptionRecord {
        $type ??= $commentId === null
            ? SubscriptionType::Conversation
            : SubscriptionType::Replies;

        return SubscriptionRecord::findOne([
            'conversationId' => $conversationId,
            'identityId' => $identityId,
            'commentId' => $commentId,
            'type' => $type->value,
        ]);
    }

    /**
     * Apply the configured default without reviving a subscription the commenter explicitly disabled.
     */
    public function applyDefaultForComment(Comment $comment): ?SubscriptionRecord
    {
        if (!$comment->conversationId || !$comment->identityId) {
            return null;
        }

        $identity = CommentsPlugin::$plugin->getIdentities()->getById((int)$comment->identityId);

        if (!$identity?->userId) {
            return null;
        }

        $settings = CommentsPlugin::$plugin->getSettings();

        if ($settings->subscriptionDefault === Settings::SUBSCRIPTION_DEFAULT_CONVERSATION
            && $settings->allowsSubscriptionType(SubscriptionType::Conversation)
        ) {
            return $this->_ensureDefault(
                (int)$comment->conversationId,
                (int)$comment->identityId,
                null,
                SubscriptionType::Conversation,
            );
        }

        if ($settings->subscriptionDefault === Settings::SUBSCRIPTION_DEFAULT_REPLIES
            && $settings->allowsSubscriptionType(SubscriptionType::Replies)
        ) {
            return $this->_ensureDefault(
                (int)$comment->conversationId,
                (int)$comment->identityId,
                (int)$comment->id,
                SubscriptionType::Replies,
            );
        }

        return null;
    }

    /**
     * Active (subscribed=true) Conversation-level subscribers.
     */
    public function getConversationSubscribers(int $conversationId): array
    {
        return SubscriptionRecord::find()
            ->where([
                'conversationId' => $conversationId,
                'commentId' => null,
                'type' => SubscriptionType::Conversation->value,
                'subscribed' => true,
            ])
            ->all();
    }

    /**
     * Active reply-thread subscribers for a Comment.
     */
    public function getReplySubscribers(int $conversationId, int $commentId): array
    {
        return SubscriptionRecord::find()
            ->where([
                'conversationId' => $conversationId,
                'commentId' => $commentId,
                'type' => SubscriptionType::Replies->value,
                'subscribed' => true,
            ])
            ->all();
    }


    // Private Methods
    // =========================================================================

    private function _clearOptOut(int $identityId): void
    {
        $identity = CommentsPlugin::$plugin->getIdentities()->getById($identityId);

        if ($identity?->emailOptOutAt) {
            $identity->emailOptOutAt = null;
            $identity->save(false);
        }
    }

    private function _emailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function _upsert(
        int $conversationId,
        int $identityId,
        ?int $commentId,
        SubscriptionType $type,
        bool $subscribed,
    ): SubscriptionRecord {
        if (!CommentsPlugin::$plugin->getConversations()->getById($conversationId)) {
            throw new InvalidArgumentException("Conversation {$conversationId} was not found.");
        }

        if (!CommentsPlugin::$plugin->getIdentities()->getById($identityId)) {
            throw new InvalidArgumentException("Identity {$identityId} was not found.");
        }

        $existing = SubscriptionRecord::findOne([
            'conversationId' => $conversationId,
            'identityId' => $identityId,
            'commentId' => $commentId,
            'type' => $type->value,
        ]);

        if ($existing) {
            $existing->subscribed = $subscribed;
            $existing->save(false);

            return $existing;
        }

        $record = new SubscriptionRecord([
            'conversationId' => $conversationId,
            'identityId' => $identityId,
            'commentId' => $commentId,
            'type' => $type->value,
            'subscribed' => $subscribed,
            'uid' => StringHelper::UUID(),
        ]);

        try {
            $record->save(false);
        } catch (IntegrityException) {
            $existing = SubscriptionRecord::findOne([
                'conversationId' => $conversationId,
                'identityId' => $identityId,
                'commentId' => $commentId,
                'type' => $type->value,
            ]);

            if (!$existing) {
                throw new InvalidArgumentException('Failed to save Subscription after unique-index race.');
            }

            $existing->subscribed = $subscribed;
            $existing->save(false);

            return $existing;
        }

        return $record;
    }

    private function _ensureDefault(
        int $conversationId,
        int $identityId,
        ?int $commentId,
        SubscriptionType $type,
    ): SubscriptionRecord {
        $existing = $this->get($conversationId, $identityId, $commentId, $type);

        if ($existing) {
            return $existing;
        }

        return $this->_upsert($conversationId, $identityId, $commentId, $type, true);
    }
}
