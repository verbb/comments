<?php

declare(strict_types=1);

use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\fields\CommentsField;

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Craft 5 lets a field layout give a field its own handle. A Comments field placed that way still turns
 * comments on, off or closed for the element.
 *
 * The owner carries its layout in memory: changing a saved layout mid-run leaves stale GraphQL types in
 * this process (see the Comments 3 field test in V4CompatibilityTest).
 */

/** An entry in the fixture section whose layout holds the Comments field under the handle `discussionOverride`. */
function handleOverrideOwner(mixed $value): Entry
{
    $fields = Craft::$app->getFields();
    $field = $fields->getFieldByHandle('handleOverrideComments');

    if (!$field instanceof CommentsField) {
        $field = new CommentsField(['name' => 'Comments', 'handle' => 'handleOverrideComments']);

        expect($fields->saveField($field))->toBeTrue();
    }

    $layout = new FieldLayout(['type' => Entry::class]);
    // As Craft builds a saved layout's element: the override is applied to the field when it's created.
    $instance = new CustomField($field, ['handle' => 'discussionOverride']);
    $tab = new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $tab->setElements([$instance]);
    $layout->setTabs([$tab]);

    $owner = new class() extends Entry {
        public ?FieldLayout $stubLayout = null;
        public mixed $stubValue = null;

        public function getFieldLayout(): ?FieldLayout
        {
            return $this->stubLayout;
        }

        public function getFieldValue(string $fieldHandle): mixed
        {
            return $fieldHandle === 'discussionOverride' ? $this->stubValue : null;
        }
    };

    $section = CommentsFixtureFactory::section();
    $owner->sectionId = $section->id;
    $owner->typeId = $section->getEntryTypes()[0]->id;
    $owner->stubLayout = $layout;
    $owner->stubValue = $value;

    return $owner;
}

it('honours Open on a field with a layout handle override where the source isn’t allowed', function() {
    $owner = handleOverrideOwner(CommentsField::STATE_ENABLED);
    $settings = Comments::$plugin->getSettings();
    // Permissions are keyed by element class, so name the stand-in's.
    $settings->permissions = [get_class($owner) => ['not-the-owner-section-uid']];
    Comments::$plugin->getConversations()->clearCaches();

    expect(Comments::$plugin->getComments()->checkPermissions($owner))->toBeFalse()
        ->and(Comments::$plugin->getConversations()->resolveCommentableConfig($owner)->enabled)->toBeTrue();
});

it('honours Off and Closed on a field with a layout handle override', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    Comments::$plugin->getConversations()->clearCaches();
    $conversations = Comments::$plugin->getConversations();

    $off = $conversations->resolveCommentableConfig(handleOverrideOwner(CommentsField::STATE_DISABLED));
    $closed = $conversations->resolveCommentableConfig(handleOverrideOwner(CommentsField::STATE_CLOSED));
    // An upgraded entry not saved since still holds Comments 3's switch.
    $upgraded = $conversations->resolveCommentableConfig(handleOverrideOwner(['commentEnabled' => false]));

    expect($off->enabled)->toBeFalse()
        ->and($closed->enabled)->toBeTrue()
        ->and($closed->closed)->toBeTrue()
        ->and($upgraded->closed)->toBeTrue();
});
