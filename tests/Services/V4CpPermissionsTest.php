<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;

/**
 * CP permission surface: one permission per capability, with Settings left to admins.
 */

it('registers permissions for each comments capability', function() {
    $registered = [];

    foreach (Craft::$app->getUserPermissions()->getAllPermissions() as $group) {
        foreach ($group['permissions'] ?? [] as $name => $permission) {
            $registered[] = $name;

            foreach (array_keys($permission['nested'] ?? []) as $nested) {
                $registered[] = $nested;
            }
        }
    }

    expect($registered)->toContain('comments-view');
    expect($registered)->toContain('comments-moderate');
    expect($registered)->toContain('comments-edit');
    expect($registered)->toContain('comments-trash');
    expect($registered)->toContain('comments-delete');
});

it('gates moderation on the moderate permission rather than edit', function() {
    $user = CommentsFixtureFactory::user('cp-permissions-moderator');
    $moderation = Comments::$plugin->getModeration();

    expect($user->admin)->toBeFalse();
    expect($moderation->canModerateComment($user))->toBeFalse();

    // `comments-edit` is an element capability now; on its own it must not unlock the queue.
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, ['comments-view', 'comments-edit']);
    expect($moderation->canModerateComment($user))->toBeFalse();

    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, ['comments-view', 'comments-moderate']);
    expect($moderation->canModerateComment($user))->toBeTrue();
});

it('uses one Comments workspace without separate moderation or utilities navigation', function() {
    $nav = Comments::$plugin->getCpNavItem();

    expect($nav['subnav'])->toHaveKeys(['comments', 'settings']);
    expect($nav['subnav'])->not->toHaveKey('moderation');
    expect($nav['subnav'])->not->toHaveKey('utilities');
});
