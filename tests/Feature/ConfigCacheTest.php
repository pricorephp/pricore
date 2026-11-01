<?php

use Illuminate\Support\Arr;

// `php artisan config:cache` runs on every deploy and in the Docker entrypoint. This
// mirrors its check: every value must survive var_export() and evaluate back.
it('only contains values that config:cache can serialize', function () {
    $nonSerializable = collect(Arr::dot(config()->all()))
        ->reject(function (mixed $value) {
            try {
                eval('return '.var_export($value, true).';');

                return true;
            } catch (Throwable) {
                return false;
            }
        })
        ->keys()
        ->all();

    expect($nonSerializable)->toBe([]);
});
