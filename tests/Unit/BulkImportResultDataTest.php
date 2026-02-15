<?php

use App\Domains\Repository\Contracts\Data\BulkImportResultData;

it('generates status message for created repositories', function () {
    $result = new BulkImportResultData(created: 3, skipped: 0, webhooksFailed: 0);

    expect($result->statusMessage())->toBe('3 repositories imported.');
});

it('generates status message for single created repository', function () {
    $result = new BulkImportResultData(created: 1, skipped: 0, webhooksFailed: 0);

    expect($result->statusMessage())->toBe('1 repository imported.');
});

it('generates status message with skipped repositories', function () {
    $result = new BulkImportResultData(created: 2, skipped: 1, webhooksFailed: 0);

    expect($result->statusMessage())->toBe('2 repositories imported, 1 already connected.');
});

it('generates status message with webhook failures', function () {
    $result = new BulkImportResultData(created: 2, skipped: 0, webhooksFailed: 1);

    expect($result->statusMessage())->toBe('2 repositories imported, 1 webhook registration failed.');
});

it('generates status message with all values', function () {
    $result = new BulkImportResultData(created: 2, skipped: 1, webhooksFailed: 2);

    expect($result->statusMessage())->toBe('2 repositories imported, 1 already connected, 2 webhook registrations failed.');
});

it('generates status message when nothing was imported', function () {
    $result = new BulkImportResultData(created: 0, skipped: 3, webhooksFailed: 0);

    expect($result->statusMessage())->toBe('3 already connected.');
});

it('generates status message when no repos were processed', function () {
    $result = new BulkImportResultData(created: 0, skipped: 0, webhooksFailed: 0);

    expect($result->statusMessage())->toBe('No repositories were imported.');
});
