<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\models\Settings;

use Craft;
use craft\base\Component;
use craft\helpers\Html;
use craft\helpers\Json;

use verbb\protect\checks\Challenge;
use verbb\protect\checks\Classification;
use verbb\protect\checks\Honeypot;
use verbb\protect\checks\JavaScriptPresence;
use verbb\protect\checks\MinimumTime;
use verbb\protect\checks\RateLimit;
use verbb\protect\checks\ReplayProtection;
use verbb\protect\enums\CheckStatus;
use verbb\protect\enums\FindingType;
use verbb\protect\errors\InvalidPlanException;
use verbb\protect\errors\PreparationException;
use verbb\protect\integrations\challenges\RecaptchaV3;
use verbb\protect\integrations\challenges\Turnstile;
use verbb\protect\integrations\classifiers\Akismet;
use verbb\protect\models\Finding;
use verbb\protect\models\ProtectionContent;
use verbb\protect\models\ProtectionContext;
use verbb\protect\models\ProtectionEvidence;
use verbb\protect\models\ProtectionIdentity;
use verbb\protect\models\ProtectionPlan;
use verbb\protect\models\ProtectionPreparation;
use verbb\protect\models\ProtectionResult;
use verbb\protect\models\RateLimitRule;
use verbb\protect\models\RequestSignals;
use verbb\protect\models\SubjectReference;
use verbb\protect\Protect as Module;

/** Comments owns plans and outcomes; all check mechanics live in the shared module. */
class Protection extends Component
{
    // Constants
    // =========================================================================

    public const ENGAGEMENT_INTERACTION = 'interaction';
    public const ENGAGEMENT_REPORT = 'report';
    public const ENGAGEMENT_SUBSCRIPTION = 'subscription';

    // Attempts and window in seconds, per the trust and security operation profiles.
    private const ENGAGEMENT_LIMITS = [
        self::ENGAGEMENT_INTERACTION => [60, 60],
        self::ENGAGEMENT_REPORT => [3, 600],
        self::ENGAGEMENT_SUBSCRIPTION => [5, 3600],
    ];


    // Public Methods
    // =========================================================================

    public function plan(int $siteId, bool $authenticated, bool $headless = false, bool $editing = false, ?Settings $settings = null): ProtectionPlan
    {
        $settings ??= Comments::$plugin->getSettings();
        $plan = new ProtectionPlan(handle: 'comments-' . ($authenticated ? 'member' : 'guest') . ($headless ? '-headless' : '-browser'));
        $enabled = $settings->enableSpamChecks;
        $action = $editing ? 'comments_edit' : 'comments_create';
        $hostname = parse_url((string)Craft::$app->getSites()->getSiteById($siteId)->getBaseUrl(), PHP_URL_HOST);
        $hostnames = $settings->protectionHostnames ?: array_filter([$hostname]);
        $plan->add(new Honeypot(['enabled' => $enabled && $settings->honeypotEnabled && !$headless]))
            ->add(new MinimumTime(['seconds' => $settings->minimumSubmitTime, 'enabled' => $enabled && $settings->minimumSubmitTime > 0 && !$headless]))
            ->add(new JavaScriptPresence(['enabled' => $enabled && $settings->javascriptCheckEnabled && !$headless]))
            ->add(new ReplayProtection(['enabled' => $enabled && $settings->replayProtectionEnabled]));
        $rules = [];
        $dimensions = [$authenticated ? 'identity.userId' : 'request.ipAddress'];

        if ($enabled && $settings->rateLimitEnabled) {
            $rules[] = new RateLimitRule(['handle' => 'comments', 'limit' => $settings->rateLimitCount, 'window' => $settings->rateLimitWindow, 'dimensions' => $dimensions]);
        }

        // Preserve the beta setting's intent while removing its database-query implementation.
        if ((int)$settings->securityFlooding > 0) {
            $rules[] = new RateLimitRule(['handle' => 'flooding', 'limit' => 1, 'window' => (int)$settings->securityFlooding, 'dimensions' => $dimensions]);
        }

        $plan->add(new RateLimit(['rules' => $rules, 'enabled' => $rules !== []]));
        $challengeEnabled = $enabled && (!$authenticated || $settings->challengeAuthenticated);

        if ($settings->turnstileEnabled) {
            $plan->add(new Challenge([
                'provider' => Craft::createObject([
                    'class' => Turnstile::class,
                    'siteKey' => (string)$settings->turnstileKey,
                    'secretKey' => (string)$settings->turnstileSecret,
                    'expectedAction' => $action,
                    'hostnames' => $hostnames,
                    'sendIp' => $settings->challengeSendIp,
                ]),
                'enabled' => $challengeEnabled,
            ]));
        } else {
            $plan->add(new Challenge([
                'provider' => Craft::createObject([
                    'class' => RecaptchaV3::class,
                    'siteKey' => (string)$settings->recaptchaKey,
                    'secretKey' => (string)$settings->recaptchaSecret,
                    'expectedAction' => $action,
                    'hostnames' => $hostnames,
                    'minimumScore' => $settings->recaptchaMinScore,
                    'sendIp' => $settings->challengeSendIp,
                ]),
                'enabled' => $challengeEnabled && $settings->recaptchaEnabled,
            ]));
        }

        $plan->add(new Classification(['provider' => $this->_akismet($siteId, $settings), 'enabled' => $enabled && $settings->akismetEnabled]));

        return $plan;
    }

    public function prepare(int $ownerId, int $siteId, ?int $commentId = null, bool $headless = false): ProtectionPreparation
    {
        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if (!$owner) {
            throw new ProtectionException(Craft::t('comments', 'Comments are unavailable for this element.'));
        }

        $config = Comments::$plugin->getConversations()->getConfigForOwner($ownerId, $siteId);

        if (!$config->isCommentingEnabled()) {
            throw new ProtectionException(Craft::t('comments', 'Comments are unavailable for this element.'));
        }

        if ($commentId) {
            $comment = Comments::$plugin->getComments()->getCommentById($commentId, $siteId);

            if (!$comment || (int)$comment->ownerId !== $ownerId || !$comment->canEdit()) {
                throw new ProtectionException(Craft::t('comments', 'You do not have permission to edit this comment.'));
            }
        }

        try {
            return Module::getInstance()->prepare(
                new ProtectionContext($commentId ? 'comments.edit' : 'comments.create', subject: $this->_subject($ownerId, $siteId, $commentId)),
                $this->plan($siteId, (bool)Comments::$plugin->getService()->getUser(), $headless, (bool)$commentId),
            );
        } catch (InvalidPlanException $e) {
            throw $this->_unavailable($e);
        }
    }

    public function getFields(int $ownerId, int $siteId, ?int $commentId = null): string
    {
        // The page around the form shouldn't go down with it, and posting will say what's wrong.
        try {
            $preparation = $this->prepare($ownerId, $siteId, $commentId);
        } catch (ProtectionException) {
            return '';
        }

        return Html::tag('div', $preparation->renderHiddenInputs(), ['data-comments-protection' => Json::encode($preparation->browserManifest())]);
    }

    public function evidence(mixed $data): ProtectionEvidence
    {
        if (is_array($data) && isset($data['challenges']['recaptchaV3'])) {
            $data['challenges']['recaptcha-v3'] = $data['challenges']['recaptchaV3'];
            unset($data['challenges']['recaptchaV3']);
        }

        try {
            return ProtectionEvidence::fromArray($data);
        } catch (PreparationException) {
            throw new ProtectionException(Craft::t('comments', 'Please refresh the form and try again.'));
        }
    }

    /**
     * Why these settings can't protect a form on every site, if they can't. Sites differ because each
     * has its own hostnames, and nothing else notices until someone's trying to comment.
     */
    public function configurationError(Settings $settings): ?string
    {
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            try {
                $this->plan((int)$site->id, false, settings: $settings)->validate();
            } catch (InvalidPlanException $e) {
                return Craft::t('comments', 'These settings can’t protect comments on {site}: {error}', [
                    'site' => $site->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    public function requestSignals(): RequestSignals
    {
        $settings = Comments::$plugin->getSettings();
        $needsIp = $settings->rateLimitEnabled || (int)$settings->securityFlooding > 0 || $settings->akismetEnabled || $settings->challengeSendIp;

        return RequestSignals::fromRequest(Craft::$app->getRequest(), ipAddress: $needsIp, userAgent: $settings->akismetEnabled, referrer: $settings->akismetEnabled);
    }

    public function check(Comment $comment, ?ProtectionEvidence $evidence, ?RequestSignals $request = null, bool $headless = false): ProtectionResult
    {
        try {
            $result = Module::getInstance()->check($this->_context($comment, $evidence, $request), $this->plan((int)$comment->siteId, (bool)$comment->userId, $headless, (bool)$comment->id));
        } catch (InvalidPlanException $e) {
            throw $this->_unavailable($e);
        }

        $settings = Comments::$plugin->getSettings();

        if ($result->hasFinding(FindingType::RateLimitExceeded)) {
            throw new ProtectionException(Craft::t('comments', 'Please wait before posting another comment.'), 429, $result->retryAfter() ?? 1);
        }

        foreach ([FindingType::PassiveCheckFailed, FindingType::ChallengeFailed, FindingType::ReplayDetected, FindingType::MissingRequiredSignal] as $type) {
            if ($result->hasFinding($type)) {
                throw new ProtectionException(Craft::t('comments', 'Please refresh the form and complete verification before trying again.'));
            }
        }

        if ($result->hasErrors() && $settings->protectionFailClosed) {
            throw new ProtectionException(Craft::t('comments', 'Verification is temporarily unavailable. Please try again.'), 503);
        }

        // An outage can be waved through, but this will fail every time until someone fixes it, so
        // nothing is published unchecked. Protect has logged what's wrong.
        // Kept on the comment, so a moderator can see why it was held and what each check said.
        $comment->protection = $this->summarize($result);

        if ($result->hasFinding(FindingType::ConfigurationError)) {
            $comment->status = Comment::STATUS_PENDING;
            $comment->moderationState = ModerationState::Pending->value;
            $comment->heldReason = HeldReason::Protection->value;
        }

        if ($result->hasFinding(FindingType::SpamDetected)) {
            if (!$settings->persistSpam) {
                throw new ProtectionException(Craft::t('comments', 'Unable to accept this comment.'));
            }

            $comment->status = Comment::STATUS_SPAM;
            $comment->moderationState = ModerationState::Spam->value;
            $comment->heldReason = HeldReason::Protection->value;
        }

        return $result;
    }

    /**
     * What each check said, in Protect's own allowlisted terms: no request data or provider payloads.
     */
    public function summarize(ProtectionResult $result): ?string
    {
        $checks = [];

        foreach ($result->results as $checkResult) {
            if ($checkResult->status === CheckStatus::Skipped) {
                continue;
            }

            $checks[] = [
                'check' => $this->_checkHandle($checkResult->check),
                'status' => $checkResult->status->value,
            ];
        }

        $findings = array_map(static fn(Finding $finding): array => $finding->jsonSerialize(), iterator_to_array($result->findings()));

        if ($checks === [] && $findings === []) {
            return null;
        }

        return Json::encode(['checks' => $checks, 'findings' => array_values($findings)]);
    }

    /**
     * The stored summary as lines a moderator can read, such as "Akismet: spam (score 0.71)".
     */
    public function describe(?string $summary): array
    {
        $data = $summary ? Json::decodeIfJson($summary) : null;

        if (!is_array($data)) {
            return [];
        }

        $findingsByCheck = [];

        foreach ($data['findings'] ?? [] as $finding) {
            if (is_array($finding) && isset($finding['check'])) {
                $findingsByCheck[(string)$finding['check']][] = $finding;
            }
        }

        $lines = [];

        foreach ($data['checks'] ?? [] as $check) {
            if (!is_array($check) || !isset($check['check'], $check['status'])) {
                continue;
            }

            $handle = (string)$check['check'];
            $findings = $findingsByCheck[$handle] ?? [];
            $provider = $findings[0]['provider'] ?? null;
            $score = $findings[0]['metadata']['score'] ?? null;
            $detail = null;

            if ($findings) {
                $detail = implode(', ', array_unique(array_map(fn(array $finding): string => $this->_findingLabel((string)($finding['type'] ?? '')), $findings)));
            }

            if (is_int($score) || is_float($score)) {
                $detail = trim(($detail ?? '') . ' ' . Craft::t('comments', '(score {score})', ['score' => round((float)$score, 2)]));
            }

            $lines[] = [
                'label' => $this->_checkLabel($handle, is_string($provider) ? $provider : null),
                'status' => (string)$check['status'],
                'detail' => $detail,
            ];
        }

        return $lines;
    }

    /**
     * Apply the abuse limits for votes and flags. Guests are keyed by network because each
     * cookieless request gets a new identity, so an identity key wouldn't limit them at all.
     */
    public function limitEngagement(string $operation): void
    {
        [$limit, $window] = self::ENGAGEMENT_LIMITS[$operation];
        $user = Comments::$plugin->getService()->getUser();
        $ipAddress = RequestSignals::fromRequest(Craft::$app->getRequest())->ipAddress;

        if (!$user && !$ipAddress) {
            return;
        }

        $plan = (new ProtectionPlan(handle: 'comments-' . $operation))->add(new RateLimit(['rules' => [
            new RateLimitRule(['handle' => $operation, 'limit' => $limit, 'window' => $window, 'dimensions' => [$user ? 'identity.userId' : 'request.ipAddress'], 'includeSite' => false]),
        ]]));
        $identity = $user ? new ProtectionIdentity(['userId' => (int)$user->id]) : null;
        $result = Module::getInstance()->check(new ProtectionContext(action: 'comments.' . $operation, identity: $identity, request: new RequestSignals(ipAddress: $ipAddress)), $plan);

        if ($result->hasFinding(FindingType::RateLimitExceeded)) {
            throw new ProtectionException(Craft::t('comments', 'Please wait before trying again.'), 429, $result->retryAfter() ?? 1);
        }
    }

    public function feedback(Comment $comment, bool $spam): void
    {
        $settings = Comments::$plugin->getSettings();

        if (!$settings->akismetEnabled || !$settings->akismetFeedbackEnabled || !$comment->ipAddress) {
            return;
        }

        $this->_akismet((int)$comment->siteId)->feedback($this->_context($comment, null, new RequestSignals($comment->ipAddress, $comment->userAgent)), $spam);
    }


    // Private Methods
    // =========================================================================

    private function _checkHandle(string $check): string
    {
        return class_exists($check) && method_exists($check, 'handle') ? $check::handle() : $check;
    }

    private function _checkLabel(string $handle, ?string $provider): string
    {
        $providers = [
            'akismet' => 'Akismet',
            'turnstile' => 'Turnstile',
            'recaptcha-v3' => 'reCAPTCHA',
        ];

        if ($provider && isset($providers[$provider])) {
            return $providers[$provider];
        }

        return match ($handle) {
            'challenge' => Craft::t('comments', 'Bot check'),
            'classification' => Craft::t('comments', 'Spam check'),
            'honeypot' => Craft::t('comments', 'Hidden field'),
            'java-script-presence' => Craft::t('comments', 'JavaScript check'),
            'minimum-time' => Craft::t('comments', 'Time to post'),
            'rate-limit' => Craft::t('comments', 'Rate limit'),
            'replay-protection' => Craft::t('comments', 'Repeat submission check'),
            default => $handle,
        };
    }

    private function _findingLabel(string $type): string
    {
        return match (FindingType::tryFrom($type)) {
            FindingType::SpamDetected => Craft::t('comments', 'says spam'),
            FindingType::ChallengeFailed, FindingType::PassiveCheckFailed => Craft::t('comments', 'failed'),
            FindingType::ProviderUnavailable, FindingType::InvalidProviderResponse => Craft::t('comments', 'didn’t answer'),
            FindingType::ConfigurationError => Craft::t('comments', 'isn’t set up correctly'),
            FindingType::RateLimitExceeded => Craft::t('comments', 'limit reached'),
            FindingType::ReplayDetected => Craft::t('comments', 'repeat submission'),
            FindingType::MissingRequiredSignal => Craft::t('comments', 'missing information'),
            default => $type,
        };
    }

    private function _unavailable(InvalidPlanException $e): ProtectionException
    {
        Craft::error('Comments protection is misconfigured: ' . $e->getMessage(), __METHOD__);

        return new ProtectionException(Craft::t('comments', 'Verification is temporarily unavailable. Please try again.'), 503);
    }

    private function _context(Comment $comment, ?ProtectionEvidence $evidence, ?RequestSignals $request): ProtectionContext
    {
        $signals = ['userId' => $comment->userId ? (int)$comment->userId : null];

        if (Comments::$plugin->getSettings()->akismetEnabled) {
            $signals['email'] = $comment->email ?: $comment->getUser()?->email;
        }

        return new ProtectionContext(
            action: $comment->id ? 'comments.edit' : 'comments.create',
            identity: new ProtectionIdentity($signals),
            subject: $this->_subject((int)$comment->ownerId, (int)$comment->siteId, $comment->id ? (int)$comment->id : null),
            content: new ProtectionContent((string)$comment->comment, authorName: $comment->getAuthorName(), dateCreated: $comment->dateCreated, edited: (bool)$comment->id),
            request: $request ?? $this->requestSignals(),
            evidence: $evidence,
        );
    }

    private function _subject(int $ownerId, int $siteId, ?int $commentId): SubjectReference
    {
        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        return new SubjectReference($commentId ? 'comment' : 'owner', (string)($commentId ?? $ownerId), $siteId, $owner?->getUrl());
    }

    private function _akismet(int $siteId, ?Settings $settings = null): Akismet
    {
        $settings ??= Comments::$plugin->getSettings();

        return Craft::createObject([
            'class' => Akismet::class,
            'apiKey' => (string)$settings->akismetApiKey,
            'blogUrl' => $settings->akismetBlogUrl ?: (string)Craft::$app->getSites()->getSiteById($siteId)->getBaseUrl(),
            'testMode' => $settings->akismetTestMode,
        ]);
    }
}
