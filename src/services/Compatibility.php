<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\compatibility\console\LegacyBaseController;
use verbb\comments\compatibility\twig\LegacyExtension;

use Craft;
use craft\base\Component;
use craft\console\Application as ConsoleApplication;

use yii\base\InvalidCallException;
use yii\base\UnknownMethodException;

/**
 * Comments 3 compatibility. With `compatibilityMode` on, the default, Comments 3's Twig helpers, element
 * methods, query parameters, GraphQL fields, console commands and browser events keep working, each
 * logging a Craft deprecation warning so a site can find and update them before turning the mode off.
 *
 * Everything it switches lives in `src/compatibility/`, so the main code has no Comments 3 handling of its
 * own. The settings translation and the Comments 3 form actions live there too, but they're documented
 * as supported and work whatever the mode.
 */
class Compatibility extends Component
{
    // Static Methods
    // =========================================================================

    /**
     * Whether Comments 3's APIs keep working. Read before the plugin has finished loading, it's on.
     */
    public static function enabled(): bool
    {
        return (bool)(Comments::$plugin?->getSettings()->compatibilityMode ?? true);
    }

    /**
     * Logs a deprecation warning under `comments.{$key}`, so each use appears once in Craft's log.
     */
    public static function deprecated(string $key, string $message): void
    {
        Craft::$app->getDeprecator()->log('comments.' . $key, $message);
    }

    /**
     * For a Comments 3 method that only exists in compatibility mode: logs its deprecation, or with the mode
     * off, fails as a method that doesn't exist would, saying how to bring it back.
     */
    public static function requireMethod(string $class, string $method, string $message): void
    {
        if (!self::enabled()) {
            throw new UnknownMethodException("Calling unknown method: {$class}::{$method}(). It was removed in Comments 4, and only works with `compatibilityMode` on. {$message}");
        }

        self::deprecated($method, $message);
    }


    /**
     * For a Comments 3 Twig function, which is only registered in compatibility mode but can still be
     * called when the mode is turned off while the app runs.
     */
    public static function requireFunction(string $function, string $message): void
    {
        if (!self::enabled()) {
            throw new InvalidCallException("`{$function}()` was removed in Comments 4, and only works with `compatibilityMode` on. {$message}");
        }

        self::deprecated($function, $message);
    }


    // Public Methods
    // =========================================================================

    public function bootstrap(): void
    {
        if (!self::enabled()) {
            return;
        }

        Craft::$app->getView()->registerTwigExtension(new LegacyExtension());

        // Comments 3's `comments/base/…` console commands, so a deploy script that still runs them doesn't fail.
        if (Craft::$app instanceof ConsoleApplication && Comments::$plugin) {
            Comments::$plugin->controllerMap['base'] = LegacyBaseController::class;
        }
    }

    public function isCompatibilityModeEnabled(): bool
    {
        return self::enabled();
    }
}
