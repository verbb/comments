# Comment Queries

You can fetch comments in your templates or PHP code using **comment queries**.

::: code
```twig Twig
{# Create a new comment query #}
{% set myQuery = craft.comments.fetch() %}
```

```php PHP
// Create a new comment query
$myQuery = \verbb\comments\elements\Comment::find();
```
:::

Once you’ve created a comment query, you can set parameters on it to narrow down the results, and then execute it by calling `.all()`. An array of [Comment](docs:developers/comment) objects will be returned. Only approved comments are returned unless you use [status](#status).

A comment its author has deleted is left out of `approved` results. Use `.status('author-removed')` to find these, or `.status(null)` for every status, where `comment.authorRemovedAt` tells you it was deleted.

::: tip
See Introduction to [Element Queries](https://craftcms.com/docs/5.x/development/element-queries.html) in the Craft docs to learn about how element queries work.
:::

## Example

We can display comments for a given user by doing the following:

1. Create a comment query with `craft.comments.fetch()`.
2. Set the [userId](#userid), [limit](#limit) and [status](#status) parameters on it.
3. Fetch all comments with `.all()` and output.
4. Loop through the comments using a [for](https://twig.symfony.com/doc/3.x/tags/for.html) tag to output the contents.

```twig
{# Create a comments query with the 'userId', 'limit', and 'status' parameters #}
{% set commentsQuery = craft.comments.fetch()
    .userId(currentUser.id)
    .limit(10)
    .status('pending') %}

{# Fetch the Comments #}
{% set comments = commentsQuery.all() %}

{# Display their contents #}
{% for comment in comments %}
    <p>{{ comment.comment }}</p>
{% endfor %}
```

## Parameters

Comment queries support the following parameters:


<!-- BEGIN PARAMS -->



### `adjacencyParentId`

Narrows the query results to direct replies of one or more comments, by the IDs of the comments they reply to. Pass `:empty:` to fetch only the comments at the top of each thread.

::: code
```twig Twig
{# Fetch direct replies #}
{% set replies = craft.comments.fetch()
    .adjacencyParentId(comment.id)
    .all() %}

{# Fetch the comments that start each thread on this entry #}
{% set topLevel = craft.comments.fetch()
    .owner(entry)
    .adjacencyParentId(':empty:')
    .all() %}
```

```php PHP
// Fetch direct replies
$replies = \verbb\comments\elements\Comment::find()
    ->adjacencyParentId($comment->id)
    ->all();

// Fetch the comments that start each thread on this entry
$topLevel = \verbb\comments\elements\Comment::find()
    ->owner($entry)
    ->adjacencyParentId(':empty:')
    ->all();
```
:::



### `asArray`

Causes the query to return matching comments as arrays of data, rather than [Comment](docs:developers/comment) objects.

::: code
```twig Twig
{# Fetch comments as arrays #}
{% set comments = craft.comments.fetch()
    .asArray()
    .all() %}
```

```php PHP
// Fetch comments as arrays
$comments = \verbb\comments\elements\Comment::find()
    ->asArray()
    ->all();
```
:::



### `comment`

Narrows the query results based on the comment's plain text. To look for words in comments, [search](#search) is usually a better fit.

::: code
```twig Twig
{# Fetch comments whose text starts with "Thanks" #}
{% set comments = craft.comments.fetch()
    .comment('Thanks*')
    .all() %}
```

```php PHP
// Fetch comments whose text starts with "Thanks"
$comments = \verbb\comments\elements\Comment::find()
    ->comment('Thanks*')
    ->all();
```
:::



### `commentDate`

Narrows the query results based on the dates the comments were posted.

Possible values include:

| Value | Fetches comments…
| - | -
| `'>= 2018-04-01'` | that were posted on or after 2018-04-01.
| `'< 2018-05-01'` | that were posted before 2018-05-01.
| `['and', '>= 2018-04-01', '< 2018-05-01']` | that were posted between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch comments posted last month #}
{% set start = date('first day of last month') | atom %}
{% set end = date('first day of this month') | atom %}

{% set comments = craft.comments.fetch()
    .commentDate(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php PHP
// Fetch comments posted last month
$start = (new \DateTime('first day of last month'))->format(\DateTime::ATOM);
$end = (new \DateTime('first day of this month'))->format(\DateTime::ATOM);

$comments = \verbb\comments\elements\Comment::find()
    ->commentDate(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::



### `conversationId`

Narrows the query results to comments in one or more conversations, by conversation ID. A conversation holds an element's comments on one site, so [owner](#owner) or [ownerId](#ownerid) is usually the simpler way to fetch the same comments.

::: code
```twig Twig
{# Fetch comments in a conversation #}
{% set comments = craft.comments.fetch()
    .conversationId(comment.conversationId)
    .all() %}
```

```php PHP
// Fetch comments in a conversation
$comments = \verbb\comments\elements\Comment::find()
    ->conversationId($comment->conversationId)
    ->all();
```
:::



### `dateCreated`

Narrows the query results based on the comments’ creation dates.

Possible values include:

| Value | Fetches comments…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01.
| `['and', '>= 2018-04-01', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch comments created last month #}
{% set start = date('first day of last month') | atom %}
{% set end = date('first day of this month') | atom %}

{% set comments = craft.comments.fetch()
    .dateCreated(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php PHP
// Fetch comments created last month
$start = (new \DateTime('first day of last month'))->format(\DateTime::ATOM);
$end = (new \DateTime('first day of this month'))->format(\DateTime::ATOM);

$comments = \verbb\comments\elements\Comment::find()
    ->dateCreated(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::



### `dateUpdated`

Narrows the query results based on the comments’ last-updated dates.

Possible values include:

| Value | Fetches comments…
| - | -
| `'>= 2018-04-01'` | that were updated on or after 2018-04-01.
| `'< 2018-05-01'` | that were updated before 2018-05-01.
| `['and', '>= 2018-04-01', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch comments updated in the last week #}
{% set lastWeek = date('1 week ago')|atom %}

{% set comments = craft.comments.fetch()
    .dateUpdated(">= #{lastWeek}")
    .all() %}
```

```php PHP
// Fetch comments updated in the last week
$lastWeek = (new \DateTime('1 week ago'))->format(\DateTime::ATOM);

$comments = \verbb\comments\elements\Comment::find()
    ->dateUpdated(">= {$lastWeek}")
    ->all();
```
:::



### `email`

Narrows the query results based on the email addresses of guests. A registered user's comments don't have one, so use [userId](#userid) for those.

Possible values include:

| Value | Fetches comments…
| - | -
| `'foo@bar.baz'` | from a guest with an email of `foo@bar.baz`.
| `'not foo@bar.baz'` | not from a guest with an email of `foo@bar.baz`.
| `'*@bar.baz'` | from a guest with an email that ends with `@bar.baz`.

::: code
```twig Twig
{# Fetch comments from guests with a .co.uk domain on their email address #}
{% set comments = craft.comments.fetch()
    .email('*.co.uk')
    .all() %}
```

```php PHP
// Fetch comments from guests with a .co.uk domain on their email address
$comments = \verbb\comments\elements\Comment::find()
    ->email('*.co.uk')
    ->all();
```
:::



### `fixedOrder`

Causes the query results to be returned in the order specified by [id](#id).

::: code
```twig Twig
{# Fetch comments in a specific order #}
{% set comments = craft.comments.fetch()
    .id([1, 2, 3, 4, 5])
    .fixedOrder()
    .all() %}
```

```php PHP
// Fetch comments in a specific order
$comments = \verbb\comments\elements\Comment::find()
    ->id([1, 2, 3, 4, 5])
    ->fixedOrder()
    ->all();
```
:::



### `hasOpenReports`

Narrows the query results based on whether the comments have an open report, meaning one that a moderator hasn't resolved or dismissed and the person who made it hasn't withdrawn. Pass `true` for comments with at least one open report, or `false` for comments without any.

::: code
```twig Twig
{# Fetch comments that have been reported #}
{% set comments = craft.comments.fetch()
    .hasOpenReports(true)
    .all() %}
```

```php PHP
// Fetch comments that have been reported
$comments = \verbb\comments\elements\Comment::find()
    ->hasOpenReports(true)
    ->all();
```
:::



### `id`

Narrows the query results based on the comments’ IDs.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | with an ID of 1.
| `'not 1'` | not with an ID of 1.
| `[1, 2]` | with an ID of 1 or 2.
| `['not', 1, 2]` | not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch the comment by its ID #}
{% set comment = craft.comments.fetch()
    .id(1)
    .one() %}
```

```php PHP
// Fetch the comment by its ID
$comment = \verbb\comments\elements\Comment::find()
    ->id(1)
    ->one();
```
:::

::: tip
This can be combined with [fixedOrder](#fixedorder) if you want the results to be returned in a specific order.
:::



### `identityId`

Narrows the query results to comments by one or more commenter identities. An identity represents a registered user or a guest, and it's the ID shown in a [Commenters](docs:feature-tour/moderation#commenters) URL. Because guests have identities too, this is the way to fetch every comment by one guest.

::: code
```twig Twig
{# Fetch every comment by the same commenter #}
{% set comments = craft.comments.fetch()
    .identityId(comment.identityId)
    .all() %}
```

```php PHP
// Fetch every comment by the same commenter
$comments = \verbb\comments\elements\Comment::find()
    ->identityId($comment->identityId)
    ->all();
```
:::



### `inReverse`

Causes the query results to be returned in reverse order.

::: code
```twig Twig
{# Fetch comments in reverse #}
{% set comments = craft.comments.fetch()
    .inReverse()
    .all() %}
```

```php PHP
// Fetch comments in reverse
$comments = \verbb\comments\elements\Comment::find()
    ->inReverse()
    ->all();
```
:::



### `isFlagged`

Works the same way as [hasOpenReports](#hasopenreports). Pass `true` for comments with at least one open report, or `false` for comments without any.

::: code
```twig Twig
{# Fetch comments that have been reported #}
{% set comments = craft.comments.fetch()
    .isFlagged(true)
    .all() %}
```

```php PHP
// Fetch comments that have been reported
$comments = \verbb\comments\elements\Comment::find()
    ->isFlagged(true)
    ->all();
```
:::



### `limit`

Determines the number of comments that should be returned.

::: code
```twig Twig
{# Fetch up to 10 comments #}
{% set comments = craft.comments.fetch()
    .limit(10)
    .all() %}
```

```php PHP
// Fetch up to 10 comments
$comments = \verbb\comments\elements\Comment::find()
    ->limit(10)
    ->all();
```
:::



### `moderationState`

Narrows the query results by moderation state, which is `approved`, `pending`, `spam` or `removed`. A comment's moderation state records the same moderation decision as its [status](#status), and the query's default `approved` status still applies, so add `.status(null)` when you use this to fetch anything other than approved comments. With `.status(null)`, `moderationState('approved')` also includes approved comments that their authors have deleted.

::: code
```twig Twig
{# Fetch comments waiting for review #}
{% set comments = craft.comments.fetch()
    .status(null)
    .moderationState('pending')
    .all() %}
```

```php PHP
// Fetch comments waiting for review
$comments = \verbb\comments\elements\Comment::find()
    ->status(null)
    ->moderationState('pending')
    ->all();
```
:::



### `name`

Narrows the query results based on the names of guests. A registered user's comments don't have one, so use [userId](#userid) for those.

::: code
```twig Twig
{# Fetch comments from a guest by name #}
{% set comments = craft.comments.fetch()
    .name('Jane Smith')
    .all() %}
```

```php PHP
// Fetch comments from a guest by name
$comments = \verbb\comments\elements\Comment::find()
    ->name('Jane Smith')
    ->all();
```
:::



### `offset`

Determines how many comments should be skipped in the results.

::: code
```twig Twig
{# Fetch all comments except for the first 3 #}
{% set comments = craft.comments.fetch()
    .offset(3)
    .all() %}
```

```php PHP
// Fetch all comments except for the first 3
$comments = \verbb\comments\elements\Comment::find()
    ->offset(3)
    ->all();
```
:::



### `orderBy`

Determines the order that the comments should be returned in. You can also use `voteCount` and `flagCount`, as covered under [Ordering](#ordering).

::: code
```twig Twig
{# Fetch all comments in order of date created #}
{% set comments = craft.comments.fetch()
    .orderBy('elements.dateCreated asc')
    .all() %}
```

```php PHP
// Fetch all comments in order of date created
$comments = \verbb\comments\elements\Comment::find()
    ->orderBy('elements.dateCreated asc')
    ->all();
```
:::



### `owner`

Sets the [ownerId](#ownerid) parameter and the site to query, based on a given element.

::: code
```twig Twig
{# Fetch comments created for this entry #}
{% set comments = craft.comments.fetch()
    .owner(entry)
    .all() %}
```

```php PHP
// Fetch comments created for this entry
$comments = \verbb\comments\elements\Comment::find()
    ->owner($entry)
    ->all();
```
:::



### `ownerEntryType`

Narrows the query results to comments on entries of specific entry types, by entry type handle or an `EntryType` object.

::: code
```twig Twig
{# Fetch comments for specific entry types #}
{% set comments = craft.comments.fetch()
    .ownerEntryType('article')
    .all() %}
```

```php PHP
// Fetch comments for specific entry types
$comments = \verbb\comments\elements\Comment::find()
    ->ownerEntryType('article')
    ->all();
```
:::



### `ownerEntryTypeId`

Narrows the query results to comments on entries of specific entry types, by entry type ID.

::: code
```twig Twig
{# Fetch comments for specific entry types #}
{% set comments = craft.comments.fetch()
    .ownerEntryTypeId(3)
    .all() %}
```

```php PHP
// Fetch comments for specific entry types
$comments = \verbb\comments\elements\Comment::find()
    ->ownerEntryTypeId(3)
    ->all();
```
:::



### `ownerId`

Narrows the query results based on the owner element of the comment, per the owners’ IDs.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | created for an element with an ID of 1.
| `'not 1'` | not created for an element with an ID of 1.
| `[1, 2]` | created for an element with an ID of 1 or 2.
| `['not', 1, 2]` | not created for an element with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch comments created for an element with an ID of 1 #}
{% set comments = craft.comments.fetch()
    .ownerId(1)
    .all() %}
```

```php PHP
// Fetch comments created for an element with an ID of 1
$comments = \verbb\comments\elements\Comment::find()
    ->ownerId(1)
    ->all();
```
:::



### `ownerSection`

Narrows the query results to comments on entries in specific sections, by section handle or a `Section` object.

::: code
```twig Twig
{# Fetch comments for specific entry sections #}
{% set comments = craft.comments.fetch()
    .ownerSection(['news', 'blog'])
    .all() %}
```

```php PHP
// Fetch comments for specific entry sections
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSection(['news', 'blog'])
    ->all();
```
:::



### `ownerSectionId`

Narrows the query results to comments on entries in specific sections, by section ID.

::: code
```twig Twig
{# Fetch comments for a specific entry section #}
{% set comments = craft.comments.fetch()
    .ownerSectionId(22)
    .all() %}
```

```php PHP
// Fetch comments for a specific entry section
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSectionId(22)
    ->all();
```
:::



### `ownerSite`, `ownerSiteId`

Narrows the query results to comments made on an element on a specific site. Use `ownerSite` with a site handle or `Site` object, or `ownerSiteId` with a site ID. Either one also sets the site to query.

::: code
```twig Twig
{# Fetch comments on the current site's elements #}
{% set comments = craft.comments.fetch()
    .ownerSiteId(currentSite.id)
    .all() %}
```

```php PHP
// Fetch comments on the current site's elements
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSiteId(\Craft::$app->getSites()->getCurrentSite()->id)
    ->all();
```
:::



### `ownerType`

Narrows the query results to comments on a specific element type, such as only those on entries. Requires the full namespaced element class.

::: code
```twig Twig
{# Fetch comments for specific element type #}
{% set comments = craft.comments.fetch()
    .ownerType('craft\\elements\\Entry')
    .all() %}
```

```php PHP
// Fetch comments for specific element type
$comments = \verbb\comments\elements\Comment::find()
    ->ownerType(\craft\elements\Entry::class)
    ->all();
```
:::



### `rootId`

Narrows the query results to a thread, by the ID of the comment at the top of it. That comment's own `rootId` is its ID, so it's included along with every reply beneath it.

::: code
```twig Twig
{# Fetch a comment's whole thread #}
{% set thread = craft.comments.fetch()
    .rootId(comment.rootId)
    .all() %}
```

```php PHP
// Fetch a comment's whole thread
$thread = \verbb\comments\elements\Comment::find()
    ->rootId($comment->rootId)
    ->all();
```
:::



### `search`

Narrows the query results to only comments that match a search query.

See [Searching](https://craftcms.com/docs/5.x/system/searching.html) for a full explanation of how to work with this parameter.

::: code
```twig Twig
{# Get the search query from the 'q' query string param #}
{% set searchQuery = craft.app.request.getQueryParam('q') %}

{# Fetch all comments that match the search query #}
{% set comments = craft.comments.fetch()
    .search(searchQuery)
    .all() %}
```

```php PHP
// Get the search query from the 'q' query string param
$searchQuery = \Craft::$app->getRequest()->getQueryParam('q');

// Fetch all comments that match the search query
$comments = \verbb\comments\elements\Comment::find()
    ->search($searchQuery)
    ->all();
```
:::



### `status`

Narrows the query results based on the comments’ statuses. To find comments in Craft's trash, use `.trashed()`.

Possible values include:

| Value | Fetches comments…
| - | -
| `'approved'` _(default)_ | that are approved, leaving out any their authors have deleted.
| `'pending'` | that are waiting for review.
| `'spam'` | that are marked as spam.
| `'removed'` | that a moderator has removed.
| `'author-removed'` | that their authors have deleted.
| `['approved', 'pending']` | that are approved or pending.
| `null` | with any status.

::: code
```twig Twig
{# Fetch pending comments #}
{% set comments = craft.comments.fetch()
    .status('pending')
    .all() %}
```

```php PHP
// Fetch pending comments
$comments = \verbb\comments\elements\Comment::find()
    ->status(\verbb\comments\elements\Comment::STATUS_PENDING)
    ->all();
```
:::



### `uid`

Narrows the query results based on the comments’ UIDs.

::: code
```twig Twig
{# Fetch the comment by its UID #}
{% set comment = craft.comments.fetch()
    .uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    .one() %}
```

```php PHP
// Fetch the comment by its UID
$comment = \verbb\comments\elements\Comment::find()
    ->uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    ->one();
```
:::



### `url`, `ipAddress`, `userAgent`

Narrow the query results based on the page URL, IP address or user agent recorded with a comment. These are recorded for comments posted through a public form.

::: code
```twig Twig
{# Fetch comments from an address #}
{% set comments = craft.comments.fetch()
    .ipAddress('192.0.2.1')
    .all() %}
```

```php PHP
// Fetch comments from an address
$comments = \verbb\comments\elements\Comment::find()
    ->ipAddress('192.0.2.1')
    ->all();
```
:::



### `userId`

Narrows the query results based on the user, per their ID.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | with a user with an ID of 1.
| `'not 1'` | not with a user with an ID of 1.
| `[1, 2]` | with a user with an ID of 1 or 2.
| `['not', 1, 2]` | not with a user with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch the current user's comments #}
{% set comments = craft.comments.fetch()
    .userId(currentUser.id)
    .all() %}
```

```php PHP
// Fetch the current user's comments
$user = \Craft::$app->getUser()->getIdentity();

$comments = \verbb\comments\elements\Comment::find()
    ->userId($user->id)
    ->all();
```
:::



<!-- END PARAMS -->


## Ordering

You can also order comments by their net votes (upvotes minus downvotes), using `voteCount`, and by their number of open reports, using `flagCount`.

```twig
{% set topComments = craft.comments.fetch()
    .orderBy('voteCount DESC')
    .all() %}

{% set mostReportedComments = craft.comments.fetch()
    .orderBy('flagCount DESC')
    .all() %}
```
