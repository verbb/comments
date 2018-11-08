# Events Reference

To learn more about how events work, see the [Craft documentation on events](http://buildwithcraft.com/docs/plugins/hooks-and-events#events).

#### onBeforeSaveComment

Raised before a comment is saved. Event handlers can prevent the comment from getting saved by setting `$event->performAction` to false.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that is about to be saved.

```php
craft()->on('comments.onBeforeSaveComment', function($event) {
    $comment = $event->params['comment'];
    $event->performAction = false;
});
```

#### onSaveComment

Raised after a comment has been saved.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that has been saved.

```php
craft()->on('comments.onSaveComment', function($event) {
    $comment = $event->params['comment'];
});
```

#### onBeforeTrashComment

Raised before a comment is 'trashed'. Event handlers can prevent the comment from getting trashed by setting `$event->performAction` to false.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that is about to be trashed.

```php
craft()->on('comments.onBeforeTrashComment', function($event) {
    $comment = $event->params['comment'];
    $event->performAction = false;
});
```

#### onTrashComment

Raised after a comment has been 'trashed'.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that has been trashed.

```php
craft()->on('comments.onTrashComment', function($event) {
    $comment = $event->params['comment'];
});
```

#### onBeforeFlagComment

Raised before a comment is flagged. Event handlers can prevent the comment from getting flagged by setting `$event->performAction` to false.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that is about to be flagged.

```php
craft()->on('comments_flag.onBeforeFlagComment', function($event) {
    $comment = $event->params['comment'];
    $event->performAction = false;
});
```

#### onFlagComment

Raised after a comment has been flagged.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that has been flagged.

```php
craft()->on('comments_flag.onFlagComment', function($event) {
    $comment = $event->
    params['comment'];
});
```

#### onBeforeVoteComment

Raised before a comment is voted on. Event handlers can prevent the comment from getting voted on by setting `$event->performAction` to false.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that is about to be voted on.

```php
craft()->on('comments_vote.onBeforeVoteComment', function($event) {
    $comment = $event->params['comment'];
    $event->performAction = false;
});
```

#### onVoteComment

Raised after a comment has been voted on.

Params: - comment – The [Comment](/craft-plugins/comments/docs/developers/comment) that has been voted on.

```php
craft()->on('comments_vote.onVoteComment', function($event) {
    $comment = $event->params['comment'];
});
```
