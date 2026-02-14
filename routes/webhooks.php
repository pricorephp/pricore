<?php

use App\Domains\Repository\Http\Controllers\GitHubWebhookController;
use App\Domains\Repository\Http\Middleware\VerifyGitHubWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/github/{repository:uuid}', GitHubWebhookController::class)
    ->middleware(VerifyGitHubWebhookSignature::class)
    ->name('webhooks.github');
