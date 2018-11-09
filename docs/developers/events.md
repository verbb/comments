# Events

Events can be used to extend the functionality of Comments.

## Comment related events

### The `beforeSaveComment` event

Plugins can get notified before a comment is saved. Event handlers can prevent the comment from getting sent by setting `$event->isValid` to false.

```php
use verbb\workflow\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, function(Event $e) {
    $comment = $event->sender;
    $event->isValid = false;
});
```

### The `afterSaveComment` event

Plugins can get notified after a comment has been saved

```php
use verbb\workflow\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_AFTER_SAVE, function(Event $e) {
    $comment = $event->sender;
});
```


## Flag related events

### The `beforeSaveFlag` event

Plugins can get notified before an flag is saved

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\FlagsService as Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_BEFORE_SAVE_FLAG, function(FlagEvent $e) {
    // Do something
});
```

### The `afterSaveFlag` event

Plugins can get notified after a flag has been saved

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\FlagsService as Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_AFTER_SAVE_FLAG, function(FlagEvent $e) {
    // Do something
});
```

### The `beforeDeleteFlag` event

Plugins can get notified before an flag is deleted

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\FlagsService as Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_BEFORE_DELETE_FLAG, function(FlagEvent $e) {
    // Do something
});
```

### The `afterDeleteFlag` event

Plugins can get notified after a flag has been deleted

```php
use verbb\comments\events\FlagEvent;
use verbb\comments\services\FlagsService as Flags;
use yii\base\Event;

Event::on(Flags::class, Flags::EVENT_AFTER_DELETE_FLAG, function(FlagEvent $e) {
    // Do something
});
```


## Vote related events

### The `beforeSaveVote` event

Plugins can get notified before an vote is saved

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\VotesService as Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_BEFORE_SAVE_VOTE, function(VoteEvent $e) {
    // Do something
});
```

### The `afterSaveVote` event

Plugins can get notified after a vote has been saved

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\VotesService as Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_AFTER_SAVE_VOTE, function(VoteEvent $e) {
    // Do something
});
```

### The `beforeDeleteVote` event

Plugins can get notified before an vote is deleted

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\VotesService as Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_BEFORE_DELETE_VOTE, function(VoteEvent $e) {
    // Do something
});
```

### The `afterDeleteVote` event

Plugins can get notified after a vote has been deleted

```php
use verbb\comments\events\VoteEvent;
use verbb\comments\services\VotesService as Votes;
use yii\base\Event;

Event::on(Votes::class, Votes::EVENT_AFTER_DELETE_VOTE, function(VoteEvent $e) {
    // Do something
});
```
