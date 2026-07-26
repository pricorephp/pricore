<?php

use Illuminate\Support\Facades\DB;

uses()->group('health');

it('reports healthy when the dependencies are reachable', function () {
    $this->get('/up')->assertOk();
});

it('fails when the database is unreachable', function () {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('could not connect'));

    $this->get('/up')->assertServerError();
});
