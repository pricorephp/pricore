<?php

use App\Models\PackageVersion;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;

function buildQueryForDriver(string $driver): \Illuminate\Database\Eloquent\Builder
{
    $pdo = new \PDO('sqlite::memory:');

    $connection = new Connection(
        $pdo,
        database: 'sqlite',
        tablePrefix: '',
        config: ['driver' => $driver]
    );

    $query = new QueryBuilder(
        $connection,
        $connection->getQueryGrammar(),
        $connection->getPostProcessor()
    );

    $model = new PackageVersion;

    return $model->newEloquentBuilder($query)->setModel($model)->from($model->getTable());
}

it('generates postgres-safe semantic version ordering SQL', function () {
    $builder = buildQueryForDriver('pgsql');
    $builder->orderBySemanticVersion('desc');

    $orders = $builder->getQuery()->orders;

    expect($orders)->toBeArray()->not->toBeEmpty();
    expect($orders[0])->toHaveKeys(['type', 'sql']);
    expect($orders[0]['type'])->toBe('Raw');

    $sql = $orders[0]['sql'];

    expect($sql)->toContain("normalized_version ~ '^[0-9]+(\\.[0-9]+){0,3}$'");
    expect($sql)->toContain('CASE WHEN');
    expect($sql)->toContain('SPLIT_PART(normalized_version');
    expect($sql)->toContain('NULLS LAST');
    expect($sql)->toContain('released_at');
    expect($sql)->toContain('version desc');

    expect($sql)->not->toContain("CAST(SPLIT_PART(normalized_version, '.', 1) AS INTEGER)");
});

it('generates sqlite-safe semantic version ordering SQL', function () {
    $builder = buildQueryForDriver('sqlite');
    $builder->orderBySemanticVersion('desc');

    $orders = $builder->getQuery()->orders;

    expect($orders)->toBeArray()->not->toBeEmpty();
    expect($orders[0]['type'])->toBe('Raw');

    $sql = $orders[0]['sql'];

    expect($sql)->toContain("normalized_version GLOB '[0-9]*.[0-9]*'");
    expect($sql)->toContain('CASE WHEN');
    expect($sql)->toContain('released_at');
    expect($sql)->toContain('version desc');
});

it('generates mysql-safe semantic version ordering SQL', function () {
    $builder = buildQueryForDriver('mysql');
    $builder->orderBySemanticVersion('desc');

    $orders = $builder->getQuery()->orders;

    expect($orders)->toBeArray()->not->toBeEmpty();
    expect($orders[0]['type'])->toBe('Raw');

    $sql = $orders[0]['sql'];

    expect($sql)->toContain('REGEXP');
    expect($sql)->toContain('CASE WHEN');
    expect($sql)->toContain('SUBSTRING_INDEX');
    expect($sql)->toContain('released_at');
    expect($sql)->toContain('version desc');
});
