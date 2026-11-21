<?php

declare(strict_types=1);

use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\fields\CommentsField;

use craft\web\View;

/**
 * The Comments field's Default option names what the element gets from the settings. Auto-close Comments
 * closes an element's thread once it's old enough, so the option says Closed for it, as the site does.
 */

function autoCloseStatusEntry(string $title, string $postDate): craft\elements\Entry
{
    $entry = CommentsFixtureFactory::entry($title);
    $entry->postDate = new DateTime($postDate);

    expect(Craft::$app->getElements()->saveElement($entry, false))->toBeTrue();

    return $entry;
}

function autoCloseStatusInput(craft\elements\Entry $entry): string
{
    $field = new CommentsField(['name' => 'Comments', 'handle' => 'autoCloseStatus']);
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);

    try {
        return $field->getInputHtml(CommentsField::STATE_INHERIT, $entry);
    } finally {
        $view->setTemplateMode($mode);
    }
}

it('shows Default (Closed) for an entry Auto-close Comments has closed, and Open for a recent one', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->closed = false;
    $settings->autoCloseDays = 30;
    Comments::$plugin->getConversations()->clearCaches();

    $old = autoCloseStatusEntry('Auto-closed status owner', '-60 days');
    $recent = autoCloseStatusEntry('Recent status owner', '-1 day');
    $owner = Comments::$plugin->getOwnerElement();

    expect(Comments::$plugin->getComments()->checkExpired($old))->toBeTrue()
        ->and($owner->getSourceStatus($old))->toBe('closed')
        ->and(autoCloseStatusInput($old))->toMatch('/<option value="inherit"[^>]*selected[^>]*>Default \(Closed\)<\/option>/')
        ->and($owner->getSourceStatus($recent))->toBe('open')
        ->and(autoCloseStatusInput($recent))->toMatch('/<option value="inherit"[^>]*selected[^>]*>Default \(Open\)<\/option>/');

    // Without Auto-close Comments, age doesn't matter.
    $settings->autoCloseDays = null;
    Comments::$plugin->getConversations()->clearCaches();

    expect($owner->getSourceStatus($old))->toBe('open');
});
