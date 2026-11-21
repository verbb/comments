<?php
namespace verbb\comments\migrations\support;

use Craft;

use Throwable;

/**
 * For upgrade migrations that commit their own progress.
 *
 * Craft wraps `safeUp()` in one transaction, which would hold hours of undo log on a large site, lose
 * all of it to a timeout, and roll back the upgrade log explaining what went wrong.
 */
trait RunsOutsideTransaction
{
    // Public Methods
    // =========================================================================

    public function up(bool $throwExceptions = false): bool
    {
        try {
            $this->runUpgrade();
        } catch (Throwable $e) {
            echo "    > The Comments 3 upgrade stopped: {$e->getMessage()}\n";
            echo "    > Run it again once this is resolved. Your Comments 3 data has not been removed.\n";

            Craft::$app->getErrorHandler()->logException($e);

            if ($throwExceptions) {
                throw $e;
            }

            return false;
        }

        // As Craft's own `up()` does once the work is done.
        $this->afterUp();

        return true;
    }


    // Protected Methods
    // =========================================================================

    abstract protected function runUpgrade(): void;
}
