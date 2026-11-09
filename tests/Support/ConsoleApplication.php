<?php
namespace Tests\Support;

use craft\web\Session;

/**
 * Gives integration tests a real session while retaining Craft's console bootstrap.
 */
class ConsoleApplication extends \craft\console\Application
{
    public function getSession(): Session
    {
        /** @var Session */
        return $this->get('session');
    }
}
