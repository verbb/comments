<?php
use craft\fieldlayoutelements\CustomField;
use craft\fields\Checkboxes;
use craft\fields\Dropdown;
use craft\fields\Lightswitch;
use craft\fields\Entries;
use craft\fields\PlainText;
use craft\models\FieldLayoutTab;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\controllers\FrontendApiController;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\models\CommentSubmission;

/**
 * The public frontend from the 2 November 2026 review, stage 6.
 */

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->allowGuest = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
});

afterEach(function() {
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    $request = Craft::$app->getRequest();
    $request->setQueryParams([]);
    $request->setBodyParams([]);
    $request->setIsConsoleRequest(true);
    unset($_SERVER['REQUEST_METHOD']);
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getIdentities()->clearCaches();
});

function passComment($entry, string $content = 'Comment', ?int $parentId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
        'parentId' => $parentId,
    ], static fn($v) => $v !== null)))->comment;
}

/** Render as if on a page of the site, with this in its URL. */
function passRender($entry, array $query = [], array $criteria = []): string
{
    $request = Craft::$app->getRequest();
    $request->setIsConsoleRequest(false);
    $request->setQueryParams($query);

    return (string)Comments::$plugin->getFrontend()->render((int)$entry->id, $criteria);
}

/**
 * Put fields on the comment layout for the length of a callback.
 *
 * @param array<string, craft\base\FieldInterface> $fields
 */
function passWithFields(array $fields, callable $callback): void
{
    $fieldsService = Craft::$app->getFields();
    $saved = [];

    foreach ($fields as $handle => $field) {
        $field->name = ucfirst($handle);
        $field->handle = $handle . bin2hex(random_bytes(3));
        expect($fieldsService->saveField($field))->toBeTrue();
        $saved[$handle] = $field;
    }

    $layout = $fieldsService->getLayoutByType(Comment::class);
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $original = $tab->getElements();
    $tab->setElements([...$original, ...array_map(static fn($field) => new CustomField($field), array_values($saved))]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
    expect($fieldsService->saveLayout($layout, false))->toBeTrue();
    Comments::$plugin->getSettings()->publicFields = array_map(static fn($field) => $field->handle, array_values($saved));

    try {
        $callback($saved);
    } finally {
        $tab->setElements($original);
        $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
        $fieldsService->saveLayout($layout, false);
    }
}

// Public Fields
// =========================================================================

it('draws the fields visitors can fill in, and no others', function() {
    $entry = CommentsFixtureFactory::entry('Public fields owner');

    passWithFields([
        'city' => new PlainText(['instructions' => 'Where you are']),
        'bio' => new PlainText(['multiline' => true]),
        'colour' => new Dropdown(['options' => [['label' => 'Red <b>', 'value' => 'red'], ['label' => 'Blue', 'value' => 'blue']]]),
        'tags' => new Checkboxes(['options' => [['label' => 'One', 'value' => 'one'], ['label' => 'Two', 'value' => 'two']]]),
        'agree' => new Lightswitch(),
        'related' => new Entries(),
    ], function(array $fields) use ($entry) {
        Comments::$plugin->getSettings()->publicFields = array_map(static fn($field) => $field->handle, array_values(array_filter(
            $fields,
            static fn($field) => $field->handle !== $fields['agree']->handle,
        )));

        $html = passRender($entry);

        expect($html)->toContain('name="fields[' . $fields['city']->handle . ']"')
            ->toContain('Where you are')
            ->toContain('<textarea id="comments-' . $entry->id . '-' . $entry->siteId . '-create-new-' . $fields['bio']->handle . '"')
            ->toContain('name="fields[' . $fields['colour']->handle . ']"')
            ->toContain('name="fields[' . $fields['tags']->handle . '][]"')
            ->toContain('Red &lt;b&gt;')
            // Not selected, and can't be drawn as a plain input.
            ->not->toContain('name="fields[' . $fields['agree']->handle . ']"')
            ->not->toContain('name="fields[' . $fields['related']->handle . ']"');
    });
});

it('fills in a public field when editing', function() {
    $entry = CommentsFixtureFactory::entry('Public field edit owner');

    passWithFields(['city' => new PlainText(), 'tags' => new Checkboxes(['options' => [['label' => 'One', 'value' => 'one'], ['label' => 'Two', 'value' => 'two']]])], function(array $fields) use ($entry) {
        $comment = passComment($entry);
        $comment->setFieldValue($fields['city']->handle, 'Hobart');
        $comment->setFieldValue($fields['tags']->handle, ['two']);
        Craft::$app->getElements()->saveElement($comment, false);

        $frontend = Comments::$plugin->getFrontend();
        $conversation = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
        $editor = $frontend->buildEditorView($conversation, 'edit', Comment::find()->id($comment->id)->one());
        $byHandle = array_column($editor->publicFields, 'values', 'handle');

        expect($byHandle[$fields['city']->handle])->toBe(['Hobart'])
            ->and($byHandle[$fields['tags']->handle])->toBe(['two']);
    });
});

it('says plainly what’s wrong with a comment, field by field', function() {
    $entry = CommentsFixtureFactory::entry('Validation owner');

    Craft::$app->getUser()->setIdentity(null);

    try {
        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'No name given',
            'email' => 'a@example.test',
        ]));
        $this->fail('Expected a validation error.');
    } catch (CommentValidationException $e) {
        expect($e->getMessage())->toBe('Name is required.')->not->toContain('{')
            ->and($e->fieldErrors)->toBe(['fields[name]' => ['Name is required.']]);
    }

    $translated = CommentValidationException::fromErrors(['field:city' => ['City cannot be blank.'], 'comment' => ['Too long.', 'Also wrong.']]);

    expect(array_keys($translated->fieldErrors))->toBe(['fields[city]', 'fields[comment]'])
        ->and($translated->getMessage())->toBe('City cannot be blank. Too long.');
});

// Working without JavaScript
// =========================================================================

it('pages and sorts through the page’s URL', function() {
    $entry = CommentsFixtureFactory::entry('URL paging owner');
    Comments::$plugin->getSettings()->interactionMode = 'like';

    foreach (range(1, 3) as $i) {
        passComment($entry, "Comment number {$i}");
    }

    $first = passRender($entry, ['other' => 'kept'], ['limit' => 2]);

    expect($first)->toContain('Comment number 3')->not->toContain('Comment number 1');

    preg_match('/href="\?([^"]+)"\s+class="comments__load-more-link"\s+data-comments-action="load-roots"/', $first, $match);
    parse_str(html_entity_decode($match[1]), $next);

    expect($next)->toHaveKeys(['other', 'commentsRootsNext', 'commentsSort']);

    expect(passRender($entry, $next, ['limit' => 2]))->toContain('Comment number 1')->not->toContain('Comment number 3');

    $oldest = passRender($entry, ['commentsSort' => 'oldest'], ['limit' => 1]);

    expect($oldest)->toContain('Comment number 1')->not->toContain('Comment number 3')
        // The sort links keep the page's other parameters.
        ->and(html_entity_decode(passRender($entry, ['other' => 'kept'])))->toMatch('/class="comments__sort-option"\s+href="\?[^"]*other=kept[^"]*commentsSort=oldest/');
});

it('lets the visitor’s sort from the URL win over the template’s default', function() {
    $entry = CommentsFixtureFactory::entry('URL sort over default owner');

    foreach (range(1, 3) as $i) {
        passComment($entry, "Sorted comment {$i}");
    }

    // The template asks for oldest first, and that's what a plain visit gets.
    expect(passRender($entry, [], ['sort' => 'oldest', 'limit' => 1]))->toContain('Sorted comment 1')->not->toContain('Sorted comment 3');

    // Following the Newest link puts its choice in the URL, which the page has to honour.
    $newest = passRender($entry, ['commentsSort' => 'newest'], ['sort' => 'oldest', 'limit' => 1]);

    expect($newest)->toContain('Sorted comment 3')->not->toContain('Sorted comment 1')
        // A sort the page doesn't know leaves the template's default in place.
        ->and(passRender($entry, ['commentsSort' => 'sideways'], ['sort' => 'oldest', 'limit' => 1]))->toContain('Sorted comment 1');
});

it('opens a reply or edit form in place for someone without JavaScript', function() {
    $entry = CommentsFixtureFactory::entry('URL forms owner');
    $comment = passComment($entry, 'Replying to this');

    $reply = passRender($entry, ['comment' => $comment->id, 'commentsCompose' => 'reply']);

    expect($reply)->toContain('data-comments-form="reply"')->toContain('name="parentId" value="' . $comment->id . '"');

    $edit = passRender($entry, ['comment' => $comment->id, 'commentsCompose' => 'edit']);

    expect($edit)->toContain('data-comments-form="edit"')->toContain('Replying to this</textarea>');

    // Someone who isn't allowed that action doesn't get its form.
    Craft::$app->getUser()->setIdentity(null);
    Craft::$app->getSession();

    expect(passRender($entry, ['comment' => $comment->id, 'commentsCompose' => 'edit']))->not->toContain('data-comments-form="edit"');
    expect(passRender($entry, ['comment' => $comment->id, 'commentsCompose' => 'nonsense']))->toContain('data-comments-comment-id="' . $comment->id . '"');
});

it('shows more of a thread’s replies through the URL', function() {
    $entry = CommentsFixtureFactory::entry('URL replies owner');
    $root = passComment($entry, 'Root');

    foreach (range(1, 6) as $i) {
        passComment($entry, "Reply number {$i}", (int)$root->id);
    }

    $preview = passRender($entry);
    $more = passRender($entry, ['comment' => $root->id, 'commentsReplyLimit' => 6]);

    expect(substr_count($preview, 'Reply number'))->toBe(3)
        ->and(substr_count($more, 'Reply number'))->toBe(6);
});

it('opens the flag form in the page for someone without JavaScript', function() {
    $entry = CommentsFixtureFactory::entry('URL flag owner');
    $comment = passComment($entry);

    $html = passRender($entry, ['comment' => $comment->id, 'commentsFlag' => 1]);

    expect($html)->toMatch('/<dialog[^>]*\sopen>/')->toContain('name="commentId" value="' . $comment->id . '"');
    expect(passRender($entry))->not->toMatch('/<dialog[^>]*\sopen>/');
});

// Shared cache and lazy loading
// =========================================================================

it('keeps anything private out of a page cached for everyone', function() {
    $entry = CommentsFixtureFactory::entry('Shared cache owner');
    passComment($entry);
    Comments::$plugin->getSettings()->subscriptionTypes = ['conversation'];

    $html = passRender($entry, [], ['sharedCache' => true]);

    expect($html)->not->toContain('name="' . Craft::$app->getConfig()->getGeneral()->csrfTokenName . '"')
        ->not->toContain('data-comments-report-dialog')
        ->not->toContain('data-comments-action="subscribe"')
        ->toContain('data-comments-shared-cache="1"');
});

it('gives the browser the same conversation ID every time, even before there is one', function() {
    $entry = CommentsFixtureFactory::entry('Stable ID owner');
    $frontend = Comments::$plugin->getFrontend();

    $first = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId)->conversationUid;
    $second = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId)->conversationUid;

    expect($first)->toBe($second);

    // What the browser fetches for a cached page has to land in that page.
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $fragments = $frontend->viewerControlFragments($view, $frontend->buildViewerView($view), $frontend->buildEditorView($view));

    expect(array_column($fragments, 'type'))->toBe(['conversationHeader', 'rootList', 'viewerControls', 'reportDialog'])
        ->and($fragments[2]['resourceUid'])->toBe($first)
        ->and($fragments[2]['html'])->toContain('data-comments-form="create"');
});

it('tells someone comments are closed on a cached page too', function() {
    $entry = CommentsFixtureFactory::entry('Cached closed owner');
    passComment($entry);
    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, true);
    $frontend = Comments::$plugin->getFrontend();

    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $fragments = $frontend->viewerControlFragments($view, $frontend->buildViewerView($view), $frontend->buildEditorView($view));

    expect($fragments[2]['html'])->toContain('Comments are closed');
});

it('leaves the comments out until they’re reached when loading lazily', function() {
    $entry = CommentsFixtureFactory::entry('Lazy owner');
    $comment = passComment($entry, 'Far below the fold');

    $lazy = passRender($entry, [], ['lazy' => true]);

    expect($lazy)->toContain('data-comments-lazy="1"')
        ->toContain('data-comments-shared-cache="1"')
        ->toContain('comments__loading')
        ->toContain('Show comments')
        ->not->toContain('Far below the fold');

    // A setting does the same.
    Comments::$plugin->getSettings()->lazyLoad = true;
    expect(passRender($entry))->toContain('data-comments-lazy="1"');

    // A URL asking for something gets it straight away, including the link for those without JavaScript.
    expect(passRender($entry, ['commentsLoad' => 1]))->toContain('Far below the fold')->not->toContain('data-comments-lazy');
    expect(passRender($entry, ['comment' => $comment->id]))->toContain('Far below the fold');
});

// Markup and accessibility
// =========================================================================

it('never repeats an ID, with both a new comment and a reply form on the page', function() {
    $entry = CommentsFixtureFactory::entry('Unique ID owner');
    $comment = passComment($entry);

    Craft::$app->getUser()->setIdentity(null);
    $html = passRender($entry, ['comment' => $comment->id, 'commentsCompose' => 'reply']);

    preg_match_all('/\sid="([^"]+)"/', $html, $matches);
    $counts = array_count_values($matches[1]);

    expect(array_filter($counts, static fn(int $count) => $count > 1))->toBe([]);
});

it('labels the form, including for guests, and describes actions without JavaScript', function() {
    $entry = CommentsFixtureFactory::entry('Labels owner');
    $comment = passComment($entry);
    Comments::$plugin->getSettings()->interactionMode = 'updown';

    $html = passRender($entry);

    expect($html)->toMatch('/<label[^>]*for="[^"]+-comment-body"/')
        ->toContain('role="list"')
        ->toContain('tabindex="-1"')
        ->toContain('role="status"')
        // Scores aren't left to an aria-label on a span, which screen readers can ignore.
        ->toContain('comments__visually-hidden')
        ->toMatch('/<noscript><a class="comments__action-link" href="\?[^"]*commentsCompose=reply/')
        ->toContain('data-comments-js-only')
        ->toContain('id="comment-' . $comment->id . '"');
});

it('caps how far a thread indents without changing its real depth', function() {
    $entry = CommentsFixtureFactory::entry('Indent owner');
    $parent = passComment($entry, 'Root');

    foreach (range(1, 6) as $i) {
        $parent = passComment($entry, "Level {$i}", (int)$parent->id);
    }

    $thread = Comments::$plugin->getFrontend()->buildThreadView((int)$parent->id, (int)$entry->id, (int)$entry->siteId, false);
    $node = $thread;

    while ($node->id !== (int)$parent->id) {
        $node = $node->replies[0];
    }

    expect($node->depth)->toBe(6)->and($node->visualDepth)->toBe(4);
});

it('stops offering Reply at the deepest level comments can go', function() {
    $entry = CommentsFixtureFactory::entry('Reply depth owner');
    $root = passComment($entry, 'Root');
    $reply = passComment($entry, 'Reply', (int)$root->id);
    Comments::$plugin->getSettings()->maxReplyDepth = 1;

    $views = Comments::$plugin->getFrontend()->buildCommentViewsForComments([$root, $reply], 0);

    expect($views[0]->actions)->toContain('reply')
        ->and($views[1]->actions)->not->toContain('reply');
});

// Responses
// =========================================================================

it('tells an author about a held comment, rather than showing it as unavailable', function() {
    $entry = CommentsFixtureFactory::entry('Held response owner');
    Comments::$plugin->getSettings()->requireModeration = true;
    $author = CommentsFixtureFactory::user('held' . bin2hex(random_bytes(3)));
    Craft::$app->getUser()->setIdentity($author);
    $comment = passComment($entry, 'Waiting');

    $controller = new FrontendApiController('frontend-api', Comments::$plugin);
    $method = new ReflectionMethod($controller, '_saveResponseEnvelope');
    $envelope = $method->invoke($controller, Comments::$plugin->getFrontend(), $comment, 'op', 'Submitted', 'created');
    $types = array_column($envelope['fragments'], 'type');

    expect($types)->not->toContain('comment')
        ->toContain('conversationHeader')
        ->and($envelope['resource'])->toBeNull();
});

it('shows an author their own new reply, even past the preview', function() {
    $entry = CommentsFixtureFactory::entry('Reply response owner');
    Comments::$plugin->getSettings()->orderBy = 'asc';
    $root = passComment($entry, 'Root');

    foreach (range(1, 4) as $i) {
        passComment($entry, "Earlier {$i}", (int)$root->id);
    }

    $reply = passComment($entry, 'The newest reply', (int)$root->id);

    $controller = new FrontendApiController('frontend-api', Comments::$plugin);
    $method = new ReflectionMethod($controller, '_saveResponseEnvelope');
    $envelope = $method->invoke($controller, Comments::$plugin->getFrontend(), $reply, 'op', 'Posted', 'created');

    expect($envelope['fragments'][0]['html'])->toContain('The newest reply')
        ->and($envelope['resource'])->toBe($reply->uid);
});

it('hides default styling with the None theme', function() {
    $entry = CommentsFixtureFactory::entry('No theme owner');
    Comments::$plugin->getSettings()->editorTheme = 'none';

    $view = Craft::$app->getView();
    $view->assetBundles = [];
    Comments::$plugin->getComments()->render((int)$entry->id);

    expect(array_keys($view->assetBundles))->not->toContain(verbb\comments\web\assets\frontend\FrontEndCssAsset::class);
});

it('recognises a guest in the same request that first gave them a cookie', function() {
    $identities = Comments::$plugin->getIdentities();
    Craft::$app->getUser()->setIdentity(null);
    Craft::$app->getRequest()->setIsConsoleRequest(false);

    expect($identities->findGuestForRequest())->toBeNull();

    $identity = $identities->resolveForCurrentRequest('Sam', 'first-visit@example.test');

    // Their vote has to come back pressed, though their browser hasn't sent the cookie back yet.
    expect((int)$identities->findGuestForRequest()?->id)->toBe((int)$identity->id);

    $identities->clearCaches();
});

it('lets a site override one template and keeps the rest of the plugin’s', function() {
    $entry = CommentsFixtureFactory::entry('Partial override');
    $folder = 'comments-override-' . bin2hex(random_bytes(3));
    $root = Craft::$app->getPath()->getSiteTemplatesPath() . DIRECTORY_SEPARATOR . $folder;
    craft\helpers\FileHelper::createDirectory($root . '/frontend/_conversation');
    file_put_contents($root . '/frontend/_conversation/empty.twig', '<p class="site-empty">Be the first.</p>');
    $frontend = Comments::$plugin->getFrontend();
    $view = Craft::$app->getView();
    $templatesPath = $view->getTemplatesPath();
    $loader = $view->getTwig()->getLoader();

    try {
        $default = (string)$frontend->render((int)$entry->id, ['siteId' => (int)$entry->siteId]);
        expect($default)->not->toContain('site-empty');

        Comments::$plugin->getSettings()->templateFolderOverride = $folder;
        $html = (string)$frontend->render((int)$entry->id, ['siteId' => (int)$entry->siteId]);

        // The partial it copied, inside everything it didn't.
        expect($html)->toContain('site-empty')
            ->and($html)->toContain('data-comments-region="editor"')
            ->and($html)->toContain('comments__header');

        // Only for the plugin's own rendering: the site's templates go back to resolving as normal.
        expect($view->getTemplatesPath())->toBe($templatesPath);
        expect($view->getTwig()->getLoader())->toBe($loader);
    } finally {
        Comments::$plugin->getSettings()->templateFolderOverride = null;
        craft\helpers\FileHelper::removeDirectory($root);
    }
});

it('resolves imports in an override inside its frontend folder, not the rest of the site’s templates', function() {
    $entry = CommentsFixtureFactory::entry('Override imports');
    $folder = 'comments-override-' . bin2hex(random_bytes(3));
    $templates = Craft::$app->getPath()->getSiteTemplatesPath();
    $root = $templates . DIRECTORY_SEPARATOR . $folder;
    $siteMacros = '_macros-' . bin2hex(random_bytes(3));
    craft\helpers\FileHelper::createDirectory($root . '/frontend/_conversation');
    craft\helpers\FileHelper::createDirectory($root . '/frontend/_macros');
    craft\helpers\FileHelper::createDirectory($templates . '/' . $siteMacros);
    file_put_contents($root . '/frontend/_macros/notes.twig', '{% macro empty() %}<p class="macro-empty">From a macro.</p>{% endmacro %}');
    file_put_contents($templates . '/' . $siteMacros . '/notes.twig', '{% macro empty() %}<p class="site-macro">From the site.</p>{% endmacro %}');
    file_put_contents($root . '/frontend/_conversation/empty.twig', "{% import '_macros/notes' as notes %}{{ notes.empty() }}");
    $frontend = Comments::$plugin->getFrontend();

    try {
        Comments::$plugin->getSettings()->templateFolderOverride = $folder;

        expect((string)$frontend->render((int)$entry->id, ['siteId' => (int)$entry->siteId]))->toContain('macro-empty');

        // A path from the site's templates folder isn't looked up.
        craft\helpers\FileHelper::createDirectory($root . '-site/frontend/_conversation');
        file_put_contents($root . '-site/frontend/_conversation/empty.twig', "{% import '{$siteMacros}/notes' as notes %}{{ notes.empty() }}");
        Comments::$plugin->getSettings()->templateFolderOverride = $folder . '-site';

        expect(fn() => (string)$frontend->render((int)$entry->id, ['siteId' => (int)$entry->siteId]))->toThrow(Twig\Error\LoaderError::class);
    } finally {
        Comments::$plugin->getSettings()->templateFolderOverride = null;
        craft\helpers\FileHelper::removeDirectory($root);
        craft\helpers\FileHelper::removeDirectory($root . '-site');
        craft\helpers\FileHelper::removeDirectory($templates . '/' . $siteMacros);
    }
});

it('keeps the chosen sort on the links that move around the page without JavaScript', function() {
    $entry = CommentsFixtureFactory::entry('Sort links');
    passComment($entry);

    $html = passRender($entry, ['commentsSort' => 'oldest']);

    expect($html)->toMatch('/commentsCompose=reply[^"]*commentsSort=oldest|commentsSort=oldest[^"]*commentsCompose=reply/');
    expect($html)->toContain('name="redirect"');
});

it('shows short public field values on a comment’s meta line, and never an address', function() {
    $entry = CommentsFixtureFactory::entry('Meta line owner');

    passWithFields([
        'city' => new PlainText(),
        'pace' => new Dropdown(['options' => [['label' => 'I race <b>', 'value' => 'race'], ['label' => 'I hike', 'value' => 'hike']]]),
        'contact' => new craft\fields\Email(),
        'bio' => new PlainText(['multiline' => true]),
    ], function(array $fields) use ($entry) {
        $comment = passComment($entry, 'With a meta line');
        $comment->setFieldValues([
            $fields['city']->handle => 'Rotorua',
            $fields['pace']->handle => 'race',
            $fields['contact']->handle => 'meta-line@example.test',
            $fields['bio']->handle => 'A long story',
        ]);
        Craft::$app->getElements()->saveElement($comment, false);

        $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);
        $html = passRender($entry);

        expect($view->roots[0]->metaLine)->toBe(['Rotorua', 'I race <b>'])
            ->and($html)->toContain('<span class="comments__meta-item">I race &lt;b&gt;</span>')
            ->not->toContain('meta-line@example.test')
            ->not->toContain('A long story');
    });
});
