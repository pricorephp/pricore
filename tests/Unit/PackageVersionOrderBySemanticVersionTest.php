<?php

use App\Models\PackageVersion;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;

it('generates postgres-safe semantic version ordering SQL', function () {
    $pdo = new \PDO('sqlite::memory:');

    $connection = new Connection(
        $pdo,
        database: 'sqlite',
        tablePrefix: '',
        config: ['driver' => 'pgsql']
    );

    $query = new QueryBuilder(
        $connection,
        $connection->getQueryGrammar(),
        $connection->getPostProcessor()
    );

    $model = new PackageVersion;
    $builder = $model->newEloquentBuilder($query)->setModel($model)->from($model->getTable());

    $builder->orderBySemanticVersion('desc');

    $orders = $builder->getQuery()->orders;

    expect($orders)->toBeArray()->not->toBeEmpty();
    expect($orders[0])->toHaveKeys(['type', 'sql']);
    expect($orders[0]['type'])->toBe('Raw');

    $sql = $orders[0]['sql'];

    expect($sql)->toContain("normalized_version ~ '^[0-9]+(\\.[0-9]+){0,3}$'");
    expect($sql)->toContain('CASE WHEN');
    expect($sql)->toContain('SPLIT_PART(normalized_version');

    expect($sql)->not->toContain("CAST(SPLIT_PART(normalized_version, '.', 1) AS INTEGER)");
});
