# Comment Queries

You can fetch comments in your templates or PHP code using **comment queries**.

:::code
```twig
{# Create a new comment query #}
{% set myQuery = craft.comments.fetch() %}
```

```php
// Create a new comment query
$myQuery = \verbb\comments\elements\Comment::find();
```
:::

Once you’ve created a comment query, you can set parameters on it to narrow down the results, and then execute it by calling `.all()`. An array of [Comment](docs:developers/comment) objects will be returned.

:::tip
See Introduction to [Element Queries](https://docs.craftcms.com/v3/dev/element-queries/) in the Craft docs to learn about how element queries work.
:::

## Example

We can display comments for a given user by doing the following:

1. Create an comment query with `craft.comments.fetch()`.
2. Set the [userId](#userId), [limit](#limit) and [status](#status) parameters on it.
3. Fetch all comments with `.all()` and output.
4. Loop through the comments using a [for](https://twig.symfony.com/doc/2.x/tags/for.html) tag to output the contents.

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

### `after`

Narrows the query results to only comments that were posted on or after a certain date.

Possible values include:

| Value | Fetches comments…
| - | -
| `'2018-04-01'` | that were posted after 2018-04-01.
| a [DateTime](http://php.net/class.datetime) object | that were posted after the date represented by the object.

::: code
```twig
{# Fetch comments posted this month #}
{% set firstDayOfMonth = date('first day of this month') %}

{% set comments = craft.comments.fetch()
    .after(firstDayOfMonth)
    .all() %}
```

```php
// Fetch comments posted this month
$firstDayOfMonth = new \DateTime('first day of this month');

$comments = \verbb\comments\elements\Comment::find()
    ->after($firstDayOfMonth)
    ->all();
```
:::



### `ancestorDist`

Narrows the query results to only comments that are up to a certain distance away from the comment specified by [ancestorOf](#ancestorof).

::: code
```twig
{# Fetch comments above this one #}
{% set comments = craft.comments.fetch()
    .ancestorOf(comment)
    .ancestorDist(3)
    .all() %}
```

```php
// Fetch comments above this one
$comments = \verbb\comments\elements\Comment::find()
    ->ancestorOf($comment)
    ->ancestorDist(3)
    ->all();
```
:::



### `ancestorOf`

Narrows the query results to only comments that are ancestors of another comment.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | above the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | above the comment represented by the object.

::: code
```twig
{# Fetch comments above this one #}
{% set comments = craft.comments.fetch()
    .ancestorOf(comment)
    .all() %}
```

```php
// Fetch comments above this one
$comments = \verbb\comments\elements\Comment::find()
    ->ancestorOf($comment)
    ->all();
```
:::

::: tip
This can be combined with [ancestorDist](#ancestordist) if you want to limit how far away the ancestor comments can be.
:::



### `anyStatus`

Clears out the [status()](https://docs.craftcms.com/api/v3/craft-elements-db-elementquery.html#method-status) and [enabledForSite()](https://docs.craftcms.com/api/v3/craft-elements-db-elementquery.html#method-enabledforsite) parameters.

::: code
```twig
{# Fetch all comments, regardless of status #}
{% set comments = craft.comments.fetch()
    .anyStatus()
    .all() %}
```

```php
// Fetch all comments, regardless of status
$comments = \verbb\comments\elements\Comment::find()
    ->anyStatus()
    ->all();
```
:::



### `asArray`

Causes the query to return matching comments as arrays of data, rather than [Comment](docs:developers/comment) objects.

::: code
```twig
{# Fetch comments as arrays #}
{% set comments = craft.comments.fetch()
    .asArray()
    .all() %}
```

```php
// Fetch comments as arrays
$comments = \verbb\comments\elements\Comment::find()
    ->asArray()
    ->all();
```
:::



### `before`

Narrows the query results to only comments that were posted before a certain date.

Possible values include:

| Value | Fetches comments…
| - | -
| `'2018-04-01'` | that were posted before 2018-04-01.
| a [DateTime](http://php.net/class.datetime) object | that were posted before the date represented by the object.

::: code
```twig
{# Fetch comments posted before this month #}
{% set firstDayOfMonth = date('first day of this month') %}

{% set comments = craft.comments.fetch()
    .before(firstDayOfMonth)
    .all() %}
```

```php
// Fetch comments posted before this month
$firstDayOfMonth = new \DateTime('first day of this month');

$comments = \verbb\comments\elements\Comment::find()
    ->before($firstDayOfMonth)
    ->all();
```
:::



### `commentDate`

Narrows the query results based on the comments’ creation dates.

Possible values include:

| Value | Fetches comments…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.

::: code
```twig
{# Fetch comments created last month #}
{% set start = date('first day of last month') | atom %}
{% set end = date('first day of this month') | atom %}

{% set comments = craft.comments.fetch()
    .commentDate(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php
// Fetch comments created last month
$start = new \DateTime('first day of next month')->format(\DateTime::ATOM);
$end = new \DateTime('first day of this month')->format(\DateTime::ATOM);

$comments = \verbb\comments\elements\Comment::find()
    ->commentDate(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::



### `dateCreated`

Narrows the query results based on the comments’ creation dates.

Possible values include:

| Value | Fetches comments…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.

::: code
```twig
{# Fetch comments created last month #}
{% set start = date('first day of last month') | atom %}
{% set end = date('first day of this month') | atom %}

{% set comments = craft.comments.fetch()
    .dateCreated(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php
// Fetch comments created last month
$start = new \DateTime('first day of next month')->format(\DateTime::ATOM);
$end = new \DateTime('first day of this month')->format(\DateTime::ATOM);

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
| `'< 2018-05-01'` | that were updated before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01.

::: code
```twig
{# Fetch comments updated in the last week #}
{% set lastWeek = date('1 week ago')|atom %}

{% set comments = craft.comments.fetch()
    .dateUpdated(">= #{lastWeek}")
    .all() %}
```

```php
// Fetch comments updated in the last week
$lastWeek = new \DateTime('1 week ago')->format(\DateTime::ATOM);

$comments = \verbb\comments\elements\Comment::find()
    ->dateUpdated(">= {$lastWeek}")
    ->all();
```
:::



### `descendantDist`

Narrows the query results to only comments that are up to a certain distance away from the comment specified by [descendantOf](#descendantof).

::: code
```twig
{# Fetch comments below this one #}
{% set comments = craft.comments.fetch()
    .descendantOf(comment)
    .descendantDist(3)
    .all() %}
```

```php
// Fetch comments below this one
$comments = \verbb\comments\elements\Comment::find()
    ->descendantOf($comment)
    ->descendantDist(3)
    ->all();
```
:::



### `descendantOf`

Narrows the query results to only comments that are descendants of another comment.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | below the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | below the comment represented by the object.

::: code
```twig
{# Fetch comments below this one #}
{% set comments = craft.comments.fetch()
    .descendantOf(comment)
    .all() %}
```

```php
// Fetch comments below this one
$comments = \verbb\comments\elements\Comment::find()
    ->descendantOf($comment)
    ->all();
```
:::

::: tip
This can be combined with [descendantDist](#descendantdist) if you want to limit how far away the descendant comments can be.
:::



### `email`

Narrows the query results based on the comments’ email addresses.

Possible values include:

| Value | Fetches comments with users…
| - | -
| `'foo@bar.baz'` | with an email of `foo@bar.baz`.
| `'not foo@bar.baz'` | not with an email of `foo@bar.baz`.
| `'*@bar.baz'` | with an email that ends with `@bar.baz`.

::: code
```twig
{# Fetch comments from users with a .co.uk domain on their email address #}
{% set comments = craft.comments.fetch()
    .email('*.co.uk')
    .all() %}
```

```php
// Fetch comments from users with a .co.uk domain on their email address
$comments = \verbb\comments\elements\Comment::find()
    ->email('*.co.uk')
    ->all();
```
:::



### `fixedOrder`

Causes the query results to be returned in the order specified by [id](#id).

::: code
```twig
{# Fetch comments in a specific order #}
{% set comments = craft.comments.fetch()
    .id([1, 2, 3, 4, 5])
    .fixedOrder()
    .all() %}
```

```php
// Fetch comments in a specific order
$comments = \verbb\comments\elements\Comment::find()
    ->id([1, 2, 3, 4, 5])
    ->fixedOrder()
    ->all();
```
:::



## `hasDescendants`

Narrows the query results based on whether the comments have any descendants.

(This has the opposite effect of calling [leaves](#leaves).)

::: code
```twig
{# Fetch comments that have descendants #}
{% set comments = craft.comments.fetch()
    .hasDescendants()
    .all() %}
```

```php
// Fetch comments that have descendants
$comments = \verbb\comments\elements\Comment::find()
    ->hasDescendants()
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
```twig
{# Fetch the comment by its ID #}
{% set comment = craft.comments.fetch()
    .id(1)
    .one() %}
```

```php
// Fetch the comment by its ID
$comment = \verbb\comments\elements\Comment::find()
    ->id(1)
    ->one();
```
:::

::: tip
This can be combined with [fixedOrder](#fixedorder) if you want the results to be returned in a specific order.
:::



### `inReverse`

Causes the query results to be returned in reverse order.

::: code
```twig
{# Fetch comments in reverse #}
{% set comments = craft.comments.fetch()
    .inReverse()
    .all() %}
```

```php
// Fetch comments in reverse
$comments = \verbb\comments\elements\Comment::find()
    ->inReverse()
    ->all();
```
:::



### `isFlagged`

Narrows the query results based on whether the comments have been flagged.

::: code
```twig
{# Fetch comments that are flagged #}
{% set comments = craft.comments.fetch()
    .isFlagged(true)
    .all() %}
```

```php
// Fetch comments that are flagged
$comments = \verbb\comments\elements\Comment::find()
    ->isFlagged(true)
    ->all();
```
:::



### `leaves`

Narrows the query results based on whether the comments are “leaves” (comments with no descendants).

(This has the opposite effect of calling [hasDescendants](#hasdescendants).)

::: code
```twig
{# Fetch comments that have no descendants #}
{% set comments = craft.comments.fetch()
    .leaves()
    .all() %}
```

```php
// Fetch comments that have no descendants
$comments = \verbb\comments\elements\Comment::find()
    ->leaves()
    ->all();
```
:::



### `level`

Narrows the query results based on the comments’ level within the structure.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | with a level of 1.
| `'not 1'` | not with a level of 1.
| `'>= 3'` | with a level greater than or equal to 3.
| `[1, 2]` | with a level of 1 or 2
| `['not', 1, 2]` | not with level of 1 or 2.

::: code
```twig
{# Fetch comments positioned at level 3 or above #}
{% set comments = craft.comments.fetch()
    .level('>= 3')
    .all() %}
```

```php
// Fetch comments positioned at level 3 or above
$comments = \verbb\comments\elements\Comment::find()
    ->level('>= 3')
    ->all();
```
:::



### `limit`

Determines the number of comments that should be returned.

::: code
```twig
{# Fetch up to 10 comments  #}
{% set comments = craft.comments.fetch()
    .limit(10)
    .all() %}
```

```php
// Fetch up to 10 comments
$comments = \verbb\comments\elements\Comment::find()
    ->limit(10)
    ->all();
```
:::



### `nextSiblingOf`

Narrows the query results to only the comment that comes immediately after another comment.

Possible values include:

| Value | Fetches the comment…
| - | -
| `1` | after the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | after the comment represented by the object.

::: code
```twig
{# Fetch the next comment #}
{% set comment = craft.comments.fetch()
    .nextSiblingOf(comment)
    .one() %}
```

```php
// Fetch the next comment
$comment = \verbb\comments\elements\Comment::find()
    ->nextSiblingOf($comment)
    ->one();
```
:::



### `offset`

Determines how many comments should be skipped in the results.

::: code
```twig
{# Fetch all comments except for the first 3 #}
{% set comments = craft.comments.fetch()
    .offset(3)
    .all() %}
```

```php
// Fetch all comments except for the first 3
$comments = \verbb\comments\elements\Comment::find()
    ->offset(3)
    ->all();
```
:::



### `orderBy`

Determines the order that the comments should be returned in. You can also use `votes` to order by the total number of votes.

::: code
```twig
{# Fetch all comments in order of date created #}
{% set comments = craft.comments.fetch()
    .orderBy('elements.dateCreated asc')
    .all() %}
```

```php
// Fetch all comments in order of date created
$comments = \verbb\comments\elements\Comment::find()
    ->orderBy('elements.dateCreated asc')
    ->all();
```
:::



### `owner`

Sets the [ownerId](#ownerid) and [siteId](#siteid) parameters based on a given element.

::: code
```twig
{# Fetch comments created for this entry #}
{% set comments = craft.comments.fetch()
    .owner(entry)
    .all() %}
```

```php
// Fetch comments created for this entry
$comments = \verbb\comments\elements\Comment::find()
    ->owner($entry)
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
```twig
{# Fetch comments created for an element with an ID of 1 #}
{% set comments = craft.comments.fetch()
    .ownerId(1)
    .all() %}
```

```php
// Fetch comments created for an element with an ID of 1
$comments = \verbb\comments\elements\Comment::find()
    ->ownerId(1)
    ->all();
```
:::



### `ownerSection`

Return comments for a specific entry section.

::: code
```twig
{# Fetch comments for specific entry section #}
{% set comments = craft.comments.fetch()
    .section(['news', 'blog'])
    .all() %}
```

```php
// Fetch comments for specific entry section
$comments = \verbb\comments\elements\Comment::find()
    ->section(['news', 'blog'])
    ->all();
```
:::



### `ownerSectionId`

Return comments for a specific entry sectionId.

::: code
```twig
{# Fetch comments for specific entry section #}
{% set comments = craft.comments.fetch()
    .sectionId(22)
    .all() %}
```

```php
// Fetch comments for specific entry section
$comments = \verbb\comments\elements\Comment::find()
    ->section(22)
    ->all();
```
:::



### `ownerType`

Return comments for a specific owner type - for instance, just for [Entries](https://docs.craftcms.com/api/v3/craft-elements-comment.html). Requires the full namespaced element class.

::: code
```twig
{# Fetch comments for specific element type #}
{% set comments = craft.comments.fetch()
    .ownerType('craft\\elements\\Entry')
    .all() %}
```

```php
// Fetch comments for specific element type
$comments = \verbb\comments\elements\Comment::find()
    ->ownerType(craft\elements\Entry::class)
    ->all();
```
:::



### `positionedAfter`

Narrows the query results to only comments that are positioned after another comment.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | after the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | after the comment represented by the object.

::: code
```twig
{# Fetch comments after this one #}
{% set comments = craft.comments.fetch()
    .positionedAfter(comment)
    .all() %}
```

```php
// Fetch comments after this one
$comments = \verbb\comments\elements\Comment::find()
    ->positionedAfter($comment)
    ->all();
```
:::



### `positionedBefore`

Narrows the query results to only comments that are positioned before another comment.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | before the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | before the comment represented by the object.

::: code
```twig
{# Fetch comments before this one #}
{% set comments = craft.comments.fetch()
    .positionedBefore(comment)
    .all() %}
```

```php
// Fetch comments before this one
$comments = \verbb\comments\elements\Comment::find()
    ->positionedBefore($comment)
    ->all();
```
:::



### `prevSiblingOf`

Narrows the query results to only the comment that comes immediately before another comment.

Possible values include:

| Value | Fetches the comment…
| - | -
| `1` | before the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | before the comment represented by the object.

::: code
```twig
{# Fetch the previous comment #}
{% set comment = craft.comments.fetch()
    .prevSiblingOf(comment)
    .one() %}
```

```php
// Fetch the previous comment
$comment = \verbb\comments\elements\Comment::find()
    ->prevSiblingOf($comment)
    ->one();
```
:::



### `search`

Narrows the query results to only comments that match a search query.

See [Searching](https://docs.craftcms.com/v3/searching.html) for a full explanation of how to work with this parameter.

::: code
```twig
{# Get the search query from the 'q' query string param #}
{% set searchQuery = craft.request.getQueryParam('q') %}

{# Fetch all comments that match the search query #}
{% set comments = craft.comments.fetch()
    .search(searchQuery)
    .all() %}
```

```php
// Get the search query from the 'q' query string param
$searchQuery = \Craft::$app->request->getQueryParam('q');

// Fetch all comments that match the search query
$comments = \verbb\comments\elements\Comment::find()
    ->search($searchQuery)
    ->all();
```
:::



### `siblingOf`

Narrows the query results to only comments that are siblings of another comment.

Possible values include:

| Value | Fetches comments…
| - | -
| `1` | beside the comment with an ID of 1.
| a [Comment](docs:developers/comment) object | beside the comment represented by the object.

::: code
```twig
{# Fetch comments beside this one #}
{% set comments = craft.comments.fetch()
    .siblingOf(comment)
    .all() %}
```

```php
// Fetch comments beside this one
$comments = \verbb\comments\elements\Comment::find()
    ->siblingOf($comment)
    ->all();
```
:::



### `status`

Narrows the query results based on the comments’ statuses.

Possible values include:

| Value | Fetches comments…
| - | -
| `'approved'` _(default)_ | that are approved.
| `'pending'` | that are pending.
| `'spam'` | that are marked as spam.
| `'trashed'` | that are trashed (not deleted).
| `['approved', 'pending']` | that are approved or pending.

::: code
```twig
{# Fetch pending comments #}
{% set comments = {twig-function}
    .status('pending')
    .all() %}
```

```php
// Fetch pending comments
$comments = \verbb\comments\elements\Comment::find()
    ->status('pending')
    ->all();
```
:::



### `uid`

Narrows the query results based on the comments’ UIDs.

::: code
```twig
{# Fetch the comment by its UID #}
{% set comment = craft.comments.fetch()
    .uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    .one() %}
```

```php
// Fetch the comment by its UID
$comment = \verbb\comments\elements\Comment::find()
    ->uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    ->one();
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
```twig
{# Fetch the current user's comments #}
{% set comments = craft.comments.fetch()
    .userId(currentUser.id)
    .all() %}
```

```php
// Fetch the current user's comments
$user = Craft::$app->user->getIdentity();

$comments = \verbb\comments\elements\Comment::find()
    ->userId($user->id)
    ->all();
```
:::



<!-- END PARAMS -->
