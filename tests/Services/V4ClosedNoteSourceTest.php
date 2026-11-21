<?php

declare(strict_types=1);

use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\fields\CommentsField;

use craft\elements\Entry;
use craft\web\View;

/**
 * Closed keeps comments hidden on an element whose source isn't allowed, as only Open overrides the
 * source, so the Comments field says so for that element instead of promising readable comments.
 *
 * Nothing shared is saved: the field and the entry live in memory, and the source permission is set on
 * the plugin's settings for the test only.
 */

const CLOSED_NOTE_HIDDEN = 'Comments stay hidden because this element isn’t included in Where Comments Are Allowed. Only Open overrides that.';
const CLOSED_NOTE_READABLE = 'Visitors can read, but not post.';

/** An unsaved entry in the fixture section. */
function closedNoteOwner(): Entry
{
    $section = CommentsFixtureFactory::section();

    return new Entry([
        'sectionId' => $section->id,
        'typeId' => $section->getEntryTypes()[0]->id,
        'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        'title' => 'Closed note owner',
    ]);
}

/** The field's input for `$entry`, as an entry's edit page renders it, with the source permissions given. */
function closedNoteInput(array $permissions, string $value, Entry $entry): string
{
    $settings = Comments::$plugin->getSettings();
    $previous = $settings->permissions;
    $settings->permissions = $permissions;
    Comments::$plugin->getConversations()->clearCaches();
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);

    try {
        $field = new CommentsField(['name' => 'Comments', 'handle' => 'closedNoteComments', 'showSummary' => false]);

        return $field->getInputHtml($value, $entry);
    } finally {
        $view->setTemplateMode($mode);
        $settings->permissions = $previous;
        Comments::$plugin->getConversations()->clearCaches();
    }
}

it('explains Closed by whether the element’s source allows comments', function() {
    $ownerElement = Comments::$plugin->getOwnerElement();
    $allowed = $ownerElement->getStateOptions(true);
    $notAllowed = $ownerElement->getStateOptions(false);

    expect($allowed[CommentsField::STATE_CLOSED]['text'])->toBe(CLOSED_NOTE_READABLE)
        ->and($notAllowed[CommentsField::STATE_CLOSED]['text'])->toBe(CLOSED_NOTE_HIDDEN)
        // Only Closed's sentence changes, and the default is an allowed source.
        ->and($notAllowed[CommentsField::STATE_CLOSED]['label'])->toBe($allowed[CommentsField::STATE_CLOSED]['label'])
        ->and($notAllowed[CommentsField::STATE_ENABLED])->toBe($allowed[CommentsField::STATE_ENABLED])
        ->and($notAllowed[CommentsField::STATE_DISABLED])->toBe($allowed[CommentsField::STATE_DISABLED])
        ->and($ownerElement->getStateOptions())->toBe($allowed);
});

it('shows the field’s Closed note for the element’s own source, in the note and the option', function() {
    $entry = closedNoteOwner();
    $section = CommentsFixtureFactory::section();

    $notAllowed = closedNoteInput([Entry::class => ['not-the-owner-section-uid']], CommentsField::STATE_CLOSED, $entry);
    $allowed = closedNoteInput([Entry::class => [$section->uid]], CommentsField::STATE_CLOSED, $entry);
    $everywhere = closedNoteInput([], CommentsField::STATE_CLOSED, $entry);

    expect($notAllowed)->toContain('data-comments-field-description>' . CLOSED_NOTE_HIDDEN . '</p>')
        ->toMatch('/<option value="closed"[^>]*data-description="' . preg_quote(CLOSED_NOTE_HIDDEN, '/') . '"/')
        ->not->toContain(CLOSED_NOTE_READABLE)
        // Default still says what the source gives, which is Off.
        ->toMatch('/<option value="inherit"[^>]*>Default \(Off\)<\/option>/');

    foreach ([$allowed, $everywhere] as $html) {
        expect($html)->toContain('data-comments-field-description>' . CLOSED_NOTE_READABLE . '</p>')
            ->not->toContain(CLOSED_NOTE_HIDDEN);
    }
});
