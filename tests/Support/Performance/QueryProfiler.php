<?php
namespace Tests\Support\Performance;

use Craft;
use Closure;
use Yii;

class QueryProfiler
{
    public static function profile(Closure $callback): array
    {
        $logger = Yii::getLogger();
        $db = Craft::$app->getDb();
        $previousFlushInterval = $logger->flushInterval;
        $previousEnableLogging = $db->enableLogging;
        $previousEnableProfiling = $db->enableProfiling;

        $logger->flushInterval = 0;
        $db->enableLogging = true;
        $db->enableProfiling = true;

        $profileOffset = count($logger->getProfiling());
        $start = hrtime(true);

        try {
            $result = $callback();
            $profiles = array_slice($logger->getProfiling(), $profileOffset);
        } finally {
            $logger->flushInterval = $previousFlushInterval;
            $db->enableLogging = $previousEnableLogging;
            $db->enableProfiling = $previousEnableProfiling;
        }

        $queries = [];

        foreach ($profiles as $profile) {
            $info = (string)$profile['info'];

            if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE|SHOW|CREATE|ALTER|DROP)\b/i', $info)) {
                $queries[] = self::normalizeQuery($info);
            }
        }

        $patterns = array_count_values($queries);
        arsort($patterns);

        return [
            'resultType' => get_debug_type($result),
            'resultSize' => self::resultSize($result),
            'durationMs' => round((hrtime(true) - $start) / 1_000_000, 2),
            'queries' => count($queries),
            'duplicatePatterns' => array_sum(array_map(static fn(int $count): int => max(0, $count - 1), $patterns)),
            'topPatterns' => array_slice($patterns, 0, 5, true),
        ];
    }

    private static function normalizeQuery(string $query): string
    {
        $query = preg_replace("/'([^'\\\\]|\\\\.)*'/", '?', $query) ?? $query;
        $query = preg_replace('/\b\d+\b/', '?', $query) ?? $query;

        return preg_replace('/\s+/', ' ', trim($query)) ?? $query;
    }

    private static function resultSize(mixed $result): ?int
    {
        if (is_countable($result)) {
            return count($result);
        }

        if (is_string($result)) {
            return strlen($result);
        }

        if (is_int($result) || is_float($result)) {
            return (int)$result;
        }

        return null;
    }
}
