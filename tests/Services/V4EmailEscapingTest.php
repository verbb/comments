<?php
use craft\models\SystemMessage;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;

/**
 * A system message customised to show what a commenter wrote: the HTML part shows their markup as
 * text, as Craft's own emails do, the text part keeps it as written, and the way to stop the emails
 * is in both.
 */

const EMAIL_ESCAPING_BODY = "Hi {{ user.friendlyName }},\n\n{{ comment.authorName }} commented on “{{ element.title }}”:\n\n{{ comment.excerpt }}\n\nFull text: {{ comment.comment }}";

function emailEscapingMailer(): object
{
    $recorder = new class () {
        public array $messages = [];

        public function to(string $email): ?craft\mail\Message
        {
            foreach ($this->messages as $message) {
                if (array_key_exists($email, (array)$message->getTo())) {
                    return $message;
                }
            }

            return null;
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

function emailEscapingGuestReply($entry, Comment $parent): Comment
{
    $user = Craft::$app->getUser();
    $signedIn = $user->getIdentity();
    $user->setIdentity(null);
    Comments::$plugin->getConversations()->clearCaches();

    try {
        return Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'parentId' => $parent->id,
            'content' => 'Nice post <a href="https://example.test">click</a><img src="https://example.test/p.gif">',
            'name' => '<b>Mallory</b> Guest',
            'email' => 'mallory@example.test',
        ]))->comment;
    } finally {
        $user->setIdentity($signedIn);
    }
}

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->getAttributes();
    $this->originalMailer = Craft::$app->getMailer();
    $settings->allowGuest = true;
    $settings->requireModeration = false;
    $settings->enableSpamChecks = false;
    $settings->useQueueForNotifications = false;
    $settings->subscriptionTypes = [];
    $settings->participantNotifications = [Settings::NOTIFICATION_REPLY];
    $settings->staffNotifications = [Settings::NOTIFICATION_STAFF_NEW];
    $settings->notificationRecipients = [['email' => 'staff-escaping@example.test', 'enabled' => true]];
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Craft::$app->set('mailer', $this->originalMailer);
    Craft::$app->getUser()->setIdentity($this->admin);
    Craft::$app->getDb()->createCommand()->delete('{{%systemmessages}}', ['key' => ['comments_admin_notification', 'comments_reply_notification']])->execute();
});

it('escapes what a commenter wrote in the HTML part of a customised message, not in the text part', function() {
    foreach (['comments_admin_notification', 'comments_reply_notification'] as $key) {
        expect(Craft::$app->getSystemMessages()->saveMessage(new SystemMessage(['key' => $key, 'subject' => 'New comment', 'body' => EMAIL_ESCAPING_BODY]), 'en-US'))->toBeTrue();
    }

    $entry = CommentsFixtureFactory::entry('Email escaping ' . uniqid());
    $parent = Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => 'Parent', 'userId' => (int)$this->admin->id]))->comment;
    $mail = emailEscapingMailer();
    emailEscapingGuestReply($entry, $parent);

    $staff = $mail->to('staff-escaping@example.test');
    $reply = $mail->to((string)$this->admin->email);

    expect($staff)->not->toBeNull()->and($reply)->not->toBeNull();

    foreach ([$staff, $reply] as $message) {
        $html = (string)$message->getSymfonyEmail()->getHtmlBody();
        $text = (string)$message->getSymfonyEmail()->getTextBody();

        expect($html)->toContain('&lt;b&gt;Mallory&lt;/b&gt; Guest')
            ->and($html)->not->toContain('<b>Mallory</b>')
            ->and($html)->not->toContain('<img')
            ->and($html)->not->toContain('<a href="https://example.test"')
            ->and($text)->toContain('<b>Mallory</b> Guest')
            ->and($text)->toContain('<a href="https://example.test">click</a>');
    }

    // The reply email to a member carries the way to stop emails in both parts.
    expect((string)$reply->getSymfonyEmail()->getTextBody())->toMatch('/\[Unsubscribe\]\(https?:\/\/[^)]+\)/')
        ->and((string)$reply->getSymfonyEmail()->getHtmlBody())->toMatch('/<a href="https?:\/\/[^"]+">Unsubscribe<\/a>/');
});
