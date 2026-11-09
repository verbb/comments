<?php
use Tests\Support\V3Site;

beforeEach(function() {
    V3Site::downgrade();
});

afterEach(function() {
    V3Site::restore();
});

// The upgrade tests are only as good as the schema they start from.
it('matches the final v3 database manifest', function() {
    $path = dirname(__DIR__) . '/Support/Fixtures/v3-schema-manifest.json';
    $manifest = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    foreach ($manifest['sourceMigrations'] as $migration) {
        expect(is_file(dirname(__DIR__, 2) . "/src/migrations/{$migration}"))->toBeTrue();
    }

    foreach ($manifest['tables'] as $tableName => $expected) {
        $schema = Craft::$app->getDb()->getTableSchema("{{%{$tableName}}}", true);

        expect($schema)->not->toBeNull();

        foreach ($expected['columns'] as $column) {
            expect(isset($schema->columns[$column]))
                ->toBeTrue("Missing expected v3 column {$tableName}.{$column}");
        }

        foreach ($expected['foreignKeys'] as $localColumn => $foreignKey) {
            $matchingForeignKey = array_values(array_filter(
                $schema->foreignKeys,
                static fn(array $actual): bool => isset($actual[$localColumn])
                    && str_ends_with((string)$actual[0], $foreignKey[0])
                    && $actual[$localColumn] === $foreignKey[1],
            ));

            expect($matchingForeignKey)
                ->not->toBeEmpty("Missing {$tableName}.{$localColumn} foreign key.");
        }
    }
});
