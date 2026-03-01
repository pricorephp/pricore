<?php

use App\Domains\Repository\Http\Controllers\GitHubWebhookController;
use App\Domains\Repository\Http\Controllers\GitLabWebhookController;
use App\Domains\Repository\Http\Middleware\VerifyGitHubWebhookSignature;
use App\Domains\Repository\Http\Middleware\VerifyGitLabWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/github/{repository:uuid}', GitHubWebhookController::class)
    ->middleware(VerifyGitHubWebhookSignature::class)
    ->name('webhooks.github');

Route::post('webhooks/gitlab/{repository:uuid}', GitLabWebhookController::class)
    ->middleware(VerifyGitLabWebhookSignature::class)
    ->name('webhooks.gitlab');
