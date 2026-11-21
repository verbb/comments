<?php
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use yii\web\ForbiddenHttpException;

/**
 * The comment page for someone who can moderate but not edit: decisions are sent on their own, and the
 * Status menu, part of the form they can't save, isn't offered. The Status menu applies the decisions'
 * rules: nobody changes the status of their own comment, and moving one to or from Removed needs the
 * permission to remove comments.
 */

function modNoEditUser(string $prefix, array $permissions): User
{
    $user = CommentsFixtureFactory::user($prefix . bin2hex(random_bytes(3)));
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);

    return User::find()->id($user->id)->status(null)->one();
}

function modNoEditComment(User $author, ModerationState $state = ModerationState::Pending): Comment
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $entry = CommentsFixtureFactory::entry('Moderate without edit ' . uniqid());
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => 'Original', 'userId' => (int)$author->id]))->comment;

    if ($state !== ModerationState::Approved) {
        Comments::$plugin->getModeration()->setState($comment, $state);
    }

    return Comment::find()->id($comment->id)->status(null)->one();
}

/** As `$user`, in a control panel request, with Twig's `currentUser` rebuilt for them. */
function modNoEditAs(User $user, callable $callback): mixed
{
    $session = Craft::$app->getUser();
    $previous = $session->getIdentity();
    $refresh = static function(): void {
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(craft\web\View::TEMPLATE_MODE_CP);
        $view->setTwig($view->createTwig());
        $view->setTemplateMode($mode);
    };

    $session->setIdentity($user);
    $refresh();

    try {
        return $callback();
    } finally {
        $session->setIdentity($previous);
        $refresh();
    }
}

function modNoEditSidebar(User $user, Comment $comment): string
{
    return modNoEditAs($user, fn() => Comment::find()->id($comment->id)->status(null)->one()->getSidebarHtml(false));
}

/** Posts the page's form with a new status, as choosing it in the Status menu and saving does. */
function modNoEditPostStatus(User $user, Comment $comment, string $status): void
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getRequest()->setBodyParams([
        'status' => $status,
        'authorId' => [$comment->userId],
        'ownerType' => Entry::class,
        'ownerEntryId' => [$comment->ownerId],
        'contentFormat' => 'plain',
        'fields' => ['comment' => $comment->comment],
    ]);

    modNoEditAs($user, function() use ($comment): void {
        $editing = Comment::find()->id($comment->id)->status(null)->one();
        $editing->setAttributesFromRequest([]);
        $editing->setScenario(Element::SCENARIO_LIVE);
        Craft::$app->getElements()->saveElement($editing);
    });
}

function modNoEditForbidden(callable $callback): bool
{
    try {
        $callback();
    } catch (ForbiddenHttpException) {
        return true;
    }

    return false;
}

afterEach(function() {
    unset($_SERVER['REQUEST_METHOD']);
    Craft::$app->getRequest()->setBodyParams([]);
});

it('gives someone who can moderate but not edit decisions that are sent on their own, and no Status menu', function() {
    $author = CommentsFixtureFactory::user('modNoEditAuthor' . bin2hex(random_bytes(3)));
    $comment = modNoEditComment($author);
    $decider = modNoEditUser('modNoEdit', ['accessCp', 'comments-view', 'comments-moderate']);
    $editor = modNoEditUser('modEdit', ['accessCp', 'comments-view', 'comments-moderate', 'comments-edit']);

    expect(modNoEditSidebar($decider, $comment))->toContain('data-comments-decision="approve"')
        ->toContain('data-comments-read-only')
        ->not->toMatch('/class="btn menubtn" data-comments-status-menu/')
        ->and(modNoEditSidebar($editor, $comment))->toContain('data-comments-decision="approve"')
        ->not->toContain('data-comments-read-only')
        ->toMatch('/class="btn menubtn" data-comments-status-menu/');
});

it('offers no Status menu on a moderator’s own comment, and refuses a status change to it', function() {
    $moderator = modNoEditUser('modOwn', ['accessCp', 'comments-view', 'comments-moderate', 'comments-edit']);
    $comment = modNoEditComment($moderator);

    expect(modNoEditSidebar($moderator, $comment))->not->toMatch('/class="btn menubtn" data-comments-status-menu/')
        ->and(modNoEditForbidden(fn() => modNoEditPostStatus($moderator, $comment, Comment::STATUS_APPROVED)))->toBeTrue()
        ->and(Comment::find()->id($comment->id)->status(null)->one()->status)->toBe(Comment::STATUS_PENDING);
});

it('needs the permission to remove comments to move one to or from Removed through the Status menu', function() {
    $author = CommentsFixtureFactory::user('modTrashAuthor' . bin2hex(random_bytes(3)));
    $moderator = modNoEditUser('modNoTrash', ['accessCp', 'comments-view', 'comments-moderate', 'comments-edit']);
    $remover = modNoEditUser('modTrash', ['accessCp', 'comments-view', 'comments-moderate', 'comments-edit', 'comments-trash']);
    $removed = modNoEditComment($author, ModerationState::Removed);

    expect(modNoEditSidebar($moderator, $removed))->not->toMatch('/class="btn menubtn" data-comments-status-menu/')
        ->and(modNoEditForbidden(fn() => modNoEditPostStatus($moderator, $removed, Comment::STATUS_APPROVED)))->toBeTrue()
        ->and(Comment::find()->id($removed->id)->status(null)->one()->status)->toBe(Comment::STATUS_REMOVED)
        ->and(modNoEditSidebar($remover, $removed))->toMatch('/class="btn menubtn" data-comments-status-menu/');

    modNoEditPostStatus($remover, $removed, Comment::STATUS_APPROVED);

    expect(Comment::find()->id($removed->id)->status(null)->one()->status)->toBe(Comment::STATUS_APPROVED);
});
