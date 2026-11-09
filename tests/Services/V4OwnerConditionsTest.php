<?php
use craft\elements\Asset;
use craft\models\Volume;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\elements\conditions\EntrySectionConditionRule;
use verbb\comments\elements\conditions\EntryTypeConditionRule;
use verbb\comments\elements\conditions\OwnerTypeConditionRule;
use craft\elements\Entry;

it('can filter by owner type, section and entry type together', function() {
    $entry = CommentsFixtureFactory::entry('Filtered owner');
    $comment = CommentsFixtureFactory::comment($entry, 'Filtered comment');

    $found = Comment::find()
        ->status(null)
        ->ownerType(Entry::class)
        ->ownerSectionId($entry->sectionId)
        ->ownerEntryTypeId($entry->typeId)
        ->ids();

    expect($found)->toContain($comment->id);
});

it('matches owner condition rules against the owner rather than the comment', function() {
    $entry = CommentsFixtureFactory::entry('Rule owner');
    $comment = CommentsFixtureFactory::comment($entry, 'Rule comment');

    $section = new EntrySectionConditionRule(['values' => [$entry->getSection()->uid]]);
    $type = new EntryTypeConditionRule(['values' => [$entry->getType()->uid]]);
    $ownerType = new OwnerTypeConditionRule(['values' => [Entry::class]]);

    expect($section->matchElement($comment))->toBeTrue()
        ->and($type->matchElement($comment))->toBeTrue()
        ->and($ownerType->matchElement($comment))->toBeTrue();
});

it('does not expire elements that have no post date', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->autoCloseDays = 1;

    expect(Comments::$plugin->getComments()->checkExpired(CommentsFixtureFactory::user('noPostDate')))->toBeFalse();
});

it('checks asset owners against their volume in the permission settings', function() {
    $asset = new class () extends Asset {
        public function getVolume(): Volume
        {
            return new Volume(['uid' => 'allowed-volume']);
        }
    };

    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [get_class($asset) => ['allowed-volume']];
    expect(Comments::$plugin->getComments()->checkPermissions($asset))->toBeTrue();

    $settings->permissions = [get_class($asset) => ['other-volume']];
    expect(Comments::$plugin->getComments()->checkPermissions($asset))->toBeFalse();
});
