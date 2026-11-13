# Events

Comments provides events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to change Comments’ behaviour.

Comments are Craft elements, so the element events Craft triggers for every element type also fire for comments. The two most useful are listed under Comment Events. Flags, votes and subscriptions are plain records managed by their own services, and none of their events can be cancelled.

## Comment Events

### The `beforeSave` Event

The event that is triggered before a comment is saved, whether it’s a new comment posted from the front end or an existing one edited in the control panel.

`$event->sender` is the comment and `$event->isNew` tells you whether it’s being created. You can change the comment’s attributes before they’re stored. To stop the save, set `$event->isValid` to `false`. The comment is then not saved, and no notification emails are sent.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    /** @var Comment $comment */
    $comment = $event->sender;

    // Reject comments that contain a link
    if (str_contains((string)$comment->comment, 'http')) {
        $event->isValid = false;
    }
});
```

### The `afterSave` Event

The event that is triggered after a comment has been saved. Comments sends any notification emails for the comment before this event fires.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    /** @var Comment $comment */
    $comment = $event->sender;
    $isNew = $event->isNew;
});
```

## Flag Events

### The `beforeSaveFlag` Event

The event that is triggered before a flag is saved, when someone flags a comment.

`$event->flag` is the [Flag](docs:reference/flag) model and `$event->isNew` tells you whether it’s new. The flag is validated and written after this event, so changes you make to its attributes are saved. The event can’t prevent the flag from being saved.

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_BEFORE_SAVE_FLAG, function(FlagEvent $event) {
    $flag = $event->flag;
    $isNew = $event->isNew;
});
```

### The `afterSaveFlag` Event

The event that is triggered after a flag has been saved. `$event->flag->id` holds the new flag’s ID.

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_AFTER_SAVE_FLAG, function(FlagEvent $event) {
    $comment = $event->flag->getComment();
});
```

### The `beforeDeleteFlag` Event

The event that is triggered before a flag is deleted. Flagging a comment a second time removes the earlier flag, which deletes it. The event can’t prevent the deletion.

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_BEFORE_DELETE_FLAG, function(FlagEvent $event) {
    $flag = $event->flag;
});
```

### The `afterDeleteFlag` Event

The event that is triggered after a flag has been deleted.

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_AFTER_DELETE_FLAG, function(FlagEvent $event) {
    $commentId = $event->flag->commentId;
});
```

## Vote Events

### The `beforeSaveVote` Event

The event that is triggered before a vote is saved, when someone upvotes or downvotes a comment, or changes their vote.

`$event->vote` is the [Vote](docs:reference/vote) model and `$event->isNew` tells you whether it’s new. The vote is validated and written after this event, so changes you make to its attributes are saved. The event can’t prevent the vote from being saved.

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_BEFORE_SAVE_VOTE, function(VoteEvent $event) {
    $vote = $event->vote;
    $isUpvote = (bool)$vote->upvote;
});
```

### The `afterSaveVote` Event

The event that is triggered after a vote has been saved.

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_AFTER_SAVE_VOTE, function(VoteEvent $event) {
    $comment = $event->vote->getComment();
    $total = $comment?->getVotes();
});
```

### The `beforeDeleteVote` Event

The event that is triggered before a vote is deleted with `Votes::deleteVote()`. Comments itself changes votes rather than deleting them, so this fires only when your own code or another plugin deletes a vote. The event can’t prevent the deletion.

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_BEFORE_DELETE_VOTE, function(VoteEvent $event) {
    $vote = $event->vote;
});
```

### The `afterDeleteVote` Event

The event that is triggered after a vote has been deleted with `Votes::deleteVote()`.

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_AFTER_DELETE_VOTE, function(VoteEvent $event) {
    $commentId = $event->vote->commentId;
});
```

## Subscription Events

### The `beforeSaveSubscribe` Event

The event that is triggered before a subscription is saved. This happens when someone subscribes or unsubscribes from an element’s or thread’s notifications, and when a commenter is subscribed automatically.

`$event->subscribe` is the subscription model, with `ownerId`, `ownerSiteId`, `userId`, `commentId` and `subscribed`. `$event->isNew` tells you whether it’s new. The subscription is validated and written after this event, so changes you make to its attributes are saved. The event can’t prevent the subscription from being saved.

```php
use verbb\comments\events\SubscribeEvent;
use verbb\comments\services\Subscribe;
use yii\base\Event;

Event::on(Subscribe::class, Subscribe::EVENT_BEFORE_SAVE_SUBSCRIBE, function(SubscribeEvent $event) {
    $subscribe = $event->subscribe;
    $isSubscribing = $subscribe->subscribed;
});
```

### The `afterSaveSubscribe` Event

The event that is triggered after a subscription has been saved.

```php
use verbb\comments\events\SubscribeEvent;
use verbb\comments\services\Subscribe;
use yii\base\Event;

Event::on(Subscribe::class, Subscribe::EVENT_AFTER_SAVE_SUBSCRIBE, function(SubscribeEvent $event) {
    $userId = $event->subscribe->userId;
    $ownerId = $event->subscribe->ownerId;
});
```

### The `beforeDeleteSubscribe` Event

The event that is triggered before a subscription is deleted with `Subscribe::deleteSubscribe()`. Unsubscribing saves the subscription with `subscribed` set to `false` instead of deleting it, so this fires only when your own code or another plugin deletes one. The event can’t prevent the deletion.

```php
use verbb\comments\events\SubscribeEvent;
use verbb\comments\services\Subscribe;
use yii\base\Event;

Event::on(Subscribe::class, Subscribe::EVENT_BEFORE_DELETE_SUBSCRIBE, function(SubscribeEvent $event) {
    $subscribe = $event->subscribe;
});
```

### The `afterDeleteSubscribe` Event

The event that is triggered after a subscription has been deleted with `Subscribe::deleteSubscribe()`.

```php
use verbb\comments\events\SubscribeEvent;
use verbb\comments\services\Subscribe;
use yii\base\Event;

Event::on(Subscribe::class, Subscribe::EVENT_AFTER_DELETE_SUBSCRIBE, function(SubscribeEvent $event) {
    $userId = $event->subscribe->userId;
});
```

## Notification Events

Each notification event receives an `EmailEvent`. `$event->mail` is the rendered `craft\mail\Message` about to be sent, so you can change its subject, recipients or body. `$event->comment` is the comment, `$event->element` is the element it was made on and `$event->user` is the recipient. Set `$event->isValid` to `false` to stop that one email. Other recipients and other notifications are unaffected.

### The `beforeSendAuthorEmail` Event

The event that is triggered before the email telling an element’s author about a new comment is sent. For an asset, the author is the person who uploaded it.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_AUTHOR_EMAIL, function(EmailEvent $event) {
    $event->mail->setSubject('New comment on ' . $event->element->title);
});
```

### The `beforeSendReplyEmail` Event

The event that is triggered before the email telling a commenter that someone replied to their comment is sent.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_REPLY_EMAIL, function(EmailEvent $event) {
    // Don't send reply notifications to staff
    if (str_ends_with((string)$event->user->email, '@example.com')) {
        $event->isValid = false;
    }
});
```

### The `beforeSendModeratorEmail` Event

The event that is triggered before each moderator is emailed about a comment waiting for moderation. It also fires for the email sent when a pending comment is edited. Moderators are the members of the user group chosen in the plugin settings, and the event fires once per moderator.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_MODERATOR_EMAIL, function(EmailEvent $event) {
    $moderator = $event->user;
    $comment = $event->comment;
});
```

### The `beforeSendModeratorApprovedEmail` Event

The event that is triggered before the email telling a commenter that a moderator approved their comment is sent.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL, function(EmailEvent $event) {
    $event->isValid = false;
});
```

### The `beforeSendSubscribeEmail` Event

The event that is triggered before a subscriber is emailed about a new comment on an element or thread they follow. It fires once per subscriber.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_SUBSCRIBE_EMAIL, function(EmailEvent $event) {
    $subscriber = $event->user;
    $element = $event->element;
});
```

### The `beforeSendAdminEmail` Event

The event that is triggered before each admin notification address is emailed about a new comment. `$event->user` is the address’s row from the plugin settings, an array with an `email` key, rather than a user element.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_ADMIN_EMAIL, function(EmailEvent $event) {
    $address = $event->user['email'];
});
```

### The `beforeSendFlagEmail` Event

The event that is triggered before each admin notification address is emailed about a flagged comment. As with the admin email, `$event->user` is an array with an `email` key.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_BEFORE_SEND_FLAG_EMAIL, function(EmailEvent $event) {
    $flaggedComment = $event->comment;
});
```
