<?php
use craft\db\Query;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\MultiSiteFixtureFactory;

it('keeps v3 comment sets distinct for one owner across sites', function() {
    $owners = MultiSiteFixtureFactory::sameOwnerDifferentSites();

    CommentsFixtureFactory::comment($owners['primary'], 'Primary-site comment');
    CommentsFixtureFactory::comment($owners['secondary'], 'Secondary-site comment');

    expect($owners['primary']->id)->toBe($owners['secondary']->id);
    expect($owners['primary']->siteId)->not->toBe($owners['secondary']->siteId);

    foreach ($owners as $owner) {
        $comments = (new Query())
            ->from('{{%comments_comments}}')
            ->where([
                'ownerId' => $owner->id,
                'ownerSiteId' => $owner->siteId,
            ])
            ->all();

        expect($comments)->toHaveCount(1);
        expect((int)$comments[0]['ownerSiteId'])->toBe($owner->siteId);
    }
});
