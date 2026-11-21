# Add Your Own Data to Comments

A comment thread usually shows a little more than who said what and when. A shop might mark the people who bought the product they're reviewing, a community might show each member's role, and a course might show which lesson a student had reached. Comments can show this kind of extra information beside each comment, and this guide explains the two ways it gets there, then builds the most common example from start to finish.

You'll work on a tea shop, Fernwood Tea Co., which runs on Craft Commerce and takes comments on its product pages as reviews. By the end, a reviewer who has bought the product gets a **Verified buyer** badge beside their name, and their review says which size they bought, such as "Bought the 250 g tin". Readers can then tell an actual customer's opinion from anyone else's.

## Two Kinds of Extra Data

Extra data on a comment comes from one of two places, and it's worth deciding which before writing anything.

**What the commenter tells you.** A rating, the city they're writing from, an order number for your support team, or a photo of their setup. These are custom fields on the comment form, which the commenter fills in. You set them up in Comments' settings without any code, and choose for each one whether it's kept private or shown on the comment. [Ask Commenters for More with Custom Fields](docs:user-guides/forms-data/ask-commenters-for-more-with-custom-fields) covers them.

**What your site already knows.** Whether the commenter bought the product, which user group they're in, how long they've been a member, or something from your own database. The commenter doesn't enter any of this, and they shouldn't be able to, because a commenter could claim to be a verified buyer. Instead, your own PHP code adds it to the comment's data on the server, through two events:

- `Comments::EVENT_DEFINE_BADGES` adds badges, which the thread shows beside the commenter's name with no other changes.
- `Api::EVENT_DEFINE_COMMENT_DATA` adds any other data to each comment, under a `custom` key, which you then draw with a part override.

The rest of this guide uses both. The badge says the reviewer bought the product, and the comment data carries which size.

## What You'll Need

You'll need Craft Commerce with products that have their own pages, and Comments set up to take comments on products. The product template outputs the thread with:

```twig
{{ craft.comments.render(product) }}
```

If comments don't appear on products yet, check that products are allowed to take comments under [Permissions](docs:feature-tour/permissions), and follow [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry), which works the same for products.

The PHP goes in a Craft module, which is a small piece of code your project loads with Craft. If your project doesn't have one, Craft's documentation on [how to build a module](https://craftcms.com/docs/5.x/extend/module-guide.html) explains it. This guide creates a module called `shop` from scratch, and you'll need to be comfortable editing PHP and running Composer in your project's root folder.

To test it, you'll need a customer account that has a completed order containing one of the product's variants, and another account that hasn't bought anything.

## How a Comment's Data Is Built

When a visitor opens a product page, Comments' script fetches the thread from Comments' data API on your Craft site, as JSON, and draws it in the browser. The server builds that JSON from the comments, and the two events let your code add to it as it does.

That JSON is **public**. The same response is served to every visitor, and it's cached, by Craft and by a CDN if you have one, so a busy product page doesn't rebuild it for every view. Comments builds it with nobody signed in, so it can't depend on who's reading. This has two consequences for your code.

First, what you add must only depend on the comment and its author, never on the person viewing the page. "This commenter bought the product" is fine. "The person reading has also bought it" belongs in the separate private data each visitor gets, through `Api::EVENT_DEFINE_VIEWER_DATA`, which [Events](docs:developers/events) describes.

Second, what you add is visible to anyone who opens the API response, even if your thread doesn't draw it. Never add an email address, an IP address, a user ID or anything else that identifies the commenter or that they'd expect to stay private. In Dev Mode, Comments writes a warning to Craft's log when it spots data like that.

Both events also give you every comment in the response at once, so you can look things up with one database query for the whole thread, rather than one query per comment. A product with two hundred reviews would otherwise make two hundred queries.

## Creating the Module

Create the file `modules/shop/Module.php` in your project's root folder:

```php
<?php
namespace modules\shop;

use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        parent::init();

        // Comments' events go here.
    }
}
```

Tell Composer where to find the module's classes, by adding it to the `autoload` section of your project's `composer.json`:

```json
"autoload": {
    "psr-4": {
        "modules\\shop\\": "modules/shop/"
    }
}
```

If the section is already there, add the `modules\\shop\\` line to it. Then run this in your project's root folder, so Composer picks up the change:

```bash
composer dump-autoload
```

Finally, load the module with Craft, in `config/app.php`:

```php
<?php

use modules\shop\Module as ShopModule;

return [
    'modules' => [
        'shop' => ShopModule::class,
    ],
    'bootstrap' => ['shop'],
];
```

`bootstrap` makes Craft load the module on every request, so its events are listened for. Reload any page of your site, and if it loads without an error, the module is in place.

## Finding Out Who Bought the Product

Both events need the same answer, which reviewers bought this product and which variant, so start with a method that works it out once. Commerce stores each order's items as line items, each pointing at the variant that was bought, which Commerce calls a purchasable. A completed order is one the customer finished paying for, and its `customerId` is the customer's user ID.

Add these to the module, replacing the whole file:

```php
<?php
namespace modules\shop;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\db\Query;
use verbb\comments\elements\Comment;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    /** Each product's buyers among the reviewers looked up so far, as user ID to the variant they bought, or null. */
    private array $purchases = [];

    public function init(): void
    {
        parent::init();

        // Comments' events go here.
    }

    /**
     * The variant each of these comments' authors bought of the product, keyed by user ID, with
     * one query for every author not looked up yet. An author who hasn't bought it maps to null.
     */
    private function purchases(Product $product, array $comments): array
    {
        $known = $this->purchases[$product->id] ?? [];
        $authorIds = array_values(array_diff(
            array_unique(array_filter(array_map(fn(Comment $comment) => (int)$comment->userId, $comments))),
            array_keys($known),
        ));

        if ($authorIds) {
            $variantIds = Variant::find()->productId($product->id)->status(null)->ids();

            // The newest completed order first, so a reviewer who bought twice shows their latest size.
            $rows = $variantIds ? (new Query())
                ->select(['orders.customerId', 'lineItems.purchasableId'])
                ->from(['orders' => CommerceTable::ORDERS])
                ->innerJoin(['lineItems' => CommerceTable::LINEITEMS], '[[lineItems.orderId]] = [[orders.id]]')
                ->where([
                    'orders.isCompleted' => true,
                    'orders.customerId' => $authorIds,
                    'lineItems.purchasableId' => $variantIds,
                ])
                ->orderBy(['orders.dateOrdered' => SORT_DESC])
                ->all() : [];

            $variants = Variant::find()->id(array_column($rows, 'purchasableId'))->status(null)->indexBy('id')->all();

            foreach ($authorIds as $authorId) {
                $known[$authorId] = null;
            }

            foreach (array_reverse($rows) as $row) {
                $known[(int)$row['customerId']] = $variants[$row['purchasableId']] ?? null;
            }

            $this->purchases[$product->id] = $known;
        }

        return $known;
    }
}
```

The method takes the product and a list of comments, and answers with the variant each comment's author bought, keyed by their user ID. Here's how it gets there:

1. It collects the user IDs of the comments' authors, leaving out guests, who have no user ID, and anyone already looked up.
2. It finds the IDs of all the product's variants, including disabled ones, so a size that's no longer sold still counts.
3. It makes one query over Commerce's orders and line items, for completed orders by those authors that contain one of those variants, newest first.
4. It loads the variants that were bought, so their titles are to hand, and records each author's latest one. Authors who haven't bought the product are recorded as `null`, so they aren't looked up again.

The results are kept in `$this->purchases` for the rest of the request. Both events ask about the same comments, and the badge event is called once for each comment, so without that every comment would repeat the query.

## Adding the Verified Buyer Badge

Now listen for the badge event. Replace the `// Comments' events go here.` line in `init()` with:

```php
        Event::on(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event): void {
            $comment = $event->comment;
            $product = $comment?->getOwner();

            if (!$product instanceof Product || !$comment->userId) {
                return;
            }

            if ($this->purchases($product, $event->comments)[(int)$comment->userId] ?? null) {
                $event->badges[] = ['type' => 'verified', 'label' => 'Verified buyer'];
            }
        });
```

And add these to the `use` lines at the top of the file:

```php
use verbb\comments\events\DefineBadgesEvent;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;
```

The event is triggered for each comment as the thread is built. `$event->comment` is the comment, and `$event->comments` holds every comment being built with it, which the method above uses to look all their authors up together. `getOwner()` returns the element the comment is on, so the listener only acts on comments on products, and only for signed-in commenters.

When the author bought the product, the listener adds a badge to `$event->badges`. A badge is an array with a `type` and a `label`. The thread shows the `label` beside the commenter's name, in an element with the classes `comments__badge` and `comments__badge--verified`, named after the `type`, so you can style it. The badges Comments adds itself, such as the staff badge, are already in the array, and yours go after them.

The badge event is also used for the **Posting as** line above the comment form for someone signed in, where `$event->comment` is a new, unsaved comment by them on the product. So a customer who has bought the product sees their badge there before they write anything, showing them it'll be on their review.

### Checking the Badge

Sign in as your test customer, the one with a completed order for the product, and post a review on the product's page. Reload the page signed out. The review now has **Verified buyer** beside the customer's name. Reviews from accounts that haven't bought the product, and from guests, have no badge.

If the badge doesn't appear, open the thread's data directly. Its address is your site's URL followed by `/actions/comments/api/thread?ownerId=` and the product's ID, such as `https://fernwood.test/actions/comments/api/thread?ownerId=1234`. Find the review in `comments`, and look at its `author.badges`. If the badge isn't there, check that the order is completed, that the customer is the account that wrote the review, and that the module is listed in `config/app.php`.

The thread's data is cached, so an order completed after a product's thread was last built won't show a badge until that cache is refreshed. Comments refreshes it whenever a comment changes, such as when a review is posted, and otherwise at least every hour. To see the change straight away while testing, clear the data caches under **Utilities** → **Caches**. If your site sets Craft's `@web` alias from each request's host, Comments doesn't cache the thread's data at all, and new badges show on the next load, as [Caching](docs:headless/data-api#caching) in the data API docs explains.

## Adding the Size They Bought

The badge says the reviewer is a customer. The size they bought is useful too, because a review of the 1 kg bag might not apply to the 50 g sampler. It isn't a badge, so it goes in the comment's data with the other event. Add a second listener to `init()`, after the first:

```php
        Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event): void {
            if (!$event->owner instanceof Product) {
                return;
            }

            $purchases = $this->purchases($event->owner, $event->comments);

            foreach ($event->comments as $uid => $comment) {
                $variant = $purchases[(int)$comment->userId] ?? null;

                if ($variant) {
                    $event->custom[$uid] = ['boughtVariant' => $variant->title];
                }
            }
        });
```

And add these `use` lines:

```php
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\services\Api;
```

This event is triggered once for the whole response, rather than once per comment. `$event->comments` holds the comments keyed by their UID, a unique ID each comment has, and `$event->owner` is the element they're on. For each comment whose author bought the product, the listener puts an array in `$event->custom` under that comment's UID. Comments adds it to that comment's JSON as its `custom` property, so the review gets `"custom": { "boughtVariant": "250 g tin" }`. Comments without an entry in `$event->custom` get an empty `custom`.

Reload the thread's data address from earlier, and the verified review now has its `custom` with the variant's title.

## Showing the Size on the Review

Comments doesn't know what your custom data means, so it doesn't draw it. You draw it with a part override, which replaces one part of the thread's markup with your own. [Change a Part's Structure](docs:user-guides/theming-markup/change-a-parts-structure) explains part overrides in depth. Here you'll wrap the `meta` part, which holds the date after the commenter's name, so "Bought the 250 g tin" follows the date.

Add this to a script that your product pages load, such as `web/js/site.js`:

```js
document.addEventListener('DOMContentLoaded', () => {
    window.Comments?.configure({
        parts: {
            meta: ({ comment, html, defaultRender }) => html`
                ${defaultRender()}
                ${comment.custom.boughtVariant
                    ? html`<span class="comments__meta-item fernwood-bought">Bought the ${comment.custom.boughtVariant}</span>`
                    : ''}`,
        },
    });
});
```

Comments' script runs just before the `DOMContentLoaded` event, so `window.Comments` is ready inside the listener, and the `?.` skips pages without comments. The override draws Comments' own date with `defaultRender()`, then adds a line when the comment has a `boughtVariant`. The `html` tag escapes what it inserts, so a variant title is always shown as text. The `comments__meta-item` class is the one Comments gives the other items after the date, such as short custom field values, so the theme spaces it the same way.

Reload the product page. The verified review now reads something like "Verified buyer · 2 days ago · Bought the 250 g tin".

If your site draws the thread with React or Vue instead, the override is a component given the same `comment`, as the [React starter](https://docs.verbb.io/comments/starters/react) and [Vue starter](https://docs.verbb.io/comments/starters/vue) show for their own **Verified buyer** badge.

## The Finished Module

Here's `modules/shop/Module.php` in full:

```php
<?php
namespace modules\shop;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\db\Query;
use verbb\comments\elements\Comment;
use verbb\comments\events\DefineBadgesEvent;
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\services\Api;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    /** Each product's buyers among the reviewers looked up so far, as user ID to the variant they bought, or null. */
    private array $purchases = [];

    public function init(): void
    {
        parent::init();

        Event::on(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event): void {
            $comment = $event->comment;
            $product = $comment?->getOwner();

            if (!$product instanceof Product || !$comment->userId) {
                return;
            }

            if ($this->purchases($product, $event->comments)[(int)$comment->userId] ?? null) {
                $event->badges[] = ['type' => 'verified', 'label' => 'Verified buyer'];
            }
        });

        Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event): void {
            if (!$event->owner instanceof Product) {
                return;
            }

            $purchases = $this->purchases($event->owner, $event->comments);

            foreach ($event->comments as $uid => $comment) {
                $variant = $purchases[(int)$comment->userId] ?? null;

                if ($variant) {
                    $event->custom[$uid] = ['boughtVariant' => $variant->title];
                }
            }
        });
    }

    /**
     * The variant each of these comments' authors bought of the product, keyed by user ID, with
     * one query for every author not looked up yet. An author who hasn't bought it maps to null.
     */
    private function purchases(Product $product, array $comments): array
    {
        $known = $this->purchases[$product->id] ?? [];
        $authorIds = array_values(array_diff(
            array_unique(array_filter(array_map(fn(Comment $comment) => (int)$comment->userId, $comments))),
            array_keys($known),
        ));

        if ($authorIds) {
            $variantIds = Variant::find()->productId($product->id)->status(null)->ids();

            // The newest completed order first, so a reviewer who bought twice shows their latest size.
            $rows = $variantIds ? (new Query())
                ->select(['orders.customerId', 'lineItems.purchasableId'])
                ->from(['orders' => CommerceTable::ORDERS])
                ->innerJoin(['lineItems' => CommerceTable::LINEITEMS], '[[lineItems.orderId]] = [[orders.id]]')
                ->where([
                    'orders.isCompleted' => true,
                    'orders.customerId' => $authorIds,
                    'lineItems.purchasableId' => $variantIds,
                ])
                ->orderBy(['orders.dateOrdered' => SORT_DESC])
                ->all() : [];

            $variants = Variant::find()->id(array_column($rows, 'purchasableId'))->status(null)->indexBy('id')->all();

            foreach ($authorIds as $authorId) {
                $known[$authorId] = null;
            }

            foreach (array_reverse($rows) as $row) {
                $known[(int)$row['customerId']] = $variants[$row['purchasableId']] ?? null;
            }

            $this->purchases[$product->id] = $known;
        }

        return $known;
    }
}
```

## Testing It All

Run through these with the product page open in a private window, signed out, so you see what every visitor sees.

1. A review by your test customer shows **Verified buyer** beside their name and "Bought the" with the size after the date.
2. A review by an account without an order for this product shows neither, and neither does a guest's review.
3. A reply by the test customer to someone else's review shows both too, because the badge belongs to the commenter, whatever they're replying to.
4. Signed in as the test customer, the **Posting as** line above the comment form shows their **Verified buyer** badge.
5. In the thread's data at `/actions/comments/api/thread?ownerId=` and the product's ID, no review's `custom` holds anything you wouldn't want every visitor to see.

To style the badge differently from Comments' other badges, use its class, `comments__badge--verified`, as [Make a Custom Theme](docs:user-guides/theming-markup/make-a-custom-theme) shows for the staff badge.

## Other Uses for the Same Events

The same two events cover most "show something the site knows" requests. A badge from a user group needs only `$event->comment->getUser()` and `isInGroup()`, with no lookup method at all, and [Events](docs:developers/events) has that example along with the full details of both events. Comment data can carry anything a part override can draw, such as a member's join year for the `authorName` part, or a link to a product the comment mentions for the `content` part.

Keep to the same two rules whatever you add. Base it only on the comment and its author, never on who's reading, and only add what you're happy for everyone to see.
