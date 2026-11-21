<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Performance\QueryProfiler;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\InteractionType;
use verbb\comments\models\CommentSubmission;

use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;

use verbb\editor\web\assets\editor\EditorAsset;

/**
 * The front-end developer pass: what a site that hooks into, restyles, bundles or goes headless with
 * Comments relies on. The script's own behaviour is checked in a browser; these cover what PHP
 * renders and serves, and the shape of the built files.
 */

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $general = Craft::$app->getConfig()->getGeneral();
    $this->originalGeneral = [
        'enableGql' => $general->enableGql,
        'allowedGraphqlOrigins' => $general->allowedGraphqlOrigins,
        'enableGraphqlCaching' => $general->enableGraphqlCaching,
    ];
    $general->enableGraphqlCaching = false;
});

afterEach(function() {
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getConversations()->clearCommentingCache();

    $general = Craft::$app->getConfig()->getGeneral();

    foreach ($this->originalGeneral as $name => $value) {
        $general->$name = $value;
    }

    Craft::$app->getGql()->flushCaches();
    Craft::$app->getCache()->flush();
    Craft::$app->getRequest()->getHeaders()->remove('Origin');
    Craft::$app->getRequest()->setQueryParams([]);
    Craft::$app->getResponse()->getHeaders()->removeAll();
    Craft::$app->getResponse()->format = yii\web\Response::FORMAT_HTML;
    unset($_SERVER['REQUEST_METHOD']);
});

function dxUser(string $first, string $last): User
{
    $user = CommentsFixtureFactory::user('dx');
    $user->fullName = "{$first} {$last}";
    $user->firstName = $first;
    $user->lastName = $last;
    Craft::$app->getElements()->saveElement($user, false);

    return $user;
}

function dxComment(Entry $entry, User $author, string $content = 'A comment', ?int $parentId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'parentId' => $parentId,
        'content' => $content,
        'userId' => (int)$author->id,
    ]))->comment;
}

function dxGql(array $scope, string $query, bool $public = false): array
{
    $schema = new GqlSchema(['name' => 'Developer pass', 'scope' => $scope, 'isPublic' => $public]);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getGql()->setActiveSchema($schema);

    return Craft::$app->getGql()->executeQuery($schema, $query, debugMode: true);
}

/**
 * The body of the `@layer comments-tokens { … }` block, and the stylesheet without it.
 */
function dxTokenLayer(string $css): array
{
    $start = strpos($css, '@layer comments-tokens');
    expect($start)->not->toBeFalse();

    $depth = 0;
    $open = strpos($css, '{', $start);

    for ($i = $open; $i < strlen($css); $i++) {
        $depth += match ($css[$i]) {
            '{' => 1,
            '}' => -1,
            default => 0,
        };

        if ($depth === 0) {
            return [substr($css, $open + 1, $i - $open - 1), substr($css, 0, $start) . substr($css, $i + 1)];
        }
    }

    throw new RuntimeException('The token layer never closes.');
}

function dxPluginPath(string $path): string
{
    return dirname(__DIR__, 2) . '/' . $path;
}

// GraphQL privacy
// =========================================================================

it('has no session or network fields on GraphQL votes and reports', function() {
    $result = dxGql(['comments:read', 'comments:save'], <<<'GQL'
        {
            vote: __type(name: "VoteInterface") { fields { name } }
            flag: __type(name: "FlagInterface") { fields { name } }
        }
    GQL);

    $fields = array_merge(
        array_column($result['data']['vote']['fields'] ?? [], 'name'),
        array_column($result['data']['flag']['fields'] ?? [], 'name'),
    );

    expect($result['errors'] ?? [])->toBe([])
        ->and($fields)->toContain('upvote')
        ->not->toContain('sessionId')
        ->not->toContain('lastIp');

    $entry = CommentsFixtureFactory::entry('DX vote fields owner');
    $comment = dxComment($entry, dxUser('Vote', 'Fields'));
    $asked = dxGql(['comments:read', 'comments:save'], "mutation { voteComment(id: {$comment->id}, upvote: true) { lastIp sessionId } }");

    expect($asked)->toHaveKey('errors');
});

it('won’t order comments by an address, network, browser or protection record over GraphQL, except for admins', function(string $orderBy) {
    $entry = CommentsFixtureFactory::entry('DX order owner ' . $orderBy);
    dxComment($entry, dxUser('Order', 'Test'));
    Craft::$app->getUser()->setIdentity(null);

    foreach ([true, false] as $public) {
        $refused = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}, orderBy: \"{$orderBy}\") { id } }", $public);

        expect($refused)->toHaveKey('errors')
            ->and($refused['errors'][0]['message'])->toContain('can’t be ordered');
    }

    // The same as the address column and search, which admins keep.
    Craft::$app->getUser()->setIdentity($this->admin);
    $admin = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}, orderBy: \"{$orderBy}\") { id } }");

    expect($admin['errors'] ?? [])->toBe([])
        ->and($admin['data']['comments'])->toHaveCount(1);
})->with(['email', 'ipAddress desc', 'comments_comments.userAgent', 'commentDate desc, protection']);

it('won’t order a comment’s replies by an address over a public schema, however they’re loaded', function() {
    $entry = CommentsFixtureFactory::entry('DX nested order owner');
    $parent = dxComment($entry, dxUser('Nested', 'Parent'));
    dxComment($entry, dxUser('Nested', 'Reply'), 'A reply', (int)$parent->id);
    Craft::$app->getUser()->setIdentity(null);

    // Craft eager-loads `children` for every comment in the list, so its arguments arrive with the results.
    $refused = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}) { children(orderBy: \"email\") { id } } }", true);

    expect($refused)->toHaveKey('errors')
        ->and($refused['errors'][0]['message'])->toContain('can’t be ordered');

    $allowed = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}, level: 1) { children(orderBy: \"commentDate\") { id } } }", true);

    expect($allowed['errors'] ?? [])->toBe([])
        ->and($allowed['data']['comments'][0]['children'])->toHaveCount(1);
});

it('still orders comments by public columns for anyone', function() {
    $entry = CommentsFixtureFactory::entry('DX public order owner');
    $author = dxUser('Public', 'Order');
    $first = dxComment($entry, $author, 'First');
    $second = dxComment($entry, $author, 'Second');
    Craft::$app->getUser()->setIdentity(null);

    $result = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}, orderBy: \"elements.id desc\") { id } }", true);

    expect($result['errors'] ?? [])->toBe([])
        ->and(array_map('intval', array_column($result['data']['comments'], 'id')))->toBe([(int)$second->id, (int)$first->id]);
});

it('only gives the report count to someone who can moderate, on a schema that can moderate', function() {
    $entry = CommentsFixtureFactory::entry('DX flags owner');
    $comment = dxComment($entry, dxUser('Flora', 'Flagged'), 'Reported');
    $reporter = Comments::$plugin->getIdentities()->getOrCreateForUser(dxUser('Rex', 'Reporter'));
    Comments::$plugin->getReports()->toggle((int)$comment->id, (int)$reporter->id, null, null);
    $moderator = dxUser('Mo', 'Derator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['accessCp', 'comments-view', 'comments-moderate']);
    $moderator = User::find()->id($moderator->id)->status(null)->one();
    $query = "{ comment(id: {$comment->id}) { flags } }";

    // Compatibility mode keeps a null `flags` on every schema, which `V4CompatibilityTest` covers.
    Comments::$plugin->getSettings()->compatibilityMode = false;

    // Without the moderation permission the field isn't in the schema at all, like `user` without users.
    Craft::$app->getUser()->setIdentity(null);
    expect(dxGql(['comments:read'], $query, true))->toHaveKey('errors')
        ->and(dxGql(['comments:read', 'comments:save'], $query))->toHaveKey('errors');

    // A public schema needs no token, so a moderator's session reads nothing through one.
    Craft::$app->getUser()->setIdentity($moderator);
    expect(dxGql(['comments:read', 'comments:moderate'], $query, true)['data']['comment']['flags'])->toBeNull();

    // A schema that can moderate still needs someone who can.
    Craft::$app->getUser()->setIdentity(dxUser('Nat', 'Notamod'));
    expect(dxGql(['comments:read', 'comments:moderate'], $query)['data']['comment']['flags'])->toBeNull();

    Craft::$app->getUser()->setIdentity($moderator);
    $result = dxGql(['comments:read', 'comments:moderate'], $query);

    expect($result['errors'] ?? [])->toBe([])
        ->and($result['data']['comment']['flags'])->toBe(1);
});

it('won’t filter or sort by commenter identity on a public schema', function() {
    $entry = CommentsFixtureFactory::entry('DX identity owner');
    $comment = dxComment($entry, dxUser('Ida', 'Identity'));
    $identityId = (int)Comment::find()->id($comment->id)->one()->identityId;
    Craft::$app->getUser()->setIdentity(null);

    foreach (["identityId: {$identityId}", 'orderBy: "identityId"', 'orderBy: "comments_comments.identityId desc"'] as $argument) {
        $refused = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}, {$argument}) { id } }", true);
        $nested = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}) { children({$argument}) { id } } }", true);

        expect($refused)->toHaveKey('errors')
            ->and($refused['errors'][0]['message'])->toContain('can’t be')
            ->and($nested)->toHaveKey('errors');
    }

    // Private schemas keep it.
    $private = dxGql(['comments:read'], "{ comments(identityId: {$identityId}, orderBy: \"identityId\") { id } }");

    expect($private['errors'] ?? [])->toBe([])
        ->and(array_map('intval', array_column($private['data']['comments'], 'id')))->toContain((int)$comment->id);
});

// GraphQL for headless front ends
// =========================================================================

it('names a member’s comment on a public schema without opening up users', function() {
    $entry = CommentsFixtureFactory::entry('DX author owner');
    $settings = Comments::$plugin->getSettings();
    $settings->ownerBadgeLabel = 'Author';
    $member = dxUser('Alex', 'Morgan');
    $entry->setAuthorIds([(int)$member->id]);
    Craft::$app->getElements()->saveElement($entry, false);
    $comment = dxComment($entry, $member, 'From a member');
    $guest = CommentsFixtureFactory::guestComment($entry, 'From a guest', 'Sam Guest', 'sam@example.test');
    Craft::$app->getUser()->setIdentity(null);

    $result = dxGql(['comments:read'], <<<GQL
        { comments(ownerId: {$entry->id}, orderBy: "elements.id") { id name author { name initials isGuest avatarUrl badges { type label } } } }
    GQL, true);

    expect($result['errors'] ?? [])->toBe([]);

    [$fromMember, $fromGuest] = $result['data']['comments'];

    expect((int)$fromMember['id'])->toBe((int)$comment->id)
        ->and($fromMember['name'])->toBeNull()
        ->and($fromMember['author'])->toMatchArray(['name' => 'Alex Morgan', 'initials' => 'AM', 'isGuest' => false])
        ->and($fromMember['author']['badges'])->toBe([['type' => 'owner', 'label' => 'Author']])
        ->and((int)$fromGuest['id'])->toBe((int)$guest->id)
        ->and($fromGuest['author'])->toMatchArray(['name' => 'Sam Guest', 'isGuest' => true, 'badges' => []])
        ->and(json_encode($result))->not->toContain('sam@example.test')
        ->not->toContain((string)$member->email);

    // The account itself still needs a schema that can read users.
    $user = dxGql(['comments:read'], "{ comments(ownerId: {$entry->id}) { user { email } } }", true);

    expect($user)->toHaveKey('errors');
});

it('tells a GraphQL client what the person asking can do with each comment, and how they voted', function() {
    $entry = CommentsFixtureFactory::entry('DX viewer owner');
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = InteractionMode::UpDown->value;
    $settings->allowFlagging = true;
    $settings->allowGuestFlagging = false;
    $settings->allowGuestVoting = false;
    $author = dxUser('Ada', 'Author');
    $reader = dxUser('Rae', 'Reader');
    $comment = dxComment($entry, $author, 'Viewer target');
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($reader);
    Comments::$plugin->getInteractions()->toggle((int)$comment->id, (int)$identity->id, InteractionType::Downvote);

    $query = "{ comment(id: {$comment->id}) { viewer { canReply canEdit canDelete canVote canDownvote canReport vote } } }";

    Craft::$app->getUser()->setIdentity($author);
    $asAuthor = dxGql(['comments:read'], $query, true);

    Craft::$app->getUser()->setIdentity($reader);
    $asReader = dxGql(['comments:read'], $query, true);

    Craft::$app->getUser()->setIdentity(null);
    Comments::$plugin->getConversations()->clearCommentingCache();
    $asGuest = dxGql(['comments:read'], $query, true);

    expect($asAuthor['errors'] ?? [])->toBe([])
        ->and($asAuthor['data']['comment']['viewer'])->toBe([
            'canReply' => true,
            'canEdit' => true,
            'canDelete' => true,
            'canVote' => true,
            'canDownvote' => true,
            'canReport' => true,
            'vote' => null,
        ])
        ->and($asReader['data']['comment']['viewer'])->toMatchArray([
            'canEdit' => false,
            'canDelete' => false,
            'canVote' => true,
            'vote' => 'downvote',
        ])
        ->and($asGuest['data']['comment']['viewer'])->toMatchArray([
            'canEdit' => false,
            'canDelete' => false,
            'canVote' => false,
            'canReport' => false,
            'vote' => null,
        ]);
});

it('works out every comment’s author and viewer together, not one comment at a time', function() {
    $entry = CommentsFixtureFactory::entry('DX batch owner');
    $settings = Comments::$plugin->getSettings();
    $settings->staffBadgeLabel = 'Staff';
    $settings->staffBadgeGroups = ['not-a-real-group'];
    $author = dxUser('Bea', 'Batch');

    foreach (range(1, 6) as $i) {
        $root = dxComment($entry, $author, "Root {$i}");
        dxComment($entry, $author, "Reply {$i}", (int)$root->id);
    }

    Craft::$app->getUser()->setIdentity($author);
    $plain = "{ comments(ownerId: {$entry->id}, parentId: \":empty:\") { id children { id } } }";
    $query = "{ comments(ownerId: {$entry->id}, parentId: \":empty:\") { author { name } viewer { canEdit vote } children { author { name } viewer { canEdit } } } }";
    dxGql(['comments:read'], $query);

    $without = QueryProfiler::profile(fn() => dxGql(['comments:read'], $plain));
    $with = QueryProfiler::profile(fn() => dxGql(['comments:read'], $query));
    $result = dxGql(['comments:read'], $query);

    expect($result['errors'] ?? [])->toBe([])
        ->and($result['data']['comments'])->toHaveCount(6)
        ->and(array_unique(array_column(array_column($result['data']['comments'], 'author'), 'name')))->toBe(['Bea Batch'])
        ->and($result['data']['comments'][0]['children'][0]['viewer']['canEdit'])->toBeTrue()
        // Twelve comments in two levels cost a few lookups per level, not one or more per comment.
        ->and($with['queries'] - $without['queries'])->toBeLessThan(12);
});

it('describes a conversation’s rules and what the person asking can do there', function() {
    $entry = CommentsFixtureFactory::entry('DX conversation owner');
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = InteractionMode::Like->value;
    $settings->securityMaxLength = '500';
    $settings->allowGuest = false;
    $settings->guestRequireEmailName = false;
    $settings->guestShowEmailName = true;
    $settings->allowFlagging = true;
    $settings->allowGuestFlagging = true;
    $settings->allowGuestVoting = false;
    $settings->reportReasons = [['value' => 'spam', 'label' => 'Spam', 'description' => 'Advertising or scams']];
    Comments::$plugin->getConversations()->clearCaches();

    $query = <<<GQL
        { commentConversation(ownerId: {$entry->id}, siteId: {$entry->siteId}) {
            interactionMode maxLength
            guestPolicy { allowed nameAndEmail canVote canReport }
            reportReasons { value label description }
            viewer { isGuest canComment canVote canReport }
        } }
    GQL;

    Craft::$app->getUser()->setIdentity(dxUser('Mia', 'Member'));
    $asMember = dxGql(['comments:read'], $query, true);

    Craft::$app->getUser()->setIdentity(null);
    Comments::$plugin->getConversations()->clearCommentingCache();
    $asGuest = dxGql(['comments:read'], $query, true);

    expect($asMember['errors'] ?? [])->toBe([])
        ->and($asMember['data']['commentConversation'])->toMatchArray([
            'interactionMode' => 'like',
            'maxLength' => 500,
            'guestPolicy' => ['allowed' => false, 'nameAndEmail' => 'optional', 'canVote' => false, 'canReport' => true],
            'reportReasons' => [['value' => 'spam', 'label' => 'Spam', 'description' => 'Advertising or scams']],
            'viewer' => ['isGuest' => false, 'canComment' => true, 'canVote' => true, 'canReport' => true],
        ])
        ->and($asGuest['data']['commentConversation']['viewer'])->toBe(['isGuest' => true, 'canComment' => false, 'canVote' => false, 'canReport' => true]);

    $settings->allowFlagging = false;
    $off = dxGql(['comments:read'], "{ commentConversation(ownerId: {$entry->id}) { reportReasons { value } } }", true);

    expect($off['data']['commentConversation']['reportReasons'])->toBe([]);
});

it('never serves one person’s viewer answers to another from the GraphQL cache', function() {
    $entry = CommentsFixtureFactory::entry('DX cache owner');
    $author = dxUser('Cal', 'Cache');
    $comment = dxComment($entry, $author, 'Cached?');
    $general = Craft::$app->getConfig()->getGeneral();
    $general->enableGraphqlCaching = true;
    $schema = new GqlSchema(['name' => 'Cached', 'scope' => ['comments:read'], 'isPublic' => true, 'uid' => StringHelper::UUID()]);
    $query = "{ comment(id: {$comment->id}) { viewer { canEdit } } }";
    $gql = Craft::$app->getGql();

    // Results are cached here: a change made behind Craft's back doesn't show.
    $gql->setActiveSchema($schema);
    $text = "{ comment(id: {$comment->id}) { comment } }";
    $gql->executeQuery($schema, $text);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['comment' => 'Changed quietly'], ['id' => $comment->id])->execute();
    expect($gql->executeQuery($schema, $text)['data']['comment']['comment'])->toBe('Cached?');

    Craft::$app->getUser()->setIdentity($author);
    $first = $gql->executeQuery($schema, $query);

    Craft::$app->getUser()->setIdentity(null);
    $second = $gql->executeQuery($schema, $query);

    expect($first['data']['comment']['viewer']['canEdit'])->toBeTrue()
        ->and($second['data']['comment']['viewer']['canEdit'])->toBeFalse()
        // Caching carries on for everything else.
        ->and($general->enableGraphqlCaching)->toBeTrue();
});

// The bundled script and assets
// =========================================================================

it('says where Editor is even when the site loads its own JavaScript', function() {
    $entry = CommentsFixtureFactory::entry('DX editor module owner');
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->outputDefaultJs = false;

    $view = Craft::$app->getView();
    $view->assetBundles = [];
    Comments::$plugin->getComments()->render((int)$entry->id);
    $editor = Comments::$plugin->getApi()->thread($entry)['editor'];

    // The thread's data names Editor's module, and nothing is registered with the page, so Editor still
    // only loads once someone starts writing.
    expect($editor['richEditor']['module'] ?? null)->toContain('editor.js')
        ->and($editor['richEditor']['css'] ?? null)->toContain('editor.css')
        ->and(array_keys($view->assetBundles))->not->toContain(EditorAsset::class)
        ->not->toContain(verbb\comments\web\assets\frontend\CommentsScriptAsset::class);

    $settings->authoringMode = AuthoringMode::Plain->value;
    expect(Comments::$plugin->getApi()->thread($entry)['editor']['richEditor'])->toBeNull();
});

it('keeps the theme’s custom properties in a layer a site’s stylesheet overrides', function(string $path) {
    [$layer, $rest] = dxTokenLayer(file_get_contents(dxPluginPath($path)));

    preg_match_all('/(--comments-[a-z0-9-]+)\s*:/', $rest, $outside);

    preg_match_all('/(--_comments-[a-z0-9-]+)\s*:/', $rest, $internal);

    // Only the two internal properties are set outside the layer, on elements inside the thread.
    expect($outside[1])->toBe([])
        ->and(array_values(array_unique($internal[1])))->toEqualCanonicalizing(['--_comments-tint-border', '--_comments-toast-icon'])
        ->and($layer)->toMatch("/data-comments-theme=['\"]?dark/")
        ->toMatch('/prefers-color-scheme:\s*dark/')
        ->toContain('--comments-color-panel')
        ->and($rest)->not->toContain('#17191c')
        ->not->toContain('#a85d00')
        ->toContain('var(--comments-color-panel)')
        ->toContain('var(--comments-color-warning)');
})->with(['packages/comments-browser/src/css/comments-theme.css', 'src/web/assets/frontend/dist/css/comments.css']);

it('documents every supported custom property', function() {
    [$layer] = dxTokenLayer(file_get_contents(dxPluginPath('packages/comments-browser/src/css/comments-theme.css')));
    preg_match_all('/(--comments-[a-z0-9-]+)\s*:/', $layer, $tokens);
    $docs = file_get_contents(dxPluginPath('docs/template-guides/theming.md'));

    foreach (array_unique($tokens[1]) as $token) {
        expect($docs)->toContain("`{$token}`");
    }

    expect($docs)->toContain('`--_comments-tint-border`')
        ->toContain('`--_comments-toast-icon`');
});

it('ships the startup script with the guards a page relies on, and the package’s types for bundling sites', function() {
    $script = file_get_contents(dxPluginPath('src/web/assets/frontend/dist/js/comments.js'));
    $chunks = implode("\n", array_map('file_get_contents', glob(dxPluginPath('src/web/assets/frontend/dist/js/chunks/*.js'))));
    $types = file_get_contents(dxPluginPath('packages/comments-browser/dist/index.d.ts')) . file_get_contents(dxPluginPath('packages/comments-browser/dist/types.d.ts')) . file_get_contents(dxPluginPath('packages/comments-browser/dist/events.d.ts'));

    expect($script)->toMatch('/Symbol\.for\(["`\']verbb\.comments["`\']\)/')
        ->toContain('data-comments-api-version')
        ->toContain('data-comments-auto-mount')
        ->not->toContain('verbb.comments.runtime')
        ->not->toContain('data-comments-contract')
        ->and($chunks)->toContain('comments:before-submit')
        ->and($types)->toContain('comments:before-submit')
        ->toContain('showFormErrors')
        ->not->toContain('contractVersion');
});
