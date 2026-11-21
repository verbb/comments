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
 * Start Closed makes comments opt-in: an element without its own value for the field reads as Closed until an
 * editor chooses something. A Comments 3 field whose Default Value was off gets it, as Comments 3 closed those
 * elements.
 *
 * The owner carries its layout in memory, as in V4CommentsFieldHandleOverrideTest, and reads its value through
 * the layout's field the way Craft does, so the field's settings apply.
 */

/** An entry in the fixture section whose layout holds a Comments field configured with `$config`, and whose stored value is `$value`. */
function startClosedOwner(array $config, mixed $value): Entry
{
    $fields = Craft::$app->getFields();
    $saved = $fields->getFieldByHandle('startClosedComments');

    if (!$saved instanceof CommentsField) {
        $saved = new CommentsField(['name' => 'Comments', 'handle' => 'startClosedComments']);

        expect($fields->saveField($saved))->toBeTrue();
    }

    // As Craft creates the field from its stored settings.
    $field = new CommentsField(['id' => $saved->id, 'uid' => $saved->uid, 'name' => 'Comments', 'handle' => 'startClosedComments'] + $config);
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $tab->setElements([new CustomField($field)]);
    $layout->setTabs([$tab]);

    $owner = new class () extends Entry {
        public ?FieldLayout $stubLayout = null;
        public mixed $stubValue = null;

        public function getFieldLayout(): ?FieldLayout
        {
            return $this->stubLayout;
        }

        public function getFieldValue(string $fieldHandle): mixed
        {
            $field = $this->stubLayout->getFieldByHandle($fieldHandle);

            return $field ? $field->normalizeValue($this->stubValue, $this) : null;
        }
    };

    $section = CommentsFixtureFactory::section();
    $owner->sectionId = $section->id;
    $owner->typeId = $section->getEntryTypes()[0]->id;
    $owner->stubLayout = $layout;
    $owner->stubValue = $value;

    return $owner;
}

it('turns Start Closed on for a Comments 3 field whose Default Value was off, and only then', function() {
    foreach ([false, 0, '0', '', 'false', 'off'] as $off) {
        $field = new CommentsField(['name' => 'Comments', 'handle' => 'comments', 'commentEnabled' => true, 'default' => $off]);

        // Through the settings Craft saves, so the field's own reading of `default` is what's checked.
        expect($field->getSettings())->toHaveKey('startClosed', true)
            ->and($field->getSettings())->not->toHaveKey('default');
    }

    foreach ([true, 1, '1', 'true', 'on'] as $on) {
        $field = new CommentsField(['name' => 'Comments', 'handle' => 'comments', 'commentEnabled' => true, 'default' => $on]);

        expect($field->startClosed)->toBeFalse();
    }

    // A new Comments 4 field, and one already saved with Start Closed, keep what they have.
    expect((new CommentsField(['name' => 'Comments', 'handle' => 'comments']))->startClosed)->toBeFalse()
        ->and((new CommentsField(['name' => 'Comments', 'handle' => 'comments', 'default' => false, 'startClosed' => false]))->startClosed)->toBeFalse();
});

it('reads an element without its own value as Closed with Start Closed, and as Default without it', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->closed = false;
    $conversations = Comments::$plugin->getConversations();
    $conversations->clearCaches();

    // A Comments 3 field with Default Value off, on an element that has never been saved with a value.
    $upgraded = $conversations->resolveCommentableConfig(startClosedOwner(['commentEnabled' => true, 'default' => false], null));
    $withoutSetting = $conversations->resolveCommentableConfig(startClosedOwner([], null));
    $defaultOn = $conversations->resolveCommentableConfig(startClosedOwner(['commentEnabled' => true, 'default' => true], null));

    expect($upgraded->enabled)->toBeTrue()
        ->and($upgraded->closed)->toBeTrue()
        ->and($withoutSetting->closed)->toBeFalse()
        ->and($defaultOn->closed)->toBeFalse();

    $startClosed = new CommentsField(['name' => 'Comments', 'handle' => 'comments', 'startClosed' => true]);
    $plain = new CommentsField(['name' => 'Comments', 'handle' => 'comments']);

    expect($startClosed->normalizeValue(null))->toBe(CommentsField::STATE_CLOSED)
        ->and($startClosed->normalizeValue(''))->toBe(CommentsField::STATE_CLOSED)
        ->and($plain->normalizeValue(null))->toBe(CommentsField::STATE_INHERIT)
        ->and($conversations->resolveCommentableConfig(startClosedOwner(['startClosed' => true], null))->closed)->toBeTrue();
});

it('lets an editor’s choice win over Start Closed, Default included', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->closed = false;
    $conversations = Comments::$plugin->getConversations();
    $conversations->clearCaches();

    $default = $conversations->resolveCommentableConfig(startClosedOwner(['startClosed' => true], CommentsField::STATE_INHERIT));
    $open = $conversations->resolveCommentableConfig(startClosedOwner(['startClosed' => true], CommentsField::STATE_ENABLED));
    $off = $conversations->resolveCommentableConfig(startClosedOwner(['startClosed' => true], CommentsField::STATE_DISABLED));
    // Comments 3's stored values: switched on follows the source, switched off is Closed.
    $legacyOn = $conversations->resolveCommentableConfig(startClosedOwner(['startClosed' => true], ['commentEnabled' => true]));
    $legacyOff = $conversations->resolveCommentableConfig(startClosedOwner(['startClosed' => true], ['commentEnabled' => false]));

    expect($default->enabled)->toBeTrue()
        ->and($default->closed)->toBeFalse()
        ->and($open->enabled)->toBeTrue()
        ->and($open->closed)->toBeFalse()
        ->and($off->enabled)->toBeFalse()
        ->and($legacyOn->closed)->toBeFalse()
        ->and($legacyOff->closed)->toBeTrue();

    // Default still follows Where Comments Are Allowed.
    $owner = startClosedOwner(['startClosed' => true], CommentsField::STATE_INHERIT);
    // Permissions are keyed by element class, so name the stand-in's.
    $settings->permissions = [get_class($owner) => ['not-the-owner-section-uid']];
    $conversations->clearCaches();

    expect($conversations->resolveCommentableConfig($owner)->enabled)->toBeFalse();
});

it('stores Closed when an element without a value is saved, so turning Start Closed off keeps what editors saw', function() {
    // An element without a value: saving it makes the value Start Closed gave it its own, so it's stored.
    $owner = startClosedOwner(['startClosed' => true], null);
    $field = $owner->getFieldLayout()->getFieldByHandle('startClosedComments');

    expect($owner->isFieldDirty('startClosedComments'))->toBeFalse()
        ->and($field->beforeElementSave($owner, false))->toBeTrue()
        ->and($owner->isFieldDirty('startClosedComments'))->toBeTrue();

    // Turning Start Closed off later, the stored value still reads Closed.
    $off = new CommentsField(['name' => 'Comments', 'handle' => 'startClosedComments', 'startClosed' => false]);

    expect($off->normalizeValue($field->serializeValue(CommentsField::STATE_CLOSED)))->toBe(CommentsField::STATE_CLOSED);

    // An element with a value of its own, one without Start Closed, and a draft are left as they are.
    $chosen = startClosedOwner(['startClosed' => true], CommentsField::STATE_INHERIT);
    $plain = startClosedOwner([], null);
    // A draft or revision: its canonical element is another one.
    $draft = startClosedOwner(['startClosed' => true], null);
    $draft->id = 1001;
    $draft->setCanonicalId(1000);

    expect($draft->getIsCanonical())->toBeFalse();

    foreach ([$chosen, $plain, $draft] as $element) {
        $element->getFieldLayout()->getFieldByHandle('startClosedComments')->beforeElementSave($element, false);

        expect($element->isFieldDirty('startClosedComments'))->toBeFalse();
    }
});
