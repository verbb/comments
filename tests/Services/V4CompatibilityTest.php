<?php

declare(strict_types=1);

use craft\elements\Entry;
use craft\models\GqlSchema;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\compatibility\console\LegacyBaseController;
use verbb\comments\compatibility\gql\LegacyGql;
use verbb\comments\compatibility\twig\LegacyRenderOptions;
use verbb\comments\elements\Comment;
use verbb\comments\fields\CommentsField;
use verbb\comments\services\Compatibility;
use verbb\comments\variables\CommentsVariable;
use Twig\Error\RuntimeError;
use yii\base\InvalidArgumentException;
use yii\base\UnknownMethodException;

/**
 * Compatibility mode: Comments 3's Twig helpers, element methods, query parameters, GraphQL schema and
 * console commands keep working with a deprecation warning while `compatibilityMode` is on, the default,
 * and are gone with it off. Comments 3's stored field values are read whatever the mode.
 */

/** The deprecation warnings logged in this request with `$key`. */
function compatibilityDeprecations(string $key): array
{
    return array_values(array_filter(Craft::$app->getDeprecator()->getRequestLogs(), static fn($log): bool => $log->key === $key));
}

function compatibilityOff(): void
{
    Comments::$plugin->getSettings()->compatibilityMode = false;
}

/** A thread three comments deep on its own entry: the top-level comment, its reply and the reply's reply. */
function compatibilityThread(string $title): array
{
    $entry = CommentsFixtureFactory::entry($title);
    $root = CommentsFixtureFactory::comment($entry, 'Top-level');
    $reply = CommentsFixtureFactory::comment($entry, 'Reply', parent: $root);
    $nested = CommentsFixtureFactory::comment($entry, 'Reply to the reply', parent: $reply);

    return [$entry, $root, $reply, $nested];
}

/** The IDs a comment query returns for the entry, in ID order. */
function compatibilityIds(Entry $entry, callable $modify): array
{
    $query = Comment::find()->ownerId($entry->id)->status(null)->orderBy(['elements.id' => SORT_ASC]);
    $modify($query);

    return array_map('intval', $query->ids());
}

it('is on by default', function() {
    expect(Comments::$plugin->getSettings()->compatibilityMode)->toBeTrue()
        ->and(Compatibility::enabled())->toBeTrue()
        ->and(Comments::$plugin->getCompatibility()->isCompatibilityModeEnabled())->toBeTrue();
});

it('keeps Comments 3’s comment methods with a deprecation warning, and drops them with the mode off', function() {
    $entry = CommentsFixtureFactory::entry('Compatibility comment methods');
    $comment = CommentsFixtureFactory::comment($entry, 'Legacy methods');
    $settings = Comments::$plugin->getSettings();

    expect($comment->can('vote'))->toBe($comment->canVote())
        ->and($comment->can('reply'))->toBe($comment->canReply())
        ->and($comment->can('allowGuest'))->toBe((bool)$settings->allowGuest)
        ->and($comment->can('anything else'))->toBeFalse()
        ->and($comment->canTrash())->toBeTrue()
        ->and(Comment::STATUS_TRASHED)->toBe(Comment::STATUS_REMOVED)
        ->and(compatibilityDeprecations('comments.can'))->not->toBe([]);

    compatibilityOff();

    // `isPoorlyRated()` and `getAuthorScore()` are Comments 4's own again (SD-57), whatever the mode.
    expect($comment->isPoorlyRated())->toBeFalse()
        ->and($comment->getAuthorScore())->toBe(0)
        ->and(fn() => $comment->can('vote'))->toThrow(UnknownMethodException::class)
        ->and(fn() => $comment->canTrash())->toThrow(UnknownMethodException::class);
});

it('says a comment can only be trashed by its own signed-in author', function() {
    $entry = CommentsFixtureFactory::entry('Compatibility canTrash');
    $other = CommentsFixtureFactory::user('compatibilityTrash');
    $theirs = CommentsFixtureFactory::comment($entry, 'Someone else’s', $other);

    expect($theirs->canTrash())->toBeFalse();
});

it('reads Comments 3’s trashed status as removed', function() {
    $entry = CommentsFixtureFactory::entry('Compatibility trashed status');
    CommentsFixtureFactory::comment($entry, 'Still here');
    $removed = CommentsFixtureFactory::comment($entry, 'Removed', status: Comment::STATUS_REMOVED);

    expect(compatibilityIds($entry, fn($query) => $query->status('trashed')))->toBe([(int)$removed->id])
        ->and(compatibilityDeprecations('comments.status.trashed'))->not->toBe([]);

    compatibilityOff();

    expect(compatibilityIds($entry, fn($query) => $query->status('trashed')))->toBe([]);
});

it('answers Comments 3’s structure parameters from each reply’s parent and depth', function() {
    [$entry, $root, $reply, $nested] = compatibilityThread('Compatibility structure params');
    $ids = static fn(Comment ...$comments): array => array_map(static fn(Comment $comment): int => (int)$comment->id, $comments);

    expect(compatibilityIds($entry, fn($query) => $query->level(1)))->toBe($ids($root))
        ->and(compatibilityIds($entry, fn($query) => $query->level('>= 2')))->toBe($ids($reply, $nested))
        ->and(compatibilityIds($entry, fn($query) => $query->hasDescendants()))->toBe($ids($root, $reply))
        ->and(compatibilityIds($entry, fn($query) => $query->leaves()))->toBe($ids($nested))
        ->and(compatibilityIds($entry, fn($query) => $query->descendantOf($root)))->toBe($ids($reply, $nested))
        ->and(compatibilityIds($entry, fn($query) => $query->descendantOf($root)->descendantDist(1)))->toBe($ids($reply))
        ->and(compatibilityIds($entry, fn($query) => $query->ancestorOf($nested)))->toBe($ids($root, $reply))
        ->and(compatibilityIds($entry, fn($query) => $query->ancestorOf($nested->id)->ancestorDist(1)))->toBe($ids($reply))
        ->and(compatibilityDeprecations('comments.query.structure'))->not->toBe([]);

    // The parameters are put back, so the same query answers the same again.
    $query = Comment::find()->ownerId($entry->id)->status(null)->level(1);

    expect(array_map('intval', $query->ids()))->toBe($ids($root))
        ->and($query->level)->toBe(1)
        ->and(array_map('intval', $query->ids()))->toBe($ids($root));

    compatibilityOff();

    // Craft refuses structure parameters on an element without a structure, so the query returns nothing.
    expect(compatibilityIds($entry, fn($query) => $query->level(1)))->toBe([]);
});

it('gives a comment Comments 3’s level and ancestors', function() {
    [$entry, $root, $reply, $nested] = compatibilityThread('Compatibility level and ancestors');
    $loaded = Comment::find()->id($nested->id)->status(null)->one();

    expect($loaded->level)->toBe(3)
        ->and(array_map(static fn(Comment $comment): int => (int)$comment->id, $loaded->getAncestors()->all()))->toBe([(int)$root->id, (int)$reply->id])
        ->and(array_map(static fn(Comment $comment): int => (int)$comment->id, $loaded->getAncestors(1)->all()))->toBe([(int)$reply->id]);

    compatibilityOff();

    expect(Comment::find()->id($nested->id)->status(null)->one()->level)->toBeNull();
});

it('reads render()’s Comments 3 orderBy as sort, and says what it ignores', function() {
    $options = LegacyRenderOptions::normalize(['orderBy' => 'commentDate asc', 'limit' => 5, 'status' => 'approved']);

    expect($options['sort'])->toBe('oldest')
        ->and(LegacyRenderOptions::normalize(['orderBy' => 'commentDate desc'])['sort'])->toBe('newest')
        ->and(LegacyRenderOptions::normalize(['orderBy' => 'commentDate asc', 'sort' => 'top'])['sort'])->toBe('top')
        ->and(compatibilityDeprecations('comments.render.orderBy'))->not->toBe([])
        ->and(compatibilityDeprecations('comments.render.status'))->not->toBe([])
        ->and(compatibilityDeprecations('comments.render.limit'))->toBe([]);

    compatibilityOff();

    expect(LegacyRenderOptions::normalize(['orderBy' => 'commentDate asc']))->not->toHaveKey('sort');
});

it('protects the page’s own element when protect() is given none, as Comments 3 did', function() {
    $entry = CommentsFixtureFactory::entry('Compatibility protect');
    $variable = new CommentsVariable();
    $urlManager = Craft::$app->getUrlManager();
    $matched = $urlManager->getMatchedElement();

    try {
        // No page element: nothing to protect, and nothing output.
        $urlManager->setMatchedElement(false);

        expect((string)$variable->protect())->toBe('')
            ->and((string)$variable->protect(true))->toBe('');

        $urlManager->setMatchedElement($entry);

        // Each call prepares its own fields, with their own names, so compare whether there are any.
        expect((string)$variable->protect() !== '')->toBe((string)$variable->protect($entry->id, $entry->siteId) !== '')
            ->and(compatibilityDeprecations('comments.protect'))->not->toBe([]);

        compatibilityOff();

        expect(fn() => $variable->protect())->toThrow(InvalidArgumentException::class)
            ->and((string)$variable->protect($entry->id, $entry->siteId))->toBeString();
    } finally {
        $urlManager->setMatchedElement($matched);
    }
});

it('keeps Comments 3’s craft.comments asset methods and Twig functions, outputting nothing', function() {
    $view = Craft::$app->getView();

    expect($view->renderString('{{ craft.comments.renderCss(1) }}{{ craft.comments.renderJs(1) }}{{ craft.comments.getJsVariables(1)|length }}{{ commentsInclude("_includes/footer") }}'))->toBe('0')
        ->and(compatibilityDeprecations('comments.renderCss'))->not->toBe([])
        ->and(compatibilityDeprecations('comments.commentsInclude'))->not->toBe([]);

    compatibilityOff();

    expect(fn() => $view->renderString('{{ craft.comments.renderCss(1) }}'))->toThrow(RuntimeError::class)
        ->and(fn() => $view->renderString('{{ commentsInclude("_includes/footer") }}'))->toThrow(RuntimeError::class);
});

it('reads a Comments 3 field that was switched off as Closed, so existing comments stay and new ones stop', function() {
    $fields = Craft::$app->getFields();
    $field = $fields->getFieldByHandle('commentsCompatibility');

    if (!$field instanceof CommentsField) {
        $field = new CommentsField(['name' => 'Comments', 'handle' => 'commentsCompatibility']);

        expect($fields->saveField($field))->toBeTrue();
    }

    // An upgraded entry keeps Comments 3's stored switch until it's next saved, so the conversation was
    // never closed by a save. Only the value is needed, so the entry stands in with it. Adding the field
    // to a layout here would leave stale GraphQL types in this process, and later GraphQL tests in the
    // run would fail with a duplicate `QueryArgument`. A fresh request builds the schema fine with a
    // Comments field on a layout, so it only affects tests that change layouts mid-run.
    $owner = new class() extends Entry {
        public function getFieldValues(?array $fieldHandles = null): array
        {
            return ['commentsCompatibility' => ['commentEnabled' => false]];
        }
    };

    expect(CommentsField::isClosedValue(['commentEnabled' => false]))->toBeTrue()
        ->and(CommentsField::isClosedValue(['commentEnabled' => true]))->toBeFalse()
        ->and(CommentsField::enabledOverride(['commentEnabled' => false]))->toBeNull()
        ->and(Comments::$plugin->getConversations()->resolveCommentableConfig($owner)->closed)->toBeTrue();

    // The same whatever the mode, because it's saved content.
    compatibilityOff();

    expect(Comments::$plugin->getConversations()->resolveCommentableConfig($owner)->closed)->toBeTrue();
});

/** Runs a GraphQL query on a fresh schema with these scopes, as `ffGql()` does in the form fields tests. */
function compatibilityGql(array $scope, string $query, bool $public = false): array
{
    $schema = new GqlSchema(['name' => 'Compatibility', 'scope' => $scope, 'isPublic' => $public]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);

    try {
        return $gql->executeQuery($schema, $query, debugMode: true);
    } finally {
        $gql->setActiveSchema(null);
        $gql->flushCaches();
    }
}

it('keeps Comments 3’s GraphQL arguments and fields, marked deprecated', function() {
    [$entry, $root, $reply, $nested] = compatibilityThread('Compatibility GraphQL');
    $scope = ['comments:read', 'sections.' . $entry->getSection()->uid . ':read'];
    $query = "{ comments(ownerId: {$entry->id}, level: 2) { id ... on Comment { level root lft rgt flags } } }";
    $introspect = '{ __type(name: "CommentInterface") { fields(includeDeprecated: true) { name isDeprecated } } }';

    $result = compatibilityGql($scope, $query, true);
    $fields = array_column(compatibilityGql($scope, $introspect, true)['data']['__type']['fields'] ?? [], 'isDeprecated', 'name');

    expect($result['errors'] ?? null)->toBeNull()
        ->and($result['data']['comments'] ?? null)->toBe([['id' => (string)$reply->id, 'level' => 2, 'root' => (int)$root->id, 'lft' => null, 'rgt' => null, 'flags' => null]])
        ->and($fields)->toMatchArray(['level' => true, 'root' => true, 'lft' => true, 'rgt' => true, 'flags' => true])
        ->and(LegacyGql::mutationArguments())->toHaveKey('newParentId')
        ->and(LegacyGql::parentId(['newParentId' => '5']))->toBe(5)
        ->and(LegacyGql::parentId(['parentId' => '4', 'newParentId' => '5']))->toBe(4);

    compatibilityOff();

    $off = compatibilityGql($scope, $query, true);
    $offFields = array_column(compatibilityGql($scope, $introspect, true)['data']['__type']['fields'] ?? [], 'name');

    expect($off['errors'] ?? [])->not->toBe([])
        ->and($offFields)->not->toContain('level')
        ->not->toContain('lft')
        ->and(LegacyGql::mutationArguments())->toBe([])
        ->and(LegacyGql::parentId(['newParentId' => '5']))->toBeNull();
});

it('keeps Comments 3’s console commands, which succeed and say they aren’t needed', function() {
    $controller = new LegacyBaseController('base', Comments::$plugin);

    ob_start();

    try {
        $codes = [
            $controller->runAction('publish-assets'),
            $controller->runAction('resave-structure'),
            $controller->runAction('set-structure'),
        ];
    } finally {
        ob_end_clean();
    }

    expect($codes)->toBe([0, 0, 0])
        ->and(compatibilityDeprecations('comments.console.publish-assets'))->not->toBe([]);
});

it('logs the Comments 3 settings a config file still sets', function() {
    $configPath = Craft::$app->getConfig()->getConfigFilePath('comments');

    if (file_exists($configPath)) {
        throw new RuntimeException('The test app already has a config/comments.php.');
    }

    file_put_contents($configPath, '<?php return ' . var_export(['hideVotingForThreshold' => true, 'allowVoting' => false, 'notificationReplyEnabled' => true, 'participantNotifications' => []], true) . ';');

    try {
        (new ReflectionMethod(Comments::$plugin->getSettings(), '_logLegacyConfigKeys'))->invoke(Comments::$plugin->getSettings());
    } finally {
        unlink($configPath);
    }

    // A translated key is logged unless the file sets what replaced it too.
    expect(compatibilityDeprecations('comments.settings.hideVotingForThreshold'))->not->toBe([])
        ->and(compatibilityDeprecations('comments.settings.allowVoting'))->not->toBe([])
        ->and(compatibilityDeprecations('comments.settings.notificationReplyEnabled'))->toBe([]);
});
