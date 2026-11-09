<?php
namespace Tests\General;

use Craft;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\ResetTestDatabase;
use verbb\comments\Comments;

abstract class TestCase extends BaseTestCase
{
    private array $settingsSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCraftBootstrapped();
        ResetTestDatabase::resetCommentsData();
        $this->settingsSnapshot = Comments::$plugin->getSettings()->getAttributes();
        // Protection has its own entry-point tests; domain fixtures are trusted test setup.
        Comments::$plugin->getSettings()->enableSpamChecks = false;

        // Console test applications have no authenticated identity by default.
        $admin = Craft::$app->getUsers()->getUserByUsernameOrEmail('admin');
        if (!$admin) {
            throw new RuntimeException('The test administrator is missing; rerun composer test:setup.');
        }

        Craft::$app->getUser()->setIdentity($admin);
    }

    protected function tearDown(): void
    {
        if (class_exists(Craft::class) && Craft::$app?->getIsInstalled()) {
            Craft::$app->getGql()->setActiveSchema(null);
            Comments::$plugin->getSettings()->setAttributes($this->settingsSnapshot, false);
        }

        parent::tearDown();
    }

    protected function ensureCraftBootstrapped(): void
    {
        if (!class_exists(Craft::class) || !Craft::$app) {
            throw new RuntimeException('Craft application must be bootstrapped before running integration tests.');
        }
    }
}
