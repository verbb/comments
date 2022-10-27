# Events
Events can be used to extend the functionality of Comments.

## Comment related events

### The `beforeSaveComment` event
Plugins can get notified before a comment is saved. Event handlers can prevent the comment from getting sent by setting `$event->isValid` to false.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    $comment = $event->sender;
    $event->isValid = false;
});
```

### The `afterSaveComment` event
Plugins can get notified after a comment has been saved

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $comment = $event->sender;
});
```


## Flag related events

### The `beforeSaveFlag` event
Plugins can get notified before a flag is saved

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_BEFORE_SAVE_FLAG, function(FlagEvent $event) {
    // Do something
});
```

### The `afterSaveFlag` event
Plugins can get notified after a flag has been saved

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_AFTER_SAVE_FLAG, function(FlagEvent $event) {
    // Do something
});
```

### The `beforeDeleteFlag` event
Plugins can get notified before an flag is deleted

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_BEFORE_DELETE_FLAG, function(FlagEvent $event) {
    // Do something
});
```

### The `afterDeleteFlag` event
Plugins can get notified after a flag has been deleted

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_AFTER_DELETE_FLAG, function(FlagEvent $event) {
    // Do something
});
```


## Vote related events

### The `beforeSaveVote` event
Plugins can get notified before a vote is saved

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_BEFORE_SAVE_VOTE, function(VoteEvent $event) {
    // Do something
});
```

### The `afterSaveVote` event
Plugins can get notified after a vote has been saved

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_AFTER_SAVE_VOTE, function(VoteEvent $event) {
    // Do something
});
```

### The `beforeDeleteVote` event
Plugins can get notified before a vote is deleted

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_BEFORE_DELETE_VOTE, function(VoteEvent $event) {
    // Do something
});
```

### The `afterDeleteVote` event
Plugins can get notified after a vote has been deleted

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_AFTER_DELETE_VOTE, function(VoteEvent $event) {
    // Do something
});
```



## Notification related events

### The `beforeSendAuthorEmail` event
Plugins can get notified before the author's email is sent

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_AUTHOR_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendReplyEmail` event
Plugins can get notified before a reply email is sent

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_REPLY_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendModeratorEmail` event
Plugins can get notified before each moderator's email is sent

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_MODERATOR_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendModeratorApprovedEmail` event
Plugins can get notified before the moderator approved email is sent

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendSubscribeEmail` event
Plugins can get notified before a subscribed element's email is sent. This is the email that is sent to all subscribers of an element, when a comment is made.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_SUBSCRIBE_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendAdminEmail` event
Plugins can get notified before the admin email is sent

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_ADMIN_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```
