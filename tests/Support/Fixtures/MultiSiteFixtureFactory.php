<?php
namespace Tests\Support\Fixtures;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use RuntimeException;

final class MultiSiteFixtureFactory
{
    public static function secondarySite(): Site
    {
        $sites = Craft::$app->getSites();
        $site = $sites->getSiteByHandle('commentsSecondary');

        if ($site) {
            return $site;
        }

        $primary = $sites->getPrimarySite();
        $site = new Site([
            'name' => 'Comments Secondary',
            'handle' => 'commentsSecondary',
            'language' => $primary->language,
            'baseUrl' => 'https://comments-secondary.test/',
            'groupId' => $primary->groupId,
            'enabled' => true,
        ]);

        if (!$sites->saveSite($site)) {
            throw new RuntimeException('Failed creating secondary test site: ' . json_encode($site->getErrors()));
        }

        return $site;
    }

    /**
     * @return array{primary: Entry, secondary: Entry}
     */
    public static function sameOwnerDifferentSites(?string $title = null): array
    {
        $secondarySite = self::secondarySite();
        $section = CommentsFixtureFactory::section();
        // Another test may have deleted the secondary site since this section last saw it.
        $siteSettings = array_filter(
            $section->getSiteSettings(),
            static fn($settings, $siteId) => Craft::$app->getSites()->getSiteById((int)$siteId) !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        if (!isset($siteSettings[$secondarySite->id])) {
            $siteSettings[$secondarySite->id] = new Section_SiteSettings([
                'siteId' => $secondarySite->id,
                'enabledByDefault' => true,
                'hasUrls' => true,
                'uriFormat' => 'comments-test/{slug}',
                'template' => '',
            ]);
            $section->setSiteSettings($siteSettings);

            if (!Craft::$app->getEntries()->saveSection($section)) {
                throw new RuntimeException('Failed enabling the Comments fixture section for the secondary site: ' . json_encode($section->getErrors()));
            }
        }

        // The section's all-sites propagation creates one owner element with a site-local representation.
        $primary = CommentsFixtureFactory::entry($title ?? 'Multi-site Comment Owner');
        $secondaryExists = (new Query())
            ->from('{{%elements_sites}}')
            ->where([
                'elementId' => $primary->id,
                'siteId' => $secondarySite->id,
            ])
            ->exists();

        if (!$secondaryExists) {
            throw new RuntimeException('The propagated secondary-site entry fixture was not created.');
        }

        // Craft's long-lived integration-test element cache can return the primary-site
        // instance for the shared ID. The clone represents the verified site-local row.
        $secondary = clone $primary;
        $secondary->siteId = $secondarySite->id;

        return [
            'primary' => $primary,
            'secondary' => $secondary,
        ];
    }
}
