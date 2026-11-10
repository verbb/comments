<?php
use craft\elements\User;
use craft\models\GqlSchema;
use craft\models\UserGroup;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\controllers\SubscriptionsController;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\helpers\SettingsTabs;
use verbb\comments\migrations\m400000_000005_guest_email;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\migrations\support\V3SettingsReview;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\records\Identity;
use verbb\comments\records\Subscription;
use verbb\comments\services\Identities;
use verbb\comments\services\Subscriptions;
use yii\web\Cookie;
use yii\web\CookieCollection;

/**
 * The 4.0 Core gaps from the 2 November 2026 review, stage 5.
 */

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $this->originalMailer = Craft::$app->getMailer();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->allowGuest = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = ['conversation'];
    $settings->useQueueForNotifications = false;
    Craft::$app->getConfig()->getGeneral()->enableGraphqlCaching = false;
});

afterEach(function() {
    yii\base\Event::off(verbb\comments\services\Notifications::class, verbb\comments\services\Notifications::EVENT_AFTER_NOTIFY);
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Craft::$app->set('mailer', $this->originalMailer);
    Craft::$app->getGql()->flushCaches();
    $request = Craft::$app->getRequest();
    (new ReflectionProperty(yii\web\Request::class, '_cookies'))->setValue($request, null);
    $request->setBodyParams([]);
    $request->setQueryParams([]);
    $request->setIsConsoleRequest(true);
    unset($_SERVER['REQUEST_METHOD']);
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getIdentities()->clearCaches();
});

function gapsComment($entry, string $content = 'Comment', ?int $parentId = null, ?int $userId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => $userId ?? (int)Craft::$app->getUser()->getIdentity()->id,
        'parentId' => $parentId,
    ], static fn($v) => $v !== null)))->comment;
}

function gapsMail(): object
{
    $recorder = new class () {
        public array $messages = [];

        public function to(string $email): array
        {
            return array_values(array_filter($this->messages, static fn($message) => array_key_exists($email, (array)$message->getTo())));
        }
    };

    Craft::$app->set('mailer', new class (['recorder' => $recorder, 'messageClass' => craft\mail\Message::class]) extends craft\mail\Mailer {
        public object $recorder;

        public function send($message): bool
        {
            $this->recorder->messages[] = $message;

            return true;
        }
    });

    return $recorder;
}

/** Act as a signed-out visitor, optionally with an existing guest cookie. */
function gapsGuest(?string $token = null): void
{
    Craft::$app->getUser()->setIdentity(null);
    $request = Craft::$app->getRequest();
    $request->setIsConsoleRequest(false);
    $cookies = $token ? [Identities::GUEST_COOKIE_NAME => new Cookie(['name' => Identities::GUEST_COOKIE_NAME, 'value' => $token])] : [];
    (new ReflectionProperty(yii\web\Request::class, '_cookies'))->setValue($request, new CookieCollection($cookies, ['readOnly' => true]));
    Craft::$app->getResponse()->getCookies()->removeAll();
    Comments::$plugin->getIdentities()->clearCaches();
}

/** The cookie the last request gave this guest. */
function gapsIssuedToken(): ?string
{
    return Craft::$app->getResponse()->getCookies()->get(Identities::GUEST_COOKIE_NAME)?->value;
}

/** A guest's comment, from an address they've confirmed so they can be emailed. */
function gapsConfirmedGuestComment($entry, string $content, string $email): Comment
{
    $user = Craft::$app->getUser();
    $signedIn = $user->getIdentity();
    $user->setIdentity(null);

    try {
        $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'name' => 'Guest commenter',
            'email' => $email,
        ]))->comment;
    } finally {
        $user->setIdentity($signedIn);
    }

    $identity = Identity::findOne((int)$comment->identityId);
    $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();

    return $comment;
}

/** @return object{events: list<verbb\comments\events\NotifyEvent>} */
function gapsNotifyEvents(): object
{
    $recorder = new class () {
        public array $events = [];
    };

    yii\base\Event::on(verbb\comments\services\Notifications::class, verbb\comments\services\Notifications::EVENT_AFTER_NOTIFY, static function($event) use ($recorder) {
        $recorder->events[] = $event;
    });

    return $recorder;
}

function gapsUser(string $prefix = 'gaps'): User
{
    return CommentsFixtureFactory::user($prefix . bin2hex(random_bytes(3)));
}

function gapsManage(string $token, string $method = 'GET', array $body = []): craft\web\Response
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $request = Craft::$app->getRequest();
    $request->setIsConsoleRequest(false);
    $request->setQueryParams(['emailToken' => $token]);
    $request->setBodyParams($body);

    return (new SubscriptionsController('subscriptions', Comments::$plugin))->actionManage();
}

function gapsToken(string $url): string
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

    // Craft rejects a request with a `token` it doesn't recognise before any controller runs.
    expect($query)->not->toHaveKey(Craft::$app->getConfig()->getGeneral()->tokenParam);

    return (string)$query['emailToken'];
}

function gapsGql(array $scope, string $query, bool $public = false): array
{
    $schema = new GqlSchema(['name' => 'Gaps', 'scope' => $scope, 'isPublic' => $public]);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getGql()->setActiveSchema($schema);

    return Craft::$app->getGql()->executeQuery($schema, $query, debugMode: true);
}

// D3 and D5: what's been cut
// =========================================================================

it('accounts for each of the 70 Comments 3 settings exactly once', function() {
    $dispositions = V3SettingsReview::dispositions();

    expect($dispositions)->toHaveCount(70);

    foreach ($dispositions as $setting => $disposition) {
        $exists = property_exists(Settings::class, $setting);

        expect($exists)->toBe($disposition === V3SettingsReview::KEPT, $setting);
    }
});

it('ignores retired settings still in project config', function() {
    $settings = new Settings([
        'giphyEnabled' => true,
        'giphyApiKey' => 'secret',
        'enableGravatar' => true,
        'flaggedCommentLimit' => 2,
        'showAuthorScore' => true,
        'defaultQueryStatus' => ['pending'],
    ]);

    expect($settings->toArray())->not->toHaveKeys(['giphyEnabled', 'giphyApiKey', 'enableGravatar', 'flaggedCommentLimit', 'showAuthorScore', 'defaultQueryStatus']);
    expect(Comment::find()->status)->toBe([Comment::STATUS_APPROVED]);
});

it('reports only the retired settings a site was actually using, and never their values', function() {
    $inUse = V3SettingsReview::retiredInUse([
        'giphyEnabled' => true,
        'giphyApiKey' => 'secret-key',
        'giphyRating' => 'g',
        'flaggedCommentLimit' => 5,
        'downvoteCommentLimit' => 2,
        'indexSidebarLimit' => 50,
        'pluginName' => 'Discussion',
    ]);

    expect($inUse)->toEqualCanonicalizing(['giphyEnabled', 'giphyApiKey', 'downvoteCommentLimit']);
    expect(V3SettingsReview::retiredInUse(['defaultQueryStatus' => ['approved'], 'showAuthorScore' => true]))->toBe([]);
    expect(V3UpgradeLog::describe('settings.retired.giphyApiKey'))->toContain('giphyApiKey')->not->toContain('secret-key');
});

it('only offers an upgrade report to a site with something in it', function() {
    expect(SettingsTabs::has('upgrade'))->toBeFalse();

    $db = Craft::$app->getDb();
    $created = !$db->tableExists(V3Schema::LOG);

    if ($created) {
        $db->createCommand()->createTable(V3Schema::LOG, ['code' => 'string(191) PRIMARY KEY', 'severity' => 'string(16)', 'count' => 'integer', 'samples' => 'text'])->execute();
    }

    $log = new V3UpgradeLog($db);
    $log->add('settings.retired.giphyEnabled', V3UpgradeLog::WARNING);
    $log->flush();

    expect(SettingsTabs::has('upgrade'))->toBeTrue();
    expect(V3UpgradeLog::issues($db)[0]['label'])->toContain('giphyEnabled');

    $db->createCommand()->delete(V3Schema::LOG)->execute();

    if ($created) {
        $db->createCommand()->dropTable(V3Schema::LOG)->execute();
    }
});

it('treats any open flag as flagged now the threshold is gone', function() {
    $entry = CommentsFixtureFactory::entry('Flag owner');
    $comment = gapsComment($entry);

    expect($comment->isFlagged())->toBeFalse();

    Comments::$plugin->getReports()->create((int)$comment->id, (int)$comment->identityId);

    expect(Comment::find()->id($comment->id)->one()->isFlagged())->toBeTrue();
});

it('has no way left to attach a new GIF, but still shows old ones', function() {
    expect(Comments::$plugin->has('giphy'))->toBeFalse();
    expect(property_exists(CommentSubmission::class, 'gifUrl'))->toBeFalse();

    $entry = CommentsFixtureFactory::entry('GIF owner');
    $comment = gapsComment($entry);
    $comment->gifUrl = 'https://media.giphy.com/old.gif';

    expect(Comments::$plugin->getFrontend()->buildCommentViewsForComments([$comment], 0)[0]->gifUrl)->toBe('https://media.giphy.com/old.gif');
});

// Conversation state
// =========================================================================

it('keeps comments visible when closed everywhere, but stops new ones', function() {
    $entry = CommentsFixtureFactory::entry('Globally closed owner');
    gapsComment($entry, 'Before closing');

    Comments::$plugin->getSettings()->closed = true;
    Comments::$plugin->getConversations()->clearCaches();

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);

    expect($view->roots)->toHaveCount(1)
        ->and($view->canComment)->toBeFalse()
        ->and($view->closedNotice)->not->toBeNull();

    gapsComment($entry, 'After closing');
})->throws(yii\base\UserException::class);

it('shows nothing at all when comments are disabled on an element', function() {
    $entry = CommentsFixtureFactory::entry('Disabled owner');
    gapsComment($entry, 'Before disabling');

    Comments::$plugin->getSettings()->permissions = [craft\elements\Entry::class => []];
    Comments::$plugin->getConversations()->clearCaches();

    expect(Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId))->toBeNull();
});

it('counts the whole conversation in the heading, not the page', function() {
    $entry = CommentsFixtureFactory::entry('Count owner');

    foreach (range(1, 3) as $i) {
        gapsComment($entry, "Comment {$i}");
    }

    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, null, 2);

    expect($view->roots)->toHaveCount(2)->and($view->commentCount)->toBe(3);

    $html = $frontend->renderPartial('_conversation/header', ['instance' => 'x', 'conversation' => $view, 'viewer' => $frontend->buildViewerView($view)]);

    expect($html)->toMatch('/<span class="comments__count">.*?3<\/span>/s');
});

// Edited and edit windows
// =========================================================================

it('marks a comment as edited only when its content changes', function() {
    $entry = CommentsFixtureFactory::entry('Edited owner');
    $comment = gapsComment($entry, 'Original');
    $elements = Craft::$app->getElements();

    expect($comment->editedAt)->toBeNull();

    // Moderation isn't an edit.
    Comments::$plugin->getModeration()->remove($comment, $this->admin);
    Comments::$plugin->getModeration()->restore($comment, $this->admin);
    expect(Comment::find()->id($comment->id)->one()->editedAt)->toBeNull();

    $comment = Comment::find()->id($comment->id)->one();
    $comment->comment = 'Changed';
    $elements->saveElement($comment);

    $saved = Comment::find()->id($comment->id)->one();
    expect($saved->editedAt)->toBeInstanceOf(DateTime::class);

    $html = Comments::$plugin->getFrontend()->renderPartial('_comment/author', [
        'comment' => Comments::$plugin->getFrontend()->buildCommentViewsForComments([$saved], 0)[0],
    ]);

    expect($html)->toContain('comments__edited');
});

it('does not mark rich content as edited when only its JSON order differs', function() {
    $entry = CommentsFixtureFactory::entry('Rich order owner');
    $comment = gapsComment($entry, 'Rich');
    $comment->contentFormat = 'editor';
    $comment->bodyContent = '{"type":"doc","content":[]}';
    Craft::$app->getElements()->saveElement($comment);

    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['editedAt' => null], ['id' => $comment->id])->execute();

    $comment = Comment::find()->id($comment->id)->one();
    $comment->bodyContent = '{"content":[],"type":"doc"}';
    Craft::$app->getElements()->saveElement($comment);

    expect(Comment::find()->id($comment->id)->one()->editedAt)->toBeNull();
});

it('stops authors editing or deleting once the window closes, but not moderators', function() {
    $entry = CommentsFixtureFactory::entry('Window owner');
    $author = gapsUser();
    $comment = gapsComment($entry, 'Mine', null, (int)$author->id);
    Craft::$app->getUser()->setIdentity($author);

    expect($comment->canEdit())->toBeTrue()->and($comment->canDelete($author))->toBeTrue();

    Comments::$plugin->getSettings()->authorEditWindow = 10;

    expect($comment->canEdit())->toBeTrue();

    $comment->commentDate = new DateTime('-11 minutes');

    expect($comment->canEdit())->toBeFalse()->and($comment->canDelete($author))->toBeFalse();
    expect(Comments::$plugin->getFrontend()->buildCommentViewsForComments([$comment], 0)[0]->actions)->not->toContain('edit', 'remove');

    $moderator = gapsUser('moderator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-edit', 'comments-delete']);
    $theirs = gapsComment($entry, 'A moderator’s own', null, (int)$moderator->id);
    $theirs->commentDate = new DateTime('-11 minutes');
    Craft::$app->getUser()->setIdentity($moderator);

    expect($theirs->canEdit())->toBeTrue()->and($theirs->canDelete($moderator))->toBeTrue();
});

// Top sort
// =========================================================================

it('sorts by score, paging without repeating anything', function() {
    $entry = CommentsFixtureFactory::entry('Top owner');
    Comments::$plugin->getSettings()->interactionMode = 'updown';

    $low = gapsComment($entry, 'Low');
    $high = gapsComment($entry, 'High');
    $none = gapsComment($entry, 'None');
    $mid = gapsComment($entry, 'Mid');

    $identity = fn() => (int)Comments::$plugin->getIdentities()->getOrCreateForUser(gapsUser())->id;
    $interactions = Comments::$plugin->getInteractions();

    $interactions->toggle((int)$high->id, $identity(), InteractionType::Upvote);
    $interactions->toggle((int)$high->id, $identity(), InteractionType::Upvote);
    $interactions->toggle((int)$mid->id, $identity(), InteractionType::Upvote);
    $interactions->toggle((int)$low->id, $identity(), InteractionType::Downvote);

    $conversations = Comments::$plugin->getConversations();
    $conversationId = (int)$high->conversationId;

    $first = $conversations->getRootComments($conversationId, CommentSort::Top, 2);
    $second = $conversations->getRootComments($conversationId, CommentSort::Top, 2, $first->next);

    expect(array_map(fn($c) => (int)$c->id, $first->comments))->toBe([(int)$high->id, (int)$mid->id])
        ->and(array_map(fn($c) => (int)$c->id, $second->comments))->toBe([(int)$none->id, (int)$low->id])
        ->and($second->next)->toBeNull();

    // A cursor from another sort or conversation starts again rather than failing.
    expect($conversations->getRootComments($conversationId, CommentSort::Top, 2, 'not-a-cursor')->comments)->toHaveCount(2);
});

it('leaves hidden comments out of Top', function() {
    $entry = CommentsFixtureFactory::entry('Top hidden owner');
    Comments::$plugin->getSettings()->interactionMode = 'like';

    $visible = gapsComment($entry, 'Visible');
    $hidden = gapsComment($entry, 'Hidden');
    Comments::$plugin->getInteractions()->toggle((int)$hidden->id, (int)$hidden->identityId, InteractionType::Upvote);
    Comments::$plugin->getModeration()->remove($hidden, $this->admin);

    $page = Comments::$plugin->getConversations()->getRootComments((int)$visible->conversationId, CommentSort::Top);

    expect(array_map(fn($c) => (int)$c->id, $page->comments))->toBe([(int)$visible->id]);
});

it('only offers Top when there is something to rank by', function() {
    $entry = CommentsFixtureFactory::entry('Top unavailable owner');
    gapsComment($entry);
    $frontend = Comments::$plugin->getFrontend();

    Comments::$plugin->getSettings()->interactionMode = 'none';
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, CommentSort::Top);

    expect($view->sort)->not->toBe('top')->and($view->allowsTopSort)->toBeFalse();

    Comments::$plugin->getSettings()->interactionMode = 'like';
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, CommentSort::Top);

    expect($view->sort)->toBe('top')->and($view->allowsTopSort)->toBeTrue();
});

// Badges
// =========================================================================

it('shows configured badges for the element author and staff groups', function() {
    $entry = CommentsFixtureFactory::entry('Badge owner');
    $authorId = (int)$entry->getAuthorIds()[0];

    $staff = gapsUser('staff');
    $group = new UserGroup(['name' => 'Badge ' . bin2hex(random_bytes(3)), 'handle' => 'badge' . bin2hex(random_bytes(3))]);
    Craft::$app->getUserGroups()->saveGroup($group, false);
    Craft::$app->getUsers()->assignUserToGroups((int)$staff->id, [(int)$group->id]);

    $settings = Comments::$plugin->getSettings();
    $settings->ownerBadgeLabel = 'Writer';
    $settings->staffBadgeLabel = 'Team';
    $settings->staffBadgeGroups = [$group->uid];

    $moderator = gapsUser('mod');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-moderate']);

    $views = Comments::$plugin->getFrontend()->buildCommentViewsForComments([
        gapsComment($entry, 'From the author', null, $authorId),
        gapsComment($entry, 'From staff', null, (int)$staff->id),
        gapsComment($entry, 'From a moderator', null, (int)$moderator->id),
    ], 0);

    expect($views[0]->badges)->toBe([['type' => 'owner', 'label' => 'Writer']])
        ->and($views[1]->badges)->toBe([['type' => 'staff', 'label' => 'Team']])
        // Moderating isn't the same as being shown as staff.
        ->and($views[2]->badges)->toBe([]);

    $settings->ownerBadgeLabel = '';
    $settings->staffBadgeLabel = '';

    $unlabelled = Comments::$plugin->getFrontend()->buildCommentViewsForComments([gapsComment($entry, 'No badge', null, $authorId)], 0);
    expect($unlabelled[0]->badges)->toBe([]);
});

it('never gives a guest a badge, whatever name they use', function() {
    $entry = CommentsFixtureFactory::entry('Guest badge owner');
    $author = $entry->getAuthors()[0];
    $settings = Comments::$plugin->getSettings();
    $settings->ownerBadgeLabel = 'Writer';

    gapsGuest();

    $guestComment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Impersonating',
        'name' => $author->fullName ?: $author->username,
        'email' => 'someone@example.test',
    ]))->comment;

    expect(Comments::$plugin->getFrontend()->buildCommentViewsForComments([$guestComment], 0)[0]->badges)->toBe([]);
});

it('shows how long ago a comment was made, unless the page is cached for everyone', function() {
    $entry = CommentsFixtureFactory::entry('Time ago owner');
    $comment = gapsComment($entry);
    $comment->commentDate = new DateTime('-3 hours');
    $frontend = Comments::$plugin->getFrontend();

    expect($frontend->buildCommentViewsForComments([$comment], 0)[0]->timeAgo)->toContain('3 hours');
    expect($frontend->buildCommentViewsForComments([$comment], 0, false)[0]->timeAgo)->toBeNull();

    Comments::$plugin->getSettings()->showTimeAgo = false;

    expect($frontend->buildCommentViewsForComments([$comment], 0)[0]->timeAgo)->toBeNull();
});

it('says how long ago a comment was made in the site’s language', function() {
    $entry = CommentsFixtureFactory::entry('Time ago language owner');
    $comment = gapsComment($entry);
    $language = Craft::$app->language;
    // Craft builds the formatter for the site's language when a site request starts.
    $useLanguage = static function(string $language): void {
        Craft::$app->language = $language;
        Craft::$app->set('locale', fn() => Craft::$app->getI18n()->getLocaleById(Craft::$app->language));
        Craft::$app->set('formattingLocale', fn() => craft\helpers\App::createFormattingLocale());
        Craft::$app->set('formatter', fn() => Craft::$app->getFormattingLocale()->getFormatter());
    };

    try {
        $useLanguage('de-DE');

        $comment->commentDate = new DateTime('-3 days -2 hours');
        expect($comment->getTimeAgo())->toBe('vor 3 Tagen');

        $comment->commentDate = new DateTime('-1 hour -5 minutes');
        expect($comment->getTimeAgo())->toBe('vor einer Stunde')
            ->and(Comments::$plugin->getFrontend()->buildCommentViewsForComments([$comment], 0)[0]->timeAgo)->toBe('vor einer Stunde');

        // A clock a moment ahead still reads as the past.
        $comment->commentDate = new DateTime('+90 seconds');
        expect($comment->getTimeAgo())->toBe('vor einer Minute');

        $useLanguage('en-US');
        $comment->commentDate = new DateTime('-3 days -2 hours');
        expect($comment->getTimeAgo())->toBe('3 days ago');
    } finally {
        $useLanguage($language);
    }
});

// D4: guest subscriptions
// =========================================================================

it('holds a guest subscription until the address is confirmed', function() {
    $entry = CommentsFixtureFactory::entry('Guest subscribe owner');
    $mail = gapsMail();
    $subscriptions = Comments::$plugin->getSubscriptions();

    gapsGuest();

    expect($subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'guest@example.test'))
        ->toBe(Subscriptions::OUTCOME_CONFIRMING);

    $identity = Identity::findOne(['email' => 'guest@example.test']);
    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId);

    expect($identity->verifiedAt)->toBeNull()
        ->and($identity->emailConfirmationSentAt)->not->toBeNull()
        ->and($subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id))->toBeFalse();

    Comments::$plugin->getComments()->sendGuestConfirmationEmail((int)$identity->id, 'guest@example.test', (int)$conversation->id, (int)$entry->siteId);

    expect($mail->to('guest@example.test'))->toHaveCount(1);

    preg_match('#https?://\S+#', $mail->to('guest@example.test')[0]->getSymfonyEmail()->getTextBody(), $match);
    $token = gapsToken(html_entity_decode($match[0]));

    // Nothing identifying travels in the link.
    expect(base64_decode(strtr($token, '-_', '+/')))->not->toContain('guest@example.test')->not->toContain('"i":' . $identity->id);

    // Following the link isn't confirming: mail scanners do that.
    gapsManage($token);
    $identity->refresh();
    expect($identity->verifiedAt)->toBeNull();

    gapsManage($token, 'POST');
    $identity->refresh();

    expect($identity->verifiedAt)->not->toBeNull()
        ->and($subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id))->toBeTrue();
});

it('lets a confirmed guest subscribe straight away, with their cookie', function() {
    $entry = CommentsFixtureFactory::entry('Verified guest owner');
    $other = CommentsFixtureFactory::entry('Verified guest second');
    $subscriptions = Comments::$plugin->getSubscriptions();

    gapsGuest();
    $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'verified@example.test');
    $token = gapsIssuedToken();

    $identity = Identity::findOne(['email' => 'verified@example.test']);
    $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->save(false);

    gapsGuest($token);

    expect($subscriptions->setConversationSubscription((int)$other->id, (int)$other->siteId, true))
        ->toBe(Subscriptions::OUTCOME_SUBSCRIBED);

    // Without the cookie the same address has to prove itself again.
    gapsGuest();

    expect($subscriptions->setConversationSubscription((int)$other->id, (int)$other->siteId, true, 'verified@example.test'))
        ->toBe(Subscriptions::OUTCOME_CONFIRMING);
});

it('forgets a confirmation when the guest changes their address', function() {
    $identities = Comments::$plugin->getIdentities();
    $resolved = $identities->resolveOrCreateGuest(null, 'Sam', 'first@example.test');
    $identity = $resolved['identity'];
    $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->emailOptOutAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->save(false);

    $identity = $identities->resolveOrCreateGuest($resolved['guestToken'], 'Sam', 'second@example.test')['identity'];

    expect($identity->verifiedAt)->toBeNull()->and($identity->emailOptOutAt)->toBeNull();
});

it('gives the same answer for a registered address, and sends it nothing', function() {
    $entry = CommentsFixtureFactory::entry('Registered address owner');
    $user = gapsUser();

    gapsGuest();

    expect(Comments::$plugin->getSubscriptions()->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, $user->email))
        ->toBe(Subscriptions::OUTCOME_CONFIRMING);

    expect(Identity::findOne(['email' => $user->email, 'type' => 'guest'])->emailConfirmationSentAt)->toBeNull();
});

it('will not flood an address with confirmations', function() {
    $entry = CommentsFixtureFactory::entry('Flood owner');
    $subscriptions = Comments::$plugin->getSubscriptions();

    gapsGuest();
    $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'victim@example.test');
    gapsGuest();
    $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'victim@example.test');

    $sent = Identity::find()->where(['email' => 'victim@example.test'])->andWhere(['not', ['emailConfirmationSentAt' => null]])->count();

    expect((int)$sent)->toBe(1);
});

it('will not subscribe anyone to an element without comments', function() {
    $entry = CommentsFixtureFactory::entry('Unavailable subscribe owner');
    $subscriptions = Comments::$plugin->getSubscriptions();

    expect(fn() => $subscriptions->setConversationSubscription(999999999, (int)$entry->siteId, true))
        ->toThrow(yii\base\UserException::class);

    Comments::$plugin->getSettings()->permissions = [craft\elements\Entry::class => []];
    Comments::$plugin->getConversations()->clearCaches();

    expect(fn() => $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true))
        ->toThrow(yii\base\UserException::class);
    expect(Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId))->toBeNull();
});

it('rejects a guest subscribing without a usable address or where guests are off', function() {
    $entry = CommentsFixtureFactory::entry('Bad address owner');
    $subscriptions = Comments::$plugin->getSubscriptions();

    gapsGuest();

    expect(fn() => $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'nope'))
        ->toThrow(yii\base\UserException::class);

    Comments::$plugin->getSettings()->allowGuest = false;
    Comments::$plugin->getConversations()->clearCaches();

    expect(fn() => $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'ok@example.test'))
        ->toThrow(yii\base\UserException::class);
});

it('only emails a guest about replies once their address is confirmed', function() {
    $entry = CommentsFixtureFactory::entry('Guest reply owner');
    $settings = Comments::$plugin->getSettings();
    $mail = gapsMail();

    gapsGuest();
    $guestComment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Guest comment',
        'name' => 'Sam',
        'email' => 'sam@example.test',
    ]))->comment;

    Craft::$app->getUser()->setIdentity($this->admin);
    Craft::$app->getRequest()->setIsConsoleRequest(true);
    $settings->participantNotifications = [Settings::NOTIFICATION_REPLY];
    $reply = gapsComment($entry, 'A reply', (int)$guestComment->id);

    expect($mail->to('sam@example.test'))->toBeEmpty();

    $identity = Identity::findOne((int)$guestComment->identityId);
    $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();

    gapsComment($entry, 'Another reply', (int)$guestComment->id);

    expect($mail->to('sam@example.test'))->toHaveCount(1);
    expect($mail->to('sam@example.test')[0]->getSymfonyEmail()->getTextBody())->toContain('Hi Sam,')->toContain('emailToken=');
});

it('asks a guest to confirm when they opt in to reply emails on a comment', function() {
    $entry = CommentsFixtureFactory::entry('Opt in owner');
    gapsGuest();

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Tell me about replies',
        'name' => 'Sam',
        'email' => 'optin@example.test',
        'notifyReplies' => true,
    ]));

    gapsGuest();

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Say nothing',
        'name' => 'Pat',
        'email' => 'noopt@example.test',
    ]));

    expect(Identity::findOne(['email' => 'optin@example.test'])->emailConfirmationSentAt)->not->toBeNull()
        ->and(Identity::findOne(['email' => 'noopt@example.test'])->emailConfirmationSentAt)->toBeNull();
});

it('emails a guest at the address they confirmed, not the one on their comment', function() {
    $entry = CommentsFixtureFactory::entry('Guest address owner');
    $settings = Comments::$plugin->getSettings();
    $mail = gapsMail();

    gapsGuest();
    $guestComment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Written with someone else’s address',
        'name' => 'Sam',
        'email' => 'victim@example.test',
    ]))->comment;

    $identity = Identity::findOne((int)$guestComment->identityId);
    $identity->email = 'attacker@example.test';
    $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();

    Craft::$app->getUser()->setIdentity($this->admin);
    Craft::$app->getRequest()->setIsConsoleRequest(true);
    $settings->participantNotifications = [Settings::NOTIFICATION_REPLY, Settings::NOTIFICATION_APPROVED];

    gapsComment($entry, 'A reply', (int)$guestComment->id);
    Comments::$plugin->getComments()->sendModeratorApprovedNotificationEmail($guestComment);

    expect($mail->to('victim@example.test'))->toBeEmpty()
        ->and($mail->to('attacker@example.test'))->toHaveCount(2);
});

it('rechecks a confirmation when it comes to send it', function() {
    $mail = gapsMail();
    $identity = Comments::$plugin->getIdentities()->resolveOrCreateGuest(null, 'Sam', 'queued@example.test')['identity'];
    $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
    $service = Comments::$plugin->getComments();

    // Swapped for another address after the request was checked and queued.
    $identity->email = 'swapped@example.test';
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();
    $service->sendGuestConfirmationEmail((int)$identity->id, 'queued@example.test', null, $siteId);

    expect($mail->messages)->toBeEmpty();

    // Or for a registered user's.
    $user = gapsUser();
    $identity->email = $user->email;
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();
    $service->sendGuestConfirmationEmail((int)$identity->id, (string)$user->email, null, $siteId);

    expect($mail->messages)->toBeEmpty();

    $identity->email = 'SWAPPED@example.test';
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();
    $service->sendGuestConfirmationEmail((int)$identity->id, 'swapped@example.test', null, $siteId);

    expect($mail->messages)->toHaveCount(1);
    expect($mail->messages[0]->getHeader('List-Unsubscribe'))->not->toBeEmpty();
});

it('stops all a guest’s identities at once, and any new one asking to confirm', function() {
    $identities = Comments::$plugin->getIdentities();
    $subscriptions = Comments::$plugin->getSubscriptions();
    $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
    $first = $identities->resolveOrCreateGuest(null, 'Sam', 'stop@example.test')['identity'];
    $second = $identities->resolveOrCreateGuest(null, 'Sam', 'STOP@example.test')['identity'];

    $subscriptions->applyManagementToken($subscriptions->readManagementToken(gapsToken($subscriptions->managementUrl(Subscriptions::PURPOSE_ALL, $first, $siteId))));

    $second->refresh();
    expect($second->emailOptOutAt)->not->toBeNull();

    $third = $identities->resolveOrCreateGuest(null, 'Attacker', 'stop@example.test')['identity'];
    expect($subscriptions->canConfirmAddress($third))->toBeFalse();
});

it('does not read a wildcard in a guest address as a search for registered users', function() {
    $entry = CommentsFixtureFactory::entry('Wildcard owner');
    $user = gapsUser('wild');

    gapsGuest();

    $post = fn(string $email) => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Probing',
        'name' => 'Sam',
        'email' => $email,
    ]));

    expect(fn() => $post('not an address'))->toThrow(yii\base\UserException::class, 'valid email');

    $wildcard = substr((string)$user->email, 0, 4) . '*@example.test';
    expect($post($wildcard)->comment->id)->not->toBeNull();

    expect(fn() => $post((string)$user->email))->toThrow(yii\base\UserException::class, 'Unauthorized');
});

it('shows nothing about unpublished elements to the public', function() {
    $entry = CommentsFixtureFactory::entry('Unpublished owner');
    gapsComment($entry, 'Before unpublishing');
    $frontend = Comments::$plugin->getFrontend();

    $entry->enabled = false;
    Craft::$app->getElements()->saveElement($entry);
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getRenderCache();

    // Someone who can see it, can see its comments.
    expect($frontend->buildConversationView((int)$entry->id, (int)$entry->siteId))->not->toBeNull();

    gapsGuest();

    expect($frontend->buildConversationView((int)$entry->id, (int)$entry->siteId))->toBeNull();
    expect(fn() => Comments::$plugin->getSubscriptions()->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'nosy@example.test'))
        ->toThrow(yii\base\UserException::class);

    Craft::$app->getUser()->setIdentity($this->admin);
    Craft::$app->getRequest()->setIsConsoleRequest(true);

    $result = gapsGql(['comments:read'], "{ commentConversation(ownerId: {$entry->id}, siteId: {$entry->siteId}) { commentCount } }");
    expect($result['data']['commentConversation'])->toBeNull();
});

it('never shows a commenter’s email address as their name', function() {
    Craft::$app->getConfig()->getGeneral()->useEmailAsUsername = true;
    $entry = CommentsFixtureFactory::entry('Email name owner');
    $user = gapsUser('email');
    $user->username = $user->email;
    $user->firstName = null;
    $user->lastName = null;
    $user->fullName = null;
    Craft::$app->getElements()->saveElement($user, false);
    $comment = gapsComment($entry, 'Mine', null, (int)$user->id);
    Craft::$app->getConfig()->getGeneral()->useEmailAsUsername = false;

    expect(Comments::$plugin->getFrontend()->buildCommentViewsForComments([$comment], 0)[0]->authorName)->toBe('Member');
});

it('only gives the edit form to someone who can edit the comment', function() {
    $entry = CommentsFixtureFactory::entry('Editor owner');
    $comment = gapsComment($entry, 'Private text', null, (int)gapsUser()->id);
    Comments::$plugin->getModeration()->remove($comment, $this->admin);

    gapsGuest();
    $request = Craft::$app->getRequest();
    $request->setQueryParams(['mode' => 'edit', 'ownerId' => $entry->id, 'siteId' => $entry->siteId, 'commentUid' => $comment->uid]);

    expect(fn() => (new verbb\comments\controllers\FrontendApiController('frontend-api', Comments::$plugin))->actionEditor())
        ->toThrow(yii\web\NotFoundHttpException::class);
});

it('lets a guest without JavaScript unsubscribe from the same button', function() {
    $entry = CommentsFixtureFactory::entry('No script unsubscribe owner');
    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $viewer = $frontend->buildViewerView($view);

    $viewer->showSubscribe = true;
    $viewer->isSubscribed = true;
    expect($frontend->renderPartial('_conversation/header', ['instance' => 'x', 'conversation' => $view, 'viewer' => $viewer]))
        ->toContain('name="subscribed" value="0"');

    $viewer->isSubscribed = false;
    expect($frontend->renderPartial('_conversation/header', ['instance' => 'x', 'conversation' => $view, 'viewer' => $viewer]))
        ->toContain('name="subscribed" value="1"');
});

// Unsubscribing
// =========================================================================

it('puts a working unsubscribe link and headers on subscriber emails', function() {
    $entry = CommentsFixtureFactory::entry('Unsubscribe owner');
    $subscriber = 'unsubscribe-guest@example.test';
    $mail = gapsMail();
    $subscriptions = Comments::$plugin->getSubscriptions();
    $first = gapsConfirmedGuestComment($entry, 'First', $subscriber);
    $identity = Identity::findOne((int)$first->identityId);
    $conversationId = (int)$first->conversationId;

    $subscriptions->subscribeToConversation($conversationId, (int)$identity->id);

    gapsComment($entry, 'Second');
    Comments::$plugin->getComments()->sendSubscribeNotificationEmail(gapsComment($entry, 'Third'));

    $message = $mail->to($subscriber)[0];
    $header = $message->getHeader('List-Unsubscribe')[0];

    expect($message->getSymfonyEmail()->getSubject())->not->toBeEmpty();
    expect($message->getHeader('List-Unsubscribe-Post')[0])->toBe('List-Unsubscribe=One-Click');
    expect($message->getSymfonyEmail()->getTextBody())->toContain('Unsubscribe');

    $token = gapsToken(html_entity_decode(trim($header, '<>')));

    expect(gapsManage($token)->getStatusCode())->toBe(200);
    expect($subscriptions->isSubscribed($conversationId, (int)$identity->id))->toBeTrue();

    // What a mail client sends for one-click, with no session or cookie.
    gapsManage($token, 'POST', ['List-Unsubscribe' => 'One-Click']);

    expect($subscriptions->isSubscribed($conversationId, (int)$identity->id))->toBeFalse();

    // Repeating it is harmless.
    expect(gapsManage($token, 'POST')->getStatusCode())->toBe(200);

    $mail->messages = [];
    Craft::$app->getRequest()->setIsConsoleRequest(true);
    Comments::$plugin->getComments()->sendSubscribeNotificationEmail(gapsComment($entry, 'Fourth'));

    expect($mail->to($subscriber))->toBeEmpty();
});

it('emails guests and passes account holders to an event instead', function() {
    $entry = CommentsFixtureFactory::entry('Account holders owner');
    $member = gapsUser('member');
    $mail = gapsMail();
    $events = gapsNotifyEvents();
    $subscriptions = Comments::$plugin->getSubscriptions();
    $guestComment = gapsConfirmedGuestComment($entry, 'Guest first', 'subscribed-guest@example.test');
    $conversationId = (int)$guestComment->conversationId;

    $subscriptions->subscribeToConversation($conversationId, (int)$guestComment->identityId);
    $subscriptions->subscribeToConversation($conversationId, (int)Comments::$plugin->getIdentities()->getOrCreateForUser($member)->id);
    // Posting sends the subscription notice itself, as there's no queue here.
    gapsComment($entry, 'From the admin');

    expect($mail->to('subscribed-guest@example.test'))->toHaveCount(1)
        ->and($mail->to($member->email))->toBeEmpty()
        ->and($events->events)->toHaveCount(1)
        ->and($events->events[0]->type)->toBe('subscribe')
        ->and(array_map(static fn(User $user): int => (int)$user->id, $events->events[0]->recipients))->toBe([(int)$member->id]);

    // A reply to the member is an event too, and only names them.
    $events->events = [];
    $mail->messages = [];
    Comments::$plugin->getSettings()->participantNotifications = [Settings::NOTIFICATION_REPLY];
    $memberComment = gapsComment($entry, 'Member comment', null, (int)$member->id);
    gapsComment($entry, 'Reply to member', (int)$memberComment->id);

    expect($mail->to($member->email))->toBeEmpty()
        ->and(array_map(static fn($event): string => $event->type, $events->events))->toBe(['reply'])
        ->and($events->events[0]->recipients[0]->id)->toBe($member->id);
});

it('names the moderator group in staff events and emails no one', function() {
    $entry = CommentsFixtureFactory::entry('Staff event owner');
    $group = new UserGroup(['name' => 'Staff events ' . bin2hex(random_bytes(3)), 'handle' => 'staffEvents' . bin2hex(random_bytes(3))]);
    Craft::$app->getUserGroups()->saveGroup($group, false);
    $moderator = gapsUser('moderator');
    Craft::$app->getUsers()->assignUserToGroups((int)$moderator->id, [(int)$group->id]);
    $settings = Comments::$plugin->getSettings();
    $settings->moderatorUserGroup = $group->uid;
    $settings->requireModeration = true;
    $settings->staffNotifications = [Settings::NOTIFICATION_MODERATOR_PENDING, Settings::NOTIFICATION_STAFF_NEW];
    $mail = gapsMail();
    $events = gapsNotifyEvents();

    gapsComment($entry, 'Waiting', null, (int)gapsUser('poster')->id);

    expect($mail->messages)->toBe([])
        ->and(array_map(static fn($event): string => $event->type, $events->events))->toBe(['admin', 'moderator'])
        ->and(array_map(static fn(User $user): int => (int)$user->id, $events->events[1]->recipients))->toBe([(int)$moderator->id]);
});

it('lets one link stop every comment email, until the person asks for them again', function() {
    $entry = CommentsFixtureFactory::entry('Stop all owner');
    $commenter = 'stop-guest@example.test';
    $settings = Comments::$plugin->getSettings();
    $mail = gapsMail();
    $parent = gapsConfirmedGuestComment($entry, 'Parent', $commenter);
    $identity = Comments::$plugin->getIdentities()->getById((int)$parent->identityId);

    $settings->participantNotifications = [Settings::NOTIFICATION_REPLY];
    gapsComment($entry, 'First reply', (int)$parent->id);

    $header = $mail->to($commenter)[0]->getHeader('List-Unsubscribe')[0];
    gapsManage(gapsToken(html_entity_decode(trim($header, '<>'))), 'POST');

    Craft::$app->getRequest()->setIsConsoleRequest(true);
    Comments::$plugin->getIdentities()->clearCaches();
    $mail->messages = [];
    gapsComment($entry, 'Second reply', (int)$parent->id);

    expect($mail->to($commenter))->toBeEmpty();

    Comments::$plugin->getSubscriptions()->subscribeToConversation((int)$parent->conversationId, (int)$identity->id);
    Comments::$plugin->getIdentities()->clearCaches();
    gapsComment($entry, 'Third reply', (int)$parent->id);

    expect($mail->to($commenter))->toHaveCount(1);
});

it('refuses tampered or expired links, and confirmations for an old address', function() {
    $subscriptions = Comments::$plugin->getSubscriptions();
    $identity = Comments::$plugin->getIdentities()->resolveOrCreateGuest(null, 'Sam', 'links@example.test')['identity'];
    $url = $subscriptions->managementUrl(Subscriptions::PURPOSE_ALL, $identity, (int)Craft::$app->getSites()->getPrimarySite()->id);
    $token = gapsToken($url);

    expect($subscriptions->readManagementToken($token))->not->toBeNull();
    expect($subscriptions->readManagementToken(substr($token, 0, -2) . 'xx'))->toBeNull();
    expect($subscriptions->readManagementToken('nonsense'))->toBeNull();
    expect(gapsManage('nonsense')->getStatusCode())->toBe(404);

    $hash = fn(array $payload) => rtrim(strtr(base64_encode(Craft::$app->getSecurity()->hashData('comments-email:' . json_encode($payload))), '+/', '-_'), '=');

    expect($subscriptions->readManagementToken($hash(['p' => 'confirm', 'i' => $identity->uid, 'c' => null, 'e' => 'x', 'x' => time() - 1])))->toBeNull();

    // Signed by this site, but not for this purpose.
    expect($subscriptions->readManagementToken(rtrim(strtr(base64_encode(Craft::$app->getSecurity()->hashData('something-else')), '+/', '-_'), '=')))->toBeNull();

    $confirm = gapsToken($subscriptions->managementUrl(Subscriptions::PURPOSE_CONFIRM, $identity, (int)Craft::$app->getSites()->getPrimarySite()->id));
    $identity->email = 'changed@example.test';
    $identity->save(false);

    expect($subscriptions->applyManagementToken($subscriptions->readManagementToken($confirm)))->toBeNull();
    $identity->refresh();
    expect($identity->verifiedAt)->toBeNull();
});

it('will not let an unsubscribe link switch email on', function() {
    $identity = Comments::$plugin->getIdentities()->resolveOrCreateGuest(null, 'Sam', 'reduce@example.test')['identity'];
    $subscriptions = Comments::$plugin->getSubscriptions();
    $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;

    $subscriptions->applyManagementToken($subscriptions->readManagementToken(gapsToken($subscriptions->managementUrl(Subscriptions::PURPOSE_ALL, $identity, $siteId))));

    $identity->refresh();

    expect($identity->verifiedAt)->toBeNull()->and($identity->emailOptOutAt)->not->toBeNull();
});

it('lets a guest unsubscribe without anything needing to be confirmed', function() {
    $entry = CommentsFixtureFactory::entry('Guest unsubscribe owner');
    $subscriptions = Comments::$plugin->getSubscriptions();

    gapsGuest();
    $subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, true, 'leave@example.test');
    $token = gapsIssuedToken();
    $identity = Identity::findOne(['email' => 'leave@example.test']);
    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId);
    $subscriptions->subscribeToConversation((int)$conversation->id, (int)$identity->id);

    gapsGuest($token);

    expect($subscriptions->setConversationSubscription((int)$entry->id, (int)$entry->siteId, false))->toBe(Subscriptions::OUTCOME_UNSUBSCRIBED)
        ->and($subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id))->toBeFalse();

    expect(Subscription::findOne(['conversationId' => $conversation->id, 'identityId' => $identity->id])->subscribed)->toBeIn([false, 0]);
});

it('stops a guest with one address getting the same email twice', function() {
    $entry = CommentsFixtureFactory::entry('Duplicate address owner');
    $mail = gapsMail();
    $first = gapsComment($entry, 'First');
    $subscriptions = Comments::$plugin->getSubscriptions();

    foreach ([1, 2] as $i) {
        $identity = Comments::$plugin->getIdentities()->resolveOrCreateGuest(null, 'Sam', 'twice@example.test')['identity'];
        $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
        $identity->save(false);
        $subscriptions->subscribeToConversation((int)$first->conversationId, (int)$identity->id);
    }

    gapsComment($entry, 'Second');

    expect($mail->to('twice@example.test'))->toHaveCount(1);
});

// Deep links
// =========================================================================

it('renders a linked comment that is pages back, inside its thread', function() {
    $entry = CommentsFixtureFactory::entry('Deep link owner');
    $root = gapsComment($entry, 'Old root');
    $reply = gapsComment($entry, 'Reply', (int)$root->id);
    $nested = gapsComment($entry, 'Nested', (int)$reply->id);
    $target = gapsComment($entry, 'Target', (int)$nested->id);

    foreach (range(1, 4) as $i) {
        gapsComment($entry, "Newer {$i}");
    }

    $frontend = Comments::$plugin->getFrontend();
    $plain = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, CommentSort::Newest, 2);

    expect(array_column($plain->roots, 'id'))->not->toContain((int)$root->id);

    $linked = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, CommentSort::Newest, 2, null, false, (int)$target->id);

    expect($linked->roots)->toHaveCount(3)->and($linked->roots[0]->id)->toBe((int)$root->id);
    expect($linked->roots[0]->replies[0]->replies[0]->replies[0]->id)->toBe((int)$target->id);

    $html = $frontend->renderPartial('_comment/comment', [
        'instance' => 'x',
        'conversation' => $linked,
        'viewer' => $frontend->buildViewerView($linked),
        'comment' => $linked->roots[0],
    ]);

    expect($html)->toContain('id="comment-' . $target->id . '"');
});

it('reaches a linked reply past the depth and preview limits', function() {
    $entry = CommentsFixtureFactory::entry('Deep reply owner');
    $root = gapsComment($entry, 'Root');
    $parent = $root;

    foreach (range(1, 7) as $i) {
        $parent = gapsComment($entry, "Level {$i}", (int)$parent->id);
    }

    foreach (range(1, 5) as $i) {
        gapsComment($entry, "Sibling {$i}", (int)$root->id);
    }

    $thread = Comments::$plugin->getFrontend()->buildThreadView((int)$parent->id, (int)$entry->id, (int)$entry->siteId, false);
    $walk = $thread;
    $ids = [];

    while ($walk) {
        $ids[] = $walk->id;
        $walk = $walk->replies[0] ?? null;

        if ($ids[count($ids) - 1] === (int)$parent->id) {
            break;
        }
    }

    expect(end($ids))->toBe((int)$parent->id)->and($ids)->toHaveCount(8);
    expect($thread->hasMoreReplies)->toBeTrue();
});

it('keeps a thread together through a removed parent', function() {
    $entry = CommentsFixtureFactory::entry('Deep placeholder owner');
    $root = gapsComment($entry, 'Root');
    $reply = gapsComment($entry, 'Reply', (int)$root->id);
    Comments::$plugin->getModeration()->remove($root, $this->admin);

    $thread = Comments::$plugin->getFrontend()->buildThreadView((int)$reply->id, (int)$entry->id, (int)$entry->siteId, false);

    expect($thread->placeholder)->toBeTrue()
        ->and($thread->content)->toBe('')
        ->and($thread->replies[0]->id)->toBe((int)$reply->id);
});

it('treats a comment the viewer cannot see the same as one that does not exist', function() {
    $entry = CommentsFixtureFactory::entry('Deep hidden owner');
    $other = CommentsFixtureFactory::entry('Deep other owner');
    $frontend = Comments::$plugin->getFrontend();
    $removed = gapsComment($entry, 'Removed');
    $spam = gapsComment($entry, 'Spam');
    $held = gapsComment($entry, 'Held');
    $elsewhere = gapsComment($other, 'Elsewhere');
    $moderation = Comments::$plugin->getModeration();

    $moderation->remove($removed, $this->admin);
    $moderation->markSpam($spam, $this->admin);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['moderationState' => 'pending', 'status' => Comment::STATUS_PENDING], ['id' => $held->id])->execute();

    foreach ([$removed, $spam, $held] as $hidden) {
        expect($frontend->buildThreadView((int)$hidden->id, (int)$entry->id, (int)$entry->siteId, false))->toBeNull();
    }

    expect($frontend->buildThreadView((int)$elsewhere->id, (int)$entry->id, (int)$entry->siteId, false))->toBeNull();
    expect($frontend->buildThreadView(999999999, (int)$entry->id, (int)$entry->siteId, false))->toBeNull();

    // The same element on another site is a different conversation.
    expect($frontend->buildThreadView((int)$elsewhere->id, (int)$other->id, (int)$other->siteId + 1000, false))->toBeNull();

    // Moderators can follow links to what they can act on.
    expect($frontend->buildThreadView((int)$held->id, (int)$entry->id, (int)$entry->siteId, true)?->id)->toBe((int)$held->id);
});

it('survives a hierarchy that loops', function() {
    $entry = CommentsFixtureFactory::entry('Deep loop owner');
    $root = gapsComment($entry, 'Root');
    $reply = gapsComment($entry, 'Reply', (int)$root->id);
    $db = Craft::$app->getDb();

    $db->createCommand()->update('{{%comments_comments}}', ['parentId' => $reply->id], ['id' => $root->id])->execute();

    $path = Comments::$plugin->getConversations()->getThreadPath($reply);

    expect(count($path))->toBeLessThanOrEqual(2);

    $db->createCommand()->update('{{%comments_comments}}', ['parentId' => null], ['id' => $root->id])->execute();
});

it('links out from a comment with both the parameter and the anchor', function() {
    $entry = CommentsFixtureFactory::entry('Permalink owner');
    $comment = gapsComment($entry);

    $permalink = (string)$comment->getPermalink();

    if ($entry->getUrl()) {
        expect($permalink)->toContain('comment=' . $comment->id)->toEndWith('#comment-' . $comment->id);
    } else {
        expect($permalink)->toBe('');
    }
});

// GraphQL
// =========================================================================

it('exposes content format, rendered content and edit state over GraphQL', function() {
    $entry = CommentsFixtureFactory::entry('Gql content owner');
    $comment = gapsComment($entry, 'Plain');
    $comment->comment = 'Changed';
    Craft::$app->getElements()->saveElement($comment);

    $result = gapsGql(['comments:read'], "{ comment(id: {$comment->id}) { ... on Comment { contentFormat contentHtml editorContent editedAt moderationState rootId depth } } }");

    expect($result['errors'] ?? [])->toBeEmpty();
    expect($result['data']['comment'])->toMatchArray([
        'contentFormat' => 'plain',
        'contentHtml' => null,
        'editorContent' => null,
        'moderationState' => 'approved',
        'rootId' => (int)$comment->id,
        'depth' => 0,
    ]);
    expect($result['data']['comment']['editedAt'])->not->toBeNull();
});

it('looks up a conversation over GraphQL', function() {
    $entry = CommentsFixtureFactory::entry('Gql conversation owner');
    gapsComment($entry);
    gapsComment($entry);

    $query = "{ commentConversation(ownerId: {$entry->id}, siteId: {$entry->siteId}) { ownerId commentCount open } }";

    $result = gapsGql(['comments:read'], $query);

    expect($result['errors'] ?? [])->toBeEmpty();
    expect($result['data']['commentConversation'])->toBe(['ownerId' => (int)$entry->id, 'commentCount' => 2, 'open' => true]);

    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, true);

    expect(gapsGql(['comments:read'], $query)['data']['commentConversation']['open'])->toBeFalse();
});

it('only moderates over GraphQL with both the schema permission and a moderator', function() {
    $entry = CommentsFixtureFactory::entry('Gql moderate owner');
    $commenter = gapsUser();
    // Nobody moderates their own comment.
    $comment = gapsComment($entry, 'Comment', null, (int)$commenter->id);
    $mutation = "mutation { moderateComment(id: {$comment->id}, action: \"spam\") { id } }";
    $selection = ['comments:read', 'comments:moderate'];

    $withoutScope = gapsGql(['comments:read', 'comments:save'], $mutation);
    expect($withoutScope['errors'] ?? [])->not->toBeEmpty();

    Craft::$app->getUser()->setIdentity(gapsUser());
    expect(gapsGql($selection, $mutation)['errors'] ?? [])->not->toBeEmpty();

    Craft::$app->getUser()->setIdentity(null);
    expect(gapsGql($selection, $mutation)['errors'] ?? [])->not->toBeEmpty();

    expect(Comments::$plugin->getModeration()->currentState(Comment::find()->id($comment->id)->one()))->toBe(ModerationState::Approved);

    Craft::$app->getUser()->setIdentity($this->admin);
    expect(gapsGql($selection, $mutation, true)['errors'] ?? [])->not->toBeEmpty();

    $allowed = gapsGql($selection, $mutation);

    expect($allowed['errors'] ?? [])->toBeEmpty();
    expect(Comments::$plugin->getModeration()->currentState(Comment::find()->id($comment->id)->status(null)->one()))->toBe(ModerationState::Spam);
});

// Reports, uploads and schema
// =========================================================================

it('offers the configured report reasons, and labels old flags honestly', function() {
    $settings = Comments::$plugin->getSettings();
    $frontend = Comments::$plugin->getFrontend();
    $reports = Comments::$plugin->getReports();

    $settings->reportReasons = [
        ['value' => 'wrong', 'label' => 'Factually wrong', 'description' => 'Says something untrue'],
        ['value' => 'rude', 'label' => 'Rude'],
        ['value' => '', 'label' => 'No value'],
        ['value' => 'nolabel', 'label' => ''],
    ];

    expect($frontend::reportReasons())->toBe([
        ['value' => 'wrong', 'label' => 'Factually wrong', 'description' => 'Says something untrue'],
        ['value' => 'rude', 'label' => 'Rude', 'description' => null],
    ]);
    expect($reports->reasonLabel('wrong'))->toBe('Factually wrong');
    expect($reports->reasonLabel('legacy'))->toBe('Legacy report');
    expect($reports->reasonLabel(null))->toBe('Other');
    expect($reports->reasonLabel('removed-reason'))->toBe('Other');
});

it('passes attachment limits to the editor', function() {
    $settings = new Settings([
        'allowAttachments' => true,
        'attachmentVolumeId' => 1,
        'attachmentExtensions' => ' JPG, .png,,pdf ',
        'attachmentMaxSize' => 2,
    ]);

    expect($settings->attachmentExtensions)->toBe(['jpg', 'png', 'pdf']);

    $assets = EditorContexts::replyConfig($settings)->assets;

    expect($assets->allowedExtensions)->toBe(['jpg', 'png', 'pdf'])
        ->and($assets->maxFileSize)->toBe(2 * 1024 * 1024);
});

it('adds the new columns to a site that already ran prepare, safely', function() {
    $db = Craft::$app->getDb();
    $db->createCommand()->dropColumn('{{%comments_comments}}', 'editedAt')->execute();
    $db->createCommand()->dropColumn('{{%comments_identities}}', 'emailOptOutAt')->execute();
    $db->getSchema()->refresh();

    $migration = new m400000_000005_guest_email(['db' => $db]);

    ob_start();
    $first = $migration->safeUp();
    $second = $migration->safeUp();
    ob_end_clean();

    $db->getSchema()->refresh();

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue()
        ->and($db->columnExists('{{%comments_comments}}', 'editedAt'))->toBeTrue()
        ->and($db->columnExists('{{%comments_identities}}', 'emailOptOutAt'))->toBeTrue()
        ->and($db->columnExists('{{%comments_identities}}', 'emailConfirmationSentAt'))->toBeTrue();
});
