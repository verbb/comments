<?php
declare(strict_types=1);

use craft\db\Query;
use craft\elements\User;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\Flag;
use verbb\comments\models\Vote;
use verbb\comments\services\Flags;
use verbb\comments\services\RenderCache;
use verbb\comments\services\Votes;
use yii\log\Logger;

// Run against a disposable, installed Craft site; see tests/README.md.
$appPath = getenv('CRAFT_TEST_APP');
if (!$appPath || getenv('COMMENTS_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('Set CRAFT_TEST_APP and COMMENTS_TEST_DISPOSABLE=1 for a disposable Craft site.');
}
require $appPath . '/bootstrap.php';
if ($source = getenv('COMMENTS_TEST_SOURCE')) {
    $loader = require CRAFT_VENDOR_PATH . '/autoload.php';
    $loader->setPsr4('verbb\\comments\\', $source);
}
$_SERVER['SCRIPT_FILENAME'] = $appPath . '/web/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
set_exception_handler(function(Throwable $error): void {
    fwrite(STDERR, (string)$error . "\n");
    exit(1);
});
$app->getPlugins()->loadPlugins();
if ($source) {
    Craft::setAlias('@verbb/comments', $source);
}
$plugin = $app->getPlugins()->getPlugin('comments');
check($plugin instanceof Comments, 'Comments must be installed.');
$db = $app->getDb();
$db->enableLogging = true;
$db->enableProfiling = true;
$db->enableQueryCache = false;
Yii::getLogger()->flushInterval = 0;
$app->getLog()->targets = [];
$app->setEdition(Craft::Pro);
$baseline = getenv('COMMENTS_TEST_BASELINE') === '1';
$settings = $plugin->getSettings();
foreach (get_object_vars($settings) as $key => $value) {
    if (str_starts_with($key, 'notification') && is_bool($value)) {
        $settings->$key = false;
    }
}
$settings->allowGuest = true;
$settings->allowGuestVoting = true;
$settings->allowGuestFlagging = true;
$settings->showAuthorScore = true;
$settings->showAvatar = false;
$app->getSession()->set('comments_vote', str_repeat('a', 32));
$app->getSession()->set('comments_flag', str_repeat('b', 32));
$app->getRequest()->getCsrfToken();
$siteId = $app->getSites()->getPrimarySite()->id;
$elements = $app->getElements();

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same(mixed $expected, mixed $actual, string $message): void
{
    check($expected === $actual, $message . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}

function resetCaches(): void
{
    Comments::$plugin->set('renderCache', new RenderCache());
    Comments::$plugin->set('votes', new Votes());
    Comments::$plugin->set('flags', new Flags());
}

function measure(callable $callback): array
{
    $logger = Yii::getLogger();
    $logger->messages = [];
    $start = hrtime(true);
    $result = $callback();
    $ms = (hrtime(true) - $start) / 1e6;
    $sql = [];
    foreach ($logger->messages as $message) {
        if ($message[1] === Logger::LEVEL_PROFILE_BEGIN && $message[2] === 'yii\\db\\Command::query') {
            $sql[] = $message[0];
        }
    }

    return ['result' => $result, 'queries' => count($sql), 'ms' => round($ms, 3), 'sql' => $sql];
}

function reactions(Comment $comment, ?User $viewer): array
{
    $votes = Comments::$plugin->getVotes();
    $flags = Comments::$plugin->getFlags();

    return [
        'total' => $comment->getAllVotes(),
        'up' => $comment->getUpvotes(),
        'down' => $comment->getDownvotes(),
        'net' => $comment->getVotes(),
        'score' => $comment->getAuthorScore(),
        'flags' => $comment->getFlags(),
        'flagged' => $comment->isFlagged(),
        'poorlyRated' => $comment->isPoorlyRated(),
        'viewerUp' => $votes->hasUpVoted($comment, $viewer ?? new User()),
        'viewerDown' => $votes->hasDownVoted($comment, $viewer ?? new User()),
        'viewerFlag' => $flags->hasFlagged($comment, $viewer),
    ];
}

function newComment(int $ownerId, ?int $authorId, string $status, ?int $parentId = null): Comment
{
    $comment = new Comment([
        'ownerId' => $ownerId,
        'ownerSiteId' => Craft::$app->getSites()->getPrimarySite()->id,
        'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        'userId' => $authorId,
        'name' => 'Guest',
        'email' => 'guest@example.test',
        'comment' => 'Test <script>alert(1)</script> comment',
        'status' => $status,
        'newParentId' => $parentId ?? 0,
    ]);
    check(Craft::$app->getElements()->saveElement($comment, false), 'Save fixture comment.');

    return $comment;
}

// Fixtures are reused by fresh baseline/changed PHP processes.
$fixturePath = $appPath . '/comments-fixtures.json';
if (!is_file($fixturePath)) {
    $users = [];
    for ($i = 0; $i < 12; $i++) {
        $user = new User(['username' => 'comments-test-' . $i, 'email' => "comments-test-$i@example.test", 'firstName' => 'Author', 'lastName' => (string)$i]);
        $saved = $elements->saveElement($user, false);
        check($saved, 'Save fixture author: ' . json_encode($user->getErrors()));
        $users[] = $user->id;
    }
    $ownerId = User::find()->username('admin')->one()->id;
    $comments = [];
    for ($i = 0; $i < 23; $i++) {
        $parentId = $i < 9 ? null : $comments[$i - 9]->id;
        $comments[] = newComment($ownerId, $i === 22 ? null : $users[$i % 10], Comment::STATUS_APPROVED, $parentId);
    }
    $histories = [];
    foreach (range(0, 99) as $i) {
        $status = [Comment::STATUS_APPROVED, Comment::STATUS_PENDING, Comment::STATUS_SPAM, Comment::STATUS_TRASHED][$i % 4];
        $histories[] = newComment($users[11], $users[$i % 10], $status);
    }
    $now = gmdate('Y-m-d H:i:s');
    $voteRows = [];
    $flagRows = [];
    foreach (array_merge($comments, $histories) as $i => $comment) {
        $count = $i < 23 ? ($i === 21 ? 0 : 5) : 100;
        for ($j = 0; $j < $count; $j++) {
            $voteRows[] = [$comment->id, $j === 0 ? $users[10] : null, $j === 1 ? str_repeat('a', 32) : md5("$i:$j"), (int)($j % 4 < 2), (int)($j % 4 === 2), $now, $now, craft\helpers\StringHelper::UUID()];
        }
        if ($i < 23 && $i !== 21) {
            foreach (range(0, $i === 0 ? 4 : 0) as $j) {
                $flagRows[] = [$comment->id, $j === 0 ? $users[10] : null, $j === 1 ? str_repeat('b', 32) : md5("flag:$i:$j"), $now, $now, craft\helpers\StringHelper::UUID()];
            }
        }
    }
    foreach (array_chunk($voteRows, 500) as $rows) {
        $db->createCommand()->batchInsert('{{%comments_votes}}', ['commentId', 'userId', 'sessionId', 'upvote', 'downvote', 'dateCreated', 'dateUpdated', 'uid'], $rows)->execute();
    }
    $db->createCommand()->batchInsert('{{%comments_flags}}', ['commentId', 'userId', 'sessionId', 'dateCreated', 'dateUpdated', 'uid'], $flagRows)->execute();
    file_put_contents($fixturePath, json_encode(['users' => $users, 'ownerId' => $ownerId, 'comments' => array_column($comments, 'id'), 'histories' => array_column($histories, 'id')]));
}
$fixture = json_decode(file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
// Keep all fixture users ordinary members, including fixtures created under Solo.
$db->createCommand()->update('{{%users}}', ['admin' => false], ['id' => $fixture['users']])->execute();
$viewer = User::find()->id($fixture['users'][10])->one();
$otherViewer = User::find()->id($fixture['users'][11])->one();
$report = ['craft' => Craft::$app->getVersion(), 'php' => PHP_VERSION, 'baseline' => $baseline, 'comments' => (int)(new Query())->from('{{%comments_comments}}')->count(), 'votes' => (int)(new Query())->from('{{%comments_votes}}')->count(), 'measurements' => []];

// Measure service reads with all displayed comments registered by the element query.
foreach ([null, $viewer] as $identity) {
    $app->getUser()->setIdentity($identity);
    resetCaches();
    $comments = Comment::find()->id($fixture['comments'])->orderBy('id')->all();
    $run = measure(fn() => array_map(fn($comment) => reactions($comment, $identity), $comments));
    if (!$baseline) {
        same(5, $run['queries'], 'One vote aggregate, one author aggregate, one flag aggregate and two viewer batches.');
        same(0, measure(fn() => array_map(fn($comment) => reactions($comment, $identity), $comments))['queries'], 'Request cache includes empty results.');
    }
    $report['measurements'][$identity ? 'signedServices' : 'guestServices'] = $run;
}

// Compare actual bundled Twig rendering including eager-loaded replies.
foreach ([null, $viewer, $otherViewer] as $identity) {
    $app->getUser()->setIdentity($identity);
    resetCaches();
    $run = measure(fn() => (string)$plugin->getComments()->render($fixture['ownerId']));
    same(23, substr_count($run['result'], 'itemprop="comment"'), 'All roots and nested replies rendered.');
    check(!str_contains($run['result'], '<script>alert(1)</script>'), 'Comment text remains escaped.');
    if (!$baseline) {
        $reactionSql = array_filter($run['sql'], fn($sql) => (bool)preg_match('/`comments_(votes|flags)`/', $sql));
        same(4, count($reactionSql), 'Bundled Twig batches reaction and author-score reads across eager-loaded replies.');
        $ownerSql = array_filter($run['sql'], fn($sql) => (bool)preg_match('/^SELECT `id`\s+FROM `comments_comments`\s+WHERE.*ownerId/s', $sql));
        same(0, count($ownerSql), 'Rendering does not perform owner-wide ID lookups.');
    }
    $report['measurements'][$identity === null ? 'guestHtml' : ($identity === $viewer ? 'signedHtml' : 'otherViewerHtml')] = $run;
}

if (!$baseline) {
    foreach ([1, 9, 23] as $size) {
        resetCaches();
        $subset = Comment::find()->id(array_slice($fixture['comments'], 0, $size))->all();
        same(5, measure(fn() => array_map(fn($comment) => reactions($comment, $viewer), $subset))['queries'], 'Batch query count is constant for ' . $size . ' displayed comments.');
    }
    $guest = $report['measurements']['guestServices']['result'];
    $signed = $report['measurements']['signedServices']['result'];
    same(5, $signed[0]['total'], 'Total counts neutral vote rows.');
    same(3, $signed[0]['up'], 'Upvotes count rows, not net votes.');
    same(1, $signed[0]['down'], 'Downvotes.');
    same(2, $signed[0]['net'], 'Net votes.');
    same(true, $signed[0]['flagged'], 'Flag threshold remains inclusive.');
    same(true, $signed[0]['viewerFlag'], 'Signed-in flag state.');
    same(true, $guest[0]['viewerFlag'], 'Guest flag session matches.');
    same(true, $guest[0]['viewerUp'], 'Guest vote session matches.');
    same(0, $signed[21]['total'], 'Empty vote cache.');
    same(0, $signed[21]['flags'], 'Empty flag cache.');
    same(0, $signed[22]['score'], 'Guest authors have zero reputation.');

    // History includes other owners and every moderation status. Independently calculate its scope.
    foreach (array_slice($fixture['users'], 0, 10) as $authorId) {
        $ids = (new Query())->select('id')->from('{{%comments_comments}}')->where(['userId' => $authorId, 'status' => Comment::STATUS_APPROVED]);
        $up = (int)(new Query())->from('{{%comments_votes}}')->where(['commentId' => $ids, 'upvote' => 1])->count();
        $down = (int)(new Query())->from('{{%comments_votes}}')->where(['commentId' => $ids, 'downvote' => 1])->count();
        same($up - $down, $plugin->getVotes()->getScoreByAuthorId($authorId), 'Historical score semantics.');
        same($up - $down, $plugin->getVotes()->getScoreByAuthorId('0' . $authorId), 'Numeric-string author IDs retain their semantics.');
    }
    $settings->downvoteCommentLimit = 1;
    same(true, $subset[0]->isPoorlyRated(), 'Downvote threshold remains inclusive.');
    $settings->downvoteCommentLimit = 5;
    $app->getUser()->setIdentity(null);
    same(false, $subset[0]->canEdit(), 'Guests cannot edit registered comments.');
    $settings->allowGuestVoting = false;
    same(false, $subset[0]->canVote(), 'Guest voting setting is respected.');
    $settings->allowGuestVoting = true;
    $settings->allowGuestFlagging = false;
    same(false, $subset[0]->canFlag(), 'Guest flagging setting is respected.');
    $settings->allowGuestFlagging = true;
    $app->getUser()->setIdentity($viewer);
    same(false, $subset[0]->canEdit(), 'Other authors cannot edit this comment.');

    // Viewer changes and guest-session changes cannot reuse personalized state.
    $comment = Comment::find()->id($fixture['comments'][0])->one();
    $votes = $plugin->getVotes();
    $flags = $plugin->getFlags();
    same(false, $votes->hasUpVoted($comment, $otherViewer), 'Other viewer vote state.');
    same(true, $votes->hasUpVoted($comment, $viewer), 'Switch back to original viewer.');
    same(false, $flags->hasFlagged($comment, $otherViewer), 'Other viewer flag state.');
    $app->getSession()->set('comments_vote', str_repeat('c', 32));
    $app->getSession()->set('comments_flag', str_repeat('d', 32));
    same(false, $votes->hasUpVoted($comment, null), 'Different guest vote session.');
    same(false, $flags->hasFlagged($comment, null), 'Different guest flag session.');
    $app->getSession()->set('comments_vote', str_repeat('a', 32));
    $app->getSession()->set('comments_flag', str_repeat('b', 32));

    // A limited query must register only its own rows, even for a large owner.
    resetCaches();
    $limited = measure(fn() => Comment::find()->ownerId($fixture['ownerId'])->level(1)->limit(2)->all());
    same(2, count($plugin->getRenderCache()->getCommentIds()), 'Pagination stays bounded.');
    same(1, $limited['queries'], 'No owner-wide ID query during populate.');
    Comment::find()->id($fixture['histories'][0])->one();
    same(3, count($plugin->getRenderCache()->getCommentIds()), 'Multiple owners accumulate loaded IDs.');
    $empty = measure(fn() => Comment::find()->ownerId($fixture['users'][10])->all());
    same([], $empty['result'], 'Empty thread.');
    same(3, count($plugin->getRenderCache()->getCommentIds()), 'Empty query preserves registered collections.');
    $emptyHtml = (string)$plugin->getComments()->render($fixture['users'][10]);
    same(0, substr_count($emptyHtml, 'itemprop="comment"'), 'Empty thread HTML.');
    resetCaches();
    $arrays = Comment::find()->id(array_slice($fixture['comments'], 0, 2))->asArray()->all();
    same(2, count($arrays), 'Array query results.');
    same(2, count($plugin->getRenderCache()->getCommentIds()), 'Array query results register IDs.');

    // Collections loaded later batch their missing IDs without re-fetching cached IDs.
    resetCaches();
    $roots = Comment::find()->id(array_slice($fixture['comments'], 0, 9))->all();
    same(1, measure(fn() => array_map(fn($comment) => $comment->getVotes(), $roots))['queries'], 'First collection vote batch.');
    $replies = Comment::find()->id(array_slice($fixture['comments'], 9))->all();
    same(1, measure(fn() => array_map(fn($comment) => $comment->getVotes(), $replies))['queries'], 'Later reply collection vote batch.');
    same(0, measure(fn() => array_map(fn($comment) => $comment->getVotes(), $roots))['queries'], 'Earlier collection stays cached.');

    foreach (['voteCount desc', 'flagCount desc'] as $order) {
        $sorted = Comment::find()->id($fixture['comments'])->orderBy($order)->all();
        $values = array_map(fn($comment) => str_starts_with($order, 'vote') ? $comment->getVotes() : $comment->getFlags(), $sorted);
        $expected = $values;
        rsort($expected);
        same($expected, $values, 'Sorting by ' . $order . ' remains unchanged.');
    }

    resetCaches();
    $standaloneRoot = Comment::find()->id($fixture['comments'][0])->one();
    $html = (string)$plugin->getComments()->renderComment($standaloneRoot);
    same(3, substr_count($html, 'itemprop="comment"'), 'Standalone existing comment includes nested replies.');

    // Keep mutations repeatable and leave the shared fixture unchanged.
    $transaction = $db->beginTransaction();
    try {
        $app->getUser()->setIdentity($viewer);
        resetCaches();
        $comment = Comment::find()->id($fixture['comments'][0])->one();
        $votes = $plugin->getVotes();
        $flags = $plugin->getFlags();
        $before = reactions($comment, $viewer);
        check(!$votes->saveVote(new Vote()), 'Invalid vote rejected by existing validation.');
        check(!$flags->saveFlag(new Flag()), 'Invalid flag rejected by existing validation.');
        same($before, reactions($comment, $viewer), 'Failed validation leaves reaction state unchanged.');
        $vote = $votes->getVoteByUser($comment->id, $viewer->id);
        check($vote instanceof Vote, 'getVoteByUser still returns a model.');
        $vote->upvote = 0;
        $vote->downvote = 1;
        check($votes->saveVote($vote, false), 'Change vote direction.');
        same($before['up'] - 1, $comment->getUpvotes(), 'Vote counts invalidated.');
        same($before['score'] - 2, $comment->getAuthorScore(), 'Author score invalidated.');
        same(true, $votes->hasDownVoted($comment, $viewer), 'Viewer vote invalidated.');
        check($votes->deleteVote($vote), 'Delete vote.');
        same(null, $votes->getVoteByUser($comment->id, $viewer->id), 'Deleted vote cached as absent.');
        $vote = new Vote(['commentId' => $comment->id, 'userId' => $viewer->id, 'upvote' => 1, 'downvote' => 0]);
        check($votes->saveVote($vote, false), 'Insert vote.');
        same($before['score'], $comment->getAuthorScore(), 'Inserted vote refreshes score.');
        $second = Comment::find()->id($fixture['comments'][1])->one();
        $secondCount = $second->getAllVotes();
        $vote->commentId = $second->id;
        check($votes->saveVote($vote, false), 'Move vote to another comment.');
        same($before['total'] - 1, $comment->getAllVotes(), 'Old comment cache invalidated after moving vote.');
        same($secondCount + 1, $second->getAllVotes(), 'New comment cache invalidated after moving vote.');
        $vote->commentId = $comment->id;
        check($votes->saveVote($vote, false), 'Restore moved vote.');
        $flag = $flags->getFlagByUser($comment->id, $viewer->id);
        check($flag instanceof Flag, 'getFlagByUser still returns a model.');
        check($flags->toggleFlag($flag), 'Remove flag.');
        same(false, $flags->hasFlagged($comment, $viewer), 'Removed viewer flag.');
        same(false, $comment->isFlagged(), 'Flag threshold refreshes after deletion.');
        $flag = new Flag(['commentId' => $comment->id, 'userId' => $viewer->id]);
        check($flags->toggleFlag($flag), 'Insert flag.');
        same(true, $comment->isFlagged(), 'Flag threshold refreshes after insertion.');
        $app->getUser()->setIdentity(null);
        $guestVote = $votes->getVoteByUser($comment->id, null);
        check($guestVote instanceof Vote, 'Guest mutation retrieves existing session vote.');
        $guestVote->upvote = 0;
        $guestVote->downvote = 1;
        check($votes->saveVote($guestVote, false), 'Change guest vote.');
        same(true, $votes->hasDownVoted($comment, null), 'Guest vote mutation refreshes session cache.');
        $guestVote->upvote = 1;
        $guestVote->downvote = 0;
        check($votes->saveVote($guestVote, false), 'Restore guest vote.');
        same(null, $flags->getFlagByUser($second->id, null), 'Guest flag initially absent on second comment.');
        $guestFlag = new Flag(['commentId' => $second->id]);
        check($flags->saveFlag($guestFlag, false), 'Insert guest flag.');
        same(true, $flags->hasFlagged($second, null), 'Guest flag insertion refreshes session cache.');
        $guestFlag = $flags->getFlagByUser($second->id, null);
        check($guestFlag instanceof Flag, 'Guest mutation retrieves existing session flag.');
        check($flags->toggleFlag($guestFlag), 'Remove guest flag.');
        same(false, $flags->hasFlagged($second, null), 'Guest flag removal refreshes session cache.');
        $app->getUser()->setIdentity($viewer);

        foreach ([Comment::STATUS_PENDING, Comment::STATUS_SPAM, Comment::STATUS_TRASHED, Comment::STATUS_APPROVED] as $status) {
            $comment->status = $status;
            check($elements->saveElement($comment, false), 'Moderate comment.');
            $expected = $status === Comment::STATUS_APPROVED ? $before['score'] : $before['score'] - $before['net'];
            same($expected, $comment->getAuthorScore(), 'Moderation invalidates reputation.');
        }
        $oldAuthor = $comment->userId;
        $newAuthor = $fixture['users'][1];
        $newScore = $votes->getScoreByAuthorId($newAuthor);
        $comment->userId = $newAuthor;
        check($elements->saveElement($comment, false), 'Change author.');
        same($before['score'] - $before['net'], $votes->getScoreByAuthorId($oldAuthor), 'Old author score refreshed.');
        same($newScore + $before['net'], $comment->getAuthorScore(), 'New author score refreshed.');
        check($elements->deleteElement($comment), 'Soft delete comment.');
        // Existing scores use the comments table status, without filtering elements.dateDeleted.
        same($newScore + $before['net'], $comment->getAuthorScore(), 'Soft deletion preserves existing score scope.');
        check($elements->restoreElement($comment), 'Restore comment.');
        same($newScore + $before['net'], $comment->getAuthorScore(), 'Restored score.');
        check($elements->deleteElement($comment, true), 'Hard delete comment.');
        same($newScore, $votes->getScoreByAuthorId($newAuthor), 'Cascading deletion refreshes reputation.');
        same(0, $comment->getAllVotes(), 'Cascading deletion refreshes vote counts.');
        same(0, $comment->getFlags(), 'Cascading deletion refreshes flag counts.');
        same(false, $flags->hasFlagged($comment, $viewer), 'Cascading deletion refreshes viewer flags.');

        resetCaches();
        $posted = newComment($fixture['ownerId'], $oldAuthor, Comment::STATUS_APPROVED);
        $html = (string)$plugin->getComments()->renderComment($posted);
        check(str_contains($html, 'id="comment-' . $posted->id . '"'), 'Standalone post response renders directly saved element.');
        $posted->comment = 'Edited standalone comment';
        check($elements->saveElement($posted, false), 'Edit posted comment.');
        check(str_contains((string)$plugin->getComments()->renderComment($posted), 'Edited standalone comment'), 'Standalone edit response is fresh.');
        $posted->status = Comment::STATUS_PENDING;
        check($elements->saveElement($posted, false), 'Moderate posted comment.');
        same('', $plugin->getComments()->renderComment($posted), 'Pending standalone response remains hidden.');
    } finally {
        $transaction->rollBack();
    }
    $report['regressions'] = 'passed';
    if (is_file($appPath . '/baseline.json')) {
        $previous = json_decode(file_get_contents($appPath . '/baseline.json'), true, 512, JSON_THROW_ON_ERROR);
        same($previous['measurements']['signedServices']['result'], $report['measurements']['signedServices']['result'], 'Signed-in results match upstream exactly.');
        foreach ($previous['measurements']['guestServices']['result'] as $i => $values) {
            foreach (array_diff(array_keys($values), ['viewerUp', 'viewerDown', 'viewerFlag']) as $key) {
                same($values[$key], $report['measurements']['guestServices']['result'][$i][$key], 'Guest count/score results match upstream.');
            }
        }
        $normalize = fn($html) => preg_replace([
            '/(name="CRAFT_CSRF_TOKEN" value=")[^"]+/',
            '/__JSCHK_[0-9a-f]+/',
            '/(\.value = ")[0-9a-f]+/',
            '/(<time\b[^>]*><small>).*?(<\/small>)/s',
        ], ['$1TOKEN', '__JSCHK_TOKEN', '$1TOKEN', '$1TIME$2'], $html);
        foreach (['signedHtml', 'otherViewerHtml'] as $key) {
            same($normalize($previous['measurements'][$key]['result']), $normalize($report['measurements'][$key]['result']), 'Signed-in HTML matches upstream except request tokens and relative time.');
        }
        $report['baselineComparison'] = 'passed';
    }
}

// Retain SQL and HTML for baseline comparisons and EXPLAIN inspection outside timed regions.
$reportPath = $appPath . '/' . ($baseline ? 'baseline' : 'changed') . '.json';
if (!$baseline) {
    $report['plans'] = [];
    foreach (array_unique(array_merge($report['measurements']['guestServices']['sql'], $report['measurements']['signedServices']['sql'])) as $sql) {
        $report['plans'][] = ['sql' => $sql, 'plan' => $db->createCommand('EXPLAIN ' . $sql)->queryAll()];
    }
}
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
foreach ($report['measurements'] as $name => $run) {
    echo $name . ': ' . $run['queries'] . ' queries, ' . $run['ms'] . " ms\n";
}
echo ($baseline ? 'Baseline recorded' : 'Regression tests passed') . ' on Craft ' . $report['craft'] . "\n";
