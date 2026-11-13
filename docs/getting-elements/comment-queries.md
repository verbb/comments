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

You can also pass parameters to `craft.comments.fetch()` as an object, such as `craft.comments.fetch({ ownerId: entry.id, limit: 10 })`, which sets them on the query in the same way as calling each one.

Once you’ve created a comment query, you can set parameters on it to narrow down the results, and then execute it by calling `.all()`. An array of [Comment](docs:reference/comment) objects will be returned.

::: tip
See [Element Queries](https://craftcms.com/docs/5.x/development/element-queries.html) in the Craft docs to learn how element queries work.
:::

## Example

You can list the latest comments the logged-in user has posted, such as on their account page, by doing the following:

1. Create a comment query with `craft.comments.fetch()`.
2. Set the [userId](#userid) and [limit](#limit) parameters on it, and [orderBy](#orderby) to show the newest first.
3. Fetch the comments with `.all()`.
4. Loop through them with a [for](https://twig.symfony.com/doc/3.x/tags/for.html) tag to output each comment and a link to the entry it was made on.

```twig
{# Create a comment query with the 'userId', 'limit' and 'orderBy' parameters #}
{% set commentsQuery = craft.comments.fetch()
    .userId(currentUser.id)
    .limit(10)
    .orderBy('commentDate DESC') %}

{# Fetch the comments #}
{% set comments = commentsQuery.all() %}

{# Display their contents #}
{% for comment in comments %}
    <p>{{ comment.comment }}</p>
    <p>On <a href="{{ comment.owner.url }}">{{ comment.owner.title }}</a>, {{ comment.commentDate|date('short') }}</p>
{% endfor %}
```

By default, comment queries only return approved comments. The [status](#status) parameter explains how to fetch others, and the [defaultQueryStatus](docs:get-started/configuration#defaultquerystatus) setting changes the default for every query.

## Parameters

Comment queries support the following parameters.

::: reference
### `ancestorDist`

Narrows the query results to only comments that are up to a certain distance away from the comment specified by [ancestorOf](#ancestorof).

::: code
```twig Twig
{# Fetch comments above this one #}
{% set comments = craft.comments.fetch()
    .ancestorOf(comment)
    .ancestorDist(3)
    .all() %}
```

```php PHP
// Fetch comments above this one
$comments = \verbb\comments\elements\Comment::find()
    ->ancestorOf($comment)
    ->ancestorDist(3)
    ->all();
```
:::
:::

::: reference
### `ancestorOf`

Narrows the query results to only comments that are ancestors of another comment.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | above the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | above the comment represented by the object. |

::: code
```twig Twig
{# Fetch comments above this one #}
{% set comments = craft.comments.fetch()
    .ancestorOf(comment)
    .all() %}
```

```php PHP
// Fetch comments above this one
$comments = \verbb\comments\elements\Comment::find()
    ->ancestorOf($comment)
    ->all();
```
:::

::: tip
This can be combined with [ancestorDist](#ancestordist) if you want to limit how far away the ancestor comments can be.
:::
:::

::: reference
### `asArray`

Causes the query to return matching comments as arrays of data, rather than [Comment](docs:reference/comment) objects.

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
:::

::: reference
### `comment`

Narrows the query results based on the comments’ text.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'*Craft*'` | with text containing “Craft”. |
| `'not *Craft*'` | with text not containing “Craft”. |

::: code
```twig Twig
{# Fetch comments that mention Craft #}
{% set comments = craft.comments.fetch()
    .comment('*Craft*')
    .all() %}
```

```php PHP
// Fetch comments that mention Craft
$comments = \verbb\comments\elements\Comment::find()
    ->comment('*Craft*')
    ->all();
```
:::
:::

::: reference
### `commentDate`

Narrows the query results based on the date the comments were posted.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'>= 2018-04-01'` | that were posted on or after 2018-04-01. |
| `'< 2018-05-01'` | that were posted before 2018-05-01. |
| `['and', '>= 2018-04-01', '< 2018-05-01']` | that were posted between 2018-04-01 and 2018-05-01. |

::: code
```twig Twig
{# Fetch comments posted last month #}
{% set start = date('first day of last month')|atom %}
{% set end = date('first day of this month')|atom %}

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
:::

::: reference
### `dateCreated`

Narrows the query results based on the comments’ creation dates.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'>= 2018-04-01'` | that were created on or after 2018-04-01. |
| `'< 2018-05-01'` | that were created before 2018-05-01. |
| `['and', '>= 2018-04-01', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01. |

::: code
```twig Twig
{# Fetch comments created last month #}
{% set start = date('first day of last month')|atom %}
{% set end = date('first day of this month')|atom %}

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
:::

::: reference
### `dateUpdated`

Narrows the query results based on the comments’ last-updated dates.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'>= 2018-04-01'` | that were updated on or after 2018-04-01. |
| `'< 2018-05-01'` | that were updated before 2018-05-01. |
| `['and', '>= 2018-04-01', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01. |

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
:::

::: reference
### `descendantDist`

Narrows the query results to only comments that are up to a certain distance away from the comment specified by [descendantOf](#descendantof).

::: code
```twig Twig
{# Fetch comments below this one #}
{% set comments = craft.comments.fetch()
    .descendantOf(comment)
    .descendantDist(3)
    .all() %}
```

```php PHP
// Fetch comments below this one
$comments = \verbb\comments\elements\Comment::find()
    ->descendantOf($comment)
    ->descendantDist(3)
    ->all();
```
:::
:::

::: reference
### `descendantOf`

Narrows the query results to only comments that are descendants of another comment.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | below the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | below the comment represented by the object. |

::: code
```twig Twig
{# Fetch comments below this one #}
{% set comments = craft.comments.fetch()
    .descendantOf(comment)
    .all() %}
```

```php PHP
// Fetch comments below this one
$comments = \verbb\comments\elements\Comment::find()
    ->descendantOf($comment)
    ->all();
```
:::

::: tip
This can be combined with [descendantDist](#descendantdist) if you want to limit how far away the descendant comments can be.
:::
:::

::: reference
### `email`

Narrows the query results based on the email address the comments were posted with. Guests enter this themselves, and comments from logged-in users store the user’s email address.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'foo@bar.baz'` | posted with an email of `foo@bar.baz`. |
| `'not foo@bar.baz'` | not posted with an email of `foo@bar.baz`. |
| `'*@bar.baz'` | posted with an email that ends with `@bar.baz`. |

::: code
```twig Twig
{# Fetch comments from users with a .co.uk domain on their email address #}
{% set comments = craft.comments.fetch()
    .email('*.co.uk')
    .all() %}
```

```php PHP
// Fetch comments from users with a .co.uk domain on their email address
$comments = \verbb\comments\elements\Comment::find()
    ->email('*.co.uk')
    ->all();
```
:::
:::

::: reference
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
:::

::: reference
### `hasDescendants`

Narrows the query results based on whether the comments have any descendants.

(This has the opposite effect of calling [leaves](#leaves).)

::: code
```twig Twig
{# Fetch comments that have descendants #}
{% set comments = craft.comments.fetch()
    .hasDescendants()
    .all() %}
```

```php PHP
// Fetch comments that have descendants
$comments = \verbb\comments\elements\Comment::find()
    ->hasDescendants()
    ->all();
```
:::
:::

::: reference
### `id`

Narrows the query results based on the comments’ IDs.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | with an ID of 1. |
| `'not 1'` | not with an ID of 1. |
| `[1, 2]` | with an ID of 1 or 2. |
| `['not', 1, 2]` | not with an ID of 1 or 2. |

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
:::

::: reference
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
:::

::: reference
### `ipAddress`

Narrows the query results based on the IP address the comments were posted from. Comments only stores an IP address when Craft’s `storeUserIps` setting is enabled.

::: code
```twig Twig
{# Fetch comments posted from an IP address #}
{% set comments = craft.comments.fetch()
    .ipAddress('203.0.113.7')
    .all() %}
```

```php PHP
// Fetch comments posted from an IP address
$comments = \verbb\comments\elements\Comment::find()
    ->ipAddress('203.0.113.7')
    ->all();
```
:::
:::

::: reference
### `isFlagged`

Narrows the query results based on whether the comments have been flagged at least once. Pass `false` to fetch only comments that have never been flagged.

::: code
```twig Twig
{# Fetch comments that are flagged #}
{% set comments = craft.comments.fetch()
    .isFlagged(true)
    .all() %}
```

```php PHP
// Fetch comments that are flagged
$comments = \verbb\comments\elements\Comment::find()
    ->isFlagged(true)
    ->all();
```
:::
:::

::: reference
### `leaves`

Narrows the query results based on whether the comments are “leaves” (comments with no descendants).

(This has the opposite effect of calling [hasDescendants](#hasdescendants).)

::: code
```twig Twig
{# Fetch comments that have no descendants #}
{% set comments = craft.comments.fetch()
    .leaves()
    .all() %}
```

```php PHP
// Fetch comments that have no descendants
$comments = \verbb\comments\elements\Comment::find()
    ->leaves()
    ->all();
```
:::
:::

::: reference
### `level`

Narrows the query results based on the comments’ level within the structure.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | with a level of 1. |
| `'not 1'` | not with a level of 1. |
| `'>= 3'` | with a level greater than or equal to 3. |
| `[1, 2]` | with a level of 1 or 2. |
| `['not', 1, 2]` | not with level of 1 or 2. |

::: code
```twig Twig
{# Fetch comments positioned at level 3 or above #}
{% set comments = craft.comments.fetch()
    .level('>= 3')
    .all() %}
```

```php PHP
// Fetch comments positioned at level 3 or above
$comments = \verbb\comments\elements\Comment::find()
    ->level('>= 3')
    ->all();
```
:::
:::

::: reference
### `limit`

Determines the number of comments that should be returned.

::: code
```twig Twig
{# Fetch up to 10 comments  #}
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
:::

::: reference
### `name`

Narrows the query results based on the name the comments were posted with. Guests enter this themselves.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'Jane Smith'` | posted with the name “Jane Smith”. |
| `'Jane*'` | posted with a name that starts with “Jane”. |

::: code
```twig Twig
{# Fetch comments posted by guests named Jane #}
{% set comments = craft.comments.fetch()
    .name('Jane*')
    .all() %}
```

```php PHP
// Fetch comments posted by guests named Jane
$comments = \verbb\comments\elements\Comment::find()
    ->name('Jane*')
    ->all();
```
:::
:::

::: reference
### `nextSiblingOf`

Narrows the query results to only the comment that comes immediately after another comment.

Possible values include:

| Value | Fetches the comment… |
| --- | --- |
| `1` | after the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | after the comment represented by the object. |

::: code
```twig Twig
{# Fetch the next comment #}
{% set comment = craft.comments.fetch()
    .nextSiblingOf(comment)
    .one() %}
```

```php PHP
// Fetch the next comment
$comment = \verbb\comments\elements\Comment::find()
    ->nextSiblingOf($comment)
    ->one();
```
:::
:::

::: reference
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
:::

::: reference
### `orderBy`

Determines the order that the comments should be returned in. Alongside the usual attributes, you can order by `voteCount` (upvotes minus downvotes) and `flagCount` (the number of flags).

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

::: code
```twig Twig
{# Fetch the highest-rated comments on this entry #}
{% set topComments = craft.comments.fetch()
    .owner(entry)
    .orderBy('voteCount DESC')
    .limit(5)
    .all() %}
```

```php PHP
// Fetch the highest-rated comments on this entry
$topComments = \verbb\comments\elements\Comment::find()
    ->owner($entry)
    ->orderBy('voteCount DESC')
    ->limit(5)
    ->all();
```
:::
:::

::: reference
### `owner`

Sets the [ownerId](#ownerid) and [siteId](#siteid) parameters based on a given element, so you get the comments made on that element in its site.

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
:::

::: reference
### `ownerEntryType`

Narrows the query results to comments made on entries of the given entry types, per the entry types’ handles. You can also pass an `EntryType` object.

::: code
```twig Twig
{# Fetch comments made on Article entries #}
{% set comments = craft.comments.fetch()
    .ownerEntryType('article')
    .all() %}
```

```php PHP
// Fetch comments made on Article entries
$comments = \verbb\comments\elements\Comment::find()
    ->ownerEntryType('article')
    ->all();
```
:::
:::

::: reference
### `ownerEntryTypeId`

Narrows the query results to comments made on entries of the given entry types, per the entry types’ IDs.

::: code
```twig Twig
{# Fetch comments made on entries with an entry type ID of 4 #}
{% set comments = craft.comments.fetch()
    .ownerEntryTypeId(4)
    .all() %}
```

```php PHP
// Fetch comments made on entries with an entry type ID of 4
$comments = \verbb\comments\elements\Comment::find()
    ->ownerEntryTypeId(4)
    ->all();
```
:::
:::

::: reference
### `ownerId`

Narrows the query results based on the owner element of the comment, per the owners’ IDs.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | created for an element with an ID of 1. |
| `'not 1'` | not created for an element with an ID of 1. |
| `[1, 2]` | created for an element with an ID of 1 or 2. |
| `['not', 1, 2]` | not created for an element with an ID of 1 or 2. |

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
:::

::: reference
### `ownerSection`

Narrows the query results to comments made on entries in the given sections, per the sections’ handles. You can also pass a `Section` object.

::: code
```twig Twig
{# Fetch comments made on entries in the News and Blog sections #}
{% set comments = craft.comments.fetch()
    .ownerSection(['news', 'blog'])
    .all() %}
```

```php PHP
// Fetch comments made on entries in the News and Blog sections
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSection(['news', 'blog'])
    ->all();
```
:::
:::

::: reference
### `ownerSectionId`

Narrows the query results to comments made on entries in the given sections, per the sections’ IDs.

::: code
```twig Twig
{# Fetch comments made on entries in the section with an ID of 22 #}
{% set comments = craft.comments.fetch()
    .ownerSectionId(22)
    .all() %}
```

```php PHP
// Fetch comments made on entries in the section with an ID of 22
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSectionId(22)
    ->all();
```
:::
:::

::: reference
### `ownerSite`

Narrows the query results to comments made on elements in the given site, per the site’s handle. You can also pass a `Site` object. This also sets [siteId](#siteid), as a comment is only stored in its owner’s site.

::: code
```twig Twig
{# Fetch comments made in the French site #}
{% set comments = craft.comments.fetch()
    .ownerSite('fr')
    .all() %}
```

```php PHP
// Fetch comments made in the French site
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSite('fr')
    ->all();
```
:::
:::

::: reference
### `ownerSiteId`

Narrows the query results to comments made on elements in the given site, per the site’s ID. This also sets [siteId](#siteid).

::: code
```twig Twig
{# Fetch comments made in the site with an ID of 2 #}
{% set comments = craft.comments.fetch()
    .ownerSiteId(2)
    .all() %}
```

```php PHP
// Fetch comments made in the site with an ID of 2
$comments = \verbb\comments\elements\Comment::find()
    ->ownerSiteId(2)
    ->all();
```
:::
:::

::: reference
### `ownerType`

Narrows the query results to comments made on a given element type, such as only those on [entries](https://craftcms.com/docs/5.x/reference/element-types/entries.html). Pass the full element class name.

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
    ->ownerType(craft\elements\Entry::class)
    ->all();
```
:::
:::

::: reference
### `positionedAfter`

Narrows the query results to only comments that are positioned after another comment.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | after the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | after the comment represented by the object. |

::: code
```twig Twig
{# Fetch comments after this one #}
{% set comments = craft.comments.fetch()
    .positionedAfter(comment)
    .all() %}
```

```php PHP
// Fetch comments after this one
$comments = \verbb\comments\elements\Comment::find()
    ->positionedAfter($comment)
    ->all();
```
:::
:::

::: reference
### `positionedBefore`

Narrows the query results to only comments that are positioned before another comment.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | before the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | before the comment represented by the object. |

::: code
```twig Twig
{# Fetch comments before this one #}
{% set comments = craft.comments.fetch()
    .positionedBefore(comment)
    .all() %}
```

```php PHP
// Fetch comments before this one
$comments = \verbb\comments\elements\Comment::find()
    ->positionedBefore($comment)
    ->all();
```
:::
:::

::: reference
### `prevSiblingOf`

Narrows the query results to only the comment that comes immediately before another comment.

Possible values include:

| Value | Fetches the comment… |
| --- | --- |
| `1` | before the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | before the comment represented by the object. |

::: code
```twig Twig
{# Fetch the previous comment #}
{% set comment = craft.comments.fetch()
    .prevSiblingOf(comment)
    .one() %}
```

```php PHP
// Fetch the previous comment
$comment = \verbb\comments\elements\Comment::find()
    ->prevSiblingOf($comment)
    ->one();
```
:::
:::

::: reference
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
:::

::: reference
### `siblingOf`

Narrows the query results to only comments that are siblings of another comment.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | beside the comment with an ID of 1. |
| a [Comment](docs:reference/comment) object | beside the comment represented by the object. |

::: code
```twig Twig
{# Fetch comments beside this one #}
{% set comments = craft.comments.fetch()
    .siblingOf(comment)
    .all() %}
```

```php PHP
// Fetch comments beside this one
$comments = \verbb\comments\elements\Comment::find()
    ->siblingOf($comment)
    ->all();
```
:::
:::

::: reference
### `site`

Determines which site the comments should be queried in, per the site’s handle. Defaults to the current site. Pass `'*'` to query every site.

::: code
```twig Twig
{# Fetch comments from the French site #}
{% set comments = craft.comments.fetch()
    .site('fr')
    .all() %}
```

```php PHP
// Fetch comments from the French site
$comments = \verbb\comments\elements\Comment::find()
    ->site('fr')
    ->all();
```
:::
:::

::: reference
### `siteId`

Determines which site the comments should be queried in, per the site’s ID. Defaults to the current site.

::: code
```twig Twig
{# Fetch comments from the site with an ID of 2 #}
{% set comments = craft.comments.fetch()
    .siteId(2)
    .all() %}
```

```php PHP
// Fetch comments from the site with an ID of 2
$comments = \verbb\comments\elements\Comment::find()
    ->siteId(2)
    ->all();
```
:::
:::

::: reference
### `status`

Narrows the query results based on the comments’ statuses. The default comes from the [defaultQueryStatus](docs:get-started/configuration#defaultquerystatus) setting, which is `approved` unless you change it. The `trashed` status is separate from Craft’s soft-deleted elements, which `trashed()` fetches.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `'approved'` _(default)_ | that are approved. |
| `'pending'` | that are pending. |
| `'spam'` | that are marked as spam. |
| `'trashed'` | that are trashed (not deleted). |
| `['approved', 'pending']` | that are approved or pending. |
| `null` | with any status. |

::: code
```twig Twig
{# Fetch comments with any status #}
{% set comments = craft.comments.fetch()
    .status(null)
    .all() %}
```

```php PHP
// Fetch comments with any status
$comments = \verbb\comments\elements\Comment::find()
    ->status(null)
    ->all();
```
:::

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
    ->status('pending')
    ->all();
```
:::
:::

::: reference
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
:::

::: reference
### `url`

Narrows the query results based on the URL of the page the comments were posted from.

::: code
```twig Twig
{# Fetch comments posted from the blog #}
{% set comments = craft.comments.fetch()
    .url('*/blog/*')
    .all() %}
```

```php PHP
// Fetch comments posted from the blog
$comments = \verbb\comments\elements\Comment::find()
    ->url('*/blog/*')
    ->all();
```
:::
:::

::: reference
### `userAgent`

Narrows the query results based on the user agent of the browser the comments were posted from.

::: code
```twig Twig
{# Fetch comments posted from Firefox #}
{% set comments = craft.comments.fetch()
    .userAgent('*Firefox*')
    .all() %}
```

```php PHP
// Fetch comments posted from Firefox
$comments = \verbb\comments\elements\Comment::find()
    ->userAgent('*Firefox*')
    ->all();
```
:::
:::

::: reference
### `userId`

Narrows the query results based on the user, per their ID.

Possible values include:

| Value | Fetches comments… |
| --- | --- |
| `1` | with a user with an ID of 1. |
| `'not 1'` | not with a user with an ID of 1. |
| `[1, 2]` | with a user with an ID of 1 or 2. |
| `['not', 1, 2]` | not with a user with an ID of 1 or 2. |

::: code
```twig Twig
{# Fetch the current user's comments #}
{% set comments = craft.comments.fetch()
    .userId(currentUser.id)
    .all() %}
```

```php PHP
// Fetch the current user's comments
$user = Craft::$app->getUser()->getIdentity();

$comments = \verbb\comments\elements\Comment::find()
    ->userId($user->id)
    ->all();
```
:::
:::
