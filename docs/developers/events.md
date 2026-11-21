# Events

Comments provides events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to change Comments’ behaviour.

## Comment Events

<span id="the-beforesavecomment-event"></span>

### The `beforeSave` Event
The event that is triggered before a comment is saved, whether it's new or being edited. This is Craft's own element event, `Comment::EVENT_BEFORE_SAVE`, which every comment save goes through. Event handlers can prevent the comment from being saved by setting `$event->isValid` to false.

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

<span id="the-aftersavecomment-event"></span>

### The `afterSave` Event
The event that is triggered after a comment has been saved, as Craft's element event `Comment::EVENT_AFTER_SAVE`. Moderation decisions change a comment's state without saving it, so use [`afterChangeState`](#the-afterChangeState-event) to follow those.

```php
use craft\events\ModelEvent;
use verbb\comments\elements\Comment;
use yii\base\Event;

Event::on(Comment::class, Comment::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $comment = $event->sender;
});
```


### The `defineBadges` Event
The event that is triggered when Comments works out the badges shown beside a commenter's name, after its own owner and staff badges. Add your own, such as “Verified buyer”, to `$event->badges`, each as an array with a `type` and a `label`. The thread shows the `label` in a `comments__badge` element with a `comments__badge--{type}` class you can style, and leaves out any badge without both. `$event->comment` is the comment, and `$event->comments` holds every comment being drawn with it, so you can look them all up in one query instead of one per comment. Guests' comments go through the event too.

```php
use verbb\comments\events\DefineBadgesEvent;
use verbb\comments\services\Comments;
use yii\base\Event;

Event::on(Comments::class, Comments::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event) {
    $user = $event->comment->getUser();

    if ($user && $user->isInGroup('customers')) {
        $event->badges[] = ['type' => 'customer', 'label' => 'Verified buyer'];
    }
});
```

Badges appear in the thread and in every data API and GraphQL response that describes a comment's author. When `$event->isPublic` is `true`, they're cached and served to everyone, and the event runs with nobody signed in, so base them only on the commenter and never on who's viewing the page. When `isPublic` is `false`, the event is working out the badges for the **Posting as** line above the form for someone signed in, and `$event->comment` is a new, unsaved comment by them on the element.

## Data API Events

### The `defineCommentData` Event
The event that is triggered when the data API puts comments in a response, so you can add your own data to each one. It runs once for all of a response's comments, given in `$event->comments` keyed by their UID, with the element they're on as `$event->owner` and its site as `$event->siteId`. Set `$event->custom` to an array keyed by comment UID, and each comment's data appears as that comment's `custom` property, where a [part override](https://docs.verbb.io/comments/browser/part-overrides) can draw it. [Add Your Own Data to Comments](docs:user-guides/forms-data/add-your-own-data-to-comments) works through an example. Anything that isn't a non-empty array is left out.

This example adds the job title from a **Job Title** field (`jobTitle`) on each commenter's user account, looking the users up together:

```php
use craft\elements\User;
use verbb\comments\elements\Comment;
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\services\Api;
use yii\base\Event;

Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event) {
    $userIds = array_unique(array_filter(array_map(fn(Comment $comment) => $comment->userId, $event->comments)));

    if (!$userIds) {
        return;
    }

    $users = User::find()->id(array_values($userIds))->indexBy('id')->all();

    foreach ($event->comments as $uid => $comment) {
        $jobTitle = $users[$comment->userId]->jobTitle ?? null;

        if ($jobTitle) {
            $event->custom[$uid] = ['jobTitle' => $jobTitle];
        }
    }
});
```

When `$event->isPublic` is `true`, as it is for the `thread`, `replies`, `comment` and `structured-data` responses, what you add is cached and served to everyone, and the event runs with nobody signed in. It must never depend on who's asking, and must never include a commenter's email or IP address, an ID that identifies them, or the value of a custom field that's **Not shown**, which is meant to stay private. In Dev Mode, Comments logs a warning in Craft's log when it finds one of these. When `isPublic` is `false`, the comments are going to one person, such as their own comment waiting for review or a moderator's held comments, and nothing is cached.

### The `defineViewerData` Event
The event that is triggered when the data API builds its `viewer` response, which describes what the person viewing a thread can do and is never cached. Use it for data that belongs to that person, such as whether they've bought the product the page is about. `$event->user` is the person, or `null` for a guest, `$event->owner` and `$event->siteId` are the element and its site, and `$event->comments` holds the comments the response covers, keyed by UID. Set `$event->custom` to add to the response's own `custom`, and `$event->commentCustom`, keyed by comment UID, to add to the `custom` of those comments' entries in the response.

```php
use verbb\comments\events\DefineViewerDataEvent;
use verbb\comments\services\Api;
use yii\base\Event;

Event::on(Api::class, Api::EVENT_DEFINE_VIEWER_DATA, function(DefineViewerDataEvent $event) {
    $event->custom = [
        'isCustomer' => $event->user?->isInGroup('customers') ?? false,
    ];
});
```

## Custom Field Events

### The `registerFormFieldTypes` Event
The event that is triggered when Comments gathers the descriptions of field types that can be collected on the site’s comment form and shown on comments. Add a description for your own field type here. [Custom Form Field Types](docs:developers/custom-form-field-types) explains how to write one.

```php
use modules\ratings\fields\StarRating;
use modules\ratings\formfields\StarRatingType;
use verbb\comments\events\RegisterFormFieldTypesEvent;
use verbb\comments\services\FormFields;
use yii\base\Event;

Event::on(FormFields::class, FormFields::EVENT_REGISTER_FORM_FIELD_TYPES, function(RegisterFormFieldTypesEvent $event) {
    $event->types[StarRating::class] = StarRatingType::class;
});
```

## Notification Events

### The `afterNotify` Event
The event that is triggered after Comments has emailed everyone a notification is for, naming them all. Use it to tell people through another channel as well, such as an in-app notice or a Slack message. It names everyone the notification was for, whether or not a [`beforeSend…Email` handler](#email-events) stopped their email, so cancelling an email there and listening here is how you replace an email with your own delivery. The event runs from the same queue job as the emails, after checking that the comment hasn't changed since it was queued. A waiting or edited comment must still be waiting, a reported one must still be published with an open report, and any new comment must not have been removed by a moderator or its author.

The event is a `NotifyEvent`, with the comment as `$event->comment`, the element it's on as `$event->owner`, and the people told as `$event->recipients`, an array of users. Guests and **Notification Recipients** addresses that don't belong to an account are users without an ID, holding only an email address and, for a guest, a name. Each person appears once in an event. For the participant types, the comment's own author and anyone who has stopped comment emails aren't included, and each person is told in only one of them for a comment. `$event->type` says what happened, as one of these `Notifications::TYPE_*` values:

| Type | Constant | Recipients | Turned on by |
| --- | --- | --- | --- |
| `author` | `TYPE_AUTHOR` | The author of the element, about a new comment on it | `owner` in `participantNotifications` |
| `reply` | `TYPE_REPLY` | The author of the comment being replied to | `reply` in `participantNotifications` |
| `mention` | `TYPE_MENTION` | A person mentioned in the comment | `mention` in `participantNotifications` |
| `moderator-approved` | `TYPE_MODERATOR_APPROVED` | The comment's author, once it's approved | `approved` in `participantNotifications` |
| `subscribe` | `TYPE_SUBSCRIBE` | People following the discussion or the comment being replied to | `subscriptionTypes` |
| `moderator` | `TYPE_MODERATOR` | The moderator group, about a comment waiting for review | `moderator-pending` in `staffNotifications` |
| `moderator-edit` | `TYPE_MODERATOR_EDIT` | The moderator group, about a waiting comment its author edited | `moderator-edited` in `staffNotifications` |
| `admin` | `TYPE_ADMIN` | The **Notification Recipients**, about any new comment | `staff-new` in `staffNotifications` |
| `flag` | `TYPE_FLAG` | The **Notification Recipients**, about a comment being reported | `staff-reported` in `staffNotifications` |

Each type matches the key of its email in **Utilities** → **System Messages**, so `admin` goes with `comments_admin_notification` and `flag` with `comments_flag_notification`. The [settings](docs:get-started/configuration#participantNotifications) use their own values, which don't always match the type, so check the last column when you match a type to the setting that turns it on.

The staff types leave out whoever caused them, which is the comment's author for `moderator`, `moderator-edit` and `admin`, and the person who made the report for `flag`.

This example adds an in-app notice for each person with an account who's told about a reply, through a hypothetical `Inbox` module of your own:

```php
use modules\inbox\Inbox;
use verbb\comments\events\NotifyEvent;
use verbb\comments\services\Notifications;
use yii\base\Event;

Event::on(Notifications::class, Notifications::EVENT_AFTER_NOTIFY, function(NotifyEvent $event) {
    if ($event->type !== Notifications::TYPE_REPLY) {
        return;
    }

    foreach ($event->recipients as $user) {
        // Guests have no account to send a notice to.
        if ($user->id) {
            Inbox::getInstance()->notify($user, 'Someone replied to your comment', $event->owner?->getUrl() . '#comment-' . $event->comment->id);
        }
    }
});
```

## Email Events
The events below are triggered before each email Comments sends. Each receives an `EmailEvent`, with the message as `$event->mail`, the recipient as `$event->user`, the element as `$event->element` and the comment as `$event->comment`. You can change the message, or set `$event->isValid` to `false` to stop it being sent to that person. The guest confirmation email doesn't have an event.

### The `beforeSendAuthorEmail` Event
The event that is triggered before the author of an element is emailed about a new comment on it.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_AUTHOR_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendReplyEmail` Event
The event that is triggered before someone is emailed about a reply to their comment.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_REPLY_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendMentionEmail` Event
The event that is triggered before someone is emailed about being mentioned in a comment.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_MENTION_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendModeratorApprovedEmail` Event
The event that is triggered before someone is emailed that their pending comment was approved.

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
The event that is triggered before each subscriber is emailed about a new comment on a discussion they follow.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_SUBSCRIBE_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendModeratorEmail` Event
The event that is triggered before each member of the moderator group is emailed about a comment waiting for review, or an edit to one. This example leaves admins out, because they already keep an eye on the control panel:

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_MODERATOR_EMAIL, function(EmailEvent $event) {
    if ($event->user->admin) {
        $event->isValid = false;
    }
});
```

### The `beforeSendAdminEmail` Event
The event that is triggered before each **Notification Recipients** address is emailed about a new comment. For an address that doesn't belong to an account, `$event->user` is a user without an ID.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_ADMIN_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

### The `beforeSendFlagEmail` Event
The event that is triggered before each **Notification Recipients** address is emailed about a comment being reported.

```php
use verbb\comments\events\EmailEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

Event::on(CommentsService::class, CommentsService::EVENT_BEFORE_SEND_FLAG_EMAIL, function(EmailEvent $event) {
    // Prevent sending
    $event->isValid = false;
});
```

## Vote, Report and Subscription Events
These fire however the change is made: the thread, the data API, GraphQL or Comments' form actions. Each event finds the comment and the person only when something is listening, so a site without listeners does no extra work.

### The `beforeVote` Event
The event that is triggered before someone's vote on a comment changes: given, switched between up and down, or taken back. Setting `$event->isValid` to false stops the change, and the person is told their vote couldn't be saved.

The event is a `VoteChangeEvent`, with the comment as `$event->comment`, the person voting as `$event->user`, which is null for a guest, their commenter identity's ID as `$event->identityId`, and the vote before and after as `$event->previousType` and `$event->type`. These are `InteractionType` enums, and `type` is null when a vote is taken back.

```php
use verbb\comments\events\VoteChangeEvent;
use verbb\comments\services\Interactions;
use yii\base\Event;

Event::on(Interactions::class, Interactions::EVENT_BEFORE_VOTE, function(VoteChangeEvent $event) {
    // Only signed-in members' votes count on this site.
    if (!$event->user) {
        $event->isValid = false;
    }
});
```

### The `afterVote` Event
The event that is triggered after someone's vote on a comment changes, with the same `VoteChangeEvent`.

```php
use verbb\comments\enums\InteractionType;
use verbb\comments\events\VoteChangeEvent;
use verbb\comments\services\Interactions;
use yii\base\Event;

Event::on(Interactions::class, Interactions::EVENT_AFTER_VOTE, function(VoteChangeEvent $event) {
    if ($event->type === InteractionType::Upvote && $event->previousType === null) {
        $comment = $event->comment;
        $voter = $event->user;
    }
});
```

### The `beforeReport` Event
The event that is triggered before someone reports a comment. Setting `$event->isValid` to false stops the report, and the person is told it couldn't be sent.

The event is a `ReportChangeEvent`, with the comment as `$event->comment`, the person reporting as `$event->user`, which is null for a guest, their commenter identity's ID as `$event->identityId`, and the `$event->reason` and `$event->details` they gave.

```php
use verbb\comments\events\ReportChangeEvent;
use verbb\comments\services\Reports;
use yii\base\Event;

Event::on(Reports::class, Reports::EVENT_BEFORE_REPORT, function(ReportChangeEvent $event) {
    $reason = $event->reason;
});
```

### The `afterReport` Event
The event that is triggered after someone reports a comment, with the same `ReportChangeEvent` and the saved report as `$event->report`.

```php
use verbb\comments\events\ReportChangeEvent;
use verbb\comments\services\Reports;
use yii\base\Event;

Event::on(Reports::class, Reports::EVENT_AFTER_REPORT, function(ReportChangeEvent $event) {
    $report = $event->report;
});
```

### The `afterWithdrawReport` Event
The event that is triggered after someone takes back their report, with a `ReportChangeEvent` whose `$event->reported` is false. A moderator resolving or dismissing reports is a moderation decision, so it's the [`afterChangeState` event](#the-afterChangeState-event) instead.

```php
use verbb\comments\events\ReportChangeEvent;
use verbb\comments\services\Reports;
use yii\base\Event;

Event::on(Reports::class, Reports::EVENT_AFTER_WITHDRAW_REPORT, function(ReportChangeEvent $event) {
    $comment = $event->comment;
});
```

### The `afterChangeSubscription` Event
The event that is triggered after someone starts or stops following a discussion, or the replies to a comment. It doesn't fire when nothing changed, such as following something already followed.

The event is a `SubscriptionChangeEvent`, with the subscription as `$event->subscription`, the person as `$event->user`, which is null for a guest, their commenter identity's ID as `$event->identityId`, `$event->type` as `SubscriptionType::Conversation` or `SubscriptionType::Replies`, the `$event->conversationId`, the `$event->commentId` for a comment's replies, and `$event->subscribed`.

```php
use verbb\comments\events\SubscriptionChangeEvent;
use verbb\comments\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_CHANGE_SUBSCRIPTION, function(SubscriptionChangeEvent $event) {
    if ($event->subscribed) {
        $follower = $event->user;
    }
});
```

## Moderation Events

### The `afterChangeState` Event
The event that is triggered after a comment's moderation state changes, such as when it's approved, removed or marked as spam, from the control panel, an element's Comments field, the site or GraphQL. The change doesn't save the comment's element, so `Comment::EVENT_AFTER_SAVE` doesn't fire for it. When a moderator makes a decision on the comment's page, any edits are saved first, which does fire the save events.

The event is a `ModerationEvent`, with the comment as `$event->comment`, the user who made the change as `$event->actor`, which is null when no user is given, and the states before and after as `$event->previousState` and `$event->state`. The states are `ModerationState` enums rather than strings, so compare them with the enum's cases. This example only acts when a comment that was waiting for review is approved:

```php
use verbb\comments\enums\ModerationState;
use verbb\comments\events\ModerationEvent;
use verbb\comments\services\Moderation;
use yii\base\Event;

Event::on(Moderation::class, Moderation::EVENT_AFTER_CHANGE_STATE, function(ModerationEvent $event) {
    if ($event->previousState !== ModerationState::Pending || $event->state !== ModerationState::Approved) {
        return;
    }

    $comment = $event->comment;
    $moderator = $event->actor;
});
```
