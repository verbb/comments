<?php
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\widgets\Comments as CommentsWidget;

/**
 * The Comments dashboard widget lists comments of any status, so it's only offered to, and only shows
 * anything for, people who can view comments.
 */

function widgetPermissionUser(array $permissions): User
{
    $user = CommentsFixtureFactory::user('widgetPermission' . bin2hex(random_bytes(3)));
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);

    return User::find()->id($user->id)->status(null)->one();
}

function widgetPermissionAs(User $user, callable $callback): mixed
{
    $session = Craft::$app->getUser();
    $previous = $session->getIdentity();
    $session->setIdentity($user);

    try {
        return $callback();
    } finally {
        $session->setIdentity($previous);
    }
}

it('isn’t offered to, and shows nothing for, someone who can’t view comments', function() {
    $cpOnly = widgetPermissionUser(['accessCp']);

    expect(widgetPermissionAs($cpOnly, fn() => CommentsWidget::isSelectable()))->toBeFalse()
        ->and(widgetPermissionAs($cpOnly, fn() => (new CommentsWidget())->getBodyHtml()))->toBeNull();
});

it('is offered to someone who can view comments, and to admins', function() {
    $viewer = widgetPermissionUser(['accessCp', 'comments-view']);
    $admin = Craft::$app->getUsers()->getUserByUsernameOrEmail('admin');

    expect(widgetPermissionAs($viewer, fn() => CommentsWidget::isSelectable()))->toBeTrue()
        ->and(widgetPermissionAs($viewer, fn() => (new CommentsWidget())->getBodyHtml()))->toBeString()
        ->and(widgetPermissionAs($admin, fn() => CommentsWidget::isSelectable()))->toBeTrue();
});
