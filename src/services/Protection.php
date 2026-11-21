<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\models\AllowedOrigin;
use verbb\comments\models\Settings;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\helpers\Html;
use craft\helpers\Json;

use verbb\protect\checks\Challenge;
use verbb\protect\checks\Classification;
use verbb\protect\checks\Honeypot;
use verbb\protect\checks\MinimumTime;
use verbb\protect\checks\RateLimit;
use verbb\protect\checks\ReplayProtection;
use verbb\protect\enums\CheckStatus;
use verbb\protect\enums\FindingType;
use verbb\protect\errors\InvalidPlanException;
use verbb\protect\errors\PreparationException;
use verbb\protect\base\ChallengeProvider;
use verbb\protect\base\ClassifierFeedbackInterface;
use verbb\protect\base\ClassifierProvider;
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

use Twig\Markup;

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


    // Properties
    // =========================================================================

    // A write running through `withoutPreparation()`.
    private bool $_unprepared = false;


    // Public Methods
    // =========================================================================

    /**
     * The checks for a comment. `$origin` is the allowed origin a data API request came from, if any:
     * its hostname is accepted in challenge tokens, and where it has its own Turnstile or reCAPTCHA keys,
     * they're used instead of the site's, with only its hostname accepted. A preparation's fingerprint
     * includes the site key, so one prepared for one set of keys can't be used with another.
     */
    /**
     * Runs a write without replay protection's single-use preparation, for Comments 3's GraphQL clients in
     * compatibility mode (see `LegacyGql::write()`). Every other check still applies.
     */
    public function withoutPreparation(callable $write): mixed
    {
        $before = $this->_unprepared;
        $this->_unprepared = true;

        try {
            return $write();
        } finally {
            $this->_unprepared = $before;
        }
    }

    public function plan(int $siteId, bool $authenticated, bool $headless = false, bool $editing = false, ?Settings $settings = null, ?AllowedOrigin $origin = null): ProtectionPlan
    {
        $settings ??= Comments::$plugin->getSettings();
        $plan = new ProtectionPlan(handle: 'comments-' . ($authenticated ? 'member' : 'guest') . ($headless ? '-headless' : '-browser'));
        $enabled = $settings->enableSpamChecks;
        $action = $editing ? 'comments_edit' : 'comments_create';
        $hostname = parse_url((string)Craft::$app->getSites()->getSiteById($siteId)->getBaseUrl(), PHP_URL_HOST);
        $hostnames = $settings->protectionHostnames ?: array_filter([$hostname]);
        $originKeys = $origin?->getProtectionKeys();

        if ($origin) {
            $hostnames = $originKeys ? [$origin->getHostname()] : array_values(array_unique([...$hostnames, $origin->getHostname()]));
        }

        $plan->add(new Honeypot(['enabled' => $enabled && $settings->honeypotEnabled && !$headless]))
            ->add(new MinimumTime(['seconds' => $settings->minimumSubmitTime, 'enabled' => $enabled && $settings->minimumSubmitTime > 0 && !$headless]))
            ->add(new ReplayProtection(['enabled' => $enabled && $settings->replayProtectionEnabled && !$this->_unprepared]));
        $rules = [];
        $dimensions = [$authenticated ? 'identity.userId' : 'request.ipAddress'];

        if ($enabled && $settings->rateLimitEnabled) {
            $rules[] = new RateLimitRule(['handle' => 'comments', 'limit' => $settings->rateLimitCount, 'window' => $settings->rateLimitWindow, 'dimensions' => $dimensions]);
        }

        // Preserve the beta setting's intent while removing its database-query implementation. Protect's
        // windows cover at most a day, so a longer wait counts as a day rather than stopping every comment.
        if ((int)$settings->securityFlooding > 0) {
            $rules[] = new RateLimitRule(['handle' => 'flooding', 'limit' => 1, 'window' => min((int)$settings->securityFlooding, 86400), 'dimensions' => $dimensions]);
        }

        $plan->add(new RateLimit(['rules' => $rules, 'enabled' => $rules !== []]));
        $challengeEnabled = $enabled && (!$authenticated || $settings->challengeAuthenticated);

        $plan->add(new Challenge([
            'provider' => $this->_challengeProvider($settings, $action, $hostnames, $originKeys),
            'enabled' => $challengeEnabled && $settings->challengeProvider !== Settings::CHALLENGE_NONE,
        ]));

        $classifier = $this->_classifier($siteId, $settings);

        if ($classifier) {
            $plan->add(new Classification(['provider' => $classifier, 'enabled' => $enabled]));
        }

        return $plan;
    }

    /**
     * A protection preparation for a new comment, or for editing `$commentId`. A caller that has already
     * loaded the owner passes it to save loading it again.
     */
    public function prepare(int $ownerId, int $siteId, ?int $commentId = null, bool $headless = false, ?ElementInterface $owner = null): ProtectionPreparation
    {
        if (!$owner || (int)$owner->id !== $ownerId || (int)$owner->siteId !== $siteId) {
            $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);
        }

        if (!$owner) {
            throw new ProtectionException(Craft::t('comments', 'Comments are unavailable for this element.'), 404, errorCode: 'not_found');
        }

        $config = Comments::$plugin->getConversations()->getConfigForOwner($ownerId, $siteId, $owner);

        if (!$config->isCommentingEnabled()) {
            throw new ProtectionException(Craft::t('comments', 'Comments are unavailable for this element.'), 403, errorCode: 'closed');
        }

        if ($commentId) {
            $comment = Comments::$plugin->getComments()->getCommentById($commentId, $siteId);

            if (!$comment || (int)$comment->ownerId !== $ownerId || !$comment->canEdit()) {
                throw new ProtectionException(Craft::t('comments', 'You do not have permission to edit this comment.'), 403, errorCode: 'forbidden');
            }
        }

        try {
            return Module::getInstance()->prepare(
                new ProtectionContext($commentId ? 'comments.edit' : 'comments.create', subject: $this->_subject($ownerId, $siteId, $commentId, $owner)),
                $this->plan($siteId, (bool)Comments::$plugin->getService()->getUser(), $headless, (bool)$commentId, origin: Comments::$plugin->getSignIn()->current()?->allowedOrigin),
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
        // GraphQL names can't hold a hyphen, so its fields are mapped to the providers' handles.
        foreach (['recaptchaV3' => 'recaptcha-v3', 'friendlyCaptcha' => 'friendly-captcha'] as $field => $handle) {
            if (is_array($data) && isset($data['challenges'][$field])) {
                $data['challenges'][$handle] = $data['challenges'][$field];
                unset($data['challenges'][$field]);
            }
        }

        try {
            return ProtectionEvidence::fromArray($data);
        } catch (PreparationException) {
            throw new ProtectionException(Craft::t('comments', 'Please refresh the form and try again.'), errorCode: 'protection_required');
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
        $classifier = $settings->enableSpamChecks ? $settings->spamClassifier : Settings::CLASSIFIER_NONE;
        $needsIp = $settings->rateLimitEnabled || (int)$settings->securityFlooding > 0 || $classifier !== Settings::CLASSIFIER_NONE || $settings->challengeSendIp;
        // Akismet and CleanTalk look at the browser and where the visitor came from as well.
        $needsBrowser = in_array($classifier, [Settings::CLASSIFIER_AKISMET, Settings::CLASSIFIER_CLEANTALK], true);

        return RequestSignals::fromRequest(Craft::$app->getRequest(), ipAddress: $needsIp, userAgent: $needsBrowser, referrer: $needsBrowser);
    }

    public function check(Comment $comment, ?ProtectionEvidence $evidence, ?RequestSignals $request = null, bool $headless = false): ProtectionResult
    {
        try {
            $result = Module::getInstance()->check($this->_context($comment, $evidence, $request), $this->plan((int)$comment->siteId, (bool)$comment->userId, $headless, (bool)$comment->id, origin: Comments::$plugin->getSignIn()->current()?->allowedOrigin));
        } catch (InvalidPlanException $e) {
            throw $this->_unavailable($e);
        }

        $settings = Comments::$plugin->getSettings();

        if ($result->hasFinding(FindingType::RateLimitExceeded)) {
            throw new ProtectionException(Craft::t('comments', 'Please wait before posting another comment.'), 429, $result->retryAfter() ?? 1, 'rate_limited');
        }

        foreach ([FindingType::PassiveCheckFailed, FindingType::ChallengeFailed, FindingType::ReplayDetected, FindingType::MissingRequiredSignal] as $type) {
            if ($result->hasFinding($type)) {
                throw new ProtectionException(Craft::t('comments', 'Please refresh the form and complete verification before trying again.'), errorCode: $this->_refusalCode($result));
            }
        }

        if ($result->hasErrors() && $settings->protectionFailClosed) {
            throw new ProtectionException(Craft::t('comments', 'Verification is temporarily unavailable. Please try again.'), 503, errorCode: 'protection_unavailable');
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
            if (!$settings->saveSpam) {
                throw new ProtectionException(Craft::t('comments', 'Unable to accept this comment.'), errorCode: 'possible_spam');
            }

            $comment->status = Comment::STATUS_SPAM;
            $comment->moderationState = ModerationState::Spam->value;
            $comment->heldReason = HeldReason::Protection->value;
        }

        return $result;
    }

    /**
     * Check a guest's upload for a custom field with the same protection as posting, apart from the
     * classifier, which has no text to read, and the posting rate limit, since uploads have a limit of their own.
     * A preparation is used up by the upload, so the comment needs a fresh one.
     *
     * The upload's preparation was issued for the comment form's plan, and Protect refuses one checked
     * against any other plan, so this checks against that same plan and skips the two checks by
     * condition, which leaves the plan's fingerprint as it was.
     */
    public function checkUpload(int $ownerId, int $siteId, ?ProtectionEvidence $evidence, bool $headless = false): void
    {
        $context = new ProtectionContext(
            action: 'comments.create',
            identity: new ProtectionIdentity(['userId' => null]),
            subject: $this->_subject($ownerId, $siteId, null),
            content: new ProtectionContent(''),
            request: $this->requestSignals(),
            evidence: $evidence,
        );

        try {
            $plan = $this->plan($siteId, false, $headless, origin: Comments::$plugin->getSignIn()->current()?->allowedOrigin);

            foreach ($plan->checks() as $check) {
                if ($check instanceof RateLimit || $check instanceof Classification) {
                    $check->condition = static fn(): bool => false;
                }
            }

            $result = Module::getInstance()->check($context, $plan);
        } catch (InvalidPlanException $e) {
            throw $this->_unavailable($e);
        }

        foreach ([FindingType::PassiveCheckFailed, FindingType::ChallengeFailed, FindingType::ReplayDetected, FindingType::MissingRequiredSignal] as $type) {
            if ($result->hasFinding($type)) {
                throw new ProtectionException(Craft::t('comments', 'Please refresh the form and complete verification before trying again.'), errorCode: $this->_refusalCode($result));
            }
        }

        if ($result->hasErrors() && Comments::$plugin->getSettings()->protectionFailClosed) {
            throw new ProtectionException(Craft::t('comments', 'Verification is temporarily unavailable. Please try again.'), 503, errorCode: 'protection_unavailable');
        }
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
            throw new ProtectionException(Craft::t('comments', 'Please wait before trying again.'), 429, $result->retryAfter() ?? 1, 'rate_limited');
        }
    }

    /**
     * The Spam Protection page's challenge provider (`challenge`) or spam classifier (`classifier`)
     * fields, which every Verbb plugin shares through Protect.
     */
    public function renderSettings(string $section, Settings $settings, array $secrets = [], array $variables = []): Markup
    {
        return Module::getInstance()->renderProviderSettings($section, [
            ...$variables,
            'settings' => $settings,
            'pluginHandle' => 'comments',
            'secrets' => $secrets,
        ]);
    }

    public function feedback(Comment $comment, bool $spam): void
    {
        $settings = Comments::$plugin->getSettings();

        // Turning protection off turns off what the classifier is told, as well as what it's asked.
        if (!$settings->enableSpamChecks || !$settings->spamFeedbackEnabled) {
            return;
        }

        $classifier = $this->_classifier((int)$comment->siteId, $settings);

        if (!$classifier instanceof ClassifierFeedbackInterface || !$comment->ipAddress) {
            return;
        }

        $classifier->feedback($this->_context($comment, null, new RequestSignals($comment->ipAddress, $comment->userAgent)), $spam);
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
            'cleantalk' => 'CleanTalk',
            'oopspam' => 'OOPSpam',
            'turnstile' => 'Turnstile',
            'recaptcha-v3' => 'reCAPTCHA',
            'hcaptcha' => 'hCaptcha',
            'friendly-captcha' => 'Friendly Captcha',
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

        return new ProtectionException(Craft::t('comments', 'Verification is temporarily unavailable. Please try again.'), 503, errorCode: 'protection_unavailable');
    }

    /**
     * The data API's code for why protection refused a submission, so a client knows whether fetching a
     * fresh preparation and trying again can help. A preparation that's missing or unreadable is
     * `protection_required`, one that's expired, for another form or already used is
     * `protection_expired`, a failed challenge is `challenge_failed`, and a honeypot or timing check
     * that failed is `possible_spam`.
     */
    private function _refusalCode(ProtectionResult $result): string
    {
        $codes = array_map(static fn(Finding $finding): string => $finding->code, iterator_to_array($result->findings()));

        return match (true) {
            in_array('preparation-invalid', $codes, true) || $result->hasFinding(FindingType::MissingRequiredSignal) => 'protection_required',
            in_array('preparation-expired', $codes, true) || in_array('preparation-mismatch', $codes, true) || $result->hasFinding(FindingType::ReplayDetected) => 'protection_expired',
            $result->hasFinding(FindingType::ChallengeFailed) => 'challenge_failed',
            default => 'possible_spam',
        };
    }

    private function _context(Comment $comment, ?ProtectionEvidence $evidence, ?RequestSignals $request): ProtectionContext
    {
        $signals = ['userId' => $comment->userId ? (int)$comment->userId : null];

        if (Comments::$plugin->getSettings()->spamClassifier !== Settings::CLASSIFIER_NONE) {
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

    private function _subject(int $ownerId, int $siteId, ?int $commentId, ?ElementInterface $owner = null): SubjectReference
    {
        $owner ??= Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        return new SubjectReference($commentId ? 'comment' : 'owner', (string)($commentId ?? $ownerId), $siteId, $owner?->getUrl());
    }

    /**
     * The chosen challenge provider. With none chosen, reCAPTCHA stands in, switched off, so the plan
     * always has the same checks.
     */
    private function _challengeProvider(Settings $settings, string $action, array $hostnames, ?array $originKeys): ChallengeProvider
    {
        return $settings->createChallengeProvider($action, $hostnames, $originKeys);
    }

    /**
     * The chosen spam classifier, or null for none.
     */
    private function _classifier(int $siteId, ?Settings $settings = null): ?ClassifierProvider
    {
        $settings ??= Comments::$plugin->getSettings();

        return $settings->createSpamClassifier((string)Craft::$app->getSites()->getSiteById($siteId)->getBaseUrl());
    }
}
