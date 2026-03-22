<?php

use App\Domains\Repository\Http\Controllers\BitbucketWebhookController;
use App\Domains\Repository\Http\Controllers\GenericGitWebhookController;
use App\Domains\Repository\Http\Controllers\GitHubWebhookController;
use App\Domains\Repository\Http\Controllers\GitLabWebhookController;
use App\Domains\Repository\Http\Middleware\VerifyBitbucketWebhookSignature;
use App\Domains\Repository\Http\Middleware\VerifyGenericGitWebhookToken;
use App\Domains\Repository\Http\Middleware\VerifyGitHubWebhookSignature;
use App\Domains\Repository\Http\Middleware\VerifyGitLabWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/github/{repository:uuid}', GitHubWebhookController::class)
    ->middleware(VerifyGitHubWebhookSignature::class)
    ->name('webhooks.github');

Route::post('webhooks/gitlab/{repository:uuid}', GitLabWebhookController::class)
    ->middleware(VerifyGitLabWebhookSignature::class)
    ->name('webhooks.gitlab');

Route::post('webhooks/bitbucket/{repository:uuid}', BitbucketWebhookController::class)
    ->middleware(VerifyBitbucketWebhookSignature::class)
    ->name('webhooks.bitbucket');

Route::post('webhooks/git/{repository:uuid}', GenericGitWebhookController::class)
    ->middleware(VerifyGenericGitWebhookToken::class)
    ->name('webhooks.git');
