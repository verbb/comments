# Events
Comments provides a collection of events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to modify Comments’ behaviour. For events in the browser, see the [JavaScript API](docs:template-guides/javascript-api#browser-events).

## Comment Related Events

### The `beforeSaveComment` Event
The event that is triggered before a comment is saved, whether it's new or being edited. Event handlers can prevent the comment from being saved by setting `$event->isValid` to false.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    $comment = $event->sender;
    $event->isValid = false;
});
```

You can also use this event to add a spam check of your own, such as a call to a third-party spam service. Rather than refusing the comment, the example below keeps it out of sight as spam, so moderators can find it under **Spam** in the Comments index and mark it as not spam if your check was wrong. Comments decides where a comment sits from its `moderationState`, so set that as well as `status`. The `heldReason` is the reason moderators see, and `HeldReason::Protection` shows it as **Possible spam**. The visitor is told that their comment was submitted for moderation, the same as for any held comment.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    /** @var Comment $comment */
    $comment = $event->sender;

    // Only check new comments, not edits or a moderator's changes.
    if (!$event->isNew) {
        return;
    }

    // Replace this with a call to your own spam service.
    $isSpam = stripos((string)$comment->comment, 'cheap watches') !== false;

    if ($isSpam) {
        $comment->moderationState = ModerationState::Spam->value;
        $comment->status = Comment::STATUS_SPAM;
        $comment->heldReason = HeldReason::Protection->value;
    }
});
```

To hold a comment for review instead, use `ModerationState::Pending` and `Comment::STATUS_PENDING`, and it waits under **Needs review**. Either way, the check runs after Comments' own keyword filters and spam protection, and a commenter's **Trusted** review setting doesn't release it.

### The `afterSaveComment` Event
The event that is triggered after a comment has been saved. Moderation decisions change a comment's state without saving it, so use [`afterChangeState`](#the-afterchangestate-event) to follow those.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $comment = $event->sender;
});
```


## Notification Related Events

### The `afterNotify` Event
The event that is triggered with the people who have a user account and should hear about a comment, once the comment is in the state that notifies them. Comments doesn't email people with an account, so listen for this event to tell them through your site, a notifications plugin, or whatever channel suits you. The exception is the moderator group, which Comments also emails about the staff types when [Email the Moderator Group](docs:feature-tour/notifications-and-subscriptions#emailing-the-moderator-group) is on. The event runs from the same queue job as guest emails, after checking that the comment hasn't changed since it was queued. A waiting or edited comment must still be waiting, a reported one must still be published with an open report, and any new comment must not have been removed by a moderator or its author.

The event is a `NotifyEvent`, with the comment as `$event->comment`, the element it's on as `$event->element`, and the users to tell as `$event->recipients`. Each person appears once in an event. For the participant types, the comment's own author and anyone who has stopped comment emails aren't included, and each person is named in only one of them for a comment. The staff types name every member of the **Moderator User Group**. `$event->type` says what happened, as one of these `Notifications::TYPE_*` values:

| Type | Constant | Recipients |
| --- | --- | --- |
| `author` | `TYPE_AUTHOR` | The author of the element, about a new comment on it |
| `reply` | `TYPE_REPLY` | The author of the comment being replied to |
| `mention` | `TYPE_MENTION` | A person mentioned in the comment |
| `moderator-approved` | `TYPE_MODERATOR_APPROVED` | The comment's author, once it's approved |
| `subscribe` | `TYPE_SUBSCRIBE` | People following the discussion or the comment being replied to |
| `moderator` | `TYPE_MODERATOR` | The moderator group, about a comment waiting for review |
| `moderator-edit` | `TYPE_MODERATOR_EDIT` | The moderator group, about a waiting comment its author edited |
| `admin` | `TYPE_ADMIN` | The moderator group, about any new comment |
| `flag` | `TYPE_FLAG` | The moderator group, about a comment being reported |

The staff types leave out whoever caused them: the comment's author for `moderator`, `moderator-edit` and `admin`, and the person who made the report for `flag`.

Each type only fires while its [participant or staff notification](docs:feature-tour/notifications-and-subscriptions) is turned on, or for `subscribe`, while subscriptions are available. This example sends moderators a short email of your own about comments waiting for review, using Craft's mailer. If the wording in **Utilities** → **System Messages** is all you need, turn on **Email the Moderator Group** instead:

```php
use Craft;
use verbb\comments\events\NotifyEvent;
use verbb\comments\services\Notifications;
use yii\base\Event;

Event::on(Notifications::class, Notifications::EVENT_AFTER_NOTIFY, function(NotifyEvent $event) {
    if ($event->type !== Notifications::TYPE_MODERATOR) {
        return;
    }

    foreach ($event->recipients as $user) {
        Craft::$app->getMailer()->compose()
            ->setTo($user)
            ->setSubject('A comment is waiting for review')
            ->setTextBody($event->comment->getCpEditUrl())
            ->send();
    }
});
```

### Email Events
The events below are for emails Comments sends to guests, and to the moderator group when **Email the Moderator Group** is on. Each receives an `EmailEvent`, with the message as `$event->mail`, the recipient as `$event->user`, the element as `$event->element` and the comment as `$event->comment`. You can change the message, or set `$event->isValid` to `false` to stop it being sent to that person. The guest confirmation email doesn't have an event.

### The `beforeSendReplyEmail` Event
The event that is triggered before a guest is emailed about a reply to their comment.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_REPLY_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendModeratorApprovedEmail` Event
The event that is triggered before a guest is emailed that their pending comment was approved.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendSubscribeEmail` Event
The event that is triggered before each guest subscriber is emailed about a new comment on a discussion they follow.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_SUBSCRIBE_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendStaffEmail` Event
The event that is triggered before each member of the moderator group is emailed about a staff notification, when **Email the Moderator Group** is on. This example leaves admins out, because they already keep an eye on the control panel:

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_STAFF_EMAIL, function(EmailEvent $event) {
    if ($event->user->admin) {
        $event->isValid = false;
    }
});
```

## Moderation Related Events

### The `afterChangeState` Event
The event that is triggered after a comment's moderation state changes, such as when it's approved, removed or marked as spam, from the control panel, an element's Comments field, the site or GraphQL. The change doesn't save the comment's element, so `Comment::EVENT_AFTER_SAVE` doesn't fire for it. When a moderator makes a decision on the comment's page, any edits are saved first, which does fire the save events.

```php
use verbb\comments\events\ModerationEvent;
use verbb\comments\services\Moderation;
use yii\base\Event;

Event::on(Moderation::class, Moderation::EVENT_AFTER_CHANGE_STATE, function(ModerationEvent $event) {
    $comment = $event->comment;
    $previousState = $event->previousState;
    $state = $event->state;
    $actor = $event->actor;
});
```
