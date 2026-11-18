<?php
use craft\elements\User;
use craft\models\UserGroup;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\queue\jobs\SendNotification;
use verbb\comments\services\Moderation;
use verbb\comments\services\Notifications;

/**
 * Staff email, as in Comments 3: comments waiting for review and edits to them go to the moderator group,
 * and new comments and reports go to the Notification Recipients addresses.
 */

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $this->originalMailer = Craft::$app->getMailer();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = true;
    $settings->allowFlagging = true;
    $settings->participantNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
    $settings->notificationRecipients = [['email' => 'staff-list@example.test', 'enabled' => true]];
    $settings->staffNotifications = [
        Settings::NOTIFICATION_MODERATOR_PENDING,
        Settings::NOTIFICATION_MODERATOR_EDITED,
        Settings::NOTIFICATION_STAFF_NEW,
        Settings::NOTIFICATION_STAFF_REPORTED,
    ];

    $group = new UserGroup(['name' => 'Staff email ' . bin2hex(random_bytes(3)), 'handle' => 'staffEmail' . bin2hex(random_bytes(3))]);
    Craft::$app->getUserGroups()->saveGroup($group, false);
    $settings->moderatorUserGroup = $group->uid;

    $this->moderators = [staffEmailUser('moderatorOne'), staffEmailUser('moderatorTwo')];

    foreach ($this->moderators as $moderator) {
        Craft::$app->getUsers()->assignUserToGroups((int)$moderator->id, [(int)$group->id]);
    }

    $this->poster = staffEmailUser('poster');
    $this->mail = staffEmailMailer();
});

afterEach(function() {
    if (isset($this->cleanup)) {
        ($this->cleanup)();
    }

    yii\base\Event::off(Notifications::class, Notifications::EVENT_AFTER_NOTIFY);
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Craft::$app->set('mailer', $this->originalMailer);
    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getIdentities()->clearCaches();
});

function staffEmailUser(string $prefix): User
{
    return CommentsFixtureFactory::user($prefix . bin2hex(random_bytes(3)));
}

function staffEmailMailer(): object
{
    $recorder = new class () {
        public array $messages = [];

        public function to(string $email): array
        {
            return array_values(array_filter($this->messages, static fn($message) => array_key_exists($email, (array)$message->getTo())));
        }

        public function subjectsTo(string $email): array
        {
            return array_map(static fn($message): string => (string)$message->getSubject(), $this->to($email));
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

function staffEmailEvents(): object
{
    $recorder = new class () {
        public array $events = [];
    };

    yii\base\Event::on(Notifications::class, Notifications::EVENT_AFTER_NOTIFY, static function($event) use ($recorder) {
        $recorder->events[] = $event;
    });

    return $recorder;
}

function staffEmailPost($entry, User $author, string $content, ?int $parentId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)$author->id,
        'parentId' => $parentId,
    ], static fn($value) => $value !== null)))->comment;
}

function staffEmailReport(Comment $comment): void
{
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam');
}

function staffEmailRunJob(Comment $comment, string $type): void
{
    (new SendNotification(['commentId' => (int)$comment->id, 'siteId' => (int)$comment->siteId, 'type' => $type]))
        ->execute(Craft::$app->getQueue());
}

it('emails staff only for the staff notifications that are chosen', function() {
    $entry = CommentsFixtureFactory::entry('Staff email switch');
    $events = staffEmailEvents();
    Comments::$plugin->getSettings()->staffNotifications = [];

    staffEmailPost($entry, $this->poster, 'Posted with staff notifications off');

    expect($this->mail->messages)->toBe([])
        ->and($events->events)->toBe([]);

    Comments::$plugin->getSettings()->staffNotifications = [Settings::NOTIFICATION_MODERATOR_PENDING, Settings::NOTIFICATION_STAFF_NEW];
    staffEmailPost($entry, $this->poster, 'Posted with staff notifications on');

    foreach ($this->moderators as $moderator) {
        expect($this->mail->subjectsTo($moderator->email))->toBe(['A comment is waiting for review on Plugin Tests']);
    }

    expect($this->mail->subjectsTo('staff-list@example.test'))->toBe(['"' . $entry->title . '" has a new comment on Plugin Tests'])
        ->and($this->mail->messages)->toHaveCount(3)
        ->and($this->mail->to($this->poster->email))->toBeEmpty();
});

it('emails the group about waiting and edited comments and the list about new and reported ones, as the event names them', function() {
    $entry = CommentsFixtureFactory::entry('Every staff email');
    $events = staffEmailEvents();

    // Wrapped in the site's HTML Email Template, like guest emails.
    $templatePath = Craft::$app->getPath()->getSiteTemplatesPath() . '/_staff-email-test.twig';
    file_put_contents($templatePath, '<div class="staff-email-wrap">{{ body }}</div>');
    Comments::$plugin->getSettings()->templateEmail = '_staff-email-test';
    $this->cleanup = static fn() => @unlink($templatePath);

    // New and waiting for review.
    $comment = staffEmailPost($entry, $this->poster, 'Waiting for a moderator');

    // Edited by its author while it waits.
    Craft::$app->getUser()->setIdentity($this->poster);
    $comment->setScenario(Comment::SCENARIO_FRONT_END);
    $comment->comment = 'Waiting, and edited';
    $saved = Craft::$app->getElements()->saveElement($comment);
    Craft::$app->getUser()->setIdentity($this->admin);

    expect($saved)->toBeTrue(json_encode($comment->getErrors()));

    // Approved, then reported.
    Comments::$plugin->getModeration()->approve($comment);
    staffEmailReport($comment);

    foreach ($this->moderators as $moderator) {
        expect($this->mail->subjectsTo($moderator->email))->toBe([
            'A comment is waiting for review on Plugin Tests',
            'A comment waiting for review was edited on Plugin Tests',
        ]);
    }

    expect($this->mail->subjectsTo('staff-list@example.test'))->toBe([
        '"' . $entry->title . '" has a new comment on Plugin Tests',
        'A comment on "' . $entry->title . '" was reported on Plugin Tests',
    ]);

    // Exactly the people each event named, and the message links to the comment in the control panel.
    $named = [];

    foreach ($events->events as $event) {
        foreach ($event->recipients as $recipient) {
            $named[] = $recipient->email;
        }
    }

    $mailed = [];

    foreach ($this->mail->messages as $message) {
        $mailed = [...$mailed, ...array_keys((array)$message->getTo())];
    }

    sort($named);
    sort($mailed);

    expect(array_map(static fn($event): string => $event->type, $events->events))->toBe(['admin', 'moderator', 'moderator-edit', 'flag'])
        ->and($mailed)->toBe($named)
        ->and($this->mail->messages[0]->getSymfonyEmail()->getHtmlBody())->toContain('<div class="staff-email-wrap">')
        ->and($this->mail->messages[0]->getSymfonyEmail()->getTextBody())->toContain($comment->getCpEditUrl())->not->toContain('Unsubscribe');
});

it('emails authors, replied-to commenters and followers with an account alongside staff', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [Settings::NOTIFICATION_OWNER, Settings::NOTIFICATION_REPLY];
    $settings->subscriptionTypes = ['conversation'];

    // The fixture's entries are written by the signed-in admin, who isn't in the moderator group.
    $entryAuthor = $this->admin;
    $entry = CommentsFixtureFactory::entry('Participants are emailed');

    $follower = staffEmailUser('follower');
    $member = staffEmailUser('member');
    $parent = staffEmailPost($entry, $member, 'A member’s comment');
    Comments::$plugin->getSubscriptions()->subscribeToConversation(
        (int)$parent->conversationId,
        (int)Comments::$plugin->getIdentities()->getOrCreateForUser($follower)->id,
    );

    $this->mail->messages = [];
    $events = staffEmailEvents();
    staffEmailPost($entry, $this->poster, 'A reply to the member', (int)$parent->id);

    expect(array_map(static fn($event): string => $event->type, $events->events))->toContain('admin', 'author', 'reply', 'subscribe')
        ->and($this->mail->subjectsTo($entryAuthor->email))->toBe(['"' . $entry->title . '" has received a comment on Plugin Tests.'])
        ->and($this->mail->subjectsTo($member->email))->toBe(['Someone has replied to your comment on Plugin Tests.'])
        ->and($this->mail->subjectsTo($follower->email))->toBe(['A new comment has been made on ' . $entry->title])
        ->and($this->mail->to($this->poster->email))->toBeEmpty()
        ->and($this->mail->subjectsTo('staff-list@example.test'))->toBe(['"' . $entry->title . '" has a new comment on Plugin Tests']);

    // Nothing is waiting, so the group isn't emailed.
    foreach ($this->moderators as $moderator) {
        expect($this->mail->to($moderator->email))->toBeEmpty();
    }
});

it('does not email staff about a comment that has changed or gone since it was queued', function() {
    Comments::$plugin->getSettings()->useQueueForNotifications = true;
    $entry = CommentsFixtureFactory::entry('Stale staff email');
    $moderation = Comments::$plugin->getModeration();
    $first = $this->moderators[0]->email;

    // Still waiting when the job runs, so it's sent.
    $waiting = staffEmailPost($entry, $this->poster, 'Still waiting');
    staffEmailRunJob($waiting, Notifications::TYPE_MODERATOR);

    expect($this->mail->to($first))->toHaveCount(1);

    $this->mail->messages = [];

    // Approved before the waiting and edited emails went out.
    $approved = staffEmailPost($entry, $this->poster, 'Approved in the meantime');
    $moderation->approve($approved);
    staffEmailRunJob($approved, Notifications::TYPE_MODERATOR);
    staffEmailRunJob($approved, Notifications::TYPE_MODERATOR_EDIT);

    // Removed by a moderator, or deleted, before the new-comment email went out.
    $removed = staffEmailPost($entry, $this->poster, 'Removed in the meantime');
    $moderation->remove($removed);
    staffEmailRunJob($removed, Notifications::TYPE_ADMIN);

    $deleted = staffEmailPost($entry, $this->poster, 'Deleted in the meantime');
    Craft::$app->getElements()->deleteElement($deleted);
    staffEmailRunJob($deleted, Notifications::TYPE_ADMIN);

    // Reported, then kept, so there's no open report left.
    $kept = staffEmailPost($entry, $this->poster, 'Kept in the meantime');
    $moderation->approve($kept);
    staffEmailReport($kept);
    $moderation->decide($kept, Moderation::DECISION_KEEP);
    staffEmailRunJob($kept, Notifications::TYPE_FLAG);

    expect($this->mail->messages)->toBe([]);
});

it('leaves out whoever caused a staff notification, in the event and the email', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    // Listed addresses that belong to accounts are those users.
    $settings->notificationRecipients = array_map(
        static fn(User $moderator): array => ['email' => $moderator->email, 'enabled' => true],
        $this->moderators,
    );
    $entry = CommentsFixtureFactory::entry('Own staff notifications');
    [$one, $two] = $this->moderators;
    $events = staffEmailEvents();

    // A moderator's own comment only goes to the rest of the list.
    staffEmailPost($entry, $one, 'A moderator’s own comment');

    expect(array_map(static fn($event): string => $event->type, $events->events))->toBe(['admin'])
        ->and(array_map(static fn($user): int => (int)$user->id, $events->events[0]->recipients))->toBe([(int)$two->id])
        ->and($this->mail->to($one->email))->toBeEmpty()
        ->and($this->mail->to($two->email))->toHaveCount(1);

    // A moderator's own report likewise, including when it goes through the queue.
    $comment = staffEmailPost($entry, $this->poster, 'Reported by a moderator');
    $events->events = [];
    $this->mail->messages = [];
    $settings->useQueueForNotifications = true;
    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();

    Comments::$plugin->getReports()->create(
        (int)$comment->id,
        (int)Comments::$plugin->getIdentities()->getOrCreateForUser($two)->id,
        'spam',
    );

    $jobs = array_map(
        static fn($row) => unserialize(is_resource($row) ? stream_get_contents($row) : $row),
        (new craft\db\Query())->select('job')->from('{{%queue}}')->column(),
    );
    $flagJobs = array_values(array_filter($jobs, static fn($job): bool => $job instanceof SendNotification && $job->type === Notifications::TYPE_FLAG));

    expect($flagJobs)->toHaveCount(1)
        ->and($flagJobs[0]->actorUserId)->toBe((int)$two->id);

    $flagJobs[0]->execute(Craft::$app->getQueue());

    expect(array_map(static fn($user): int => (int)$user->id, $events->events[0]->recipients))->toBe([(int)$one->id])
        ->and($this->mail->to($one->email))->toHaveCount(1)
        ->and($this->mail->to($two->email))->toBeEmpty();
});
