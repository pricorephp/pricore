<?php

use App\Http\Controllers\Composer\MetadataController;
use App\Http\Controllers\Composer\NotifyBatchController;
use App\Http\Controllers\Composer\PackageController;
use Illuminate\Support\Facades\Route;

Route::prefix('{organization:slug}')
    ->middleware('composer.token')
    ->group(function () {
        // Root packages.json - returns metadata-url template
        Route::get('packages.json', [PackageController::class, 'index'])
            ->name('composer.packages.index');

        // Individual package metadata (Composer v2 format)
        Route::get('p2/{vendor}/{package}.json', [MetadataController::class, 'show'])
            ->name('composer.metadata.show')
            ->where(['vendor' => '[a-z0-9_.-]+', 'package' => '[a-z0-9_.-]+']);

        // Dev versions metadata
        Route::get('p2/{vendor}/{package}~dev.json', [MetadataController::class, 'showDev'])
            ->name('composer.metadata.showDev')
            ->where(['vendor' => '[a-z0-9_.-]+', 'package' => '[a-z0-9_.-]+']);

        // Download notification endpoint
        Route::post('notify-batch', NotifyBatchController::class)
            ->name('composer.notify-batch');
    });
