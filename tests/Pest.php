<?php
uses(Tests\General\TestCase::class)
    ->in('Performance', 'Services');

/**
 * Run a GraphQL query against a new schema with `$scope`, as Craft's GraphQL endpoint would. Shared,
 * so each test file that uses it runs on its own.
 */
function gapsGql(array $scope, string $query, bool $public = false): array
{
    $schema = new craft\models\GqlSchema(['name' => 'Gaps', 'scope' => $scope, 'isPublic' => $public]);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getGql()->setActiveSchema($schema);

    return Craft::$app->getGql()->executeQuery($schema, $query, debugMode: true);
}
